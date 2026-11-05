USE `gym_management`;

ALTER TABLE `users`
    ADD COLUMN `email_verified` TINYINT(1) NOT NULL DEFAULT 1 AFTER `status`;

CREATE TABLE IF NOT EXISTS `email_verification_otps` (
    `user_id` INT UNSIGNED NOT NULL,
    `selected_plan_id` INT UNSIGNED NOT NULL,
    `otp_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `resends` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `last_sent_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    KEY `idx_email_otp_expiry` (`expires_at`),
    CONSTRAINT `fk_email_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_email_otp_plan` FOREIGN KEY (`selected_plan_id`) REFERENCES `membership_plans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_rate_limits` (
    `identifier_hash` CHAR(64) NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `window_started_at` DATETIME NOT NULL,
    `blocked_until` DATETIME DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`identifier_hash`),
    KEY `idx_login_limit_blocked` (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `phonepe_orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `member_id` INT UNSIGNED NOT NULL,
    `plan_id` INT UNSIGNED NOT NULL,
    `payment_id` INT UNSIGNED NOT NULL,
    `merchant_order_id` VARCHAR(63) NOT NULL,
    `phonepe_order_id` VARCHAR(100) DEFAULT NULL,
    `amount_paise` BIGINT UNSIGNED NOT NULL,
    `currency` CHAR(3) NOT NULL DEFAULT 'INR',
    `status` ENUM('created', 'pending', 'completed', 'failed', 'expired') NOT NULL DEFAULT 'created',
    `provider_state` VARCHAR(40) DEFAULT NULL,
    `redirect_url` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `completed_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_phonepe_merchant_order` (`merchant_order_id`),
    UNIQUE KEY `uq_phonepe_provider_order` (`phonepe_order_id`),
    UNIQUE KEY `uq_phonepe_payment` (`payment_id`),
    KEY `idx_phonepe_member_status` (`member_id`, `status`),
    CONSTRAINT `fk_phonepe_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_phonepe_plan` FOREIGN KEY (`plan_id`) REFERENCES `membership_plans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_phonepe_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
