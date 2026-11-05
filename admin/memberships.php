<?php
/**
 * Member Subscriptions & Renewals Module
 * Gym Management System
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$db = getDB();

// 1. Fetch all subscriptions with member and plan details
$subscriptions = $db->query("
    SELECT ms.*, 
           m.full_name, m.member_code, m.phone, m.email,
           mp.plan_name, mp.duration_days,
           DATEDIFF(ms.expiry_date, CURRENT_DATE) AS days_remaining
    FROM memberships ms
    JOIN members m ON ms.member_id = m.id
    JOIN membership_plans mp ON ms.plan_id = mp.id
    ORDER BY ms.id DESC
")->fetchAll();

// 2. Fetch active members and plans for the renewal/assign modal
$members = $db->query("SELECT id, full_name, member_code FROM members ORDER BY full_name ASC")->fetchAll();
$plans = $db->query("SELECT id, plan_name, duration_days, price FROM membership_plans WHERE status = 'active' ORDER BY price ASC")->fetchAll();

$pageTitle = 'Member Subscriptions';
$navTitle = 'Subscriptions & Renewals';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <main class="main-content">
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-body" style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                    <div class="search-input-wrapper" style="max-width: 320px;">
                        <span class="search-icon">🔍</span>
                        <input type="text" class="form-control" placeholder="Search subscriptions..." data-table-search="subsTable">
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openModal('assignSubModal')">
                        + Assign / Renew Subscription
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <svg width="18" height="18"><use href="#icon-membership"></use></svg>
                        All Subscriptions History (<?= count($subscriptions) ?>)
                    </h2>
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="subsTable">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Plan</th>
                                <th>Start Date</th>
                                <th>Expiry Date</th>
                                <th>Financials</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subscriptions)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-dim); padding: 36px;">
                                        No subscription records found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($subscriptions as $s): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 700; color: #ffffff;"><?= e($s['full_name']) ?></div>
                                            <div style="font-size: 11px; font-family: monospace; color: var(--primary);">
                                                <?= e($s['member_code']) ?>
                                            </div>
                                        </td>
                                        <td><span class="badge badge-orange"><?= e($s['plan_name']) ?></span></td>
                                        <td><?= format_date($s['start_date']) ?></td>
                                        <td>
                                            <div><?= format_date($s['expiry_date']) ?></div>
                                            <?php if ((int)$s['days_remaining'] < 0): ?>
                                                <span style="font-size: 11px; color: var(--danger);">Expired <?= abs((int)$s['days_remaining']) ?>d ago</span>
                                            <?php elseif ((int)$s['days_remaining'] <= 7): ?>
                                                <span style="font-size: 11px; color: var(--warning); font-weight: bold;"><?= (int)$s['days_remaining'] ?> days remaining</span>
                                            <?php else: ?>
                                                <span style="font-size: 11px; color: var(--success);"><?= (int)$s['days_remaining'] ?> days remaining</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 700; color: #ffffff;"><?= format_currency($s['total_amount']) ?></div>
                                            <div style="font-size: 11px; color: <?= ($s['paid_amount'] >= $s['total_amount']) ? 'var(--success)' : 'var(--warning)' ?>;">
                                                Paid: <?= format_currency($s['paid_amount']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= e($s['status']) ?>"><?= e($s['status']) ?></span>
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

<!-- ================= ASSIGN / RENEW MODAL ================= -->
<div class="modal-backdrop" id="assignSubModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Assign or Renew Subscription</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('assignSubModal')">&times;</button>
        </div>
        <form action="<?= base_url('actions/membership-actions.php') ?>" method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="assign_subscription">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="sub_member">Select Gym Member <span class="required">*</span></label>
                    <select id="sub_member" name="member_id" class="form-control" required>
                        <option value="">-- Choose Member --</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>">
                                <?= e($m['full_name']) ?> (<?= e($m['member_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="planSelect">Select Membership Tier <span class="required">*</span></label>
                    <select id="planSelect" name="plan_id" class="form-control" required>
                        <option value="">-- Choose Plan --</option>
                        <?php foreach ($plans as $p): ?>
                            <option value="<?= $p['id'] ?>" data-duration="<?= $p['duration_days'] ?>" data-price="<?= $p['price'] ?>">
                                <?= e($p['plan_name']) ?> (<?= format_currency($p['price']) ?> / <?= $p['duration_days'] ?> days)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="startDateInput">Start Date <span class="required">*</span></label>
                        <input type="date" id="startDateInput" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="expiryDateInput">Calculated Expiry Date</label>
                        <input type="date" id="expiryDateInput" name="expiry_date" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="amountInput">Total Fee ($)</label>
                        <input type="number" step="0.01" id="amountInput" name="amount" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="paid_amount">Initial Amount Paid ($)</label>
                        <input type="number" step="0.01" id="paid_amount" name="paid_amount" class="form-control" value="0.00">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="payment_method">Payment Method</label>
                        <select id="payment_method" name="payment_method" class="form-control">
                            <option value="Cash">Cash</option>
                            <option value="UPI" data-upi-qr-select="membershipUpiPaymentQr">UPI</option>
                            <option value="Card">Card</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="transaction_ref">Reference / Transaction ID</label>
                        <input type="text" id="transaction_ref" name="transaction_ref" class="form-control" placeholder="Leave empty to auto-generate">
                    </div>
                </div>

                <div class="upi-payment-panel" id="membershipUpiPaymentQr" hidden aria-live="polite">
                    <img src="<?= e(base_url('assets/images/phonepe-payment-qr.png')) ?>" alt="PhonePe payment QR code">
                    <div>
                        <strong>Scan with PhonePe or another UPI app</strong>
                        <p>Enter the initial payment amount in your UPI app. Confirm that the payment arrived in your account before activating the subscription; scanning the QR does not verify payment.</p>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('assignSubModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Activate Subscription</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
