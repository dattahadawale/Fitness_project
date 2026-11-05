
-- Run once in phpMyAdmin (select the gym_management database first) -> SQL tab.
-- Stores the payment proofs members submit after scanning the PhonePe QR.
-- No foreign keys on purpose: they would fail if your users/members/membership_plans
-- id columns have a different integer type. Add them later once the types are confirmed.

CREATE TABLE IF NOT EXISTS payment_proofs (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    member_id       INT UNSIGNED NULL,
    plan_id         INT UNSIGNED NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    transaction_id  VARCHAR(40) NOT NULL,
    screenshot_file VARCHAR(100) NOT NULL,
    status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    email_sent      TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_proofs_txn (transaction_id),
    KEY idx_payment_proofs_user (user_id, created_at),
    KEY idx_payment_proofs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


