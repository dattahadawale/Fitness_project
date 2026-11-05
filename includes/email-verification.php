<?php

require_once __DIR__ . '/email.php';



echo 'Admin OTP page is working.';


function create_email_otp(PDO $db, int $userId, int $planId): string
{
    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $stmt = $db->prepare('
        INSERT INTO email_verification_otps
            (user_id, selected_plan_id, otp_hash, expires_at, attempts, resends, last_sent_at)
        VALUES (:user_id, :plan_id, :otp_hash, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 5 MINUTE), 0, 0, CURRENT_TIMESTAMP)
    ');
    $stmt->execute([
        ':user_id' => $userId,
        ':plan_id' => $planId,
        ':otp_hash' => email_otp_hash($otp),
    ]);
    return $otp;
}

function expire_email_otp_after_delivery_failure(PDO $db, int $userId): void
{
    $stmt = $db->prepare('
        UPDATE email_verification_otps
        SET expires_at = CURRENT_TIMESTAMP,
            last_sent_at = DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 60 SECOND)
        WHERE user_id = :user_id
    ');
    $stmt->execute([':user_id' => $userId]);
}

function email_otp_hash(string $otp): string
{
    $appKey = getenv('APP_KEY');
    if ($appKey === false || strlen($appKey) < 32) {
        throw new RuntimeException('APP_KEY must contain at least 32 characters.');
    }
    return hash_hmac('sha256', $otp, $appKey);
}

function resend_email_otp(PDO $db, int $userId): void
{
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('
            SELECT o.*, u.email, u.name, u.email_verified
            FROM email_verification_otps o
            JOIN users u ON u.id = o.user_id
            WHERE o.user_id = :user_id
            FOR UPDATE
        ');
        $stmt->execute([':user_id' => $userId]);
        $challenge = $stmt->fetch();
        if (!$challenge || (int) $challenge['email_verified'] === 1) {
            throw new RuntimeException('No pending email verification was found.');
        }

        $cooldownStmt = $db->prepare('SELECT GREATEST(0, 60 - TIMESTAMPDIFF(SECOND, :sent_at, CURRENT_TIMESTAMP))');
        $cooldownStmt->execute([':sent_at' => $challenge['last_sent_at']]);
        $remaining = (int) $cooldownStmt->fetchColumn();
        if ($remaining > 0) {
            throw new RuntimeException("Please wait {$remaining} seconds before requesting another code.");
        }
        if ((int) $challenge['resends'] >= 5) {
            throw new RuntimeException('The maximum number of resend requests has been reached. Please register again later.');
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $update = $db->prepare('
            UPDATE email_verification_otps
            SET otp_hash = :otp_hash, expires_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 5 MINUTE),
                attempts = 0, resends = resends + 1, last_sent_at = CURRENT_TIMESTAMP
            WHERE user_id = :user_id
        ');
        $update->execute([':otp_hash' => email_otp_hash($otp), ':user_id' => $userId]);
        try {
            send_email_otp($challenge['email'], $challenge['name'], $otp);
        } catch (Throwable $mailException) {
            $restore = $db->prepare('
                UPDATE email_verification_otps
                SET otp_hash = :otp_hash, expires_at = :expires_at, attempts = :attempts,
                    last_sent_at = CURRENT_TIMESTAMP
                WHERE user_id = :user_id
            ');
            $restore->execute([
                ':otp_hash' => $challenge['otp_hash'],
                ':expires_at' => $challenge['expires_at'],
                ':attempts' => $challenge['attempts'],
                ':user_id' => $userId,
            ]);
            $db->commit();
            throw new RuntimeException(
                'Verification email could not be sent. Your previous code remains valid if it has not expired. Please retry after 60 seconds.',
                0,
                $mailException
            );
        }

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}
