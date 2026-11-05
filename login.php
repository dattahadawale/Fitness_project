<?php
/**
 * Login Portal (Unified Admin & Member)
 * Gym Management System
 */

require_once __DIR__ . '/includes/functions.php';

// If already logged in, redirect to respective dashboard
if (isset($_SESSION['user']['role'])) {
    if ($_SESSION['user']['role'] === 'admin') {
        header('Location: ' . base_url('admin/dashboard.php'));
    } else {
        header('Location: ' . base_url('member/dashboard.php'));
    }
    exit;
}

$pageTitle = 'Sign In';
$bodyClass = 'auth-page';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
    <div class="auth-header">
        <a href="<?= base_url('index.php') ?>" class="auth-brand">
            <div class="brand-icon">⚡</div>
            <div class="brand-text">APEX<span>FITNESS</span></div>
        </a>
        <h2 class="auth-title">Welcome Back</h2>
        <p class="auth-subtitle">Sign in to access your gym dashboard</p>
    </div>

    <form action="<?= base_url('actions/auth-actions.php') ?>" method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="login">

        <div class="form-group">
            <label class="form-label" for="email">Email Address <span class="required">*</span></label>
            <input type="email" id="email" name="email" class="form-control" placeholder="name@example.com" required autofocus>
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Password <span class="required">*</span></label>
            <div style="position: relative;">
                <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                <button type="button" onclick="const p = document.getElementById('password'); p.type = p.type === 'password' ? 'text' : 'password';" 
                        style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-dim); cursor: pointer;" title="Toggle password visibility">
                    👁️
                </button>
            </div>
        </div>

        <div style="text-align: right; margin-top: -8px; margin-bottom: 16px;">
            <a href="<?= base_url('verify-email.php') ?>" style="color: var(--primary); font-size: 13px;">Verify email / resend code</a>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
            Sign In &rarr;
        </button>
    </form>

    <!-- Test Credentials Quick Selector -->
    <div style="margin-top: 24px; padding: 14px; background: var(--bg-main); border: 1px dashed var(--border-color); border-radius: var(--radius-sm); font-size: 12px;">
        <div style="font-weight: 700; color: var(--primary); margin-bottom: 6px;">⚡ Quick Test Credentials:</div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 4px; color: var(--text-muted);">
            <span>Admin: <code>admin@gym.com</code></span>
            <span>Pass: <code>Admin@123</code></span>
            <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 6px; font-size: 10px;" onclick="document.getElementById('email').value='admin@gym.com'; document.getElementById('password').value='Admin@123';">Fill</button>
        </div>
        <div style="display: flex; justify-content: space-between; color: var(--text-muted);">
            <span>Member: <code>alex@example.com</code></span>
            <span>Pass: <code>Member@123</code></span>
            <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 6px; font-size: 10px;" onclick="document.getElementById('email').value='alex@example.com'; document.getElementById('password').value='Member@123';">Fill</button>
        </div>
    </div>

    <div class="auth-footer">
        New to Apex Fitness? <a href="<?= base_url('register.php') ?>">Create a Member Account</a>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
