<?php
/**
 * Member Portal Dashboard
 * Gym Management System
 */

require_once __DIR__ . '/../includes/member_auth.php';

$db = getDB();
$userId = $_SESSION['user']['id'];

// 1. Fetch Member Profile
$mStmt = $db->prepare("SELECT * FROM members WHERE user_id = :uid LIMIT 1");
$mStmt->execute([':uid' => $userId]);
$member = $mStmt->fetch();

if (!$member) {
    die("Member profile record not linked to this user account. Please contact gym administrator.");
}

$memberId = (int) $member['id'];

// 2. Fetch Active Membership
$subStmt = $db->prepare("
    SELECT ms.*, mp.plan_name, mp.duration_days,
           DATEDIFF(ms.expiry_date, CURRENT_DATE) AS days_remaining
    FROM memberships ms
    JOIN membership_plans mp ON ms.plan_id = mp.id
    WHERE ms.member_id = :mid AND ms.status = 'active'
      AND ms.start_date <= CURRENT_DATE AND ms.expiry_date >= CURRENT_DATE
    ORDER BY ms.expiry_date DESC
    LIMIT 1
");
$subStmt->execute([':mid' => $memberId]);
$membership = $subStmt->fetch();

// 3. Fetch Monthly Check-Ins
$attStmt = $db->prepare("
    SELECT COUNT(*) 
    FROM attendance 
    WHERE member_id = :mid 
      AND MONTH(attendance_date) = MONTH(CURRENT_DATE) 
      AND YEAR(attendance_date) = YEAR(CURRENT_DATE)
");
$attStmt->execute([':mid' => $memberId]);
$monthCheckIns = (int) $attStmt->fetchColumn();

// 4. Check If Checked In Today
$todayStmt = $db->prepare("SELECT * FROM attendance WHERE member_id = :mid AND attendance_date = CURRENT_DATE LIMIT 1");
$todayStmt->execute([':mid' => $memberId]);
$todayAttendance = $todayStmt->fetch();

// 5. Fetch Active Assigned Workout Plan
$wStmt = $db->prepare("
    SELECT wp.*, mw.assigned_date, mw.notes AS assignment_notes,
           t.name AS trainer_name
    FROM member_workouts mw
    JOIN workout_plans wp ON mw.workout_plan_id = wp.id
    LEFT JOIN trainers t ON wp.trainer_id = t.id
    WHERE mw.member_id = :mid AND mw.status = 'active'
    ORDER BY mw.id DESC
    LIMIT 1
");
$wStmt->execute([':mid' => $memberId]);
$workoutPlan = $wStmt->fetch();

$plans = $db->query("SELECT id, plan_name, duration_days, price, description FROM membership_plans WHERE status = 'active' ORDER BY price ASC")->fetchAll();
$selectedPlanId = (int) ($_SESSION['selected_plan_id'] ?? 0);
unset($_SESSION['selected_plan_id']);

// 6. Fetch Today's Day of Week Exercises if plan exists
$todayDay = date('l'); // 'Monday', 'Tuesday', etc.
$todayExercises = [];
if ($workoutPlan) {
    $exStmt = $db->prepare("
        SELECT * FROM workout_exercises 
        WHERE workout_plan_id = :pid AND day_of_week = :day
        ORDER BY display_order ASC
    ");
    $exStmt->execute([':pid' => $workoutPlan['id'], ':day' => $todayDay]);
    $todayExercises = $exStmt->fetchAll();
}

$pageTitle = 'Member Overview';
$navTitle = 'Welcome back, ' . $member['full_name'];
include __DIR__ . '/../includes/header.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <main class="main-content">
            <!-- Digital Membership Card -->
            <div class="membership-card">
                <div class="card-top">
                    <div>
                        <span
                            style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--primary); font-weight: 800;">
                            OFFICIAL ATHLETE PASS
                        </span>
                        <div class="card-plan-name">
                            <?= $membership ? e($membership['plan_name']) : 'No Active Membership' ?>
                        </div>
                        <span class="card-code"><?= e($member['member_code']) ?> &bull;
                            <?= e($member['full_name']) ?></span>
                    </div>

                    <div>
                        <?php if ($membership): ?>
                            <span class="badge badge-success" style="font-size: 13px; padding: 6px 14px;">ACTIVE
                                ATHLETE</span>
                        <?php else: ?>
                            <span class="badge badge-danger" style="font-size: 13px; padding: 6px 14px;">EXPIRED /
                                INACTIVE</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-bottom">
                    <div class="card-detail-item">
                        <span class="card-detail-label">Membership Validity</span>
                        <span class="card-detail-value">
                            <?= $membership ? format_date($membership['expiry_date']) : 'Expired' ?>
                        </span>
                    </div>

                    <div class="card-detail-item">
                        <span class="card-detail-label">Days Remaining</span>
                        <span class="card-detail-value" style="color: var(--primary);">
                            <?= $membership ? ((int) $membership['days_remaining'] . ' Days') : '0 Days' ?>
                        </span>
                    </div>

                    <div class="card-detail-item">
                        <span class="card-detail-label">Visits This Month</span>
                        <span class="card-detail-value">
                            <?= $monthCheckIns ?> Check-Ins
                        </span>
                    </div>

                    <div class="card-detail-item">
                        <span class="card-detail-label">Today's Check-In</span>
                        <span class="card-detail-value"
                            style="color: <?= $todayAttendance ? 'var(--success)' : 'var(--text-dim)' ?>;">
                            <?= $todayAttendance ? ('⏱️ In at ' . format_time($todayAttendance['check_in_time'])) : 'Not checked in yet' ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top: 24px;">
                <div class="card-header">
                    <h2 class="card-title">Membership Plans</h2>
                </div>
                <div class="plans-grid" style="padding: 20px;">
                    <?php foreach ($plans as $plan): ?>
                        <div class="pricing-card">
                            <h3 class="plan-name"><?= e($plan['plan_name']) ?></h3>
                            <?php if ((int) $plan['id'] === $selectedPlanId): ?>
                                <span class="badge badge-success">Selected during registration</span>
                            <?php endif; ?>
                            <div class="plan-price"><?= format_currency($plan['price']) ?>
                                <span>/ <?= (int) $plan['duration_days'] ?> Days</span>
                            </div>
                            <p style="color: var(--text-dim); font-size: 13px; margin-bottom: 16px;">
                                <?= e($plan['description'] ?: 'Complete gym access package.') ?>
                            </p>
                            <form action="<?= base_url('actions/phonepe-actions.php') ?>" method="POST"
                                data-loading="Connecting to PhonePe...">
                                <?= csrf_input() ?>
                                <button type="button" class="btn btn-primary" style="width:100%;" data-upi-pay
                                    data-plan-id="<?= (int) $plan['id'] ?>" data-plan-name="<?= e($plan['plan_name']) ?>"
                                    data-plan-price="<?= e(format_currency($plan['price'])) ?>">
                                    Pay Now with PhonePe
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Two-Column Grid: Today's Workout & Quick Stats -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(440px, 1fr)); gap: 24px;">
                <!-- Today's Workout Routine Card -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <svg width="18" height="18">
                                <use href="#icon-workout"></use>
                            </svg>
                            Today's Workout (<?= $todayDay ?>)
                        </h2>
                        <?php if ($workoutPlan): ?>
                            <a href="<?= base_url('member/workouts.php') ?>" class="btn btn-secondary btn-sm">Full Routine
                                &rarr;</a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (!$workoutPlan): ?>
                            <div style="text-align: center; padding: 24px; color: var(--text-dim);">
                                No workout routine assigned yet. Your coach will configure your plan soon!
                            </div>
                        <?php elseif (empty($todayExercises)): ?>
                            <div style="text-align: center; padding: 24px;">
                                <div style="font-size: 32px; margin-bottom: 8px;">🧘</div>
                                <h3 style="color: #ffffff; font-size: 16px;">Scheduled Rest & Recovery Day</h3>
                                <p style="color: var(--text-muted); font-size: 13px; margin-top: 4px;">
                                    No heavy training programmed for <?= $todayDay ?>. Focus on hydration, mobility, and
                                    nutrition.
                                </p>
                            </div>
                        <?php else: ?>
                            <div style="margin-bottom: 12px; font-size: 13px; color: var(--text-dim);">
                                Program: <strong style="color: var(--primary);"><?= e($workoutPlan['plan_name']) ?></strong>
                                &bull; Coach <?= e($workoutPlan['trainer_name']) ?>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Movement</th>
                                            <th>Volume</th>
                                            <th>Rest</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($todayExercises as $ex): ?>
                                            <tr>
                                                <td style="font-weight: 700; color: #ffffff;">
                                                    <?= e($ex['exercise_name']) ?>
                                                    <?php if ($ex['instructions']): ?>
                                                        <div
                                                            style="font-size: 11px; color: var(--text-muted); font-weight: normal;">
                                                            <?= e($ex['instructions']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge badge-orange"><?= (int) $ex['sets'] ?> &times;
                                                        <?= e($ex['reps']) ?></span></td>
                                                <td><?= (int) $ex['rest_seconds'] ?>s</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Fast Links & Athlete Profile Snapshot -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <svg width="18" height="18">
                                <use href="#icon-users"></use>
                            </svg>
                            Athlete Profile
                        </h2>
                        <a href="<?= base_url('member/profile.php') ?>" class="btn btn-secondary btn-sm">Edit
                            Profile</a>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 12px; font-size: 14px;">
                            <div
                                style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                                <span style="color: var(--text-muted);">Member Code:</span>
                                <strong
                                    style="font-family: monospace; color: var(--primary);"><?= e($member['member_code']) ?></strong>
                            </div>
                            <div
                                style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                                <span style="color: var(--text-muted);">Registered Email:</span>
                                <strong style="color: #ffffff;"><?= e($member['email']) ?></strong>
                            </div>
                            <div
                                style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                                <span style="color: var(--text-muted);">Phone Number:</span>
                                <strong style="color: #ffffff;"><?= e($member['phone']) ?></strong>
                            </div>
                            <div
                                style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                                <span style="color: var(--text-muted);">Blood Group:</span>
                                <strong
                                    style="color: #ffffff;"><?= e($member['blood_group'] ?: 'Not recorded') ?></strong>
                            </div>
                            <div
                                style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                                <span style="color: var(--text-muted);">Emergency Contact:</span>
                                <strong
                                    style="color: #ffffff;"><?= e($member['emergency_contact'] ?: 'None listed') ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding-top: 4px;">
                                <span style="color: var(--text-muted);">Member Since:</span>
                                <strong style="color: #ffffff;"><?= format_date($member['created_at']) ?></strong>
                            </div>
                        </div>

                        <div style="display: flex; gap: 10px; margin-top: 24px;">
                            <a href="<?= base_url('member/attendance.php') ?>" class="btn btn-secondary btn-sm"
                                style="flex: 1;">
                                Attendance History
                            </a>
                            <a href="<?= base_url('member/payments.php') ?>" class="btn btn-secondary btn-sm"
                                style="flex: 1;">
                                Payment Receipts
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../includes/upi-pay-modal.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="<?= base_url('assets/js/Upi_pay.js') ?>"></script>