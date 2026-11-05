<?php
/**
 * Admin Dashboard
 * Gym Management System
 */

require_once __DIR__ . '/../includes/admin_auth.php';

// Auto-check for any expired memberships today
auto_check_membership_expiry();

$db = getDB();

// 1. Fetch KPI Statistics
$kpis = get_admin_kpi_stats();

// 2. Fetch Memberships Expiring in the Next 7 Days
$expiringList = get_expiring_memberships_alert(7);

// 3. Fetch Recent Member Registrations (Latest 5)
$recentMembers = [];
try {
    $stmt = $db->query("
        SELECT m.id, m.member_code, m.full_name, m.phone, m.created_at, m.status,
               COALESCE(mp.plan_name, 'No Active Plan') AS current_plan
        FROM members m
        LEFT JOIN (
            SELECT member_id,
                   COALESCE(
                       MAX(CASE WHEN status = 'active' AND expiry_date >= CURRENT_DATE THEN id END),
                       MAX(id)
                   ) AS latest_id
            FROM memberships
            GROUP BY member_id
        ) latest_ms ON m.id = latest_ms.member_id
        LEFT JOIN memberships ms ON ms.id = latest_ms.latest_id
        LEFT JOIN membership_plans mp ON ms.plan_id = mp.id
        ORDER BY m.id DESC
        LIMIT 5
    ");
    $recentMembers = $stmt->fetchAll();
} catch (Exception $e) {}

// 4. Fetch Recent Payments (Latest 5)
$recentPayments = [];
try {
    $payStmt = $db->query("
        SELECT p.id, p.amount, p.payment_date, p.payment_method, p.transaction_ref, p.status,
               m.full_name, m.member_code
        FROM payments p
        JOIN members m ON p.member_id = m.id
        ORDER BY p.id DESC
        LIMIT 5
    ");
    $recentPayments = $payStmt->fetchAll();
} catch (Exception $e) {}

$pageTitle = 'Admin Dashboard';
$navTitle = 'Overview & Statistics';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <main class="main-content">
            <!-- Expiry Warning Banner if Any Expiring Soon -->
            <?php if (!empty($expiringList)): ?>
                <div class="alert-banner">
                    <div class="alert-banner-content">
                        <span class="alert-banner-icon">⚠️</span>
                        <div>
                            <strong style="color: #ffffff;"><?= count($expiringList) ?> Membership(s) Expiring Soon!</strong>
                            <div style="font-size: 13px; color: var(--text-muted); margin-top: 2px;">
                                Members including <?= e($expiringList[0]['full_name']) ?> (<?= e($expiringList[0]['member_code']) ?>) have memberships expiring within 7 days.
                            </div>
                        </div>
                    </div>
                    <a href="<?= base_url('admin/memberships.php') ?>" class="btn btn-primary btn-sm">Review & Renew &rarr;</a>
                </div>
            <?php endif; ?>

            <!-- 6 KPI Metric Cards -->
            <div class="kpi-grid">
                <div class="kpi-card kpi-orange">
                    <div class="kpi-details">
                        <span class="kpi-title">Total Members</span>
                        <span class="kpi-value"><?= number_format($kpis['total_members']) ?></span>
                        <span class="kpi-subtext">Registered athletes</span>
                    </div>
                    <div class="kpi-icon">
                        <svg width="24" height="24"><use href="#icon-users"></use></svg>
                    </div>
                </div>

                <div class="kpi-card kpi-green">
                    <div class="kpi-details">
                        <span class="kpi-title">Active Members</span>
                        <span class="kpi-value"><?= number_format($kpis['active_members']) ?></span>
                        <span class="kpi-subtext">Valid subscriptions</span>
                    </div>
                    <div class="kpi-icon">
                        <svg width="24" height="24"><use href="#icon-membership"></use></svg>
                    </div>
                </div>

                <div class="kpi-card kpi-blue">
                    <div class="kpi-details">
                        <span class="kpi-title">Active Trainers</span>
                        <span class="kpi-value"><?= number_format($kpis['total_trainers']) ?></span>
                        <span class="kpi-subtext">Coaching staff</span>
                    </div>
                    <div class="kpi-icon">
                        <svg width="24" height="24"><use href="#icon-trainer"></use></svg>
                    </div>
                </div>

                <div class="kpi-card kpi-orange">
                    <div class="kpi-details">
                        <span class="kpi-title">Monthly Revenue</span>
                        <span class="kpi-value"><?= format_currency($kpis['monthly_revenue']) ?></span>
                        <span class="kpi-subtext"><?= date('F Y') ?> collections</span>
                    </div>
                    <div class="kpi-icon">
                        <svg width="24" height="24"><use href="#icon-payment"></use></svg>
                    </div>
                </div>

                <div class="kpi-card kpi-green">
                    <div class="kpi-details">
                        <span class="kpi-title">Today's Check-Ins</span>
                        <span class="kpi-value"><?= number_format($kpis['today_attendance']) ?></span>
                        <span class="kpi-subtext">Attendance count</span>
                    </div>
                    <div class="kpi-icon">
                        <svg width="24" height="24"><use href="#icon-attendance"></use></svg>
                    </div>
                </div>

                <div class="kpi-card kpi-red">
                    <div class="kpi-details">
                        <span class="kpi-title">Expired Memberships</span>
                        <span class="kpi-value"><?= number_format($kpis['expired_memberships']) ?></span>
                        <span class="kpi-subtext">Action required</span>
                    </div>
                    <div class="kpi-icon">
                        <svg width="24" height="24"><use href="#icon-plan"></use></svg>
                    </div>
                </div>
            </div>

            <!-- Quick Action Toolbar -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-body" style="padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                    <div style="font-size: 15px; font-weight: 700; color: #ffffff;">Quick Operations:</div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="<?= base_url('admin/members.php?action=new') ?>" class="btn btn-primary btn-sm">+ Add Member</a>
                        <a href="<?= base_url('admin/attendance.php') ?>" class="btn btn-secondary btn-sm">⏱️ Check-In Member</a>
                        <a href="<?= base_url('admin/payments.php') ?>" class="btn btn-secondary btn-sm">💳 Record Payment</a>
                        <a href="<?= base_url('admin/workouts.php') ?>" class="btn btn-secondary btn-sm">🏋️ Build Workout Plan</a>
                    </div>
                </div>
            </div>

            <!-- Two-Column Grid: Recent Members & Recent Payments -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(460px, 1fr)); gap: 24px;">
                <!-- Recent Members -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <svg width="18" height="18"><use href="#icon-users"></use></svg>
                            Recent Registrations
                        </h2>
                        <a href="<?= base_url('admin/members.php') ?>" class="btn btn-secondary btn-sm">View All &rarr;</a>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Plan</th>
                                    <th>Joined</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentMembers)): ?>
                                    <tr><td colspan="4" style="text-align: center; color: var(--text-dim); padding: 24px;">No member registrations found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recentMembers as $m): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600; color: #ffffff;"><?= e($m['full_name']) ?></div>
                                                <div style="font-size: 11px; font-family: monospace; color: var(--primary);"><?= e($m['member_code']) ?></div>
                                            </td>
                                            <td><span class="badge badge-info"><?= e($m['current_plan']) ?></span></td>
                                            <td><?= format_date($m['created_at']) ?></td>
                                            <td><span class="badge badge-<?= e($m['status']) ?>"><?= e($m['status']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Payments -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <svg width="18" height="18"><use href="#icon-payment"></use></svg>
                            Recent Transactions
                        </h2>
                        <a href="<?= base_url('admin/payments.php') ?>" class="btn btn-secondary btn-sm">View All &rarr;</a>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentPayments)): ?>
                                    <tr><td colspan="5" style="text-align: center; color: var(--text-dim); padding: 24px;">No payment records found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recentPayments as $p): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600; color: #ffffff;"><?= e($p['full_name']) ?></div>
                                                <div style="font-size: 11px; font-family: monospace; color: var(--text-dim);"><?= e($p['transaction_ref']) ?></div>
                                            </td>
                                            <td style="font-weight: 700; color: var(--success);"><?= format_currency($p['amount']) ?></td>
                                            <td><span class="badge badge-secondary"><?= e($p['payment_method']) ?></span></td>
                                            <td><?= format_date($p['payment_date']) ?></td>
                                            <td><span class="badge badge-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
