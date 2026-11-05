<?php
/**
 * CSV Reports Exporter
 * Gym Management System
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$type = $_GET['type'] ?? 'members';
$db = getDB();

$filename = 'gym_' . $type . '_export_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

switch ($type) {
    case 'members':
        fputcsv($output, ['Member ID', 'Member Code', 'Full Name', 'Email', 'Phone', 'Gender', 'Blood Group', 'Status', 'Registered Date']);
        $stmt = $db->query("
            SELECT id, member_code, full_name, email, phone, gender, blood_group, status, created_at 
            FROM members 
            ORDER BY id ASC
        ");
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['id'],
                $row['member_code'],
                $row['full_name'],
                $row['email'],
                $row['phone'],
                $row['gender'],
                $row['blood_group'] ?: '-',
                strtoupper($row['status']),
                date('Y-m-d', strtotime($row['created_at']))
            ]);
        }
        break;

    case 'payments':
        fputcsv($output, ['Payment ID', 'Member Code', 'Member Name', 'Plan Name', 'Amount ($)', 'Payment Date', 'Method', 'Transaction Ref', 'Status']);
        $stmt = $db->query("
            SELECT p.id, m.member_code, m.full_name, COALESCE(mp.plan_name, 'Direct Payment') AS plan_name,
                   p.amount, p.payment_date, p.payment_method, p.transaction_ref, p.status
            FROM payments p
            JOIN members m ON p.member_id = m.id
            LEFT JOIN memberships ms ON p.membership_id = ms.id
            LEFT JOIN membership_plans mp ON ms.plan_id = mp.id
            ORDER BY p.payment_date DESC
        ");
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['id'],
                $row['member_code'],
                $row['full_name'],
                $row['plan_name'],
                number_format($row['amount'], 2),
                $row['payment_date'],
                $row['payment_method'],
                $row['transaction_ref'] ?: '-',
                strtoupper($row['status'])
            ]);
        }
        break;

    case 'attendance':
        fputcsv($output, ['Record ID', 'Member Code', 'Member Name', 'Date', 'Check-In Time', 'Check-Out Time', 'Notes']);
        $stmt = $db->query("
            SELECT a.id, m.member_code, m.full_name, a.attendance_date, a.check_in_time, a.check_out_time, a.notes
            FROM attendance a
            JOIN members m ON a.member_id = m.id
            ORDER BY a.attendance_date DESC, a.check_in_time DESC
        ");
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['id'],
                $row['member_code'],
                $row['full_name'],
                $row['attendance_date'],
                date('h:i A', strtotime($row['check_in_time'])),
                $row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '-',
                $row['notes'] ?: '-'
            ]);
        }
        break;

    default:
        fputcsv($output, ['Invalid report type specified']);
        break;
}

fclose($output);
exit;
