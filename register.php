<?php
/**
 * Member Self-Registration Portal
 * Gym Management System
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . base_url(has_role('admin') ? 'admin/dashboard.php' : 'member/dashboard.php'));
    exit;
}

$db = getDB();

// Fetch active plans for selection
$plans = [];
try {
    $pStmt = $db->query("SELECT * FROM membership_plans WHERE status = 'active' ORDER BY price ASC");
    $plans = $pStmt->fetchAll();
} catch (Exception $e) {}

$requestedPlanId = $_GET['plan_id'] ?? '';
$selectedPlanId = is_string($requestedPlanId) && ctype_digit($requestedPlanId)
    ? (int)$requestedPlanId
    : (int)($plans[0]['id'] ?? 0);

$pageTitle = 'Join Apex Fitness';
$bodyClass = 'auth-page';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-card" style="max-width: 520px;">
    <div class="auth-header">
        <a href="<?= base_url('index.php') ?>" class="auth-brand">
            <div class="brand-icon">⚡</div>
            <div class="brand-text">APEX<span>FITNESS</span></div>
        </a>
        <h2 class="auth-title">Join Apex Fitness Club</h2>
        <p class="auth-subtitle">Create your member profile, verify your email, and choose a membership</p>
    </div>

    <form action="<?= base_url('actions/auth-actions.php') ?>" method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="register">

        <div class="form-group">
            <label class="form-label" for="full_name">Full Name <span class="required">*</span></label>
            <input type="text" id="full_name" name="full_name" class="form-control" placeholder="e.g. Marcus Johnson" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control" placeholder="marcus@example.com" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="phone">Phone Number <span class="required">*</span></label>
                <input type="tel" id="phone" name="phone" class="form-control" placeholder="+91 98765 43210" maxlength="25" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="gender">Gender</label>
                <select id="gender" name="gender" class="form-control">
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="plan_id">Membership Plan <span class="required">*</span></label>
                <select id="plan_id" name="plan_id" class="form-control" required>
                    <?php foreach ($plans as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ((int)$p['id'] === $selectedPlanId) ? 'selected' : '' ?>>
                            <?= e($p['plan_name']) ?> (<?= format_currency($p['price']) ?> / <?= (int)$p['duration_days'] ?>d)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Min. 8 characters" minlength="8" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirm Password <span class="required">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
            Complete Registration &rarr;
        </button>
    </form>

    <div class="auth-footer">
        Already an Apex member? <a href="<?= base_url('login.php') ?>">Sign In Here</a>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
