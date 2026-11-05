<?php
/**
 * Member Management Actions
 * Gym Management System
 */

require_once __DIR__ . '/../includes/admin_auth.php';

// Verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid security token. Please try again.');
        header('Location: ' . base_url('admin/members.php'));
        exit;
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db = getDB();

switch ($action) {
    case 'add_member':
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $gender = $_POST['gender'] ?? 'Male';
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        $address = trim($_POST['address'] ?? '');
        $emergencyContact = trim($_POST['emergency_contact'] ?? '');
        $bloodGroup = trim($_POST['blood_group'] ?? '');
        $planId = (int)($_POST['plan_id'] ?? 0);
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
        $createAccount = isset($_POST['create_account']);
        $accountPassword = $_POST['account_password'] ?? 'Member@123';

        if (empty($fullName) || empty($email) || empty($phone)) {
            set_flash('error', 'Full Name, Email, and Phone number are required.');
            header('Location: ' . base_url('admin/members.php'));
            exit;
        }

        try {
            $db->beginTransaction();

            $userId = null;
            if ($createAccount) {
                // Check if user email already exists
                $chk = $db->prepare("SELECT id FROM users WHERE email = :email");
                $chk->execute([':email' => $email]);
                if ($chk->fetch()) {
                    throw new Exception('A user account with this email address already exists.');
                }

                $userStmt = $db->prepare("
                    INSERT INTO users (name, email, password, role, status)
                    VALUES (:name, :email, :password, 'member', 'active')
                ");
                $userStmt->execute([
                    ':name'     => $fullName,
                    ':email'    => $email,
                    ':password' => password_hash($accountPassword, PASSWORD_DEFAULT)
                ]);
                $userId = (int)$db->lastInsertId();
            }

            // Generate Member Code
            $memberCode = generate_next_member_code();

            $memberStmt = $db->prepare("
                INSERT INTO members (user_id, member_code, full_name, email, phone, gender, dob, address, emergency_contact, blood_group, status)
                VALUES (:user_id, :member_code, :full_name, :email, :phone, :gender, :dob, :address, :emergency_contact, :blood_group, 'active')
            ");
            $memberStmt->execute([
                ':user_id'           => $userId,
                ':member_code'       => $memberCode,
                ':full_name'         => $fullName,
                ':email'             => $email,
                ':phone'             => $phone,
                ':gender'            => $gender,
                ':dob'               => $dob,
                ':address'           => $address,
                ':emergency_contact' => $emergencyContact,
                ':blood_group'       => $bloodGroup
            ]);
            $memberId = (int)$db->lastInsertId();

            // If a membership plan was selected, create initial subscription
            if ($planId > 0) {
                $planStmt = $db->prepare("SELECT * FROM membership_plans WHERE id = :id");
                $planStmt->execute([':id' => $planId]);
                $plan = $planStmt->fetch();

                if ($plan) {
                    $duration = (int)$plan['duration_days'];
                    $expiryDate = date('Y-m-d', strtotime("$startDate + $duration days"));

                    $subStmt = $db->prepare("
                        INSERT INTO memberships (member_id, plan_id, start_date, expiry_date, total_amount, paid_amount, status)
                        VALUES (:member_id, :plan_id, :start_date, :expiry_date, :total_amount, 0.00, 'active')
                    ");
                    $subStmt->execute([
                        ':member_id'    => $memberId,
                        ':plan_id'      => $plan['id'],
                        ':start_date'   => $startDate,
                        ':expiry_date'  => $expiryDate,
                        ':total_amount' => $plan['price']
                    ]);
                }
            }

            $db->commit();
            set_flash('success', "Member {$fullName} ({$memberCode}) registered successfully!");
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('error', 'Error adding member: ' . $e->getMessage());
        }

        header('Location: ' . base_url('admin/members.php'));
        exit;
        break;

    case 'edit_member':
        $memberId = (int)($_POST['member_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $gender = $_POST['gender'] ?? 'Male';
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        $address = trim($_POST['address'] ?? '');
        $emergencyContact = trim($_POST['emergency_contact'] ?? '');
        $bloodGroup = trim($_POST['blood_group'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if ($memberId <= 0 || empty($fullName) || empty($email) || empty($phone)) {
            set_flash('error', 'Invalid member record or required fields missing.');
            header('Location: ' . base_url('admin/members.php'));
            exit;
        }

        try {
            $stmt = $db->prepare("
                UPDATE members SET
                    full_name = :full_name,
                    email = :email,
                    phone = :phone,
                    gender = :gender,
                    dob = :dob,
                    address = :address,
                    emergency_contact = :emergency_contact,
                    blood_group = :blood_group,
                    status = :status
                WHERE id = :id
            ");
            $stmt->execute([
                ':full_name'         => $fullName,
                ':email'             => $email,
                ':phone'             => $phone,
                ':gender'            => $gender,
                ':dob'               => $dob,
                ':address'           => $address,
                ':emergency_contact' => $emergencyContact,
                ':blood_group'       => $bloodGroup,
                ':status'            => $status,
                ':id'                => $memberId
            ]);

            // Sync user name and email if linked user account exists
            $db->prepare("
                UPDATE users u
                JOIN members m ON u.id = m.user_id
                SET u.name = :name, u.email = :email
                WHERE m.id = :id
            ")->execute([
                ':name'  => $fullName,
                ':email' => $email,
                ':id'    => $memberId
            ]);

            set_flash('success', 'Member details updated successfully.');
        } catch (Exception $e) {
            set_flash('error', 'Failed to update member: ' . $e->getMessage());
        }

        header('Location: ' . base_url('admin/members.php'));
        exit;
        break;

    case 'delete_member':
        $memberId = (int)($_POST['member_id'] ?? $_GET['id'] ?? 0);

        if ($memberId <= 0) {
            set_flash('error', 'Invalid member ID.');
            header('Location: ' . base_url('admin/members.php'));
            exit;
        }

        try {
            $db->beginTransaction();

            // Find linked user_id if any
            $stmt = $db->prepare("SELECT user_id, full_name FROM members WHERE id = :id");
            $stmt->execute([':id' => $memberId]);
            $member = $stmt->fetch();

            if ($member) {
                $gatewayStmt = $db->prepare('SELECT id FROM phonepe_orders WHERE member_id = :member_id LIMIT 1');
                $gatewayStmt->execute([':member_id' => $memberId]);
                if ($gatewayStmt->fetch()) {
                    $db->rollBack();
                    set_flash('warning', 'This member has PhonePe payment records and cannot be deleted. Deactivate the member instead.');
                    header('Location: ' . base_url('admin/members.php'));
                    exit;
                }

                // Delete member (cascades to attendance, memberships, payments, member_workouts)
                $del = $db->prepare("DELETE FROM members WHERE id = :id");
                $del->execute([':id' => $memberId]);

                // Delete linked user account if exists
                if (!empty($member['user_id'])) {
                    $delUser = $db->prepare("DELETE FROM users WHERE id = :user_id");
                    $delUser->execute([':user_id' => $member['user_id']]);
                }

                $db->commit();
                set_flash('success', "Member '{$member['full_name']}' was deleted successfully.");
            } else {
                $db->rollBack();
                set_flash('error', 'Member not found.');
            }
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('error', 'Failed to delete member: ' . $e->getMessage());
        }

        header('Location: ' . base_url('admin/members.php'));
        exit;
        break;

    default:
        header('Location: ' . base_url('admin/members.php'));
        exit;
}
