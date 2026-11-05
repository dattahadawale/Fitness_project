<?php
/**
 * Authentication Actions (Login, Register, Password Update)
 * Gym Management System
 */

// require_once __DIR__ . '/../includes/functions.php';
// require_once __DIR__ . '/../includes/security.php';
// require_once __DIR__ . '/../includes/email-verification.php';


require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/email-verification.php';
require_once __DIR__ . '/../includes/email.php';




if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Security token mismatch. Please try again.');
    header('Location: ' . base_url('login.php'));
    exit;
}

$action = $_POST['action'] ?? '';
$db = getDB();

switch ($action) {

    case 'login':
        $emailValue = $_POST['email'] ?? '';
        $passwordValue = $_POST['password'] ?? '';

        $email = is_string($emailValue)
            ? strtolower(trim($emailValue))
            : '';

        $password = is_string($passwordValue)
            ? $passwordValue
            : '';

        // Validate login input.
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            set_flash('error', 'Please enter a valid email address and password.');
            header('Location: ' . base_url('login.php'));
            exit;
        }

        // Check login rate limits.
        $rateLimitKeys = login_rate_limit_keys(
            $email,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );

        if (login_is_rate_limited($db, $rateLimitKeys)) {
            set_flash(
                'error',
                'Too many sign-in attempts. Please wait 15 minutes before trying again.'
            );
            header('Location: ' . base_url('login.php'));
            exit;
        }

        // Find the account.
        $stmt = $db->prepare(
            'SELECT * FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        // Verify password.
        if (!$user || !password_verify($password, $user['password'])) {
            record_login_failure($db, $rateLimitKeys);

            set_flash('error', 'Invalid email or password combination.');
            header('Location: ' . base_url('login.php'));
            exit;
        }

        // Check account status.
        if ($user['status'] !== 'active') {
            set_flash(
                'error',
                'Your account has been deactivated. Please contact the administrator.'
            );
            header('Location: ' . base_url('login.php'));
            exit;
        }

        // Preserve the existing member email-verification process.
        if (
            $user['role'] === 'member'
            && (int) $user['email_verified'] !== 1
        ) {
            session_regenerate_id(true);

            unset(
                $_SESSION['user'],
                $_SESSION['admin_otp_verified'],
                $_SESSION['admin_otp_pending_id'],
                $_SESSION['admin_otp_pending_email'],
                $_SESSION['admin_otp_issued_at']
            );

            $_SESSION['otp_user_id'] = (int) $user['id'];
            $_SESSION['otp_email'] = $user['email'];

            set_flash('warning', 'Verify your email address before signing in.');
            header('Location: ' . base_url('verify-email.php'));
            exit;
        }

        // Require email OTP for every administrator account.

        // Require email OTP for every administrator account.
        if ($user['role'] === 'admin') {
            $appKey = getenv('APP_KEY');

            // Admin account email and OTP delivery email.

            $adminLoginEmails = [
                'admin@gym.com',
                'dattahadawale673@gmail.com',
            ];

            $otpEmail = 'dattahadawale9322@gmail.com';

            if (
                !in_array(
                    strtolower(trim($user['email'])),
                    $adminLoginEmails,
                    true
                )
            ) {
                set_flash(
                    'error',
                    'This administrator account is not configured for OTP login.'
                );
                header('Location: ' . base_url('login.php'));
                exit;
            }


            if ($appKey === false || strlen($appKey) < 32) {
                error_log('Admin OTP unavailable: APP_KEY is missing or too short.');

                set_flash(
                    'error',
                    'Admin verification is temporarily unavailable. Please contact the administrator.'
                );

                header('Location: ' . base_url('login.php'));
                exit;
            }

            // Generate a six-digit OTP valid for five minutes.
            $otp = (string) random_int(100000, 999999);
            $otpHash = hash_hmac('sha256', $otp, $appKey);

            $now = time();
            $nowSql = date('Y-m-d H:i:s', $now);
            $expiresSql = date('Y-m-d H:i:s', $now + 300);

            try {
                // Store the OTP under the delivery email address.
                $otpStmt = $db->prepare("
                    INSERT INTO admin_login_otps
                        (email, otp_hash, expires_at, attempts, last_sent_at)
                    VALUES
                        (:email, :otp_hash, :expires_at, 0, :last_sent_at)
                    ON DUPLICATE KEY UPDATE
                        otp_hash = VALUES(otp_hash),
                        expires_at = VALUES(expires_at),
                        attempts = 0,
                        last_sent_at = VALUES(last_sent_at)
                ");

                $otpStmt->execute([
                    ':email' => $otpEmail,
                    ':otp_hash' => $otpHash,
                    ':expires_at' => $expiresSql,
                    ':last_sent_at' => $nowSql,
                ]);

                // Send the OTP to the configured delivery email.
                send_email_otp(
                    $otpEmail,
                    $user['name'],
                    $otp
                );
            } catch (Throwable $exception) {
                // Remove the OTP if email delivery fails.
                try {
                    $deleteStmt = $db->prepare(
                        'DELETE FROM admin_login_otps WHERE email = :email'
                    );

                    $deleteStmt->execute([
                        ':email' => $otpEmail,
                    ]);
                } catch (Throwable $cleanupException) {
                    error_log('Could not invalidate an undelivered admin OTP.');
                }

                error_log(
                    'Admin OTP delivery failed: ' . get_class($exception)
                );

                set_flash(
                    'error',
                    'The verification email could not be sent. Check the SMTP configuration and try again.'
                );

                header('Location: ' . base_url('login.php'));
                exit;
            }

            // Start a fresh pending administrator session.
            clear_login_rate_limit($db, $rateLimitKeys);
            session_regenerate_id(true);

            unset(
                $_SESSION['user'],
                $_SESSION['admin_otp_verified'],
                $_SESSION['otp_user_id'],
                $_SESSION['otp_email']
            );

            $_SESSION['admin_otp_pending_id'] = (int) $user['id'];
            $_SESSION['admin_otp_pending_email'] = $otpEmail;
            $_SESSION['admin_otp_issued_at'] = $now;

            set_flash(
                'success',
                'A verification code has been sent to your administrator verification email.'
            );

            header('Location: ' . base_url('admin/verify-otp.php'));
            exit;
        }

        // Normal login for members and other non-admin roles.
        clear_login_rate_limit($db, $rateLimitKeys);

        session_regenerate_id(true);

        unset(
            $_SESSION['admin_otp_verified'],
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
            'role' => $user['role'],
            'email_verified' => (int) $user['email_verified'] === 1,
        ];

        $_SESSION['last_activity'] = time();

        set_flash('success', 'Welcome back, ' . $user['name'] . '!');

        header('Location: ' . base_url('member/dashboard.php'));
        exit;


    case 'register':
        if (!empty($_SESSION['user']['id'])) {
            header('Location: ' . base_url($_SESSION['user']['role'] === 'admin' ? 'admin/dashboard.php' : 'member/dashboard.php'));
            exit;
        }

        $appKey = getenv('APP_KEY');
        if ($appKey === false || strlen($appKey) < 32) {
            error_log('Member registration unavailable: APP_KEY is missing or too short.');
            set_flash('error', 'Member registration is temporarily unavailable because server security configuration is incomplete. Please contact the administrator.');
            header('Location: ' . base_url('register.php'));
            exit;
        }

        $fullNameValue = $_POST['full_name'] ?? '';
        $emailValue = $_POST['email'] ?? '';
        $phoneValue = $_POST['phone'] ?? '';
        $genderValue = $_POST['gender'] ?? 'Male';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $planValue = $_POST['plan_id'] ?? '';
        $fullName = is_string($fullNameValue) ? trim($fullNameValue) : '';
        $email = is_string($emailValue) ? strtolower(trim($emailValue)) : '';
        $phone = is_string($phoneValue) ? trim($phoneValue) : '';
        $gender = is_string($genderValue) ? $genderValue : '';
        $password = is_string($password) ? $password : '';
        $confirmPassword = is_string($confirmPassword) ? $confirmPassword : '';
        $planId = is_string($planValue) && ctype_digit($planValue) ? (int) $planValue : 0;
        $digitsOnlyPhone = preg_replace('/\D+/', '', $phone);

        if (
            $fullName === '' || strlen($fullName) > 120 || preg_match('/[\x00-\x1F\x7F]/', $fullName)
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($email) > 150 || strlen($phone) > 25 || strlen($digitsOnlyPhone) < 7
            || strlen($digitsOnlyPhone) > 15 || !preg_match('/^[0-9+(). -]+$/', $phone)
            || !in_array($gender, ['Male', 'Female', 'Other'], true)
            || $password === '' || $planId <= 0
        ) {
            set_flash('error', 'Please provide valid registration details and select an active membership plan.');
            header('Location: ' . base_url('register.php'));
            exit;
        }
        if ($password !== $confirmPassword) {
            set_flash('error', 'Passwords do not match.');
            header('Location: ' . base_url('register.php'));
            exit;
        }
        if (strlen($password) < 8) {
            set_flash('error', 'Password must be at least 8 characters long.');
            header('Location: ' . base_url('register.php'));
            exit;
        }

        $planStmt = $db->prepare("SELECT id FROM membership_plans WHERE id = :id AND status = 'active'");
        $planStmt->execute([':id' => $planId]);
        if (!$planStmt->fetch()) {
            set_flash('error', 'That membership plan is unavailable. Please select another plan.');
            header('Location: ' . base_url('register.php'));
            exit;
        }
        $checkStmt = $db->prepare('SELECT id FROM users WHERE email = :email');
        $checkStmt->execute([':email' => $email]);
        if ($checkStmt->fetch()) {
            set_flash('error', 'An account with this email address already exists.');
            header('Location: ' . base_url('register.php'));
            exit;
        }

        try {
            $db->beginTransaction();
            $userStmt = $db->prepare("
                INSERT INTO users (name, email, password, role, status, email_verified)
                VALUES (:name, :email, :password, 'member', 'active', 0)
            ");
            $userStmt->execute([
                ':name' => $fullName,
                ':email' => $email,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $userId = (int) $db->lastInsertId();

            $memberStmt = $db->prepare("
                INSERT INTO members (user_id, member_code, full_name, email, phone, gender, status)
                VALUES (:user_id, :member_code, :full_name, :email, :phone, :gender, 'inactive')
            ");
            $memberStmt->execute([
                ':user_id' => $userId,
                ':member_code' => generate_next_member_code(),
                ':full_name' => $fullName,
                ':email' => $email,
                ':phone' => $phone,
                ':gender' => $gender,
            ]);
            $otp = create_email_otp($db, $userId, $planId);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Member registration failed (' . get_class($exception) . '): ' . $exception->getMessage());
            set_flash('error', 'Registration could not be completed. Check your details and try again.');
            header('Location: ' . base_url('register.php'));
            exit;
        }

        $_SESSION['otp_user_id'] = $userId;
        $_SESSION['otp_email'] = $email;
        try {
            send_email_otp($email, $fullName, $otp);
            set_flash('success', 'Registration saved. Enter the six-digit code sent to your email to verify your account.');
        } catch (Throwable $exception) {
            try {
                expire_email_otp_after_delivery_failure($db, $userId);
            } catch (Throwable $databaseException) {
                error_log('Failed to invalidate undelivered registration OTP (' . get_class($databaseException) . ').');
            }
            set_flash('error', 'Your account was created but the verification email was not sent. Check SMTP settings, then use Resend verification code to try again.');
        }
        header('Location: ' . base_url('verify-email.php'));
        exit;

    case 'change_password':
        require_once __DIR__ . '/../includes/auth.php';
        if (!is_logged_in()) {
            header('Location: ' . base_url('login.php'));
            exit;
        }
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $currentPassword = is_string($currentPassword) ? $currentPassword : '';
        $newPassword = is_string($newPassword) ? $newPassword : '';
        $confirmPassword = is_string($confirmPassword) ? $confirmPassword : '';
        $userId = (int) $_SESSION['user']['id'];
        $returnUrl = base_url('member/profile.php');

        if ($currentPassword === '' || strlen($newPassword) < 8 || $newPassword !== $confirmPassword) {
            set_flash('error', 'Enter your current password and a matching new password of at least 8 characters.');
            header('Location: ' . $returnUrl);
            exit;
        }
        $userStmt = $db->prepare('SELECT password FROM users WHERE id = :id');
        $userStmt->execute([':id' => $userId]);
        $row = $userStmt->fetch();
        if (!$row || !password_verify($currentPassword, $row['password'])) {
            set_flash('error', 'Current password is incorrect.');
            header('Location: ' . $returnUrl);
            exit;
        }
        $updateStmt = $db->prepare('UPDATE users SET password = :password WHERE id = :id');
        $updateStmt->execute([':password' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $userId]);
        set_flash('success', 'Password updated successfully!');
        header('Location: ' . $returnUrl);
        exit;

    default:
        header('Location: ' . base_url('login.php'));
        exit;
}


