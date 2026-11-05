<?php

require_once __DIR__ . '/includes/functions.php';

if (empty($_SESSION['otp_user_id'])) {
    set_flash('warning', 'Start registration or sign in to verify your email address.');
    header('Location: ' . base_url('register.php'));
    exit;
}

$pageTitle = 'Verify Your Email';
$bodyClass = 'auth-page';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
    <div class="auth-header">
        <a href="<?= base_url('index.php') ?>" class="auth-brand">
            <div class="brand-icon">⚡</div>
            <div class="brand-text">APEX<span>FITNESS</span></div>
        </a>
        <h2 class="auth-title">Verify Your Email</h2>
        <p class="auth-subtitle">
            Enter the six-digit code sent to <?= e($_SESSION['otp_email'] ?? 'your email address') ?>.
            The code expires in five minutes.
        </p>
    </div>

    <form action="<?= base_url('actions/verify-email-actions.php') ?>" method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="verify">
        <div class="form-group">
            <label class="form-label" for="otp">Email verification code <span class="required">*</span></label>
            <input type="text" id="otp" name="otp" class="form-control" inputmode="numeric"
                   autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6"
                   title="Enter the six-digit code from your email." aria-describedby="otp-help"
                   autocapitalize="off" spellcheck="false" required autofocus>
            <small id="otp-help" class="form-hint">Enter all six digits. The code expires in five minutes.</small>
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%;">Verify Email</button>
    </form>

    <form action="<?= base_url('actions/verify-email-actions.php') ?>" method="POST" style="margin-top: 12px;">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="resend">
        <button type="submit" class="btn btn-secondary" style="width: 100%;">Resend verification code</button>
    </form>

    <div class="auth-footer">
        Already verified? <a href="<?= base_url('login.php') ?>">Sign in</a>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
