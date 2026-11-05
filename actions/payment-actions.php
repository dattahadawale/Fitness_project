<?php
/**
 * Payment Recording & Management Actions
 * Gym Management System
 */

require_once __DIR__ . '/../includes/admin_auth.php';

// Verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid security token.');
        header('Location: ' . base_url('admin/payments.php'));
        exit;
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db = getDB();

switch ($action) {
    case 'record_payment':
        $memberId = (int)($_POST['member_id'] ?? 0);
        $membershipId = !empty($_POST['membership_id']) ? (int)$_POST['membership_id'] : null;
        $amount = (float)($_POST['amount'] ?? 0);
        $paymentDate = !empty($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
        $paymentMethod = $_POST['payment_method'] ?? 'Cash';
        $transactionRef = trim($_POST['transaction_ref'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($memberId <= 0 || $amount <= 0) {
            set_flash('error', 'Please select a member and enter a valid positive amount.');
            header('Location: ' . base_url('admin/payments.php'));
            exit;
        }

        if (empty($transactionRef)) {
            $prefix = match($paymentMethod) {
                'UPI'           => 'UPI-',
                'Card'          => 'CARD-',
                'Bank Transfer' => 'BNK-',
                default         => 'CASH-'
            };
            $transactionRef = $prefix . strtoupper(bin2hex(random_bytes(4)));
        }

        try {
            $db->beginTransaction();

            $insertStmt = $db->prepare("
                INSERT INTO payments (member_id, membership_id, amount, payment_date, payment_method, transaction_ref, notes, status)
                VALUES (:member_id, :membership_id, :amount, :payment_date, :payment_method, :transaction_ref, :notes, 'completed')
            ");
            $insertStmt->execute([
                ':member_id'        => $memberId,
                ':membership_id'    => $membershipId,
                ':amount'           => $amount,
                ':payment_date'     => $paymentDate,
                ':payment_method'   => $paymentMethod,
                ':transaction_ref'  => $transactionRef,
                ':notes'            => $notes
            ]);

            // If attached to a membership, update paid_amount
            if ($membershipId) {
                $updStmt = $db->prepare("
                    UPDATE memberships 
                    SET paid_amount = paid_amount + :amount 
                    WHERE id = :id
                ");
                $updStmt->execute([':amount' => $amount, ':id' => $membershipId]);
            }

            $db->commit();
            set_flash('success', "Payment of " . format_currency($amount) . " recorded successfully! (Ref: {$transactionRef})");
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('error', 'Failed to record payment: ' . $e->getMessage());
        }

        header('Location: ' . base_url('admin/payments.php'));
        exit;
        break;

    case 'delete_payment':
        $paymentId = (int)($_POST['payment_id'] ?? $_GET['id'] ?? 0);

        if ($paymentId <= 0) {
            set_flash('error', 'Invalid payment ID.');
            header('Location: ' . base_url('admin/payments.php'));
            exit;
        }

        try {
            $db->beginTransaction();

            $pStmt = $db->prepare("SELECT membership_id, amount FROM payments WHERE id = :id");
            $pStmt->execute([':id' => $paymentId]);
            $pay = $pStmt->fetch();

            if ($pay) {
                $gatewayStmt = $db->prepare('SELECT id FROM phonepe_orders WHERE payment_id = :payment_id');
                $gatewayStmt->execute([':payment_id' => $paymentId]);
                if ($gatewayStmt->fetch()) {
                    $db->rollBack();
                    set_flash('error', 'PhonePe transactions are retained for audit and payment reconciliation and cannot be deleted.');
                    header('Location: ' . base_url('admin/payments.php'));
                    exit;
                }

                if (!empty($pay['membership_id'])) {
                    $upd = $db->prepare("
                        UPDATE memberships 
                        SET paid_amount = GREATEST(0, paid_amount - :amount) 
                        WHERE id = :id
                    ");
                    $upd->execute([':amount' => $pay['amount'], ':id' => $pay['membership_id']]);
                }

                $del = $db->prepare("DELETE FROM payments WHERE id = :id");
                $del->execute([':id' => $paymentId]);

                $db->commit();
                set_flash('success', 'Payment record deleted.');
            } else {
                $db->rollBack();
                set_flash('error', 'Payment not found.');
            }
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('error', 'Failed to delete payment: ' . $e->getMessage());
        }

        header('Location: ' . base_url('admin/payments.php'));
        exit;
        break;

    default:
        header('Location: ' . base_url('admin/payments.php'));
        exit;
}
