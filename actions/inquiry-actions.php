<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Read a setting from the process environment, then from the project .env file.
 */
function inquiry_env(string $key): string
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return (string) $value;
    }
    if (!empty($_ENV[$key])) {
        return (string) $_ENV[$key];
    }

    static $fileValues = null;
    if ($fileValues === null) {
        $fileValues = [];
        $envFile = dirname(__DIR__) . '/.env';
        if (is_readable($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $fileValues[trim($k)] = trim(trim($v), "\"'");
            }
        }
    }

    return $fileValues[$key] ?? '';
}

/**
 * Send the inquiry emails through the same Gmail SMTP settings used for OTP emails:
 *   1. a notification to the admin
 *   2. a confirmation to the customer (the email address typed in the form)
 * Never throws. A failed email is only logged; the inquiry is already saved.
 */
function send_inquiry_emails(int $inquiryId, string $name, string $address, string $mobile, string $email): void
{
    // Who receives the admin notification (change this line to use another inbox).
    $adminEmail = 'dattahadawale673@gmail.com';

    // Gym details printed in the customer's email; replies from the customer go here.
    $gymEmail = 'dattahadawale9322@gmail.com';
    $gymPhone = '+91 93596 03384';
    $gymAddress = 'Gokhalenagar, Pune - 411016';

    try {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            error_log('Inquiry emails skipped: vendor/autoload.php not found (run composer install).');
            return;
        }
        require_once $autoload;

        $host = inquiry_env('SMTP_HOST');
        $username = inquiry_env('SMTP_USERNAME');
        $password = inquiry_env('SMTP_PASSWORD');
        if ($host === '' || $username === '' || $password === '') {
            error_log('Inquiry emails skipped: SMTP settings are missing in .env.');
            return;
        }

        $encryption = strtolower(inquiry_env('SMTP_ENCRYPTION') ?: 'tls');
        $fromEmail = inquiry_env('SMTP_FROM_EMAIL') ?: $username;
        $fromName = inquiry_env('SMTP_FROM_NAME') ?: 'Apex Fitness Club';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->SMTPKeepAlive = true; // reuse one connection for both emails
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = $encryption === 'ssl'
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) (inquiry_env('SMTP_PORT') ?: 587);
        $mail->Timeout = 10;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromEmail, $fromName);

        // 1. Admin notification
        try {
            $mail->clearAllRecipients();
            $mail->clearReplyTos();
            $mail->addAddress($adminEmail);
            $mail->addReplyTo($email, $name);
            $mail->Subject = 'New Gym Inquiry Received - #' . $inquiryId;
            $mail->Body =
                "A new inquiry has been submitted.\n\n" .
                "Inquiry ID: {$inquiryId}\n" .
                "Name: {$name}\n" .
                "Address: {$address}\n" .
                "Mobile: {$mobile}\n" .
                "Customer Email: {$email}\n\n" .
                'Please contact the customer to follow up.';
            $mail->send();
        } catch (\Throwable $e) {
            error_log('Inquiry #' . $inquiryId . ' admin email failed: ' . $e->getMessage());
        }

        // 2. Customer confirmation
        try {
            $mail->clearAllRecipients();
            $mail->clearReplyTos();
            $mail->addAddress($email, $name);
            $mail->addReplyTo($gymEmail, 'Apex Fitness Club');
            $mail->Subject = 'We received your inquiry - Apex Fitness Club';
            $mail->Body =
                "Dear {$name},\n\n" .
                "Thank you for contacting Apex Fitness Club!\n\n" .
                "We have received your inquiry (Reference #{$inquiryId}). " .
                "Our team will contact you soon on {$mobile}.\n\n" .
                "Your details:\n" .
                "Name: {$name}\n" .
                "Address: {$address}\n" .
                "Mobile: {$mobile}\n" .
                "Email: {$email}\n\n" .
                "Apex Fitness Club\n" .
                "{$gymAddress}\n" .
                "Phone: {$gymPhone}\n" .
                "Email: {$gymEmail}\n\n" .
                "Best regards,\n" .
                'Apex Fitness Club Team';
            $mail->send();
        } catch (\Throwable $e) {
            error_log('Inquiry #' . $inquiryId . ' customer email failed: ' . $e->getMessage());
        }

        $mail->smtpClose();
    } catch (\Throwable $e) {
        error_log('Inquiry #' . $inquiryId . ' emails failed: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . base_url('index.php'));
    exit;
}

$action = $_POST['action'] ?? '';

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash(
        'error',
        'Security token mismatch. Please refresh the page and try again.'
    );

    header('Location: ' . base_url(
        $action === 'submit_inquiry'
        ? 'index.php#inquiry'
        : 'admin/inquiries.php'
    ));
    exit;
}

