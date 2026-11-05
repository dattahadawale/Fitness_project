<?php
/** Member payment history (UPI proof submissions) - real DB rows for the signed-in member only. */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/member_auth.php';
require_once __DIR__ . '/../includes/payment_proofs.php';

$db = getDB();
$uid = pp_current_user_id();
$rows = [];
$error = '';
try {
  $st = $db->prepare(
    "SELECT pp.*, mp.plan_name FROM payment_proofs pp
           JOIN membership_plans mp ON mp.id = pp.plan_id
          WHERE pp.user_id = :u ORDER BY pp.submitted_at DESC, pp.id DESC"
  );
  $st->execute([':u' => $uid]);
  $rows = $st->fetchAll();
} catch (PDOException $e) {
  error_log('payment-history: ' . $e->getMessage());
  $error = 'Payment history is unavailable: the payment_proofs table is missing. Ask the administrator to run database/migrations/20261026_01_payment_proofs.sql.';
}
$justSubmitted = $_GET['submitted'] ?? '';

$pageTitle = 'Payment History';
include __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width: 980px; margin: 24px auto;">
  <div class="card-header">
    <h3 class="card-title">Payment History</h3>
  </div>
  <div class="card-body">
    <?php if ($error): ?>
      <p style="color: var(--danger);"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if ($justSubmitted !== ''):
      foreach ($rows as $r):
        if ($r['payment_ref'] === $justSubmitted): ?>
          <div class="alert-toast alert-success"
            style="position: static; animation: none; margin-bottom: 16px; display:block;">
            <strong>Payment submitted successfully. Your payment is pending administrator verification.</strong><br>
            Reference: <?= e($r['payment_ref']) ?> &middot; Plan: <?= e($r['plan_name']) ?> &middot;
            Amount: <?= e($r['currency']) ?>       <?= number_format((float) $r['amount'], 2) ?><br>
            UPI/UTR: <?= e($r['utr_reference']) ?> &middot; Submitted: <?= e($r['submitted_at']) ?> &middot;
            Status: PENDING VERIFICATION
          </div>
        <?php endif; endforeach; endif; ?>

    <div style="overflow-x:auto;">
      <table class="data-table" style="width:100%;">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Plan</th>
            <th>Amount</th>
            <th>UTR</th>
            <th>Submitted</th>
            <th>Status</th>
            <th>Note</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr>
              <td colspan="7" style="text-align:center;color:var(--text-muted);">No payments submitted yet.</td>
            </tr>
          <?php endif; ?>
          <?php foreach ($rows as $r):
            $color = $r['payment_status'] === PP_VERIFIED ? 'var(--success)' : ($r['payment_status'] === PP_REJECTED ? 'var(--danger)' : 'var(--warning)'); ?>
            <tr>
              <td><?= e($r['payment_ref']) ?></td>
              <td><?= e($r['plan_name']) ?></td>
              <td><?= e($r['currency']) ?>   <?= number_format((float) $r['amount'], 2) ?></td>
              <td><?= e($r['utr_reference']) ?></td>
              <td><?= e($r['submitted_at']) ?></td>
              <td><strong style="color: <?= $color ?>;"><?= e(pp_status_label($r['payment_status'])) ?></strong></td>
              <td>
                <?php if ($r['payment_status'] === PP_REJECTED): ?>
                  Rejected: <?= e($r['rejection_reason']) ?>
                <?php elseif ($r['payment_status'] === PP_VERIFIED): ?>
                  Verified <?= e($r['reviewed_at']) ?>
                <?php else: ?>
                  Awaiting administrator review
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p style="margin-top:16px;color:var(--text-muted);font-size:13px;">
      A rejected payment can be resubmitted from your dashboard. Membership activates only after an administrator
      verifies the money was received.
    </p>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>