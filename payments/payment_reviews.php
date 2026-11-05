<?php
/** Admin-only payment review: verify / reject. */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/payment_proofs.php';

if (($_SESSION['user']['role'] ?? '') !== 'admin') {
  http_response_code(403);
  exit('Administrators only.');
}

$db = getDB();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$adminId = pp_current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $back = base_url('admin/payment-review.php');
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Security token expired. Refresh and try again.');
    header('Location: ' . $back);
    exit;
  }
  $id = filter_var($_POST['payment_id'] ?? '', FILTER_VALIDATE_INT);
  $action = $_POST['action'] ?? '';
  $notifyAfter = null;

  try {
    if (!$id) {
      throw new InvalidArgumentException('Invalid payment.');
    }

    if ($action === 'verify' && empty($_POST['confirm_received'])) {
      throw new InvalidArgumentException('Tick the box to confirm the money was received in your bank/UPI records.');
    }
    $reason = trim((string) ($_POST['reason'] ?? ''));
    if ($action === 'reject' && (mb_strlen($reason) < 3 || mb_strlen($reason) > 500)) {
      throw new InvalidArgumentException('Enter a rejection reason (3-500 characters).');
    }
    if (!in_array($action, ['verify', 'reject'], true)) {
      throw new InvalidArgumentException('Unknown action.');
    }

    $db->beginTransaction();
    $p = pp_load($db, $id, true);                       // row lock
    if (!$p) {
      throw new InvalidArgumentException('Payment not found.');
    }
    if ($p['payment_status'] !== PP_PENDING) {
      throw new InvalidArgumentException('This payment was already ' . pp_status_label($p['payment_status']) . '. No changes were made.');
    }

    if ($action === 'verify') {
      $u = $db->prepare(
        "UPDATE payment_proofs SET payment_status = :new, reviewed_by = :a, reviewed_at = NOW()
                  WHERE id = :id AND payment_status = :old"
      );
      $u->execute([':new' => PP_VERIFIED, ':a' => $adminId, ':id' => $id, ':old' => PP_PENDING]);
      if ($u->rowCount() !== 1) {
        throw new InvalidArgumentException('Payment changed while you were reviewing it.');
      }
      pp_activate_membership($db, (int) $p['user_id'], (int) $p['plan_id'], (int) $p['duration_days']);
      $msg = 'Payment ' . $p['payment_ref'] . ' verified and membership activated.';
    } else {
      $u = $db->prepare(
        "UPDATE payment_proofs SET payment_status = :new, reviewed_by = :a, reviewed_at = NOW(), rejection_reason = :r
                  WHERE id = :id AND payment_status = :old"
      );
      $u->execute([':new' => PP_REJECTED, ':a' => $adminId, ':r' => $reason, ':id' => $id, ':old' => PP_PENDING]);
      if ($u->rowCount() !== 1) {
        throw new InvalidArgumentException('Payment changed while you were reviewing it.');
      }
      $msg = 'Payment ' . $p['payment_ref'] . ' rejected.';
    }
    $db->commit();                                       // success message only after commit
    set_flash('success', $msg);
    $notifyAfter = [$p, $action, $reason];
  } catch (InvalidArgumentException $e) {
    if ($db->inTransaction()) {
      $db->rollBack();
    }
    set_flash('error', $e->getMessage());
  } catch (Throwable $e) {
    if ($db->inTransaction()) {
      $db->rollBack();
    }
    error_log('payment-review: ' . $e->getMessage());
    set_flash('error', 'Could not complete the action: ' . $e->getMessage());
  }

  if ($notifyAfter) {                                      // optional member email; never affects the result
    [$p, $act, $why] = $notifyAfter;
    try {
      $body = $act === 'verify'
        ? '<p>Hi ' . e($p['member_name']) . ', your payment ' . e($p['payment_ref']) . ' for ' . e($p['plan_name']) . ' was verified. Your membership is active.</p>'
        : '<p>Hi ' . e($p['member_name']) . ', your payment ' . e($p['payment_ref']) . ' was rejected. Reason: ' . e($why) . '. You can submit a new payment from your dashboard.</p>';
      pp_send_mail($p['member_email'], 'Payment ' . ($act === 'verify' ? 'verified' : 'rejected') . ' - ' . $p['payment_ref'], $body);
    } catch (Throwable $e) {
      error_log('Member payment email failed: ' . $e->getMessage());
    }
  }
  header('Location: ' . $back);
  exit;
}

