<?php
/**
 * Membership Plans & Subscription Actions
 * Gym Management System
 */

require_once __DIR__ . '/../includes/admin_auth.php';

// Verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid security token.');
        header('Location: ' . base_url('admin/membership-plans.php'));
        exit;
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db = getDB();

switch ($action) {
    case 'add_plan':
        $planName = trim($_POST['plan_name'] ?? '');
        $durationDays = (int)($_POST['duration_days'] ?? 30);
        $price = (float)($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($planName) || $durationDays <= 0 || $price < 0) {
            set_flash('error', 'Please provide a valid plan name, positive duration, and price.');
            header('Location: ' . base_url('admin/membership-plans.php'));
            exit;
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO membership_plans (plan_name, duration_days, price, description, status)
                VALUES (:plan_name, :duration_days, :price, :description, :status)
            ");
            $stmt->execute([
                ':plan_name'     => $planName,
                ':duration_days' => $durationDays,
                ':price'         => $price,
                ':description'   => $description,
                ':status'        => $status
            ]);
            set_flash('success', "Membership plan '{$planName}' created successfully!");
        } catch (Exception $e) {
            set_flash('error', 'Failed to create plan: ' . $e->getMessage());
        }

        header('Location: ' . base_url('admin/membership-plans.php'));
        exit;
        break;

    case 'edit_plan':
        $planId = (int)($_POST['plan_id'] ?? 0);
        $planName = trim($_POST['plan_name'] ?? '');
        $durationDays = (int)($_POST['duration_days'] ?? 30);
        $price = (float)($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if ($planId <= 0 || empty($planName) || $durationDays <= 0 || $price < 0) {
            set_flash('error', 'Valid inputs required.');
            header('Location: ' . base_url('admin/membership-plans.php'));
            exit;
        }

        try {
            $stmt = $db->prepare("
                UPDATE membership_plans SET
                    plan_name = :plan_name,
                    duration_days = :duration_days,
                    price = :price,
                    description = :description,
                    status = :status
                WHERE id = :id
            ");
            $stmt->execute([
                ':plan_name'     => $planName,
                ':duration_days' => $durationDays,
                ':price'         => $price,
                ':description'   => $description,
                ':status'        => $status,
                ':id'            => $planId
            ]);
            set_flash('success', 'Membership plan updated successfully.');
        } catch (Exception $e) {
            set_flash('error', 'Failed to update plan: ' . $e->getMessage());
        }

        header('Location: ' . base_url('admin/membership-plans.php'));
        exit;
        break;

    case 'delete_plan':
        $planId = (int)($_POST['plan_id'] ?? $_GET['id'] ?? 0);

        if ($planId <= 0) {
            set_flash('error', 'Invalid plan ID.');
            header('Location: ' . base_url('admin/membership-plans.php'));
            exit;
        }

        try {
            // Check if plan is linked to any memberships
            $chk = $db->prepare("SELECT COUNT(*) FROM memberships WHERE plan_id = :id");
            $chk->execute([':id' => $planId]);
            $count = (int)$chk->fetchColumn();
            $orderChk = $db->prepare("SELECT COUNT(*) FROM phonepe_orders WHERE plan_id = :id");
            $orderChk->execute([':id' => $planId]);
            $orderCount = (int)$orderChk->fetchColumn();
            $otpChk = $db->prepare("SELECT COUNT(*) FROM email_verification_otps WHERE selected_plan_id = :id");
            $otpChk->execute([':id' => $planId]);
            $otpCount = (int)$otpChk->fetchColumn();

            if ($count > 0 || $orderCount > 0 || $otpCount > 0) {
                // Prevent deletion to preserve integrity; mark as inactive instead
                $db->prepare("UPDATE membership_plans SET status = 'inactive' WHERE id = :id")->execute([':id' => $planId]);
                set_flash('warning', "Plan cannot be permanently deleted because it is linked to {$count} subscriptions, {$orderCount} PhonePe orders, and {$otpCount} pending registrations. It has been deactivated instead.");
            } else {
                $stmt = $db->prepare("DELETE FROM membership_plans WHERE id = :id");
                $stmt->execute([':id' => $planId]);
                set_flash('success', 'Membership plan deleted successfully.');
            }
        } catch (Exception $e) {
            set_flash('error', 'Failed to delete plan: ' . $e->getMessage());
        }

        header('Location: ' . base_url('admin/membership-plans.php'));
        exit;
        break;

    case 'assign_subscription':
        $memberId = (int)($_POST['member_id'] ?? 0);
        $planId = (int)($_POST['plan_id'] ?? 0);
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
        $expiryDate = $_POST['expiry_date'] ?? '';
        $amount = (float)($_POST['amount'] ?? 0);
        $paidAmount = (float)($_POST['paid_amount'] ?? 0);
        $paymentMethod = $_POST['payment_method'] ?? 'Cash';
        $transactionRef = trim($_POST['transaction_ref'] ?? '');

        if ($memberId <= 0 || $planId <= 0 || empty($startDate)) {
            set_flash('error', 'Please select a member, a plan, and a valid start date.');
            header('Location: ' . base_url('admin/memberships.php'));
            exit;
        }

        try {
            $db->beginTransaction();

            // If expiry date wasn't provided, calculate it from plan duration
            if (empty($expiryDate)) {
                $pStmt = $db->prepare("SELECT duration_days, price FROM membership_plans WHERE id = :id");
                $pStmt->execute([':id' => $planId]);
                $planData = $pStmt->fetch();
                $dur = (int)($planData['duration_days'] ?? 30);
                $expiryDate = date('Y-m-d', strtotime("$startDate + $dur days"));
                if ($amount <= 0) $amount = (float)($planData['price'] ?? 0);
            }

            // Insert membership
            $subStmt = $db->prepare("
                INSERT INTO memberships (member_id, plan_id, start_date, expiry_date, total_amount, paid_amount, status)
                VALUES (:member_id, :plan_id, :start_date, :expiry_date, :total_amount, :paid_amount, 'active')
            ");
            $subStmt->execute([
                ':member_id'    => $memberId,
                ':plan_id'      => $planId,
                ':start_date'   => $startDate,
                ':expiry_date'  => $expiryDate,
                ':total_amount' => $amount,
                ':paid_amount'  => $paidAmount
            ]);
            $membershipId = (int)$db->lastInsertId();

            // If an initial payment was received, record it
            if ($paidAmount > 0) {
                $payStmt = $db->prepare("
                    INSERT INTO payments (member_id, membership_id, amount, payment_date, payment_method, transaction_ref, notes, status)
                    VALUES (:member_id, :membership_id, :amount, :payment_date, :payment_method, :transaction_ref, 'Initial subscription payment', 'completed')
                ");
                $payStmt->execute([
                    ':member_id'        => $memberId,
                    ':membership_id'    => $membershipId,
                    ':amount'           => $paidAmount,
                    ':payment_date'     => $startDate,
                    ':payment_method'   => $paymentMethod,
                    ':transaction_ref'  => $transactionRef ?: ('INIT-' . time())
                ]);
            }

            // Update member's status to active
            $db->prepare("UPDATE members SET status = 'active' WHERE id = :id")->execute([':id' => $memberId]);

            $db->commit();
            set_flash('success', 'Membership assigned/renewed successfully!');
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('error', 'Failed to assign membership: ' . $e->getMessage());
        }

        header('Location: ' . base_url('admin/memberships.php'));
        exit;
        break;

    default:
        header('Location: ' . base_url('admin/membership-plans.php'));
        exit;
}
