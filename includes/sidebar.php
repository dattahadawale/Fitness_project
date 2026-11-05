<?php
/**
 * Shared Responsive Sidebar Component
 * Gym Management System
 */

$user = current_user();
$role = $user['role'] ?? 'member';
$currentScript = basename($_SERVER['SCRIPT_NAME']);
?>
<aside class="sidebar" id="appSidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">⚡</div>
        <div class="brand-text">APEX<span>FITNESS</span></div>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar">
            <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="user-info">
            <span class="user-name"><?= e($user['name'] ?? 'User') ?></span>
            <span class="user-role-badge badge-<?= e($role) ?>"><?= e(strtoupper($role)) ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php if ($role === 'admin'): ?>
            <span class="nav-section-title">Core Management</span>
            <a href="<?= base_url('admin/dashboard.php') ?>" class="nav-link <?= ($currentScript === 'dashboard.php') ? 'active' : '' ?>">
                <svg><use href="#icon-dashboard"></use></svg>
                <span>Dashboard</span>
            </a>
            <a href="<?= base_url('admin/members.php') ?>" class="nav-link <?= ($currentScript === 'members.php') ? 'active' : '' ?>">
                <svg><use href="#icon-users"></use></svg>
                <span>Members</span>
            </a>
            <a href="<?= base_url('admin/trainers.php') ?>" class="nav-link <?= ($currentScript === 'trainers.php') ? 'active' : '' ?>">
                <svg><use href="#icon-trainer"></use></svg>
                <span>Trainers</span>
            </a>
            <a href="<?= base_url('admin/membership-plans.php') ?>" class="nav-link <?= ($currentScript === 'membership-plans.php') ? 'active' : '' ?>">
                <svg><use href="#icon-plan"></use></svg>
                <span>Membership Plans</span>
            </a>
            <a href="<?= base_url('admin/memberships.php') ?>" class="nav-link <?= ($currentScript === 'memberships.php') ? 'active' : '' ?>">
                <svg><use href="#icon-membership"></use></svg>
                <span>Subscriptions</span>
            </a>

            <span class="nav-section-title">Operations</span>
            <a href="<?= base_url('admin/attendance.php') ?>" class="nav-link <?= ($currentScript === 'attendance.php') ? 'active' : '' ?>">
                <svg><use href="#icon-attendance"></use></svg>
                <span>Attendance</span>
            </a>
            <a href="<?= base_url('admin/payments.php') ?>" class="nav-link <?= ($currentScript === 'payments.php') ? 'active' : '' ?>">
                <svg><use href="#icon-payment"></use></svg>
                <span>Payments</span>
            </a>
            <a href="<?= base_url('admin/payment-proofs.php') ?>" class="nav-link <?= ($currentScript === 'payment-proofs.php') ? 'active' : '' ?>"
               style="<?= ($currentScript === 'payment-proofs.php') ? '' : '' ?>">
                <svg><use href="#icon-payment"></use></svg>
                <span>Payment Proofs
                <?php
                // Show pending count badge
                try {
                    $db ??= getDB();
                    $pendBadge = (int)$db->query("SELECT COUNT(*) FROM payment_proofs WHERE status='pending'")->fetchColumn();
                    if ($pendBadge > 0) {
                        echo '<span style="background:var(--danger);color:#fff;border-radius:20px;font-size:10px;padding:1px 6px;margin-left:6px;font-weight:700;">' . $pendBadge . '</span>';
                    }
                } catch (Throwable $e) { /* table may not exist yet */ }
                ?>
                </span>
            </a>
            <a href="<?= base_url('admin/workouts.php') ?>" class="nav-link <?= ($currentScript === 'workouts.php') ? 'active' : '' ?>">
                <svg><use href="#icon-workout"></use></svg>
                <span>Workout Plans</span>
            </a>
            <a href="<?= base_url('admin/reports.php') ?>" class="nav-link <?= ($currentScript === 'reports.php') ? 'active' : '' ?>">
                <svg><use href="#icon-report"></use></svg>
                <span>Reports & Analytics</span>
            </a>

        <?php else: ?>
            <span class="nav-section-title">Member Area</span>
            <a href="<?= base_url('member/dashboard.php') ?>" class="nav-link <?= ($currentScript === 'dashboard.php') ? 'active' : '' ?>">
                <svg><use href="#icon-dashboard"></use></svg>
                <span>Overview</span>
            </a>
            <a href="<?= base_url('member/profile.php') ?>" class="nav-link <?= ($currentScript === 'profile.php') ? 'active' : '' ?>">
                <svg><use href="#icon-users"></use></svg>
                <span>My Profile</span>
            </a>
            <a href="<?= base_url('member/attendance.php') ?>" class="nav-link <?= ($currentScript === 'attendance.php') ? 'active' : '' ?>">
                <svg><use href="#icon-attendance"></use></svg>
                <span>Attendance Logs</span>
            </a>
            <a href="<?= base_url('member/payments.php') ?>" class="nav-link <?= ($currentScript === 'payments.php') ? 'active' : '' ?>">
                <svg><use href="#icon-payment"></use></svg>
                <span>Payment History</span>
            </a>
            <a href="<?= base_url('member/workouts.php') ?>" class="nav-link <?= ($currentScript === 'workouts.php') ? 'active' : '' ?>">
                <svg><use href="#icon-workout"></use></svg>
                <span>My Workout Plan</span>
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <form action="<?= base_url('logout.php') ?>" method="POST">
            <?= csrf_input() ?>
            <button type="submit" class="nav-link" style="color: var(--danger); border: 0; background: transparent; width: 100%; text-align: left; cursor: pointer;">
                <svg><use href="#icon-logout"></use></svg>
                <span>Sign Out</span>
            </button>
        </form>
    </div>
</aside>