$filter = in_array($_GET['status'] ?? '', [PP_PENDING, PP_VERIFIED, PP_REJECTED], true) ? $_GET['status'] : PP_PENDING;
$st = $db->prepare(
  "SELECT pp.*, u.name AS member_name, u.email AS member_email, mp.plan_name
       FROM payment_proofs pp
       JOIN users u ON u.id = pp.user_id
       JOIN membership_plans mp ON mp.id = pp.plan_id
      WHERE pp.payment_status = :s ORDER BY pp.submitted_at ASC LIMIT 200"
);
$st->execute([':s' => $filter]);
$rows = $st->fetchAll();

$pageTitle = 'Payment Verification';
include __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width: 1200px; margin: 24px auto;">
  <div class="card-header">
    <h3 class="card-title">Payment Verification</h3>
  </div>
  <div class="card-body">
    <p style="margin-bottom:12px;">
      <?php foreach ([PP_PENDING, PP_VERIFIED, PP_REJECTED] as $s): ?>
        <a class="btn btn-sm <?= $filter === $s ? 'btn-primary' : 'btn-secondary' ?>"
          href="?status=<?= $s ?>"><?= e(pp_status_label($s)) ?></a>
      <?php endforeach; ?>
    </p>
    <div style="overflow-x:auto;">
      <table class="data-table" style="width:100%;">
        <thead>
          <tr>
            <th>Ref</th>
            <th>Member</th>
            <th>Plan / Amount</th>
            <th>UTR</th>
            <th>Submitted</th>
            <th>Screenshot</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr>
              <td colspan="8" style="text-align:center;">No <?= e(pp_status_label($filter)) ?> payments.</td>
            </tr><?php endif; ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= e($r['payment_ref']) ?></td>
              <td><?= e($r['member_name']) ?> (ID
                <?= (int) $r['user_id'] ?>)<br><small><?= e($r['member_email']) ?></small>
              </td>
              <td><?= e($r['plan_name']) ?><br><strong><?= e($r['currency']) ?>
                  <?= number_format((float) $r['amount'], 2) ?></strong></td>
              <td><?= e($r['utr_reference']) ?></td>
              <td><?= e($r['submitted_at']) ?></td>
              <td><a href="<?= base_url('admin/payment-proof-file.php?id=' . (int) $r['id']) ?>" target="_blank"
                  rel="noopener">View</a></td>
              <td><?= e(pp_status_label($r['payment_status'])) ?>
                <?php if ($r['rejection_reason']): ?><br><small><?= e($r['rejection_reason']) ?></small><?php endif; ?>
              </td>
              <td>
                <?php if ($r['payment_status'] === PP_PENDING): ?>
                  <form method="POST"
                    onsubmit="return confirm('Verify this payment and activate the membership? Only continue if the money is in your bank/UPI records.');"
                    style="margin-bottom:8px;">
                    <?= csrf_input() ?>
                    <input type="hidden" name="payment_id" value="<?= (int) $r['id'] ?>">
                    <input type="hidden" name="action" value="verify">
                    <label style="font-size:12px;"><input type="checkbox" name="confirm_received" value="1" required> I
                      confirmed the money was received</label><br>
                    <button class="btn btn-primary btn-sm" type="submit">Verify Payment</button>
                  </form>
                  <form method="POST" onsubmit="return confirm('Reject this payment?');">
                    <?= csrf_input() ?>
                    <input type="hidden" name="payment_id" value="<?= (int) $r['id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="text" name="reason" class="form-control" placeholder="Rejection reason" maxlength="500"
                      required>
                    <button class="btn btn-secondary btn-sm" type="submit" style="margin-top:4px;">Reject Payment</button>
                  </form>
                <?php else: ?>
                  Reviewed <?= e($r['reviewed_at']) ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>