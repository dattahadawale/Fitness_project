<?php
/**
 * Attendance Management Actions
 * Gym Management System
 */

require_once __DIR__ . '/../includes/admin_auth.php';

// Verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid security token.');
        header('Location: ' . base_url('admin/attendance.php'));
        exit;
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db = getDB();

switch ($action) {
    case 'check_in':
        $memberId = (int) ($_POST['member_id'] ?? 0);
        $attendanceDate = !empty($_POST['attendance_date']) ? $_POST['attendance_date'] : date('Y-m-d');
        $checkInTime = !empty($_POST['check_in_time']) ? $_POST['check_in_time'] : date('H:i:s');
        $notes = trim($_POST['notes'] ?? '');

        if ($memberId <= 0) {
            set_flash('error', 'Please select a valid member.');
            header('Location: ' . base_url('admin/attendance.php'));
            exit;
        }

        // Prevent duplicate check-in for the same member on the same day
        $checkStmt = $db->prepare("
            SELECT a.check_in_time, m.full_name, m.member_code 
            FROM attendance a
            JOIN members m ON a.member_id = m.id
            WHERE a.member_id = :member_id AND a.attendance_date = :att_date
            LIMIT 1
        ");
        $checkStmt->execute([
            ':member_id' => $memberId,
            ':att_date' => $attendanceDate
        ]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            $formattedTime = date('h:i A', strtotime($existing['check_in_time']));
            set_flash('warning', "Duplicate check-in prevented! {$existing['full_name']} ({$existing['member_code']}) already checked in today at {$formattedTime}.");
            header('Location: ' . base_url('admin/attendance.php'));
            exit;
        }

        try {
            $insertStmt = $db->prepare("
                INSERT INTO attendance (member_id, attendance_date, check_in_time, notes)
                VALUES (:member_id, :attendance_date, :check_in_time, :notes)
            ");
            $insertStmt->execute([
                ':member_id' => $memberId,
                ':attendance_date' => $attendanceDate,
                ':check_in_time' => $checkInTime,
                ':notes' => $notes
            ]);

            // Fetch member name for friendly notification
            $mStmt = $db->prepare("SELECT full_name FROM members WHERE id = :id");
            $mStmt->execute([':id' => $memberId]);
            $memberName = $mStmt->fetchColumn() ?: 'Member';

            set_flash('success', "Check-in recorded for {$memberName} at " . date('h:i A', strtotime($checkInTime)));
        } catch (Exception $e) {
            set_flash('error', 'Failed to record attendance: ' . $e->getMessage());
        }

        header('Location: ' . base_url('admin/attendance.php'));
        exit;
        break;

    case 'check_out':
        $attendanceId = (int) ($_POST['attendance_id'] ?? 0);
        $checkOutTime = !empty($_POST['check_out_time']) ? $_POST['check_out_time'] : date('H:i:s');

        if ($attendanceId > 0) {
            $stmt = $db->prepare("UPDATE attendance SET check_out_time = :time WHERE id = :id");
            $stmt->execute([':time' => $checkOutTime, ':id' => $attendanceId]);
            set_flash('success', 'Check-out time recorded.');
        }

        header('Location: ' . base_url('admin/attendance.php'));
        exit;
        break;

    case 'delete_attendance':
        $attendanceId = (int) ($_POST['attendance_id'] ?? $_GET['id'] ?? 0);

        if ($attendanceId > 0) {
            $stmt = $db->prepare("DELETE FROM attendance WHERE id = :id");
            $stmt->execute([':id' => $attendanceId]);
            set_flash('success', 'Attendance record deleted.');
        }

        header('Location: ' . base_url('admin/attendance.php'));
        exit;
        break;

    default:
        header('Location: ' . base_url('admin/attendance.php'));
        exit;
}


