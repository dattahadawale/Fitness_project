<?php
/**
 * Trainer Management Actions
 * Gym Management System
 */

require_once __DIR__ . '/../includes/admin_auth.php';

// Verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid security token.');
        header('Location: ' . base_url('admin/trainers.php'));
        exit;
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db = getDB();

switch ($action) {
    case 'add_trainer':
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $gender = $_POST['gender'] ?? 'Male';
        $specialization = trim($_POST['specialization'] ?? '');
        $experience = (int)($_POST['experience_years'] ?? 1);
        $salary = (float)($_POST['salary'] ?? 0);
        $bio = trim($_POST['bio'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($name) || empty($email) || empty($phone) || empty($specialization)) {
            set_flash('error', 'Name, Email, Phone, and Specialization are required.');
            header('Location: ' . base_url('admin/trainers.php'));
            exit;
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO trainers (name, email, phone, gender, specialization, experience_years, salary, bio, status)
                VALUES (:name, :email, :phone, :gender, :specialization, :experience, :salary, :bio, :status)
            ");
            $stmt->execute([
                ':name'           => $name,
                ':email'          => $email,
                ':phone'          => $phone,
                ':gender'         => $gender,
                ':specialization' => $specialization,
                ':experience'     => $experience,
                ':salary'         => $salary,
                ':bio'            => $bio,
                ':status'         => $status
            ]);
            set_flash('success', "Trainer '{$name}' added successfully!");
        } catch (Exception $e) {
            set_flash('error', 'Failed to add trainer: ' . $e->getMessage());
        }

        header('Location: ' . base_url('admin/trainers.php'));
        exit;
        break;

    case 'edit_trainer':
        $trainerId = (int)($_POST['trainer_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $gender = $_POST['gender'] ?? 'Male';
        $specialization = trim($_POST['specialization'] ?? '');
        $experience = (int)($_POST['experience_years'] ?? 1);
        $salary = (float)($_POST['salary'] ?? 0);
        $bio = trim($_POST['bio'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if ($trainerId <= 0 || empty($name) || empty($email) || empty($phone)) {
            set_flash('error', 'Required fields are missing.');
            header('Location: ' . base_url('admin/trainers.php'));
            exit;
        }

        try {
            $stmt = $db->prepare("
                UPDATE trainers SET
                    name = :name,
                    email = :email,
                    phone = :phone,
                    gender = :gender,
                    specialization = :specialization,
                    experience_years = :experience,
                    salary = :salary,
                    bio = :bio,
                    status = :status
                WHERE id = :id
            ");
            $stmt->execute([
                ':name'           => $name,
                ':email'          => $email,
                ':phone'          => $phone,
                ':gender'         => $gender,
                ':specialization' => $specialization,
                ':experience'     => $experience,
                ':salary'         => $salary,
                ':bio'            => $bio,
                ':status'         => $status,
                ':id'             => $trainerId
            ]);
            set_flash('success', 'Trainer details updated successfully.');
        } catch (Exception $e) {
            set_flash('error', 'Failed to update trainer: ' . $e->getMessage());
        }

        header('Location: ' . base_url('admin/trainers.php'));
        exit;
        break;

    case 'delete_trainer':
        $trainerId = (int)($_POST['trainer_id'] ?? $_GET['id'] ?? 0);

        if ($trainerId <= 0) {
            set_flash('error', 'Invalid trainer ID.');
            header('Location: ' . base_url('admin/trainers.php'));
            exit;
        }

        try {
            $stmt = $db->prepare("DELETE FROM trainers WHERE id = :id");
            $stmt->execute([':id' => $trainerId]);
            set_flash('success', 'Trainer removed successfully.');
        } catch (Exception $e) {
            set_flash('error', 'Failed to delete trainer: ' . $e->getMessage());
        }

        header('Location: ' . base_url('admin/trainers.php'));
        exit;
        break;

    default:
        header('Location: ' . base_url('admin/trainers.php'));
        exit;
}