$db = getDB();

if ($action === 'submit_inquiry') {

    // Ignore automated submissions caught by the honeypot.
    if (!empty($_POST['website'])) {
        set_flash('success', 'Thank you! Your inquiry has been received.');
        header('Location: ' . base_url('index.php#inquiry'));
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');

    $error = '';

    if ($name === '' || $address === '' || $mobile === '' || $email === '') {
        $error = 'Please fill in all fields.';
    } elseif (
        mb_strlen($name) > 120 ||
        mb_strlen($address) > 255 ||
        mb_strlen($email) > 190
    ) {
        $error = 'One of the fields is too long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^\+?[0-9][0-9 \-]{6,23}$/', $mobile)) {
        $error = 'Please enter a valid mobile number.';
    }

    if ($error !== '') {
        $_SESSION['inquiry_old'] = compact('name', 'address', 'mobile', 'email');
        set_flash('error', $error);
        header('Location: ' . base_url('index.php#inquiry'));
        exit;
    }

    try {
        // 1. Save the inquiry to MySQL.
        $stmt = $db->prepare(
            'INSERT INTO inquiries
                (name, address, mobile, email, ip_address)
             VALUES
                (:name, :address, :mobile, :email, :ip)'
        );

        $stmt->execute([
            ':name' => $name,
            ':address' => $address,
            ':mobile' => $mobile,
            ':email' => $email,
            ':ip' => substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45),
        ]);

        $inquiryId = (int) $db->lastInsertId();

        // 2. Email the admin and the customer. The inquiry stays saved even if this fails.
        $safeName = preg_replace('/[\r\n]+/', ' ', $name);
        send_inquiry_emails($inquiryId, $safeName, $address, $mobile, $email);

        unset($_SESSION['inquiry_old']);

        set_flash(
            'success',
            'Your inquiry has been submitted successfully! Our team will contact you soon.'
        );
    } catch (Throwable $e) {
        error_log('Inquiry processing error: ' . $e->getMessage());

        $_SESSION['inquiry_old'] = compact('name', 'address', 'mobile', 'email');

        set_flash(
            'error',
            'Sorry, we could not save your inquiry. Please try again later.'
        );
    }

    // Redirect after POST to prevent form resubmission.
    header('Location: ' . base_url('index.php#inquiry'));
    exit;
}

// Admin-only status and delete actions.
if (!is_logged_in() || !has_role('admin')) {
    http_response_code(403);
    exit('Access denied.');
}

$id = (int) ($_POST['id'] ?? 0);

if ($action === 'update_status') {
    $status = $_POST['status'] ?? '';

    if ($id > 0 && in_array($status, ['new', 'contacted', 'closed'], true)) {
        $stmt = $db->prepare('UPDATE inquiries SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);

        set_flash('success', 'Inquiry status updated.');
    } else {
        set_flash('error', 'Invalid inquiry or status.');
    }
} elseif ($action === 'delete_inquiry') {
    if ($id > 0) {
        $stmt = $db->prepare('DELETE FROM inquiries WHERE id = :id');
        $stmt->execute([':id' => $id]);

        set_flash('success', 'Inquiry deleted.');
    }
}

header('Location: ' . base_url('admin/inquiries.php'));
exit;

