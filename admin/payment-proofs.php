<?php
/**
 * Admin – UPI Payment Proof Verification
 * Gym Management System – APEXFITNESS
 *
 * Admins view UPI payment submissions, inspect the screenshot, and either
 * approve (activating the membership exactly once) or reject them.
 *
 * Requires the `payment_proofs` table (database/migrations/20261027_04_payment_proofs.sql).
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$db = getDB();
$adminUid = (int) ($_SESSION['user']['id'] ?? 0);
$backUrl = base_url('admin/payment-proofs.php');

// ── Handle POST actions (approve / reject) ────────────────────────────────────
// Done BEFORE any HTML is printed so the redirect headers can still be sent.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security token mismatch. Please try again.');
        header('Location: ' . $backUrl);
        exit;
    }

    // One-click installer for the payment_proofs table (admin only, CSRF-checked, safe to repeat).
    if (($_POST['action'] ?? '') === 'create_table') {
        $ddl = <<<'SQL'
CREATE TABLE IF NOT EXISTS payment_proofs (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id           INT UNSIGNED NOT NULL,
    member_id         INT UNSIGNED NULL,
    plan_id           INT UNSIGNED NOT NULL,
    amount            DECIMAL(10,2) NOT NULL,
    currency          CHAR(3) NOT NULL DEFAULT 'INR',
    transaction_id    VARCHAR(64) NOT NULL,
    payment_ref       VARCHAR(32) NOT NULL,
    screenshot_file   VARCHAR(255) NOT NULL,
    status            VARCHAR(20) NOT NULL DEFAULT 'pending',
    reviewed_by       INT UNSIGNED NULL,
    reviewed_at       DATETIME NULL,
    rejection_reason  VARCHAR(500) NULL,
    admin_notified_at DATETIME NULL,
    notify_error      VARCHAR(500) NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_proofs_txn (transaction_id),
    UNIQUE KEY uq_payment_proofs_ref (payment_ref),
    KEY idx_payment_proofs_user_status (user_id, status),
    KEY idx_payment_proofs_status_created (status, created_at),
    KEY idx_payment_proofs_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        try {
            $db->exec($ddl);
            set_flash('success', 'The payment_proofs table is ready. Submitted payment proofs will now appear here.');
        } catch (Throwable $e) {
            error_log('payment_proofs create failed: ' . $e->getMessage());
            set_flash('error', 'Could not create the table: ' . $e->getMessage());
        }
        header('Location: ' . $backUrl);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $proofId = (int) ($_POST['proof_id'] ?? 0);
    $reason = trim((string) ($_POST['rejection_reason'] ?? ''));

    if ($proofId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
        set_flash('error', 'Invalid action.');
        header('Location: ' . $backUrl);
        exit;
    }
    if ($action === 'reject' && $reason === '') {
        set_flash('error', 'Please provide a rejection reason.');
        header('Location: ' . $backUrl);
        exit;
    }
    if (mb_strlen($reason) > 500) {
        $reason = mb_substr($reason, 0, 500);
    }

    try {
        $db->beginTransaction();

        // Lock the proof row: two admins (or a double click) are serialized here,
        // so a payment can only ever be reviewed, and a membership activated, once.
        $lock = $db->prepare('SELECT * FROM payment_proofs WHERE id = :id FOR UPDATE');
        $lock->execute([':id' => $proofId]);
        $proof = $lock->fetch();

        if (!$proof) {
            throw new InvalidArgumentException('Payment proof not found.');
        }
        if ($proof['status'] !== 'pending') {
            throw new InvalidArgumentException('This submission has already been reviewed.');
        }

        $memberId = (int) $proof['member_id'];
        $paymentRef = (string) $proof['payment_ref'];

        if ($action === 'approve') {
            $planStmt = $db->prepare('SELECT duration_days FROM membership_plans WHERE id = :id');
            $planStmt->execute([':id' => (int) $proof['plan_id']]);
            $duration = (int) $planStmt->fetchColumn();
            if ($duration <= 0) {
                throw new InvalidArgumentException('The membership plan for this payment no longer exists.');
            }

            // Dates come from MySQL so they match the rest of the app (no PHP timezone mismatch).
            $today = (string) $db->query('SELECT CURRENT_DATE')->fetchColumn();

            // Extend from the current active membership, otherwise start today.
            $expiryQ = $db->prepare("
                SELECT MAX(expiry_date) FROM memberships
                WHERE member_id = :mid AND status = 'active' AND expiry_date >= CURRENT_DATE
            ");
            $expiryQ->execute([':mid' => $memberId]);
            $existingExpiry = $expiryQ->fetchColumn();

            $startDate = $existingExpiry
                ? (new DateTimeImmutable($existingExpiry))->modify('+1 day')->format('Y-m-d')
                : $today;
            $expiryDate = (new DateTimeImmutable($startDate))->modify('+' . $duration . ' days')->format('Y-m-d');

            $db->prepare("
                INSERT INTO memberships (member_id, plan_id, start_date, expiry_date, total_amount, paid_amount, status)
                VALUES (:mid, :pid, :start, :expiry, :total, :paid, 'active')
            ")->execute([
                        ':mid' => $memberId,
                        ':pid' => (int) $proof['plan_id'],
                        ':start' => $startDate,
                        ':expiry' => $expiryDate,
                        ':total' => $proof['amount'],
                        ':paid' => $proof['amount'],
                    ]);
            $membershipId = (int) $db->lastInsertId();

            $payUpdate = $db->prepare("
                UPDATE payments
                SET status = 'completed', membership_id = :msid, notes = :notes
                WHERE transaction_ref = :pref AND member_id = :mid AND status = 'pending'
            ");
            $payUpdate->execute([
                ':msid' => $membershipId,
                ':notes' => 'UPI QR payment approved by admin. UTR: ' . $proof['transaction_id'],
                ':pref' => $paymentRef,
                ':mid' => $memberId,
            ]);
            if ($payUpdate->rowCount() !== 1) {
                throw new RuntimeException('Pending payment record not found for ' . $paymentRef . '; nothing was activated.');
            }

            $proofUpdate = $db->prepare("
                UPDATE payment_proofs
                SET status = 'approved', reviewed_by = :rid, reviewed_at = NOW()
                WHERE id = :id AND status = 'pending'
            ");
            $proofUpdate->execute([':rid' => $adminUid, ':id' => $proofId]);
            if ($proofUpdate->rowCount() !== 1) {
                throw new RuntimeException('The payment proof changed while it was being reviewed.');
            }

            $db->prepare("UPDATE members SET status = 'active' WHERE id = :id")->execute([':id' => $memberId]);

            $db->commit();
            set_flash('success', 'Payment approved. Membership active from ' . $startDate . ' to ' . $expiryDate . '.');
        } else { // reject
            $payUpdate = $db->prepare("
                UPDATE payments
                SET status = 'failed', notes = CONCAT(IFNULL(notes, ''), ' | REJECTED: ', :reason)
                WHERE transaction_ref = :pref AND member_id = :mid AND status = 'pending'
            ");
            $payUpdate->execute([':reason' => $reason, ':pref' => $paymentRef, ':mid' => $memberId]);
            if ($payUpdate->rowCount() !== 1) {
                throw new RuntimeException('Pending payment record not found for ' . $paymentRef . '; no changes were saved.');
            }

            $proofUpdate = $db->prepare("
                UPDATE payment_proofs
                SET status = 'rejected', rejection_reason = :reason, reviewed_by = :rid, reviewed_at = NOW()
                WHERE id = :id AND status = 'pending'
            ");
            $proofUpdate->execute([':reason' => $reason, ':rid' => $adminUid, ':id' => $proofId]);
            if ($proofUpdate->rowCount() !== 1) {
                throw new RuntimeException('The payment proof changed while it was being reviewed.');
            }

            $db->commit();
            set_flash('warning', 'Payment submission rejected.');
        }
    } catch (InvalidArgumentException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        set_flash('warning', $e->getMessage());
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('payment-proofs ' . $action . ' failed: ' . $e->getMessage());
        set_flash('error', 'The action failed and nothing was changed. Details were written to the server log.');
    }

    header('Location: ' . $backUrl);
    exit;
}

// ── Filters ───────────────────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$filterSearch = trim($_GET['search'] ?? '');
$filterStart = $_GET['start_date'] ?? '';
$filterEnd = $_GET['end_date'] ?? '';

$where = ['1=1'];
$params = [];

if (in_array($filterStatus, ['pending', 'approved', 'rejected'], true)) {
    $where[] = 'pp.status = :status';
    $params[':status'] = $filterStatus;
}
if ($filterSearch !== '') {
    $where[] = '(m.full_name LIKE :s1 OR m.member_code LIKE :s2 OR pp.transaction_id LIKE :s3 OR pp.payment_ref LIKE :s4)';
    $like = '%' . $filterSearch . '%';
    $params[':s1'] = $like;
    $params[':s2'] = $like;
    $params[':s3'] = $like;
    $params[':s4'] = $like;
}
if ($filterStart !== '') {
    $where[] = 'DATE(pp.created_at) >= :start';
    $params[':start'] = $filterStart;
}
if ($filterEnd !== '') {
    $where[] = 'DATE(pp.created_at) <= :end';
    $params[':end'] = $filterEnd;
}
$whereSQL = implode(' AND ', $where);

$proofRows = [];
$pendingCount = $approvedCount = $rejectedCount = 0;
$setupError = '';
$tableMissing = false;

try {
    $proofs = $db->prepare("
        SELECT pp.*,
               m.full_name, m.member_code, m.email AS member_email, m.phone AS member_phone,
               mp.plan_name, mp.duration_days,
               ru.name AS reviewer_name
        FROM payment_proofs pp
        JOIN members m ON m.id = pp.member_id
        JOIN membership_plans mp ON mp.id = pp.plan_id
        LEFT JOIN users ru ON ru.id = pp.reviewed_by
        WHERE {$whereSQL}
        ORDER BY FIELD(pp.status, 'pending', 'rejected', 'approved'), pp.created_at DESC
    ");
    $proofs->execute($params);
    $proofRows = $proofs->fetchAll();

    $counts = $db->query("SELECT status, COUNT(*) AS c FROM payment_proofs GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $pendingCount = (int) ($counts['pending'] ?? 0);
    $approvedCount = (int) ($counts['approved'] ?? 0);
    $rejectedCount = (int) ($counts['rejected'] ?? 0);
} catch (PDOException $e) {
    error_log('payment-proofs list failed: ' . $e->getMessage());
    $tableMissing = (($e->errorInfo[1] ?? 0) == 1146);
    $setupError = $tableMissing
        ? 'The payment_proofs table has not been created yet. Click "Create table now" to set it up (nothing else is changed).'
        : 'Could not load payment proofs. Check the PHP error log for details.';
}

$pageTitle = 'Payment Proofs';
$navTitle = 'UPI Payment Proof Verification';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>
        <main class="main-content">

            <?php if ($setupError !== ''): ?>
                <div class="alert-banner">
                    <div class="alert-banner-content">
                        <span class="alert-banner-icon">&#9888;</span>
                        <span><?= e($setupError) ?></span>
                    </div>
                    <?php if ($tableMissing): ?>
                        <form method="POST" action="<?= $backUrl ?>" style="margin:0;">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="create_table">
                            <button type="submit" class="btn btn-primary btn-sm">Create table now</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- KPI row -->
            <div class="kpi-grid" style="margin-bottom:24px;">
                <div class="kpi-card kpi-orange">
                    <div class="kpi-details">
                        <span class="kpi-title">Pending Review</span>
                        <span class="kpi-value"><?= $pendingCount ?></span>
                        <span class="kpi-subtext">Awaiting verification</span>
                    </div>
                </div>
                <div class="kpi-card kpi-green">
                    <div class="kpi-details">
                        <span class="kpi-title">Approved</span>
                        <span class="kpi-value" style="color:var(--success);"><?= $approvedCount ?></span>
                        <span class="kpi-subtext">Memberships activated</span>
                    </div>
                </div>
                <div class="kpi-card kpi-red">
                    <div class="kpi-details">
                        <span class="kpi-title">Rejected</span>
                        <span class="kpi-value" style="color:var(--danger);"><?= $rejectedCount ?></span>
                        <span class="kpi-subtext">Payment not confirmed</span>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-body" style="padding:16px 20px;">
                    <form method="GET" class="filter-bar" style="margin:0; flex-wrap:wrap; gap:10px;">
                        <div class="search-input-wrapper" style="min-width:200px;">
                            <span class="search-icon">🔍</span>
                            <input type="text" name="search" class="form-control"
                                placeholder="Search member, UTR or ref…" value="<?= e($filterSearch) ?>">
                        </div>
                        <select name="status" class="form-control" style="width:auto;min-width:140px;">
                            <option value="">All Statuses</option>
                            <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved
                            </option>
                            <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected
                            </option>
                        </select>
                        <input type="date" name="start_date" class="form-control" style="width:auto;"
                            value="<?= e($filterStart) ?>" title="From date">
                        <input type="date" name="end_date" class="form-control" style="width:auto;"
                            value="<?= e($filterEnd) ?>" title="To date">
                        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                        <?php if ($filterSearch || $filterStatus || $filterStart || $filterEnd): ?>
                            <a href="<?= $backUrl ?>" class="btn btn-secondary btn-sm">&times; Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Table -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <svg width="18" height="18">
                            <use href="#icon-payment"></use>
                        </svg>
                        UPI Payment Submissions
                    </h2>
                    <span style="font-size:13px; color:var(--text-muted);"><?= count($proofRows) ?> record(s)</span>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Member</th>
                                <th>Plan / Amount</th>
                                <th>UPI / UTR</th>
                                <th>Ref</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($proofRows)): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center;color:var(--text-dim);padding:40px;">
                                        No payment proofs found.
                                    </td>
                                </tr>
                            <?php else:
                                foreach ($proofRows as $r): ?>
                                    <tr>
                                        <td style="font-family:monospace;color:var(--text-dim);">#<?= (int) $r['id'] ?></td>
                                        <td>
                                            <div style="font-weight:700;color:#fff;"><?= e($r['full_name']) ?></div>
                                            <div style="font-size:12px;color:var(--text-dim);font-family:monospace;">
                                                <?= e($r['member_code']) ?>
                                            </div>
                                            <div style="font-size:12px;color:var(--text-muted);"><?= e($r['member_email']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight:600;color:#fff;"><?= e($r['plan_name']) ?></div>
                                            <div style="color:var(--primary);font-weight:800;">
                                                ₹<?= number_format((float) $r['amount'], 2) ?></div>
                                            <div style="font-size:12px;color:var(--text-dim);"><?= (int) $r['duration_days'] ?>
                                                days</div>
                                        </td>
                                        <td><code
                                                style="font-size:13px;color:var(--warning);"><?= e($r['transaction_id']) ?></code>
                                        </td>
                                        <td><code
                                                style="font-size:11px;color:var(--text-dim);"><?= e($r['payment_ref'] ?? '—') ?></code>
                                        </td>
                                        <td style="font-size:13px;color:var(--text-muted);">
                                            <?= e(date('d M Y H:i', strtotime((string) $r['created_at']))) ?>
                                        </td>
                                        <td>
                                            <?php if ($r['status'] === 'pending'): ?>
                                                <span class="badge badge-warning">PENDING</span>
                                            <?php elseif ($r['status'] === 'approved'): ?>
                                                <span class="badge badge-success">APPROVED</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">REJECTED</span>
                                            <?php endif; ?>
                                            <?php if (!empty($r['reviewer_name'])): ?>
                                                <div style="font-size:11px;color:var(--text-dim);margin-top:3px;">
                                                    by <?= e($r['reviewer_name']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick='openReviewModal(<?= json_encode([
                                                'id' => (int) $r['id'],
                                                'full_name' => $r['full_name'],
                                                'member_code' => $r['member_code'],
                                                'member_email' => $r['member_email'],
                                                'member_phone' => $r['member_phone'],
                                                'plan_name' => $r['plan_name'],
                                                'duration_days' => (int) $r['duration_days'],
                                                'amount' => $r['amount'],
                                                'currency' => $r['currency'] ?? 'INR',
                                                'transaction_id' => $r['transaction_id'],
                                                'payment_ref' => $r['payment_ref'] ?? '',
                                                'status' => $r['status'],
                                                'rejection_reason' => $r['rejection_reason'] ?? '',
                                                'created_at' => $r['created_at'],
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                Review
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</div>

<!-- ══════════════ Review Modal ══════════════ -->
<div class="modal-backdrop" id="reviewModal" role="dialog" aria-modal="true" aria-labelledby="reviewModalTitle">
    <div class="modal-dialog" style="max-width:640px;">
        <div class="modal-header">
            <h3 id="reviewModalTitle" class="modal-title">Review Payment Proof</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('reviewModal')"
                aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">

            <!-- Member & plan details (filled by JS) -->
            <div id="reviewDetailGrid"
                style="display:grid;grid-template-columns:1fr 1fr;gap:8px;background:var(--bg-input);border-radius:var(--radius-sm);padding:14px;margin-bottom:16px;font-size:13px;">
            </div>

            <!-- Screenshot -->
            <div style="margin-bottom:16px;">
                <div
                    style="font-size:12px;color:var(--text-dim);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;">
                    Payment Screenshot
                </div>
                <img id="reviewScreenshot" src="" alt="Payment screenshot"
                    style="max-width:100%;border-radius:var(--radius-sm);border:1px solid var(--border-color);">
                <div style="margin-top:6px;font-size:11px;color:var(--text-dim);">
                    Served privately; only logged-in admins can open it.
                </div>
            </div>

            <!-- Already-reviewed banner -->
            <div id="reviewAlreadyDone" class="alert-toast"
                style="display:none;position:static;animation:none;margin-bottom:12px;max-width:none;"></div>

            <!-- Approve form -->
            <form id="approveForm" method="POST" action="<?= $backUrl ?>" style="display:none;margin-bottom:12px;"
                onsubmit="return confirm('Confirm that this money has arrived in your PhonePe / bank account. Approve and activate the membership?');">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="proof_id" id="approveProofId" value="">
                <div
                    style="background:var(--success-bg);border:1px solid var(--success);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:12px;font-size:13px;color:var(--success);">
                    ✓ Approving activates the member's membership immediately.
                    Only approve after you have found this transaction in your PhonePe merchant records.
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    ✓ Confirm Approval &amp; Activate Membership
                </button>
            </form>

            <!-- Reject form -->
            <form id="rejectForm" method="POST" action="<?= $backUrl ?>" style="display:none;"
                onsubmit="return confirm('Reject this payment submission?');">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="proof_id" id="rejectProofId" value="">
                <div class="form-group">
                    <label class="form-label" for="rejectionReason">Rejection Reason <span
                            class="required">*</span></label>
                    <textarea id="rejectionReason" name="rejection_reason" class="form-control" rows="3" maxlength="500"
                        placeholder="e.g. Transaction not found in PhonePe, amount mismatch, duplicate submission…"
                        required></textarea>
                </div>
                <button type="submit" class="btn btn-danger" style="width:100%;">
                    ✗ Reject Payment Submission
                </button>
            </form>

        </div>
    </div>
</div>

<script>
    function escH(str) {
        const d = document.createElement('div');
        d.textContent = str == null ? '' : String(str);
        return d.innerHTML;
    }

    function statusBadge(s) {
        const map = { pending: 'warning', approved: 'success', rejected: 'danger' };
        return '<span class="badge badge-' + (map[s] || 'secondary') + '">' + escH(String(s).toUpperCase()) + '</span>';
    }

    function openReviewModal(r) {
        const grid = document.getElementById('reviewDetailGrid');
        const row = (label, val) =>
            '<div style="color:var(--text-dim);font-size:11px;text-transform:uppercase;letter-spacing:.5px;">' + label + '</div>' +
            '<div style="font-weight:600;color:#fff;">' + val + '</div>';

        grid.innerHTML =
            row('Member', escH(r.full_name) + ' <span style="color:var(--text-dim);font-size:12px;">(' + escH(r.member_code) + ')</span>') +
            row('Email / Phone', escH(r.member_email) + '<br><span style="color:var(--text-dim);">' + escH(r.member_phone || '—') + '</span>') +
            row('Plan', escH(r.plan_name) + ' (' + escH(r.duration_days) + ' days)') +
            row('Amount', '<span style="color:var(--primary);font-weight:800;">₹' + parseFloat(r.amount).toFixed(2) + ' ' + escH(r.currency) + '</span>') +
            row('UPI Transaction ID', '<code style="font-size:14px;color:var(--warning);">' + escH(r.transaction_id) + '</code>') +
            row('Payment Ref', '<code style="font-size:12px;">' + escH(r.payment_ref || '—') + '</code>') +
            row('Submitted', escH(r.created_at)) +
            row('Status', statusBadge(r.status));

        // Screenshot is streamed from private storage by an authenticated endpoint.
        document.getElementById('reviewScreenshot').src =
            '<?= base_url('payments/payment_proof_file.php') ?>?id=' + encodeURIComponent(r.id);

        const approveForm = document.getElementById('approveForm');
        const rejectForm = document.getElementById('rejectForm');
        const doneBanner = document.getElementById('reviewAlreadyDone');

        document.getElementById('approveProofId').value = r.id;
        document.getElementById('rejectProofId').value = r.id;
        document.getElementById('rejectionReason').value = '';

        approveForm.style.display = 'none';
        rejectForm.style.display = 'none';
        doneBanner.style.display = 'none';

        if (r.status === 'pending') {
            approveForm.style.display = '';
            rejectForm.style.display = '';
        } else {
            doneBanner.textContent = r.status === 'approved'
                ? '✓ This submission has already been approved.'
                : '✗ This submission was rejected. Reason: ' + (r.rejection_reason || '—');
            doneBanner.className = 'alert-toast alert-' + (r.status === 'approved' ? 'success' : 'error');
            doneBanner.style.display = '';
        }

        openModal('reviewModal');
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


