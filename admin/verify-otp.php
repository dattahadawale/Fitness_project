<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Both configured administrator accounts are allowed.
$adminLoginEmails = [
    'admin@gym.com',
    'dattahadawale673@gmail.com',
];

// OTP recipient configured in actions/auth-actions.php.
$otpEmail = 'dattahadawale9322@gmail.com';

$adminId = (int) ($_SESSION['admin_otp_pending_id'] ?? 0);
$pendingEmail = (string) ($_SESSION['admin_otp_pending_email'] ?? '');
$issuedAt = (int) ($_SESSION['admin_otp_issued_at'] ?? 0);

if (
    $adminId <= 0 ||
    $pendingEmail !== $otpEmail ||
    $issuedAt <= 0 ||
    time() - $issuedAt > 300
) {
    unset(
        $_SESSION['admin_otp_pending_id'],
        $_SESSION['admin_otp_pending_email'],
        $_SESSION['admin_otp_issued_at']
    );

    set_flash('error', 'Your verification session expired. Please sign in again.');
    header('Location: ' . base_url('login.php'));
    exit;
}

$appKey = getenv('APP_KEY');

if ($appKey === false || strlen($appKey) < 32) {
    http_response_code(500);
    exit('Admin verification is unavailable. Check the server configuration.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security token mismatch. Refresh the page and try again.';
    } else {
        $otpValue = $_POST['otp'] ?? '';
        $otp = is_string($otpValue) ? trim($otpValue) : '';

        if (!preg_match('/^[0-9]{6}$/', $otp)) {
            $error = 'Please enter a valid six-digit code.';
        } else {
            $db = getDB();

            try {
                $db->beginTransaction();

                // Recheck the account that initiated this login.
                $userStmt = $db->prepare(
                    'SELECT id, name, email, role, status
                     FROM users
                     WHERE id = :id
                     LIMIT 1
                     FOR UPDATE'
                );
                $userStmt->execute([':id' => $adminId]);
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);

                if (
                    !$user ||
                    !in_array(
                        strtolower(trim($user['email'])),
                        $adminLoginEmails,
                        true
                    ) ||
                    $user['role'] !== 'admin' ||
                    $user['status'] !== 'active'
                ) {
                    $db->rollBack();
                    $error = 'This administrator account is not valid or active.';
                } else {
                    $otpStmt = $db->prepare(
                        'SELECT otp_hash, expires_at, attempts
                         FROM admin_login_otps
                         WHERE email = :email
                         LIMIT 1
                         FOR UPDATE'
                    );
                    $otpStmt->execute([':email' => $otpEmail]);
                    $record = $otpStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$record) {
                        $db->rollBack();
                        $error = 'No active code was found. Please sign in again.';
                    } elseif ((int) $record['attempts'] >= 5) {
                        $db->rollBack();
                        $error = 'Too many incorrect attempts. Please sign in again.';
                    } elseif (strtotime($record['expires_at']) <= time()) {
                        $deleteStmt = $db->prepare(
                            'DELETE FROM admin_login_otps WHERE email = :email'
                        );
                        $deleteStmt->execute([':email' => $otpEmail]);
                        $db->commit();
                        $error = 'The code has expired. Please sign in again.';
                    } else {
                        $expectedHash = hash_hmac('sha256', $otp, $appKey);

                        if (!hash_equals($record['otp_hash'], $expectedHash)) {
                            $attemptStmt = $db->prepare(
                                'UPDATE admin_login_otps
                                 SET attempts = attempts + 1
                                 WHERE email = :email'
                            );
                            $attemptStmt->execute([':email' => $otpEmail]);
                            $db->commit();
                            $error = 'Incorrect code. Please try again.';
                        } else {
                            $deleteStmt = $db->prepare(
                                'DELETE FROM admin_login_otps WHERE email = :email'
                            );
                            $deleteStmt->execute([':email' => $otpEmail]);
                            $db->commit();

                            session_regenerate_id(true);

                            unset(
                                $_SESSION['admin_otp_pending_id'],
                                $_SESSION['admin_otp_pending_email'],
                                $_SESSION['admin_otp_issued_at'],
                                $_SESSION['otp_user_id'],
                                $_SESSION['otp_email']
                            );

                            $_SESSION['user'] = [
                                'id' => (int) $user['id'],
                                'name' => $user['name'],
                                'email' => $user['email'],
                                'role' => 'admin',
                                'email_verified' => true,
                            ];

                            $_SESSION['admin_otp_verified'] = true;
                            $_SESSION['last_activity'] = time();

                            header('Location: ' . base_url('admin/dashboard.php'));
                            exit;
                        }
                    }
                }
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }

                error_log(
                    'Admin OTP verification error: ' .
                    get_class($exception) . ': ' .
                    $exception->getMessage()
                );

                $error = 'Verification failed due to a server error. Please check the PHP server log.';
            }
        }
    }
}

$csrfToken = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Verification | Apex Fitness</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px;
            background: #11151c;
            color: #f8fafc;
            font-family: Arial, sans-serif;
        }

        .card {
            width: 100%;
            max-width: 440px;
            padding: 30px;
            background: #1a202b;
            border: 1px solid #303949;
            border-radius: 16px;
        }

        h1 { margin-top: 0; font-size: 26px; }

        p {
            color: #bdc8d8;
            line-height: 1.6;
        }

        .email {
            color: #ff8a32;
            overflow-wrap: anywhere;
        }

        label {
            display: block;
            margin: 22px 0 8px;
        }

        input {
            width: 100%;
            padding: 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 22px;
            letter-spacing: 6px;
        }

        button {
            width: 100%;
            margin-top: 20px;
            padding: 14px;
            border: 0;
            border-radius: 8px;
            background: #f56600;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .error {
            padding: 12px;
            border-radius: 8px;
            background: #7f1d1d;
            color: white;
        }
    </style>
</head>
<body>
<main class="card">
    <h1>Administrator Verification</h1>

    <p>Enter the six-digit code sent to:</p>
    <p class="email">
        <?= htmlspecialchars($otpEmail, ENT_QUOTES, 'UTF-8') ?>
    </p>
    <p>Your code is valid for five minutes.</p>

    <?php if ($error !== ''): ?>
        <p class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="post" action="">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
        >

        <label for="otp">Six-digit verification code</label>
        <input
            id="otp"
            name="otp"
            type="text"
            inputmode="numeric"
            pattern="[0-9]{6}"
            minlength="6"
            maxlength="6"
            autocomplete="one-time-code"
            required
            autofocus
        >

        <button type="submit">Verify and Continue</button>
    </form>
</main>
</body>
</html>
