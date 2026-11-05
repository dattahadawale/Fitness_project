-- UPI payment proofs submitted by members (QR payment + transaction ID + screenshot).
-- Admin reviews each row; membership is activated only after verification.
-- status is VARCHAR (not ENUM) so any status wording used by the review pages is accepted.

CREATE TABLE IF NOT EXISTS payment_proofs (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id           INT UNSIGNED NOT NULL,
    member_id         INT UNSIGNED NULL,
    plan_id           INT UNSIGNED NOT NULL,
    amount            DECIMAL(10,2) NOT NULL,
    currency          CHAR(3) NOT NULL DEFAULT 'INR',
    transaction_id    VARCHAR(64) NOT NULL,
    payment_ref       VARCHAR(32) NOT NULL,
    screenshot_file   VARCHAR(255) NOT NULL,
    status            VARCHAR(20) NOT NULL DEFAULT 'pending',
    reviewed_by       INT UNSIGNED NULL,
    reviewed_at       DATETIME NULL,
    rejection_reason  VARCHAR(500) NULL,
    admin_notified_at DATETIME NULL,
    notify_error      VARCHAR(500) NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_proofs_txn (transaction_id),
    UNIQUE KEY uq_payment_proofs_ref (payment_ref),
    KEY idx_payment_proofs_user_status (user_id, status),
    KEY idx_payment_proofs_status_created (status, created_at),
    KEY idx_payment_proofs_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


