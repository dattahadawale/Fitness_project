<?php
/**
 * Reports & Analytics Module
 * Gym Management System
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$db = getDB();

// 1. Date Range Filter
$range = $_GET['range'] ?? 'this_month';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

if ($range === 'last_month') {
    $startDate = date('Y-m-01', strtotime('first day of last month'));
    $endDate = date('Y-m-t', strtotime('last day of last month'));
} elseif ($range === 'this_year') {
    $startDate = date('Y-01-01');
    $endDate = date('Y-12-31');
}

// 2. Summary KPIs within Selected Date Range
// Total Registrations in range
$regStmt = $db->prepare("SELECT COUNT(*) FROM members WHERE DATE(created_at) BETWEEN :s AND :e");
$regStmt->execute([':s' => $startDate, ':e' => $endDate]);
$rangeRegistrations = (int)$regStmt->fetchColumn();

// Revenue in range
$revStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND payment_date BETWEEN :s AND :e");
$revStmt->execute([':s' => $startDate, ':e' => $endDate]);
$rangeRevenue = (float)$revStmt->fetchColumn();

// Attendance visits in range
$attStmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date BETWEEN :s AND :e");
$attStmt->execute([':s' => $startDate, ':e' => $endDate]);
$rangeAttendance = (int)$attStmt->fetchColumn();

// 3. Overall Membership Breakdown (Active vs Expired)
$totalMembers = (int)$db->query("SELECT COUNT(*) FROM members")->fetchColumn();
$activeMembers = (int)$db->query("
    SELECT COUNT(DISTINCT member_id) 
    FROM memberships 
    WHERE status = 'active' AND expiry_date >= CURRENT_DATE
")->fetchColumn();
$expiredMembers = (int)$db->query("
    SELECT COUNT(DISTINCT member_id) 
    FROM memberships 
    WHERE status = 'expired' OR expiry_date < CURRENT_DATE
")->fetchColumn();

// 4. Plan Popularity Breakdown
$planPopularity = $db->query("
    SELECT mp.plan_name, mp.price,
           COUNT(ms.id) AS total_sold,
           COALESCE(SUM(ms.total_amount), 0) AS total_plan_revenue
    FROM membership_plans mp
    LEFT JOIN memberships ms ON mp.id = ms.plan_id
    GROUP BY mp.id
    ORDER BY total_sold DESC
")->fetchAll();

// 5. Payment Methods Breakdown
$methodBreakdown = $db->query("
    SELECT payment_method, COUNT(*) AS count, SUM(amount) AS total_amount
    FROM payments
    WHERE status = 'completed'
    GROUP BY payment_method
    ORDER BY total_amount DESC
")->fetchAll();

// 6. Recent Daily Attendance Trend in Range (Last 7 Days)
$attendanceTrend = $db->prepare("
    SELECT attendance_date, COUNT(*) AS check_ins
    FROM attendance
    WHERE attendance_date BETWEEN :s AND :e
    GROUP BY attendance_date
    ORDER BY attendance_date DESC
    LIMIT 10
");
$attendanceTrend->execute([':s' => $startDate, ':e' => $endDate]);
$trendRows = $attendanceTrend->fetchAll();

$pageTitle = 'Reports & Analytics';
$navTitle = 'Gym Intelligence & Reports';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <main class="main-content">
            <!-- Filter & Export Action Toolbar -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-body" style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                    <form method="GET" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 0;">
                        <span style="font-size: 13px; font-weight: 600; color: var(--text-muted);">Report Window:</span>
                        <input type="date" name="start_date" class="form-control" style="width: auto;" value="<?= e($startDate) ?>">
                        <span style="color: var(--text-dim);">&rarr;</span>
                        <input type="date" name="end_date" class="form-control" style="width: auto;" value="<?= e($endDate) ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                        <a href="?range=this_month" class="btn btn-outline-primary btn-sm <?= ($range === 'this_month') ? 'btn-primary' : '' ?>">This Month</a>
                        <a href="?range=last_month" class="btn btn-outline-primary btn-sm <?= ($range === 'last_month') ? 'btn-primary' : '' ?>">Last Month</a>
                        <a href="?range=this_year" class="btn btn-outline-primary btn-sm <?= ($range === 'this_year') ? 'btn-primary' : '' ?>">This Year</a>
                    </form>

                    <!-- CSV Export Buttons -->
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <a href="<?= base_url('actions/export-reports.php?type=members') ?>" class="btn btn-secondary btn-sm" title="Download Members CSV">
                            📥 Members CSV
                        </a>
                        <a href="<?= base_url('actions/export-reports.php?type=payments') ?>" class="btn btn-secondary btn-sm" title="Download Payments CSV">
                            📥 Payments CSV
                        </a>
                        <a href="<?= base_url('actions/export-reports.php?type=attendance') ?>" class="btn btn-secondary btn-sm" title="Download Attendance CSV">
                            📥 Attendance CSV
                        </a>
                    </div>
                </div>
            </div>

            <!-- KPI Metric Cards for Selected Date Range -->
            <div class="kpi-grid">
                <div class="kpi-card kpi-orange">
                    <div class="kpi-details">
                        <span class="kpi-title">Revenue Generated</span>
                        <span class="kpi-value"><?= format_currency($rangeRevenue) ?></span>
                        <span class="kpi-subtext"><?= format_date($startDate) ?> &ndash; <?= format_date($endDate) ?></span>
                    </div>
                    <div class="kpi-icon">
                        <svg width="24" height="24"><use href="#icon-payment"></use></svg>
                    </div>
                </div>

                <div class="kpi-card kpi-green">
                    <div class="kpi-details">
                        <span class="kpi-title">New Registrations</span>
                        <span class="kpi-value"><?= number_format($rangeRegistrations) ?></span>
                        <span class="kpi-subtext">Signed up in window</span>
                    </div>
                    <div class="kpi-icon">
                        <svg width="24" height="24"><use href="#icon-users"></use></svg>
                    </div>
                </div>

                <div class="kpi-card kpi-blue">
                    <div class="kpi-details">
                        <span class="kpi-title">Facility Check-Ins</span>
                        <span class="kpi-value"><?= number_format($rangeAttendance) ?></span>
                        <span class="kpi-subtext">Gym visits in period</span>
                    </div>
                    <div class="kpi-icon">
                        <svg width="24" height="24"><use href="#icon-attendance"></use></svg>
                    </div>
                </div>

                <div class="kpi-card kpi-green">
                    <div class="kpi-details">
                        <span class="kpi-title">Active Member Ratio</span>
                        <span class="kpi-value"><?= ($totalMembers > 0) ? round(($activeMembers / $totalMembers) * 100, 1) : 0 ?>%</span>
                        <span class="kpi-subtext"><?= $activeMembers ?> of <?= $totalMembers ?> total</span>
                    </div>
                    <div class="kpi-icon">
                        <svg width="24" height="24"><use href="#icon-membership"></use></svg>
                    </div>
                </div>
            </div>

            <!-- Two-Column Analytics Layout -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(460px, 1fr)); gap: 24px; margin-bottom: 24px;">
                <!-- Plan Popularity & Revenue Contribution -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <svg width="18" height="18"><use href="#icon-plan"></use></svg>
                            Membership Plan Popularity
                        </h2>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Plan Name</th>
                                    <th>Price</th>
                                    <th>Subscriptions Sold</th>
                                    <th>Total Generated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($planPopularity as $plan): ?>
                                    <tr>
                                        <td style="font-weight: 700; color: #ffffff;"><?= e($plan['plan_name']) ?></td>
                                        <td><?= format_currency($plan['price']) ?></td>
                                        <td>
                                            <span class="badge badge-orange"><?= (int)$plan['total_sold'] ?> purchases</span>
                                        </td>
                                        <td style="font-weight: 700; color: var(--success);"><?= format_currency($plan['total_plan_revenue']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Payment Methods Revenue Breakdown -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <svg width="18" height="18"><use href="#icon-payment"></use></svg>
                            Collections by Payment Method
                        </h2>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Payment Method</th>
                                    <th>Transaction Count</th>
                                    <th>Total Volume</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($methodBreakdown as $m): ?>
                                    <tr>
                                        <td style="font-weight: 700; color: #ffffff;">
                                            <span class="badge badge-secondary"><?= e($m['payment_method']) ?></span>
                                        </td>
                                        <td><?= (int)$m['count'] ?> transactions</td>
                                        <td style="font-weight: 800; color: var(--success);"><?= format_currency($m['total_amount']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Attendance Daily Trend Table -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <svg width="18" height="18"><use href="#icon-attendance"></use></svg>
                        Daily Check-In Activity (In Selected Period)
                    </h2>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day of Week</th>
                                <th>Total Check-Ins</th>
                                <th>Relative Activity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($trendRows)): ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--text-dim); padding: 24px;">No attendance logged in this range.</td></tr>
                            <?php else: ?>
                                <?php foreach ($trendRows as $tr): ?>
                                    <tr>
                                        <td style="font-weight: 700; color: #ffffff;"><?= format_date($tr['attendance_date']) ?></td>
                                        <td><span class="badge badge-secondary"><?= date('l', strtotime($tr['attendance_date'])) ?></span></td>
                                        <td style="font-weight: 800; color: var(--primary);"><?= (int)$tr['check_ins'] ?> athletes</td>
                                        <td>
                                            <div style="background: var(--bg-main); width: 100%; max-width: 200px; height: 8px; border-radius: 4px; overflow: hidden;">
                                                <div style="background: var(--primary); height: 100%; width: <?= min(100, (int)$tr['check_ins'] * 15) ?>%;"></div>
                                            </div>
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
