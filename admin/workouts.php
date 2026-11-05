<?php
/**
 * Workout Plan & Routine Builder Module
 * Gym Management System
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$db = getDB();

// 1. Fetch all workout plans with trainer information and exercises count
$plans = $db->query("
    SELECT wp.*, 
           COALESCE(t.name, 'Unassigned / Staff') AS trainer_name,
           COUNT(DISTINCT we.id) AS total_exercises,
           COUNT(DISTINCT mw.id) AS assigned_members_count
    FROM workout_plans wp
    LEFT JOIN trainers t ON wp.trainer_id = t.id
    LEFT JOIN workout_exercises we ON wp.id = we.workout_plan_id
    LEFT JOIN member_workouts mw ON wp.id = mw.workout_plan_id AND mw.status = 'active'
    GROUP BY wp.id
    ORDER BY wp.id DESC
")->fetchAll();

// 2. Fetch active trainers and active members for builder & assignment modals
$trainers = $db->query("SELECT id, name FROM trainers WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$members = $db->query("SELECT id, full_name, member_code FROM members WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();

// 3. If a specific plan is being inspected, fetch all its exercises
$inspectPlanId = (int)($_GET['view_plan_id'] ?? 0);
$planExercises = [];
$activePlan = null;
if ($inspectPlanId > 0) {
    $pStmt = $db->prepare("SELECT * FROM workout_plans WHERE id = :id");
    $pStmt->execute([':id' => $inspectPlanId]);
    $activePlan = $pStmt->fetch();

    if ($activePlan) {
        $eStmt = $db->prepare("
            SELECT * FROM workout_exercises 
            WHERE workout_plan_id = :id 
            ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), display_order ASC
        ");
        $eStmt->execute([':id' => $inspectPlanId]);
        $planExercises = $eStmt->fetchAll();
    }
}

$pageTitle = 'Workout Plan Builder';
$navTitle = 'Workout Regimens & Routines';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <main class="main-content">
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-body" style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h3 style="font-size: 16px; color: #ffffff; margin-bottom: 4px;">Workout Plans & Programming</h3>
                        <p style="font-size: 13px; color: var(--text-muted); margin: 0;">Create multi-exercise routines and assign them to gym athletes.</p>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="openModal('assignWorkoutModal')">
                            👤 Assign to Member
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('createPlanModal')">
                            + Build New Workout Plan
                        </button>
                    </div>
                </div>
            </div>

            <!-- Inspect Routine Preview if requested -->
            <?php if ($activePlan): ?>
                <div class="card" style="border-color: var(--primary); margin-bottom: 24px;">
                    <div class="card-header">
                        <div>
                            <span class="badge badge-orange"><?= e($activePlan['difficulty_level']) ?></span>
                            <span class="badge badge-info"><?= e($activePlan['goal']) ?></span>
                            <h2 class="card-title" style="margin-top: 6px; font-size: 20px;">
                                <?= e($activePlan['plan_name']) ?>
                            </h2>
                            <p style="font-size: 13px; color: var(--text-muted); margin: 4px 0 0;">
                                <?= e($activePlan['description']) ?>
                            </p>
                        </div>
                        <a href="<?= base_url('admin/workouts.php') ?>" class="btn btn-secondary btn-sm">&times; Close View</a>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th>Exercise Name</th>
                                    <th>Sets & Reps</th>
                                    <th>Rest Interval</th>
                                    <th>Coaching Cues / Instructions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($planExercises)): ?>
                                    <tr><td colspan="5" style="text-align: center; color: var(--text-dim); padding: 24px;">No exercises added to this routine yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($planExercises as $ex): ?>
                                        <tr>
                                            <td><span class="badge badge-secondary"><?= e($ex['day_of_week']) ?></span></td>
                                            <td style="font-weight: 700; color: #ffffff;"><?= e($ex['exercise_name']) ?></td>
                                            <td><strong style="color: var(--primary);"><?= (int)$ex['sets'] ?> sets</strong> &bull; <?= e($ex['reps']) ?> reps</td>
                                            <td><?= (int)$ex['rest_seconds'] ?> seconds</td>
                                            <td style="color: var(--text-muted); font-size: 13px;"><?= e($ex['instructions'] ?: '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Workout Plans Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
                <?php foreach ($plans as $p): ?>
                    <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
                        <div class="card-header" style="align-items: flex-start;">
                            <div>
                                <span class="badge badge-orange" style="font-size: 11px;"><?= e($p['difficulty_level']) ?></span>
                                <span class="badge badge-info" style="font-size: 11px;"><?= e($p['goal']) ?></span>
                                <h3 style="font-size: 18px; font-weight: 800; color: #ffffff; margin-top: 8px;"><?= e($p['plan_name']) ?></h3>
                                <div style="font-size: 12px; color: var(--text-dim); margin-top: 2px;">
                                    Coach: <strong><?= e($p['trainer_name']) ?></strong>
                                </div>
                            </div>
                            <span class="badge badge-<?= e($p['status']) ?>"><?= e($p['status']) ?></span>
                        </div>

                        <div class="card-body">
                            <p style="color: var(--text-muted); font-size: 13px; line-height: 1.5; min-height: 40px; margin-bottom: 16px;">
                                <?= e($p['description'] ?: 'High performance workout plan.') ?>
                            </p>

                            <div style="background: var(--bg-main); padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); display: flex; justify-content: space-between; font-size: 13px;">
                                <div>
                                    <span style="color: var(--text-dim);">Exercises:</span>
                                    <strong style="color: #ffffff;"><?= (int)$p['total_exercises'] ?> movements</strong>
                                </div>
                                <div>
                                    <span style="color: var(--text-dim);">Athletes:</span>
                                    <strong style="color: var(--primary);"><?= (int)$p['assigned_members_count'] ?> active</strong>
                                </div>
                            </div>
                        </div>

                        <div class="card-body" style="padding-top: 0; border-top: 1px solid var(--border-color); display: flex; gap: 8px;">
                            <a href="?view_plan_id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" style="flex: 1;">
                                👁️ View Exercises
                            </a>
                            <form action="<?= base_url('actions/workout-actions.php') ?>" method="POST" onsubmit="return confirm('Delete workout plan <?= e($p['plan_name']) ?>?');">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="delete_plan">
                                <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Delete Plan">&times;</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</div>

<!-- ================= CREATE WORKOUT PLAN MODAL ================= -->
<div class="modal-backdrop" id="createPlanModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h3 class="modal-title">Create Structured Workout Routine</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('createPlanModal')">&times;</button>
        </div>
        <form action="<?= base_url('actions/workout-actions.php') ?>" method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="create_plan">

            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label" for="wp_name">Routine Title <span class="required">*</span></label>
                        <input type="text" id="wp_name" name="plan_name" class="form-control" placeholder="e.g. 4-Day Push/Pull/Legs Split" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="wp_trainer">Supervising Coach</label>
                        <select id="wp_trainer" name="trainer_id" class="form-control">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($trainers as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="wp_diff">Difficulty Level</label>
                        <select id="wp_diff" name="difficulty_level" class="form-control">
                            <option value="Beginner">Beginner</option>
                            <option value="Intermediate" selected>Intermediate</option>
                            <option value="Advanced">Advanced</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="wp_goal">Primary Focus / Goal</label>
                        <select id="wp_goal" name="goal" class="form-control">
                            <option value="Muscle Building">Muscle Building / Hypertrophy</option>
                            <option value="Weight Loss">Weight Loss & Fat Burn</option>
                            <option value="Endurance">Cardiovascular & Endurance</option>
                            <option value="Flexibility">Mobility & Flexibility</option>
                            <option value="General Fitness">General Fitness</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="wp_desc">Description & Program Notes</label>
                    <textarea id="wp_desc" name="description" class="form-control" rows="2" placeholder="Overview of split, target rest periods, progressive overload instructions..."></textarea>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h4 style="color: var(--primary); font-size: 15px; margin: 0;">Routine Exercises</h4>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addExerciseRow()">
                        + Add Exercise Row
                    </button>
                </div>

                <div style="font-size: 12px; color: var(--text-dim); margin-bottom: 10px; display: grid; grid-template-columns: 140px 1.5fr 70px 90px 80px 1fr 40px; gap: 10px; padding: 0 14px;">
                    <span>Day</span><span>Exercise Name</span><span>Sets</span><span>Reps</span><span>Rest (s)</span><span>Instructions</span><span></span>
                </div>

                <!-- Dynamic Exercise Rows Container -->
                <div id="exerciseRowsContainer">
                    <div class="exercise-row">
                        <select name="exercise_day[]" class="form-control" required>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                            <option value="Sunday">Sunday</option>
                        </select>
                        <input type="text" name="exercise_name[]" class="form-control" placeholder="Exercise Name (e.g. Barbell Squats)" required>
                        <input type="number" name="exercise_sets[]" class="form-control" value="4" min="1" max="20" title="Sets" required>
                        <input type="text" name="exercise_reps[]" class="form-control" value="8-10" placeholder="Reps" required>
                        <input type="number" name="exercise_rest[]" class="form-control" value="90" min="0" step="15" title="Rest (seconds)" required>
                        <input type="text" name="exercise_instructions[]" class="form-control" placeholder="Key coaching cues">
                        <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.exercise-row').remove();" title="Remove">&times;</button>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createPlanModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Workout Plan</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= ASSIGN WORKOUT MODAL ================= -->
<div class="modal-backdrop" id="assignWorkoutModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Assign Workout Plan to Athlete</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('assignWorkoutModal')">&times;</button>
        </div>
        <form action="<?= base_url('actions/workout-actions.php') ?>" method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="assign_workout">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="assign_member">Select Gym Member <span class="required">*</span></label>
                    <select id="assign_member" name="member_id" class="form-control" required>
                        <option value="">-- Choose Member --</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>">
                                <?= e($m['full_name']) ?> (<?= e($m['member_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="assign_plan">Select Workout Routine <span class="required">*</span></label>
                    <select id="assign_plan" name="workout_plan_id" class="form-control" required>
                        <option value="">-- Choose Routine --</option>
                        <?php foreach ($plans as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= e($p['plan_name']) ?> (<?= e($p['difficulty_level']) ?> &bull; <?= (int)$p['total_exercises'] ?> exercises)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="assign_date">Assignment Date</label>
                    <input type="date" id="assign_date" name="assigned_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="assign_notes">Trainer Note for Member</label>
                    <input type="text" id="assign_notes" name="notes" class="form-control" placeholder="e.g. Focus on strict form for week 1; increase load on compounds.">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('assignWorkoutModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Assign Routine</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
