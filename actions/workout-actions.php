<?php
/**
 * Workout Plan & Routine Management Actions
 * Gym Management System
 */

require_once __DIR__ . '/../includes/admin_auth.php';

// Verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid security token.');
        header('Location: ' . base_url('admin/workouts.php'));
        exit;
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db = getDB();

switch ($action) {
    case 'create_plan':
        $planName = trim($_POST['plan_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $difficulty = $_POST['difficulty_level'] ?? 'Beginner';
        $goal = $_POST['goal'] ?? 'General Fitness';
        $trainerId = !empty($_POST['trainer_id']) ? (int)$_POST['trainer_id'] : null;

        $exerciseDays = $_POST['exercise_day'] ?? [];
        $exerciseNames = $_POST['exercise_name'] ?? [];
        $exerciseSets = $_POST['exercise_sets'] ?? [];
        $exerciseReps = $_POST['exercise_reps'] ?? [];
        $exerciseRests = $_POST['exercise_rest'] ?? [];
        $exerciseInstructions = $_POST['exercise_instructions'] ?? [];

        if (empty($planName)) {
            set_flash('error', 'Workout plan name is required.');
            header('Location: ' . base_url('admin/workouts.php'));
            exit;
        }

        try {
            $db->beginTransaction();

            // 1. Insert Workout Plan
            $planStmt = $db->prepare("
                INSERT INTO workout_plans (trainer_id, plan_name, description, difficulty_level, goal, status)
                VALUES (:trainer_id, :plan_name, :description, :difficulty_level, :goal, 'active')
            ");
            $planStmt->execute([
                ':trainer_id'        => $trainerId,
                ':plan_name'         => $planName,
                ':description'       => $description,
                ':difficulty_level'  => $difficulty,
                ':goal'              => $goal
            ]);
            $planId = (int)$db->lastInsertId();

            // 2. Insert Exercises
            $exStmt = $db->prepare("
                INSERT INTO workout_exercises (workout_plan_id, day_of_week, exercise_name, sets, reps, rest_seconds, instructions, display_order)
                VALUES (:plan_id, :day, :name, :sets, :reps, :rest, :instructions, :display_order)
            ");

            for ($i = 0; $i < count($exerciseNames); $i++) {
                $name = trim($exerciseNames[$i]);
                if (empty($name)) continue;

                $day = $exerciseDays[$i] ?? 'Monday';
                $sets = (int)($exerciseSets[$i] ?? 3);
                $reps = trim($exerciseReps[$i] ?? '10-12');
                $rest = (int)($exerciseRests[$i] ?? 60);
                $instructions = trim($exerciseInstructions[$i] ?? '');

                $exStmt->execute([
                    ':plan_id'       => $planId,
                    ':day'           => $day,
                    ':name'          => $name,
                    ':sets'          => $sets,
                    ':reps'          => $reps,
                    ':rest'          => $rest,
                    ':instructions'  => $instructions,
                    ':display_order' => $i + 1
                ]);
            }

            $db->commit();
            set_flash('success', "Workout plan '{$planName}' created successfully with " . count($exerciseNames) . " exercises!");
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('error', 'Failed to create workout plan: ' . $e->getMessage());
        }

        header('Location: ' . base_url('admin/workouts.php'));
        exit;
        break;

    case 'assign_workout':
        $memberId = (int)($_POST['member_id'] ?? 0);
        $planId = (int)($_POST['workout_plan_id'] ?? 0);
        $assignedDate = !empty($_POST['assigned_date']) ? $_POST['assigned_date'] : date('Y-m-d');
        $notes = trim($_POST['notes'] ?? '');

        if ($memberId <= 0 || $planId <= 0) {
            set_flash('error', 'Please select both a member and a workout plan.');
            header('Location: ' . base_url('admin/workouts.php'));
            exit;
        }

        try {
            // Deactivate previous active workout plans for this member
            $db->prepare("UPDATE member_workouts SET status = 'inactive' WHERE member_id = :mid AND status = 'active'")
               ->execute([':mid' => $memberId]);

            // Assign new workout
            $stmt = $db->prepare("
                INSERT INTO member_workouts (member_id, workout_plan_id, assigned_date, status, notes)
                VALUES (:member_id, :workout_plan_id, :assigned_date, 'active', :notes)
            ");
            $stmt->execute([
                ':member_id'        => $memberId,
                ':workout_plan_id'  => $planId,
                ':assigned_date'    => $assignedDate,
                ':notes'            => $notes
            ]);

            set_flash('success', 'Workout plan assigned to member successfully!');
        } catch (Exception $e) {
            set_flash('error', 'Failed to assign workout plan: ' . $e->getMessage());
        }

        header('Location: ' . base_url('admin/workouts.php'));
        exit;
        break;

    case 'delete_plan':
        $planId = (int)($_POST['plan_id'] ?? $_GET['id'] ?? 0);

        if ($planId <= 0) {
            set_flash('error', 'Invalid workout plan ID.');
            header('Location: ' . base_url('admin/workouts.php'));
            exit;
        }

        try {
            $stmt = $db->prepare("DELETE FROM workout_plans WHERE id = :id");
            $stmt->execute([':id' => $planId]);
            set_flash('success', 'Workout plan deleted successfully.');
        } catch (Exception $e) {
            set_flash('error', 'Failed to delete workout plan: ' . $e->getMessage());
        }

        header('Location: ' . base_url('admin/workouts.php'));
        exit;
        break;

    default:
        header('Location: ' . base_url('admin/workouts.php'));
        exit;
}
