<?php
/**
 * Member Payment History Module
 * Gym Management System
 */

require_once __DIR__ . '/../includes/member_auth.php';

$db = getDB();
$userId = $_SESSION['user']['id'];

$mStmt = $db->prepare("SELECT id, full_name, member_code, email, phone FROM members WHERE user_id = :uid LIMIT 1");
$mStmt->execute([':uid' => $userId]);
$member = $mStmt->fetch();

$memberId = (int)$member['id'];

// Fetch payment transactions for this member
$payStmt = $db->prepare("
    SELECT p.*, COALESCE(po.provider_transaction_id, p.transaction_ref) AS display_transaction_ref,
           COALESCE(mp.plan_name, gateway_plan.plan_name, 'Gym Subscription') AS plan_name
    FROM payments p
    LEFT JOIN memberships ms ON p.membership_id = ms.id
    LEFT JOIN membership_plans mp ON ms.plan_id = mp.id
    LEFT JOIN phonepe_orders po ON po.payment_id = p.id
    LEFT JOIN membership_plans gateway_plan ON po.plan_id = gateway_plan.id
    WHERE p.member_id = :mid
    ORDER BY p.payment_date DESC, p.id DESC
");
$payStmt->execute([':mid' => $memberId]);
$payments = $payStmt->fetchAll();

// Total paid
$totalPaid = array_reduce($payments, fn($sum, $p) => $sum + ($p['status'] === 'completed' ? (float)$p['amount'] : 0), 0);

// Fetch UPI proof submissions for this member
$proofStmt = $db->prepare("
    SELECT pp.*, mp.plan_name, mp.duration_days
    FROM payment_proofs pp
    JOIN membership_plans mp ON mp.id = pp.plan_id
    WHERE pp.user_id = :uid
    ORDER BY pp.created_at DESC, pp.id DESC
");
$proofStmt->execute([':uid' => $userId]);
$proofRows = $proofStmt->fetchAll();

$pageTitle = 'Payment Receipts';
$navTitle = 'My Invoices & Payment History';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <main class="main-content">
            <div class="kpi-grid">
                <div class="kpi-card kpi-orange">
                    <div class="kpi-details">
                        <span class="kpi-title">Total Contributions</span>
                        <span class="kpi-value"><?= format_currency($totalPaid) ?></span>
                        <span class="kpi-subtext"><?= count($payments) ?> receipts on file</span>
                    </div>
                    <div class="kpi-icon">
                        <svg width="24" height="24"><use href="#icon-payment"></use></svg>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <svg width="18" height="18"><use href="#icon-payment"></use></svg>
                        Transactions & Invoices
                    </h2>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Transaction Ref</th>
                                <th>Description / Plan</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Payment Date</th>
                                <th>Status</th>
                                <th style="text-align: right;">Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--text-dim); padding: 36px;">
                                        No payment records on file yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td>
                                            <span style="font-family: monospace; font-size: 13px; color: var(--primary); font-weight: 700;">
                                                <?= e($p['display_transaction_ref']) ?>
                                            </span>
                                        </td>
                                        <td style="font-weight: 600; color: #ffffff;"><?= e($p['plan_name']) ?></td>
                                        <td style="font-weight: 800; color: var(--success); font-size: 15px;">
                                            <?= format_currency($p['amount']) ?>
                                        </td>
                                        <td><span class="badge badge-secondary"><?= e($p['payment_method']) ?></span></td>
                                        <td><?= format_date($p['payment_date']) ?></td>
                                        <td><span class="badge badge-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
                                        <td style="text-align: right;">
                                            <button type="button" class="btn btn-secondary btn-sm" onclick='viewReceipt(<?= json_encode(array_merge($p, ['transaction_ref' => $p['display_transaction_ref'], 'full_name' => $member['full_name'], 'member_code' => $member['member_code'], 'phone' => $member['phone'], 'email' => $member['email']]), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
                                                🧾 View / Print
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card" style="margin-top: 24px;">
                <div class="card-header">
                    <h2 class="card-title">UPI Payment Verification</h2>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Reference / UTR</th>
                                <th>Plan</th>
                                <th>Amount</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th>Review note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($proofRows)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-dim); padding: 36px;">
                                        No UPI payment proofs submitted yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($proofRows as $proof): ?>
                                    <?php
                                    $proofStatus = (string) $proof['status'];
                                    $statusClass = $proofStatus === 'approved'
                                        ? 'success'
                                        : ($proofStatus === 'rejected' ? 'danger' : 'warning');
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="font-family: monospace; color: var(--primary);"><?= e($proof['payment_ref'] ?? '—') ?></div>
                                            <div style="font-size: 12px; color: var(--text-dim);"><?= e($proof['transaction_id']) ?></div>
                                        </td>
                                        <td><?= e($proof['plan_name']) ?></td>
                                        <td><?= e($proof['currency']) ?> <?= number_format((float) $proof['amount'], 2) ?></td>
                                        <td><?= format_date($proof['created_at'], 'd M Y H:i') ?></td>
                                        <td><span class="badge badge-<?= $statusClass ?>"><?= e(ucfirst($proofStatus)) ?></span></td>
                                        <td><?= e($proof['rejection_reason'] ?: ($proofStatus === 'pending' ? 'Awaiting administrator review' : '—')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- ================= PRINTABLE PAYMENT RECEIPT MODAL ================= -->
<div class="modal-backdrop" id="receiptModal">
    <div class="modal-dialog">
        <div class="modal-header no-print">
            <h3 class="modal-title">Payment Invoice & Receipt</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('receiptModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="receipt-container" id="printableReceipt" style="background: var(--bg-card); padding: 24px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; border-bottom: 2px solid var(--primary); padding-bottom: 16px;">
                    <div>
                        <h2 style="color: var(--primary); font-size: 22px; font-weight: 800; margin: 0;">APEX FITNESS CLUB</h2>
                        <div style="font-size: 12px; color: var(--text-dim); margin-top: 4px;">Official Payment Acknowledgment</div>
                    </div>
                    <div style="text-align: right;">
                        <span class="badge badge-success" id="rcpt_status">COMPLETED</span>
                        <div style="font-size: 12px; font-family: monospace; color: var(--text-muted); margin-top: 4px;" id="rcpt_ref"></div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; font-size: 13px;">
                    <div>
                        <span style="color: var(--text-dim); text-transform: uppercase; font-size: 11px;">Billed To:</span>
                        <div style="font-weight: 700; color: #ffffff; font-size: 15px; margin-top: 2px;" id="rcpt_name"></div>
                        <div style="color: var(--text-muted);" id="rcpt_code"></div>
                        <div style="color: var(--text-dim);" id="rcpt_contact"></div>
                    </div>
                    <div style="text-align: right;">
                        <span style="color: var(--text-dim); text-transform: uppercase; font-size: 11px;">Receipt Details:</span>
                        <div style="margin-top: 2px;"><strong>Date:</strong> <span id="rcpt_date"></span></div>
                        <div><strong>Method:</strong> <span id="rcpt_method"></span></div>
                    </div>
                </div>

                <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 13px;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color); text-align: left; color: var(--text-dim);">
                            <th style="padding: 8px 0;">Description</th>
                            <th style="padding: 8px 0; text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 12px 0;">
                                <strong id="rcpt_plan" style="color: #ffffff;"></strong>
                                <div style="font-size: 12px; color: var(--text-dim);">Fitness membership dues and access fee</div>
                            </td>
                            <td style="padding: 12px 0; text-align: right; font-weight: 800; font-size: 16px; color: var(--success);" id="rcpt_amount"></td>
                        </tr>
                    </tbody>
                </table>

                <div style="text-align: center; font-size: 12px; color: var(--text-dim); border-top: 1px dashed var(--border-color); padding-top: 16px;">
                    Thank you for training with Apex Fitness Club!<br>
                    <em>Note: This is an official digital record of payment acknowledgment.</em>
                </div>
            </div>
        </div>
        <div class="modal-footer no-print">
            <button type="button" class="btn btn-secondary" onclick="closeModal('receiptModal')">Close</button>
            <button type="button" class="btn btn-primary" onclick="window.print()">🖨️ Print Receipt</button>
        </div>
    </div>
</div>

<script>
function viewReceipt(p) {
    document.getElementById('rcpt_ref').textContent = p.transaction_ref;
    document.getElementById('rcpt_name').textContent = p.full_name;
    document.getElementById('rcpt_code').textContent = 'ID: ' + p.member_code;
    document.getElementById('rcpt_contact').textContent = (p.phone || '') + ' • ' + (p.email || '');
    document.getElementById('rcpt_date').textContent = p.payment_date;
    document.getElementById('rcpt_method').textContent = p.payment_method;
    document.getElementById('rcpt_plan').textContent = p.plan_name;
    document.getElementById('rcpt_amount').textContent = '$' + parseFloat(p.amount).toFixed(2);
    document.getElementById('rcpt_status').textContent = (p.status || 'COMPLETED').toUpperCase();
    openModal('receiptModal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
