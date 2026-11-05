-- ============================================================
-- Migration 04: UPI Payment Workflow - Extended payment_proofs
-- Run in: phpMyAdmin → gym_management → SQL tab
-- Safe to run multiple times (uses IF NOT EXISTS / column checks)
-- ============================================================

USE `gym_management`;

-- Ensure payment_proofs table exists with full schema
CREATE TABLE IF NOT EXISTS `payment_proofs` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`          INT UNSIGNED NOT NULL,
    `member_id`        INT UNSIGNED NULL,
    `plan_id`          INT UNSIGNED NOT NULL,
    `amount`           DECIMAL(10,2) NOT NULL,
    `currency`         CHAR(3) NOT NULL DEFAULT 'INR',
    `transaction_id`   VARCHAR(40) NOT NULL,
    `payment_ref`      VARCHAR(60) DEFAULT NULL COMMENT 'Internal APXREF-... reference',
    `screenshot_file`  VARCHAR(100) NOT NULL,
    `status`           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `rejection_reason` TEXT DEFAULT NULL,
    `reviewed_by`      INT UNSIGNED DEFAULT NULL COMMENT 'admin users.id',
    `reviewed_at`      DATETIME DEFAULT NULL,
    `email_sent`       TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payment_proofs_txn` (`transaction_id`),
    KEY `idx_payment_proofs_user` (`user_id`, `created_at`),
    KEY `idx_payment_proofs_status` (`status`),
    KEY `idx_payment_proofs_member` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add missing columns to existing table (ALTER TABLE with IF NOT EXISTS workaround via PROCEDURE)
