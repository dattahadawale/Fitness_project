<?php
/**
 * Member Attendance History Module
 * Gym Management System
 */

require_once __DIR__ . '/../includes/member_auth.php';

$db = getDB();
$userId = $_SESSION['user']['id'];

$mStmt = $db->prepare("SELECT id, full_name, member_code FROM members WHERE user_id = :uid LIMIT 1");
$mStmt->execute([':uid' => $userId]);
$member = $mStmt->fetch();

$memberId = (int)$member['id'];

// Fetch all attendance logs for this member
$attStmt = $db->prepare("
    SELECT * FROM attendance 
    WHERE member_id = :mid 
    ORDER BY attendance_date DESC, check_in_time DESC
");
$attStmt->execute([':mid' => $memberId]);
$logs = $attStmt->fetchAll();

$totalVisits = count($logs);

// Visits this month
$thisMonthCount = count(array_filter($logs, fn($l) => date('Y-m', strtotime($l['attendance_date'])) === date('Y-m')));

$pageTitle = 'Attendance History';
$navTitle = 'My Training Attendance Logs';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <main class="main-content">
            <!-- Metrics Summary Cards -->
            <div class="kpi-grid">
                <div class="kpi-card kpi-orange">
                    <div class="kpi-details">
                        <span class="kpi-title">Total Lifetime Visits</span>
                        <span class="kpi-value"><?= $totalVisits ?></span>
                        <span class="kpi-subtext">Gym floor sessions</span>
                    </div>
                    <div class="kpi-icon">
                        <svg width="24" height="24"><use href="#icon-attendance"></use></svg>
                    </div>
                </div>

                <div class="kpi-card kpi-green">
                    <div class="kpi-details">
                        <span class="kpi-title">Visits This Month</span>
                        <span class="kpi-value"><?= $thisMonthCount ?></span>
                        <span class="kpi-subtext"><?= date('F Y') ?> check-ins</span>
                    </div>
                    <div class="kpi-icon">
                        <svg width="24" height="24"><use href="#icon-workout"></use></svg>
                    </div>
                </div>
            </div>

            <!-- Attendance History Table -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <svg width="18" height="18"><use href="#icon-attendance"></use></svg>
                        Training Attendance Record
                    </h2>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Check-In Time</th>
                                <th>Check-Out Time</th>
                                <th>Workout Notes</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-dim); padding: 36px;">
                                        No check-in logs recorded yet. Visit the front desk to check in!
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td style="font-weight: 700; color: #ffffff;"><?= format_date($log['attendance_date']) ?></td>
                                        <td><span class="badge badge-secondary"><?= date('l', strtotime($log['attendance_date'])) ?></span></td>
                                        <td style="font-weight: 600; color: var(--success);">
                                            ⏱️ <?= format_time($log['check_in_time']) ?>
                                        </td>
                                        <td>
                                            <?= $log['check_out_time'] ? format_time($log['check_out_time']) : '<span style="color: var(--text-dim);">-</span>' ?>
                                        </td>
                                        <td style="color: var(--text-muted); font-size: 13px;">
                                            <?= e($log['notes'] ?: 'Gym Session') ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-success">Attended</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
