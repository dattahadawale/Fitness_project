<?php
/**
 * Member endpoint: submit UPI payment proof. Always answers JSON.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/payment_proofs.php';

set_exception_handler(function (Throwable $e) {
  error_log('payment-proof-actions: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  $msg = $e instanceof PDOException
    ? 'Database error while saving your payment. Ask the administrator to apply the latest database migration. (' . ($e->errorInfo[1] ?? $e->getCode()) . ')'
    : 'Unexpected server error. Please try again.';
  pp_json(['ok' => false, 'error' => $msg], 500);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  pp_json(['ok' => false, 'error' => 'Invalid request method.'], 405);
}
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
  pp_json(['ok' => false, 'error' => 'Security token expired. Refresh the page and try again.'], 419);
}
$userId = pp_current_user_id();
if ($userId <= 0 || ($_SESSION['user']['role'] ?? '') !== 'member') {
  pp_json(['ok' => false, 'error' => 'Please sign in as a member to submit a payment.'], 401);
}

$db = getDB();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
  // 1. Plan and price come from the server, never from the browser.
  $planId = filter_var($_POST['plan_id'] ?? '', FILTER_VALIDATE_INT);
  if (!$planId || $planId < 1) {
    throw new InvalidArgumentException('Select a membership plan.');
  }
  $ps = $db->prepare("SELECT id, plan_name, price FROM membership_plans WHERE id = :id AND status = 'active'");
  $ps->execute([':id' => $planId]);
  $plan = $ps->fetch();
  if (!$plan) {
    throw new InvalidArgumentException('That membership plan is not available.');
  }
  $memberStmt = $db->prepare('SELECT id FROM members WHERE user_id = :user_id LIMIT 1');
  $memberStmt->execute([':user_id' => $userId]);
  $memberId = (int) $memberStmt->fetchColumn();
  if ($memberId <= 0) {
    throw new InvalidArgumentException('No member profile is linked to this account.');
  }

  // 2. Inputs
  $utr = pp_validate_utr((string) ($_POST['utr'] ?? ''));
  [$tmp, $ext] = pp_validate_upload($_FILES['screenshot'] ?? null);

  // 3. Store file privately under a random name.
  $fileName = bin2hex(random_bytes(16)) . '.' . $ext;
  $dest = pp_storage_dir() . DIRECTORY_SEPARATOR . $fileName;
  if (!move_uploaded_file($tmp, $dest)) {
    throw new RuntimeException('Could not store the screenshot. Check folder permissions.');
  }
  @chmod($dest, 0640);

  // 4. One transaction: lock the user row so double-clicks serialize.
  $ref = 'PP' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
  try {
    $db->beginTransaction();
    $db->prepare("SELECT id FROM users WHERE id = :id FOR UPDATE")->execute([':id' => $userId]);

    $pend = $db->prepare("SELECT payment_ref FROM payment_proofs WHERE user_id = :u AND status = 'pending' LIMIT 1");
    $pend->execute([':u' => $userId]);
    if ($existing = $pend->fetchColumn()) {
      throw new InvalidArgumentException("You already have a payment ($existing) waiting for verification. Wait for it to be reviewed first.");
    }
    $dup = $db->prepare("SELECT 1 FROM payment_proofs WHERE transaction_id = :u LIMIT 1");
    $dup->execute([':u' => $utr]);
    if ($dup->fetchColumn()) {
      throw new InvalidArgumentException('This transaction ID was already submitted.');
    }

    $db->prepare("
      INSERT INTO payments
        (member_id, membership_id, amount, payment_date, payment_method, transaction_ref, notes, status)
      VALUES
        (:member_id, NULL, :amount, CURRENT_DATE, 'UPI', :transaction_ref, 'UPI proof submitted; awaiting verification.', 'pending')
    ")->execute([
      ':member_id' => $memberId,
      ':amount' => $plan['price'],
      ':transaction_ref' => $ref,
    ]);
    $db->prepare("
      INSERT INTO payment_proofs
        (user_id, member_id, plan_id, amount, currency, transaction_id, payment_ref, screenshot_file, status, created_at)
      VALUES
        (:user_id, :member_id, :plan_id, :amount, 'INR', :transaction_id, :payment_ref, :screenshot_file, 'pending', NOW())
    ")->execute([
      ':user_id' => $userId,
      ':member_id' => $memberId,
      ':plan_id' => (int) $plan['id'],
      ':amount' => $plan['price'],
      ':transaction_id' => $utr,
      ':payment_ref' => $ref,
      ':screenshot_file' => $fileName,
    ]);
    $newId = (int) $db->lastInsertId();
    $db->commit();
  } catch (Throwable $e) {
    if ($db->inTransaction()) {
      $db->rollBack();
    }
    @unlink($dest);   // do not keep orphan files
    throw $e;
  }

  // 5. Email AFTER commit; a mail failure never loses the saved record.
  pp_notify_admin_workflow($db, $newId);

  pp_json([
    'ok' => true,
    'message' => 'Payment submitted successfully. Your payment is pending administrator verification.',
    'payment_ref' => $ref,
    'redirect' => base_url('member/payments.php?submitted=' . rawurlencode($ref)),
  ]);
} catch (InvalidArgumentException $e) {
  pp_json(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (PDOException $e) {
  if (($e->errorInfo[0] ?? '') === '23000') {   // unique key hit by a concurrent duplicate
    pp_json(['ok' => false, 'error' => 'This transaction ID was already submitted.'], 409);
  }
  throw $e;
}