DROP PROCEDURE IF EXISTS `migrate_payment_proofs_04`;
DELIMITER //
CREATE PROCEDURE `migrate_payment_proofs_04`()
BEGIN
    DECLARE has_utr_reference INT DEFAULT 0;
    DECLARE has_payment_status INT DEFAULT 0;
    DECLARE has_submitted_at INT DEFAULT 0;
    DECLARE has_status INT DEFAULT 0;

    -- Add columns required by the member dashboard and admin review screen.
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payment_proofs'
          AND COLUMN_NAME  = 'member_id'
    ) THEN
        ALTER TABLE `payment_proofs` ADD COLUMN `member_id` INT UNSIGNED NULL AFTER `user_id`;
    END IF;

    UPDATE `payment_proofs` pp
    JOIN `members` m ON m.user_id = pp.user_id
       SET pp.member_id = m.id
     WHERE pp.member_id IS NULL;

    -- Add currency column if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payment_proofs'
          AND COLUMN_NAME  = 'currency'
    ) THEN
        ALTER TABLE `payment_proofs` ADD COLUMN `currency` CHAR(3) NOT NULL DEFAULT 'INR' AFTER `amount`;
    END IF;

    -- Add payment_ref column if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payment_proofs'
          AND COLUMN_NAME  = 'payment_ref'
    ) THEN
        ALTER TABLE `payment_proofs` ADD COLUMN `payment_ref` VARCHAR(60) DEFAULT NULL COMMENT 'Internal APXREF-... reference' AFTER `currency`;
    END IF;

    -- Normalize older UTR-based tables to the transaction_id column used by the app.
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payment_proofs'
          AND COLUMN_NAME  = 'transaction_id'
    ) THEN
        ALTER TABLE `payment_proofs` ADD COLUMN `transaction_id` VARCHAR(40) NULL AFTER `currency`;
    END IF;

    SELECT COUNT(*) INTO has_utr_reference
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'payment_proofs'
       AND COLUMN_NAME = 'utr_reference';
    IF has_utr_reference > 0 THEN
        SET @copy_utr_sql = 'UPDATE `payment_proofs` SET `transaction_id` = `utr_reference` WHERE `transaction_id` IS NULL';
        PREPARE copy_utr_stmt FROM @copy_utr_sql;
        EXECUTE copy_utr_stmt;
        DEALLOCATE PREPARE copy_utr_stmt;
    END IF;

    UPDATE `payment_proofs`
       SET `transaction_id` = CONCAT('LEGACY-', `id`)
     WHERE `transaction_id` IS NULL OR `transaction_id` = '';
    ALTER TABLE `payment_proofs` MODIFY COLUMN `transaction_id` VARCHAR(40) NOT NULL;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payment_proofs'
          AND INDEX_NAME   = 'uq_payment_proofs_txn'
    ) THEN
        ALTER TABLE `payment_proofs` ADD UNIQUE KEY `uq_payment_proofs_txn` (`transaction_id`);
    END IF;

    -- Map legacy uppercase payment states into the dashboard's lowercase states.
    SELECT COUNT(*) INTO has_status
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'payment_proofs'
       AND COLUMN_NAME = 'status';
    IF has_status = 0 THEN
        ALTER TABLE `payment_proofs`
            ADD COLUMN `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending';

        SELECT COUNT(*) INTO has_payment_status
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'payment_proofs'
           AND COLUMN_NAME = 'payment_status';
        IF has_payment_status > 0 THEN
            SET @copy_status_sql = 'UPDATE `payment_proofs` SET `status` = CASE `payment_status` WHEN ''VERIFIED'' THEN ''approved'' WHEN ''REJECTED'' THEN ''rejected'' ELSE ''pending'' END';
            PREPARE copy_status_stmt FROM @copy_status_sql;
            EXECUTE copy_status_stmt;
            DEALLOCATE PREPARE copy_status_stmt;
        END IF;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payment_proofs'
          AND COLUMN_NAME  = 'created_at'
    ) THEN
        ALTER TABLE `payment_proofs` ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
    END IF;

    SELECT COUNT(*) INTO has_submitted_at
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'payment_proofs'
       AND COLUMN_NAME = 'submitted_at';
    IF has_submitted_at > 0 THEN
        SET @copy_submitted_sql = 'UPDATE `payment_proofs` SET `created_at` = `submitted_at`';
        PREPARE copy_submitted_stmt FROM @copy_submitted_sql;
        EXECUTE copy_submitted_stmt;
        DEALLOCATE PREPARE copy_submitted_stmt;
    END IF;

    UPDATE `payment_proofs`
       SET `payment_ref` = CONCAT('PP-LEGACY-', `id`)
     WHERE `payment_ref` IS NULL OR `payment_ref` = '';

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payment_proofs'
          AND COLUMN_NAME  = 'email_sent'
    ) THEN
        ALTER TABLE `payment_proofs` ADD COLUMN `email_sent` TINYINT(1) NOT NULL DEFAULT 0;
    END IF;

    -- Add rejection_reason column if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payment_proofs'
          AND COLUMN_NAME  = 'rejection_reason'
    ) THEN
        ALTER TABLE `payment_proofs` ADD COLUMN `rejection_reason` TEXT DEFAULT NULL AFTER `status`;
    END IF;

    -- Add reviewed_by column if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payment_proofs'
          AND COLUMN_NAME  = 'reviewed_by'
    ) THEN
        ALTER TABLE `payment_proofs` ADD COLUMN `reviewed_by` INT UNSIGNED DEFAULT NULL COMMENT 'admin users.id' AFTER `rejection_reason`;
    END IF;

    -- Add reviewed_at column if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payment_proofs'
          AND COLUMN_NAME  = 'reviewed_at'
    ) THEN
        ALTER TABLE `payment_proofs` ADD COLUMN `reviewed_at` DATETIME DEFAULT NULL AFTER `reviewed_by`;
    END IF;

    -- Widen screenshot_file if still 100 chars
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payment_proofs'
          AND COLUMN_NAME  = 'screenshot_file'
          AND CHARACTER_MAXIMUM_LENGTH < 200
    ) THEN
        ALTER TABLE `payment_proofs` MODIFY COLUMN `screenshot_file` VARCHAR(200) NOT NULL;
    END IF;
END//
DELIMITER ;

CALL `migrate_payment_proofs_04`();
DROP PROCEDURE IF EXISTS `migrate_payment_proofs_04`;

-- Backfill payment ledger entries for submissions created by older proof flows.
INSERT INTO `payments`
    (`member_id`, `membership_id`, `amount`, `payment_date`, `payment_method`, `transaction_ref`, `notes`, `status`)
SELECT
    pp.`member_id`,
    NULL,
    pp.`amount`,
    DATE(pp.`created_at`),
    'UPI',
    pp.`payment_ref`,
    'UPI proof imported from payment history.',
    CASE pp.`status`
        WHEN 'approved' THEN 'completed'
        WHEN 'rejected' THEN 'failed'
        ELSE 'pending'
    END
FROM `payment_proofs` pp
LEFT JOIN `payments` p ON p.`transaction_ref` = pp.`payment_ref`
WHERE pp.`member_id` IS NOT NULL
  AND p.`id` IS NULL;
