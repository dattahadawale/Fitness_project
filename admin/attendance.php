<?php
/**
 * Attendance Management Module
 * Gym Management System
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$db = getDB();

// 1. Date & Member Filters
$filterDate = $_GET['date'] ?? date('Y-m-d');
$memberFilter = (int)($_GET['member_id'] ?? 0);

$whereClauses = ["1=1"];
$params = [];

if (!empty($filterDate)) {
    $whereClauses[] = "a.attendance_date = :date";
    $params[':date'] = $filterDate;
}
if ($memberFilter > 0) {
    $whereClauses[] = "a.member_id = :member_id";
    $params[':member_id'] = $memberFilter;
}

$whereSQL = implode(" AND ", $whereClauses);

// Fetch attendance logs
$attendanceLogs = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, m.full_name, m.member_code, m.phone, m.gender,
               COALESCE(mp.plan_name, 'No Active Plan') AS plan_name
        FROM attendance a
        JOIN members m ON a.member_id = m.id
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
        WHERE {$whereSQL}
        ORDER BY a.check_in_time DESC
    ");
    $stmt->execute($params);
    $attendanceLogs = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch active members for fast check-in selector
$activeMembers = $db->query("
    SELECT id, full_name, member_code 
    FROM members 
    WHERE status = 'active' 
    ORDER BY full_name ASC
")->fetchAll();

// Daily Summary KPI for selected date
$dayCount = count($attendanceLogs);

$pageTitle = 'Attendance Management';
$navTitle = 'Daily Check-Ins & Attendance';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <main class="main-content">
            <!-- Fast Check-In & Filter Bar -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 24px;">
                <!-- Fast Check-In Form Card -->
                <div class="card" style="margin-bottom: 0;">
                    <div class="card-header">
                        <h2 class="card-title">
                            <svg width="18" height="18"><use href="#icon-attendance"></use></svg>
                            Instant Member Check-In
                        </h2>
                    </div>
                    <div class="card-body">
                        <form action="<?= base_url('actions/attendance-actions.php') ?>" method="POST">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="check_in">

                            <div class="form-group">
                                <label class="form-label" for="ci_member">Select Member <span class="required">*</span></label>
                                <select id="ci_member" name="member_id" class="form-control" required autofocus>
                                    <option value="">-- Choose Member to Check-In --</option>
                                    <?php foreach ($activeMembers as $m): ?>
                                        <option value="<?= $m['id'] ?>">
                                            <?= e($m['full_name']) ?> (<?= e($m['member_code']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="ci_date">Date</label>
                                    <input type="date" id="ci_date" name="attendance_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="ci_time">Check-In Time</label>
                                    <input type="time" id="ci_time" name="check_in_time" class="form-control" value="<?= date('H:i') ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="ci_notes">Notes / Workout Focus</label>
                                <input type="text" id="ci_notes" name="notes" class="form-control" placeholder="e.g. Chest & Triceps routine, Leg day...">
                            </div>

                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                Record Check-In &rarr;
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Date & Member Filter Card -->
                <div class="card" style="margin-bottom: 0;">
                    <div class="card-header">
                        <h2 class="card-title">Filter Attendance Records</h2>
                    </div>
                    <div class="card-body">
                        <form method="GET">
                            <div class="form-group">
                                <label class="form-label" for="filter_date">Select Date</label>
                                <input type="date" id="filter_date" name="date" class="form-control" value="<?= e($filterDate) ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="filter_member">Filter by Member</label>
                                <select id="filter_member" name="member_id" class="form-control">
                                    <option value="">All Members</option>
                                    <?php foreach ($activeMembers as $m): ?>
                                        <option value="<?= $m['id'] ?>" <?= ($memberFilter == $m['id']) ? 'selected' : '' ?>>
                                            <?= e($m['full_name']) ?> (<?= e($m['member_code']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="display: flex; gap: 10px; margin-top: 24px;">
                                <button type="submit" class="btn btn-secondary" style="flex: 1;">Apply Filter</button>
                                <a href="?date=<?= date('Y-m-d') ?>" class="btn btn-outline-primary">Today</a>
                            </div>

                            <div style="margin-top: 24px; padding: 14px; background: var(--bg-main); border-radius: var(--radius-sm); border: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: var(--text-muted); font-size: 13px;">Total Check-Ins on <?= format_date($filterDate) ?>:</span>
                                <strong style="font-size: 18px; color: var(--primary);"><?= $dayCount ?> athletes</strong>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Attendance Records Table -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        Attendance Logs for <?= format_date($filterDate) ?>
                    </h2>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Plan</th>
                                <th>Check-In Time</th>
                                <th>Check-Out Time</th>
                                <th>Workout Notes</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendanceLogs)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-dim); padding: 36px;">
                                        No check-ins recorded for this date.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendanceLogs as $a): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 700; color: #ffffff;"><?= e($a['full_name']) ?></div>
                                            <div style="font-size: 11px; font-family: monospace; color: var(--primary);">
                                                <?= e($a['member_code']) ?>
                                            </div>
                                        </td>
                                        <td><span class="badge badge-info"><?= e($a['plan_name']) ?></span></td>
                                        <td>
                                            <span style="font-weight: 600; color: var(--success);">
                                                ⏱️ <?= format_time($a['check_in_time']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($a['check_out_time']): ?>
                                                <span style="color: var(--text-muted);"><?= format_time($a['check_out_time']) ?></span>
                                            <?php else: ?>
                                                <form action="<?= base_url('actions/attendance-actions.php') ?>" method="POST" style="display: inline;">
                                                    <?= csrf_input() ?>
                                                    <input type="hidden" name="action" value="check_out">
                                                    <input type="hidden" name="attendance_id" value="<?= $a['id'] ?>">
                                                    <button type="submit" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 11px;">
                                                        Mark Exit
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: var(--text-muted); font-size: 13px;">
                                            <?= e($a['notes'] ?: '-') ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <form action="<?= base_url('actions/attendance-actions.php') ?>" method="POST" style="display: inline;" onsubmit="return confirm('Remove attendance entry?');">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="action" value="delete_attendance">
                                                <input type="hidden" name="attendance_id" value="<?= $a['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" title="Delete Entry">&times;</button>
                                            </form>
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
