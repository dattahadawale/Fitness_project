<?php
/**
 * Payment Management Module
 * Gym Management System
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$db = getDB();

// 1. Filtering Parameters
$search = trim($_GET['search'] ?? '');
$methodFilter = $_GET['method'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

$whereClauses = ["1=1"];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(m.full_name LIKE :s1 OR m.member_code LIKE :s2 OR p.transaction_ref LIKE :s3)";
    $params[':s1'] = "%{$search}%";
    $params[':s2'] = "%{$search}%";
    $params[':s3'] = "%{$search}%";
}
if (!empty($methodFilter)) {
    $whereClauses[] = "p.payment_method = :method";
    $params[':method'] = $methodFilter;
}
if (!empty($startDate)) {
    $whereClauses[] = "p.payment_date >= :start_date";
    $params[':start_date'] = $startDate;
}
if (!empty($endDate)) {
    $whereClauses[] = "p.payment_date <= :end_date";
    $params[':end_date'] = $endDate;
}

$whereSQL = implode(" AND ", $whereClauses);

// Fetch payments
$payments = [];
try {
    $query = "
        SELECT p.*, m.full_name, m.member_code, m.phone, m.email,
               COALESCE(mp.plan_name, 'Custom / Direct') AS plan_name
        FROM payments p
        JOIN members m ON p.member_id = m.id
        LEFT JOIN memberships ms ON p.membership_id = ms.id
        LEFT JOIN membership_plans mp ON ms.plan_id = mp.id
        WHERE {$whereSQL}
        ORDER BY p.id DESC
    ";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
} catch (Exception $e) {
}

// Calculate total revenue from filtered payments
$filteredRevenue = array_reduce($payments, fn($carry, $item) => $carry + (float) $item['amount'], 0);

// Fetch members and their active/pending memberships for payment recording modal
$memberOptions = $db->query("
    SELECT m.id, m.full_name, m.member_code, ms.id AS membership_id, mp.plan_name, ms.total_amount, ms.paid_amount
    FROM members m
    LEFT JOIN memberships ms ON m.id = ms.member_id AND ms.status = 'active'
    LEFT JOIN membership_plans mp ON ms.plan_id = mp.id
    ORDER BY m.full_name ASC
")->fetchAll();

// Pending UPI payment proofs waiting for admin review (never breaks this page if the table is missing)
$pendingProofs = 0;
try {
    $pendingProofs = (int) $db->query("SELECT COUNT(*) FROM payment_proofs WHERE status = 'pending'")->fetchColumn();
} catch (Throwable $e) {
    $pendingProofs = 0;
}

$pageTitle = 'Payment Management';
$navTitle = 'Financials & Payment Records';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <main class="main-content">
            <!-- Filter & Action Bar -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-body" style="padding: 16px 20px;">
                    <form method="GET" class="filter-bar" style="margin-bottom: 0;">
                        <div class="search-input-wrapper" style="min-width: 220px;">
                            <span class="search-icon">🔍</span>
                            <input type="text" name="search" class="form-control"
                                placeholder="Search member or Txn Ref..." value="<?= e($search) ?>">
                        </div>

                        <select name="method" class="form-control" style="width: auto; min-width: 140px;">
                            <option value="">All Methods</option>
                            <option value="Cash" <?= ($methodFilter === 'Cash') ? 'selected' : '' ?>>Cash</option>
                            <option value="UPI" <?= ($methodFilter === 'UPI') ? 'selected' : '' ?>>UPI</option>
                            <option value="Card" <?= ($methodFilter === 'Card') ? 'selected' : '' ?>>Card</option>
                            <option value="Bank Transfer" <?= ($methodFilter === 'Bank Transfer') ? 'selected' : '' ?>>Bank
                                Transfer</option>
                        </select>

                        <input type="date" name="start_date" class="form-control" style="width: auto;"
                            value="<?= e($startDate) ?>" title="Start Date">
                        <input type="date" name="end_date" class="form-control" style="width: auto;"
                            value="<?= e($endDate) ?>" title="End Date">

                        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                        <?php if (!empty($search) || !empty($methodFilter) || !empty($startDate) || !empty($endDate)): ?>
                            <a href="<?= base_url('admin/payments.php') ?>" class="btn btn-secondary btn-sm">&times;
                                Reset</a>
                        <?php endif; ?>

                        <div style="margin-left: auto; display: flex; gap: 8px; flex-wrap: wrap;">
                            <a href="<?= base_url('admin/payment-proofs.php') ?>" class="btn btn-secondary btn-sm">
                                UPI Proofs
                                <?php if ($pendingProofs > 0): ?>
                                    <span class="badge badge-pending"><?= $pendingProofs ?></span>
                                <?php endif; ?>
                            </a>
                            <button type="button" class="btn btn-primary btn-sm"
                                onclick="openModal('recordPaymentModal')">
                                + Record Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Revenue Summary Card -->
            <div class="kpi-grid" style="margin-bottom: 24px;">
                <div class="kpi-card kpi-orange">
                    <div class="kpi-details">
                        <span class="kpi-title">Filtered Collections</span>
                        <span class="kpi-value"><?= format_currency($filteredRevenue) ?></span>
                        <span class="kpi-subtext"><?= count($payments) ?> transactions matched</span>
                    </div>
                    <div class="kpi-icon">
                        <svg width="24" height="24">
                            <use href="#icon-payment"></use>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Payments Ledger Table -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <svg width="18" height="18">
                            <use href="#icon-payment"></use>
                        </svg>
                        Transactions Ledger (<?= count($payments) ?>)
                    </h2>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Transaction Ref</th>
                                <th>Member</th>
                                <th>Membership Plan</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th style="text-align: right;">Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: var(--text-dim); padding: 36px;">
                                        No transactions recorded matching the criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td>
                                            <span
                                                style="font-family: monospace; font-size: 13px; color: var(--primary); font-weight: 700;">
                                                <?= e($p['transaction_ref']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-weight: 700; color: #ffffff;"><?= e($p['full_name']) ?></div>
                                            <div style="font-size: 11px; font-family: monospace; color: var(--text-dim);">
                                                <?= e($p['member_code']) ?>
                                            </div>
                                        </td>
                                        <td><span class="badge badge-info"><?= e($p['plan_name']) ?></span></td>
                                        <td style="font-size: 16px; font-weight: 800; color: var(--success);">
                                            <?= format_currency($p['amount']) ?>
                                        </td>
                                        <td><span class="badge badge-secondary"><?= e($p['payment_method']) ?></span></td>
                                        <td><?= format_date($p['payment_date']) ?></td>
                                        <td><span class="badge badge-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
                                        <td style="text-align: right;">
                                            <button type="button" class="btn btn-secondary btn-sm"
                                                onclick='viewReceipt(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'
                                                title="Print Receipt">
                                                🧾 Receipt
                                            </button>
                                        </td>
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

<!-- ================= RECORD PAYMENT MODAL ================= -->
<div class="modal-backdrop" id="recordPaymentModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Record Membership Payment</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('recordPaymentModal')">&times;</button>
        </div>
        <form action="<?= base_url('actions/payment-actions.php') ?>" method="POST" id="recordPaymentForm">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="record_payment">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="pay_member">Select Gym Member <span class="required">*</span></label>
                    <select id="pay_member" name="member_id" class="form-control" required
                        onchange="updatePayMembership(this)">
                        <option value="">-- Choose Member --</option>
                        <?php foreach ($memberOptions as $m): ?>
                            <option value="<?= $m['id'] ?>" data-membership="<?= $m['membership_id'] ?>"
                                data-plan="<?= e($m['plan_name']) ?>">
                                <?= e($m['full_name']) ?> (<?= e($m['member_code']) ?>)
                                <?= $m['plan_name'] ? "— [{$m['plan_name']}]" : "" ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <input type="hidden" id="pay_membership_id" name="membership_id" value="">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="pay_amount">Payment Amount (₹) <span
                                class="required">*</span></label>
                        <input type="number" step="0.01" id="pay_amount" name="amount" class="form-control"
                            placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="pay_date">Payment Date <span class="required">*</span></label>
                        <input type="date" id="pay_date" name="payment_date" class="form-control"
                            value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="pay_method">Payment Method <span
                                class="required">*</span></label>
                        <select id="pay_method" name="payment_method" class="form-control" required>
                            <option value="Cash">Cash (Reception)</option>
                            <option value="UPI" data-upi-qr-select="upiPaymentQr">UPI</option>
                            <option value="Card">Debit / Credit Card</option>
                            <option value="Bank Transfer">Direct Bank Transfer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="pay_ref">Reference / Transaction Number</label>
                        <input type="text" id="pay_ref" name="transaction_ref" class="form-control"
                            placeholder="Optional (auto-generated if empty)">
                    </div>
                </div>

                <div class="upi-payment-panel" id="upiPaymentQr" hidden aria-live="polite">
                    <img src="<?= e(base_url('assets/images/phonepe-payment-qr.png')) ?>" alt="PhonePe payment QR code">
                    <div>
                        <strong>Scan with PhonePe or another UPI app</strong>
                        <p>Enter the payment amount in your UPI app. Confirm that the payment arrived in your account
                            before saving this record; scanning the QR does not verify payment.</p>
                    </div>
                </div>

                <!-- UPI 30-second wait timer (30 small circles) -->
                <div id="upiWait" style="display:none; margin: 0 0 16px; text-align:center;" aria-live="polite">
                    <div id="upiWaitDots"
                        style="display:flex; flex-wrap:wrap; justify-content:center; gap:6px; max-width:420px; margin:0 auto;">
                    </div>
                    <div id="upiWaitText" style="margin-top:10px; font-size:13px; color:var(--text-muted, #9aa4b2);">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="pay_notes">Notes / Remarks</label>
                    <input type="text" id="pay_notes" name="notes" class="form-control"
                        placeholder="e.g. Month renewal, locker fee, kit...">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    onclick="closeModal('recordPaymentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="savePaymentBtn">Save &amp; Confirm Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= PRINTABLE PAYMENT RECEIPT MODAL ================= -->
<div class="modal-backdrop" id="receiptModal">
    <div class="modal-dialog">
        <div class="modal-header no-print">
            <h3 class="modal-title">Payment Invoice &amp; Receipt</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('receiptModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="receipt-container" id="printableReceipt"
                style="background: var(--bg-card); padding: 24px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                <div
                    style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; border-bottom: 2px solid var(--primary); padding-bottom: 16px;">
                    <div>
                        <h2 style="color: var(--primary); font-size: 22px; font-weight: 800; margin: 0;">APEX FITNESS
                            CLUB</h2>
                        <div style="font-size: 12px; color: var(--text-dim); margin-top: 4px;">Official Payment
                            Acknowledgment</div>
                    </div>
                    <div style="text-align: right;">
                        <span class="badge badge-success" id="rcpt_status">COMPLETED</span>
                        <div style="font-size: 12px; font-family: monospace; color: var(--text-muted); margin-top: 4px;"
                            id="rcpt_ref"></div>
                    </div>
                </div>

                <div
                    style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; font-size: 13px;">
                    <div>
                        <span style="color: var(--text-dim); text-transform: uppercase; font-size: 11px;">Billed
                            To:</span>
                        <div style="font-weight: 700; color: #ffffff; font-size: 15px; margin-top: 2px;" id="rcpt_name">
                        </div>
                        <div style="color: var(--text-muted);" id="rcpt_code"></div>
                        <div style="color: var(--text-dim);" id="rcpt_contact"></div>
                    </div>
                    <div style="text-align: right;">
                        <span style="color: var(--text-dim); text-transform: uppercase; font-size: 11px;">Receipt
                            Details:</span>
                        <div style="margin-top: 2px;"><strong>Date:</strong> <span id="rcpt_date"></span></div>
                        <div><strong>Method:</strong> <span id="rcpt_method"></span></div>
                    </div>
                </div>

                <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 13px;">
                    <thead>
                        <tr
                            style="border-bottom: 1px solid var(--border-color); text-align: left; color: var(--text-dim);">
                            <th style="padding: 8px 0;">Description</th>
                            <th style="padding: 8px 0; text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 12px 0;">
                                <strong id="rcpt_plan" style="color: #ffffff;"></strong>
                                <div style="font-size: 12px; color: var(--text-dim);">Fitness gym subscription &amp;
                                    facility access</div>
                            </td>
                            <td style="padding: 12px 0; text-align: right; font-weight: 800; font-size: 16px; color: var(--success);"
                                id="rcpt_amount"></td>
                        </tr>
                    </tbody>
                </table>

                <div
                    style="text-align: center; font-size: 12px; color: var(--text-dim); border-top: 1px dashed var(--border-color); padding-top: 16px;">
                    Thank you for training with Apex Fitness Club! Keep pushing your limits.<br>
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
    function updatePayMembership(select) {
        const opt = select.options[select.selectedIndex];
        const msId = opt.getAttribute('data-membership');
        document.getElementById('pay_membership_id').value = msId || '';
    }

    function viewReceipt(p) {
        document.getElementById('rcpt_ref').textContent = p.transaction_ref;
        document.getElementById('rcpt_name').textContent = p.full_name;
        document.getElementById('rcpt_code').textContent = 'ID: ' + p.member_code;
        document.getElementById('rcpt_contact').textContent = (p.phone || '') + ' • ' + (p.email || '');
        document.getElementById('rcpt_date').textContent = p.payment_date;
        document.getElementById('rcpt_method').textContent = p.payment_method;
        document.getElementById('rcpt_plan').textContent = p.plan_name;
        document.getElementById('rcpt_amount').textContent = '₹' + parseFloat(p.amount).toFixed(2);
        document.getElementById('rcpt_status').textContent = (p.status || 'COMPLETED').toUpperCase();
        openModal('receiptModal');
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>