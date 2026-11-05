<?php

function send_email_otp(string $email, string $name, string $otp): void
{
    try {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new RuntimeException('Composer dependencies are not installed.');
        }
        require_once $autoload;

        $required = ['SMTP_HOST', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'SMTP_FROM_EMAIL'];
        $settings = [];
        foreach ($required as $key) {
            $settings[$key] = getenv($key);
            if ($settings[$key] === false || trim($settings[$key]) === '') {
                throw new RuntimeException("Missing required SMTP setting: {$key}");
            }
        }

        $port = (int)(getenv('SMTP_PORT') ?: 587);
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('SMTP_PORT must be between 1 and 65535.');
        }
        if (!filter_var($settings['SMTP_FROM_EMAIL'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('SMTP_FROM_EMAIL must be a valid email address.');
        }

        $encryption = strtolower((string)(getenv('SMTP_ENCRYPTION') ?: 'tls'));
        if (!in_array($encryption, ['tls', 'ssl'], true)) {
            throw new RuntimeException('SMTP_ENCRYPTION must be tls or ssl.');
        }

        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $settings['SMTP_HOST'];
        $mailer->SMTPAuth = true;
        $mailer->Username = $settings['SMTP_USERNAME'];
        $mailer->Password = $settings['SMTP_PASSWORD'];
        $mailer->Port = $port;
        $mailer->Timeout = 10;
        $mailer->Timelimit = 20;
        $mailer->SMTPSecure = $encryption === 'ssl'
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom($settings['SMTP_FROM_EMAIL'], getenv('SMTP_FROM_NAME') ?: 'Apex Fitness Club');
        $mailer->addAddress($email, $name);
        $mailer->isHTML(true);
        $mailer->Subject = 'Verify your Apex Fitness email';
        $mailer->Body = '<p>Hello ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>Your Apex Fitness verification code is <strong>' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8')
            . '</strong>. It expires in five minutes.</p><p>If you did not request this code, ignore this email.</p>';
        $mailer->AltBody = "Your Apex Fitness verification code is {$otp}. It expires in five minutes.";
        $mailer->send();
    } catch (Throwable $exception) {
        $diagnostic = preg_replace('/[\r\n]+/', ' ', $exception->getMessage());
        error_log('Email OTP delivery failed (' . get_class($exception) . '): ' . $diagnostic);
        throw new RuntimeException(
            'Verification email could not be sent. Check the SMTP configuration and try again.',
            0,
            $exception
        );
    }
}
