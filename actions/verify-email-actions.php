<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/email-verification.php';

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !verify_csrf_token($_POST['csrf_token'] ?? '')
) {
    set_flash('error', 'Security token mismatch. Please try again.');
    header('Location: ' . base_url('verify-email.php'));
    exit;
}

$userId = (int) ($_SESSION['otp_user_id'] ?? 0);

if ($userId <= 0) {
    set_flash(
        'error',
        'Start registration or sign in to continue email verification.'
    );
    header('Location: ' . base_url('register.php'));
    exit;
}

$db = getDB();
$action = $_POST['action'] ?? '';

if ($action === 'resend') {
    try {
        resend_email_otp($db, $userId);

        set_flash(
            'success',
            'A new verification code has been sent. It expires in five minutes.'
        );
    } catch (Throwable $exception) {
        error_log(
            'Email OTP resend failed (' .
            get_class($exception) . '): ' .
            $exception->getMessage()
        );

        set_flash('error', $exception->getMessage());
    }

    header('Location: ' . base_url('verify-email.php'));
    exit;
}

// Keep the rest of your existing member OTP verification logic below.

if ($action !== 'verify') {
    header('Location: ' . base_url('verify-email.php'));
    exit;
}

$otp = $_POST['otp'] ?? '';
if (!is_string($otp) || !preg_match('/^\d{6}$/', $otp)) {
    set_flash('error', 'Enter the six-digit verification code.');
    header('Location: ' . base_url('verify-email.php'));
    exit;
}

$challenge = null;
try {
    $db->beginTransaction();
    $stmt = $db->prepare('
        SELECT o.otp_hash, o.selected_plan_id, o.expires_at, o.attempts,
               (o.expires_at <= CURRENT_TIMESTAMP) AS is_expired, u.email_verified
        FROM email_verification_otps o
        JOIN users u ON u.id = o.user_id
        WHERE o.user_id = :user_id
        FOR UPDATE
    ');
    $stmt->execute([':user_id' => $userId]);
    $challenge = $stmt->fetch();
    if (!$challenge) {
        $db->commit();
        set_flash('error', 'No pending email verification was found. Please register again or sign in.');
        unset($_SESSION['otp_user_id'], $_SESSION['otp_email']);
        header('Location: ' . base_url('register.php'));
        exit;
    }
    if ((int) $challenge['email_verified'] === 1) {
        $db->commit();
        set_flash('warning', 'This email address is already verified. Please sign in.');
        unset($_SESSION['otp_user_id'], $_SESSION['otp_email']);
        header('Location: ' . base_url('login.php'));
        exit;
    }
    if ((int) $challenge['is_expired'] === 1) {
        $db->commit();
        set_flash('error', 'That verification code has expired. Request a new code and try again.');
        header('Location: ' . base_url('verify-email.php'));
        exit;
    }
    if ((int) $challenge['attempts'] >= 5) {
        $db->commit();
        set_flash('error', 'Too many incorrect codes. Request a new code to continue.');
        header('Location: ' . base_url('verify-email.php'));
        exit;
    }

    if (!hash_equals($challenge['otp_hash'], email_otp_hash($otp))) {
        $attemptStmt = $db->prepare('
            UPDATE email_verification_otps
            SET attempts = attempts + 1
            WHERE user_id = :user_id AND attempts < 5 AND expires_at > CURRENT_TIMESTAMP
        ');
        $attemptStmt->execute([':user_id' => $userId]);
        $accepted = $attemptStmt->rowCount() === 1;
        $db->commit();
        set_flash('error', $accepted
            ? 'The verification code is incorrect.'
            : 'The verification code expired or the attempt limit was reached. Request a new code.');
        header('Location: ' . base_url('verify-email.php'));
        exit;
    }

    $verifyStmt = $db->prepare('
        UPDATE users
        SET email_verified = 1
        WHERE id = :id AND role = "member" AND email_verified = 0
    ');
    $verifyStmt->execute([':id' => $userId]);
    if ($verifyStmt->rowCount() !== 1) {
        throw new RuntimeException('The account could not be verified.');
    }
    $db->prepare('
        UPDATE email_verification_otps
        SET otp_hash = REPEAT("0", 64), expires_at = CURRENT_TIMESTAMP
        WHERE user_id = :id
    ')
        ->execute([':id' => $userId]);
    $userStmt = $db->prepare('SELECT id, name, email, role FROM users WHERE id = :id');
    $userStmt->execute([':id' => $userId]);
    $user = $userStmt->fetch();
    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Email OTP verification failed (' . get_class($exception) . '): ' . $exception->getMessage());
    set_flash('error', 'Email verification could not be completed. Please try again.');
    header('Location: ' . base_url('verify-email.php'));
    exit;
}

session_regenerate_id(true);
$_SESSION['user'] = [
    'id' => (int) $user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'role' => $user['role'],
    'email_verified' => true,
];
$_SESSION['last_activity'] = time();
$_SESSION['selected_plan_id'] = (int) $challenge['selected_plan_id'];
unset($_SESSION['otp_user_id'], $_SESSION['otp_email']);
set_flash('success', 'Your email is verified. You can now select a plan and pay securely.');
header('Location: ' . base_url('member/dashboard.php'));
exit;
