<?php

function login_rate_limit_keys(string $email, string $ipAddress): array
{
    return [
        hash('sha256', 'email:' . strtolower($email)),
        hash('sha256', 'ip:' . $ipAddress),
    ];
}

function login_is_rate_limited(PDO $db, array $keys): bool
{
    $stmt = $db->prepare('
        SELECT COUNT(*)
        FROM login_rate_limits
        WHERE identifier_hash = :identifier
          AND blocked_until IS NOT NULL
          AND blocked_until > CURRENT_TIMESTAMP
    ');
    foreach ($keys as $key) {
        $stmt->execute([':identifier' => $key]);
        if ((int)$stmt->fetchColumn() > 0) {
            return true;
        }
    }
    return false;
}

function record_login_failure(PDO $db, array $keys): void
{
    $stmt = $db->prepare('
        INSERT INTO login_rate_limits (identifier_hash, attempts, window_started_at)
        VALUES (:identifier, 1, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE
            blocked_until = CASE
                WHEN window_started_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE) THEN NULL
                WHEN attempts + 1 >= 5 THEN DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE)
                ELSE blocked_until
            END,
            attempts = CASE
                WHEN window_started_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE) THEN 1
                ELSE LEAST(attempts + 1, 255)
            END,
            window_started_at = CASE
                WHEN window_started_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE) THEN CURRENT_TIMESTAMP
                ELSE window_started_at
            END
    ');
    foreach ($keys as $key) {
        $stmt->execute([':identifier' => $key]);
    }
}

function clear_login_rate_limit(PDO $db, array $keys): void
{
    $stmt = $db->prepare('DELETE FROM login_rate_limits WHERE identifier_hash = :identifier');
    foreach ($keys as $key) {
        $stmt->execute([':identifier' => $key]);
    }
}
