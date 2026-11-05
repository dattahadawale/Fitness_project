-- Landing page inquiry form submissions
CREATE TABLE IF NOT EXISTS inquiries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    address VARCHAR(255) NOT NULL,
    mobile VARCHAR(25) NOT NULL,
    email VARCHAR(190) NOT NULL,
    status ENUM('new', 'contacted', 'closed') NOT NULL DEFAULT 'new',
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inquiries_status (status),
    KEY idx_inquiries_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


