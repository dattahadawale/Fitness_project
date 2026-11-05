<?php
/**
 * Member Assigned Workout Regimen Module
 * Gym Management System
 */

require_once __DIR__ . '/../includes/member_auth.php';

$db = getDB();
$userId = $_SESSION['user']['id'];

$mStmt = $db->prepare("SELECT id, full_name, member_code FROM members WHERE user_id = :uid LIMIT 1");
$mStmt->execute([':uid' => $userId]);
$member = $mStmt->fetch();

$memberId = (int)$member['id'];

// Fetch active assigned workout plan
$planStmt = $db->prepare("
    SELECT wp.*, mw.assigned_date, mw.notes AS coach_note,
           COALESCE(t.name, 'Staff Coach') AS trainer_name,
           t.specialization, t.phone AS trainer_phone
    FROM member_workouts mw
    JOIN workout_plans wp ON mw.workout_plan_id = wp.id
    LEFT JOIN trainers t ON wp.trainer_id = t.id
    WHERE mw.member_id = :mid AND mw.status = 'active'
    ORDER BY mw.id DESC
    LIMIT 1
");
$planStmt->execute([':mid' => $memberId]);
$plan = $planStmt->fetch();

$exercisesByDay = [];
if ($plan) {
    $eStmt = $db->prepare("
        SELECT * FROM workout_exercises 
        WHERE workout_plan_id = :pid 
        ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), display_order ASC
    ");
    $eStmt->execute([':pid' => $plan['id']]);
    $allExercises = $eStmt->fetchAll();

    foreach ($allExercises as $ex) {
        $exercisesByDay[$ex['day_of_week']][] = $ex;
    }
}

$pageTitle = 'My Workout Routine';
$navTitle = 'Assigned Training Programming';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <main class="main-content">
            <?php if (!$plan): ?>
                <div class="card">
                    <div class="card-body" style="text-align: center; padding: 60px 20px;">
                        <div style="font-size: 48px; margin-bottom: 12px;">🏋️</div>
                        <h2 style="color: #ffffff; margin-bottom: 8px;">No Workout Routine Assigned Yet</h2>
                        <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto;">
                            Your certified coach will create and assign a tailored regimen based on your personal fitness goals and assessment.
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <!-- Routine Overview Card -->
                <div class="card" style="border-color: var(--primary); margin-bottom: 24px;">
                    <div class="card-header">
                        <div>
                            <span class="badge badge-orange"><?= e($plan['difficulty_level']) ?></span>
                            <span class="badge badge-info"><?= e($plan['goal']) ?></span>
                            <h2 class="card-title" style="font-size: 22px; margin-top: 6px;">
                                <?= e($plan['plan_name']) ?>
                            </h2>
                            <p style="font-size: 13px; color: var(--text-muted); margin: 4px 0 0;">
                                Assigned on <?= format_date($plan['assigned_date']) ?> by Coach <?= e($plan['trainer_name']) ?> (<?= e($plan['specialization']) ?>)
                            </p>
                        </div>
                    </div>
                    <?php if ($plan['description'] || $plan['coach_note']): ?>
                        <div class="card-body" style="padding: 16px 24px; background: var(--bg-main); font-size: 13px;">
                            <?php if ($plan['description']): ?>
                                <div style="color: var(--text-muted); margin-bottom: 6px;">
                                    <strong>Overview:</strong> <?= e($plan['description']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($plan['coach_note']): ?>
                                <div style="color: var(--primary);">
                                    <strong>Coach Advice:</strong> <?= e($plan['coach_note']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Daily Workout Breakdowns -->
                <?php if (empty($exercisesByDay)): ?>
                    <div class="card">
                        <div class="card-body" style="text-align: center; padding: 40px; color: var(--text-dim);">
                            No specific movements logged under this plan yet.
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($exercisesByDay as $day => $exercises): ?>
                        <div class="card" style="margin-bottom: 24px;">
                            <div class="card-header" style="background: var(--bg-sidebar);">
                                <h3 class="card-title" style="font-size: 16px;">
                                    📅 <?= e($day) ?> Session
                                </h3>
                                <span class="badge badge-secondary"><?= count($exercises) ?> Movements</span>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Exercise Name</th>
                                            <th>Target Sets</th>
                                            <th>Rep Range</th>
                                            <th>Rest Period</th>
                                            <th>Execution Instructions & Form Cues</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($exercises as $i => $ex): ?>
                                            <tr>
                                                <td style="color: var(--text-dim); font-family: monospace;"><?= $i + 1 ?></td>
                                                <td style="font-weight: 700; color: #ffffff; font-size: 15px;">
                                                    <?= e($ex['exercise_name']) ?>
                                                </td>
                                                <td><span class="badge badge-orange"><?= (int)$ex['sets'] ?> Sets</span></td>
                                                <td><strong style="color: var(--text-main);"><?= e($ex['reps']) ?></strong></td>
                                                <td><?= (int)$ex['rest_seconds'] ?> sec</td>
                                                <td style="color: var(--text-muted); font-size: 13px;">
                                                    <?= e($ex['instructions'] ?: 'Standard form and controlled cadence.') ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
