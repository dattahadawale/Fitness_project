<?php
/**
 * Payment proof helpers (submission, storage, email, activation).
 * Assumes functions.php already provides getDB() and session start.
 */

const PP_PENDING = 'PENDING_VERIFICATION';
const PP_VERIFIED = 'VERIFIED';
const PP_REJECTED = 'REJECTED';
const PP_MAX_BYTES = 5 * 1024 * 1024;

/** Where to send "new payment" alerts. Override with env ADMIN_NOTIFY_EMAIL. */
function pp_admin_email(): string
{
  return getenv('ADMIN_NOTIFY_EMAIL') ?: 'dattahadawale9322@gmail.com';
}

/** Session user id. ADJUST here if your session stores the id under another key. */
function pp_current_user_id(): int
{
  return (int) ($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
}

/** Private directory OUTSIDE the web root (one level above the project folder). */
function pp_storage_dir(): string
{
  $dir = getenv('PAYMENT_PROOF_DIR') ?: dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'gym_private_uploads' . DIRECTORY_SEPARATOR . 'payment_proofs';
  if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
    throw new RuntimeException('Cannot create the screenshot storage folder. Check folder permissions.');
  }
  return $dir;
}

function pp_json(array $data, int $status = 200)
{
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function pp_status_label(string $s): string
{
  return str_replace('_', ' ', $s);
}

/** Validates the UTR/UPI reference; returns normalised value or throws. */
function pp_validate_utr(string $raw): string
{
  $utr = strtoupper(trim($raw));
  if ($utr === '') {
    throw new InvalidArgumentException('Enter your UPI transaction ID / UTR number.');
  }
  if (!preg_match('/^[A-Z0-9]{10,30}$/', $utr)) {
    throw new InvalidArgumentException('Transaction ID must be 10-30 letters/digits with no spaces or symbols.');
  }
  return $utr;
}

/**
 * Validates the upload by real content (not the browser-supplied type).
 * Returns [tmpPath, extension].
 */
function pp_validate_upload(?array $file): array
{
  if (!$file || !isset($file['error'])) {
    throw new InvalidArgumentException('Attach your payment screenshot.');
  }
  $errors = [
    UPLOAD_ERR_INI_SIZE => 'The screenshot is larger than the server limit (upload_max_filesize in php.ini).',
    UPLOAD_ERR_FORM_SIZE => 'The screenshot is too large.',
    UPLOAD_ERR_PARTIAL => 'The screenshot upload was interrupted. Please try again.',
    UPLOAD_ERR_NO_FILE => 'Attach your payment screenshot.',
    UPLOAD_ERR_NO_TMP_DIR => 'Server error: missing temporary upload folder.',
    UPLOAD_ERR_CANT_WRITE => 'Server error: cannot write the uploaded file.',
    UPLOAD_ERR_EXTENSION => 'Server error: an extension blocked the upload.',
  ];
  if ($file['error'] !== UPLOAD_ERR_OK) {
    throw new InvalidArgumentException($errors[$file['error']] ?? 'Screenshot upload failed.');
  }
  if (!is_uploaded_file($file['tmp_name'])) {
    throw new InvalidArgumentException('Invalid upload.');
  }
  if ($file['size'] <= 0 || $file['size'] > PP_MAX_BYTES) {
    throw new InvalidArgumentException('Screenshot must be larger than 0 and at most 5 MB.');
  }
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($file['tmp_name']);
  $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
  if (!isset($map[$mime])) {
    throw new InvalidArgumentException('Screenshot must be a JPG, PNG or WEBP image.');
  }
  $info = @getimagesize($file['tmp_name']);
  if ($info === false || $info[0] < 100 || $info[1] < 100 || $info[0] * $info[1] > 50_000_000) {
    throw new InvalidArgumentException('The file is not a valid screenshot image.');
  }
  return [$file['tmp_name'], $map[$mime]];
}

/** Sends mail through the same SMTP env vars the project already uses. */
function pp_send_mail(string $to, string $subject, string $html, ?string $attachPath = null): void
{
  require_once dirname(__DIR__) . '/vendor/autoload.php';
  $m = new PHPMailer\PHPMailer\PHPMailer(true);
  $m->isSMTP();
  $m->Host = getenv('SMTP_HOST') ?: '';
  $m->Port = (int) (getenv('SMTP_PORT') ?: 587);
  $m->SMTPAuth = true;
  $m->Username = getenv('SMTP_USERNAME') ?: '';
  $m->Password = getenv('SMTP_PASSWORD') ?: '';
  $enc = strtolower(getenv('SMTP_ENCRYPTION') ?: 'tls');
  $m->SMTPSecure = $enc === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
  $m->CharSet = 'UTF-8';
  $m->setFrom(getenv('SMTP_FROM_EMAIL') ?: $m->Username, getenv('SMTP_FROM_NAME') ?: 'Apex Fitness Club');
  $m->addAddress($to);
  $m->isHTML(true);
  $m->Subject = $subject;
  $m->Body = $html;
  $m->AltBody = trim(strip_tags(str_replace(['<br>', '</tr>'], "\n", $html)));
  if ($attachPath && is_file($attachPath)) {
    $m->addAttachment($attachPath);
  }
  $m->send();
}

/** Loads one payment joined with member + plan info. */
function pp_load(PDO $db, int $id, bool $lock = false): ?array
{
  $st = $db->prepare(
    "SELECT pp.*, u.name AS member_name, u.email AS member_email, mp.plan_name, mp.duration_days
           FROM payment_proofs pp
           JOIN users u ON u.id = pp.user_id
           JOIN membership_plans mp ON mp.id = pp.plan_id
          WHERE pp.id = :id" . ($lock ? ' FOR UPDATE' : '')
  );
  $st->execute([':id' => $id]);
  return $st->fetch() ?: null;
}

/** Emails the admin about a saved submission. Never throws; records the outcome. */
function pp_notify_admin(PDO $db, int $id): void
{
  try {
    $p = pp_load($db, $id);
    if (!$p) {
      return;
    }
    $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $body = '<h3>New payment awaiting verification</h3><table cellpadding="6">'
      . '<tr><td>Reference</td><td>' . $h($p['payment_ref']) . '</td></tr>'
      . '<tr><td>Member</td><td>' . $h($p['member_name']) . ' (ID ' . (int) $p['user_id'] . ')</td></tr>'
      . '<tr><td>Email</td><td>' . $h($p['member_email']) . '</td></tr>'
      . '<tr><td>Plan</td><td>' . $h($p['plan_name']) . '</td></tr>'
      . '<tr><td>Amount</td><td>' . $h($p['currency'] . ' ' . number_format((float) $p['amount'], 2)) . '</td></tr>'
      . '<tr><td>UTR</td><td>' . $h($p['utr_reference']) . '</td></tr>'
      . '<tr><td>Submitted</td><td>' . $h($p['submitted_at']) . '</td></tr>'
      . '<tr><td>Status</td><td>PENDING VERIFICATION</td></tr></table>'
      . '<p>Screenshot is attached. Review it in the admin panel: admin/payment-review.php</p>';
    pp_send_mail(
      pp_admin_email(),
      'Payment pending verification - ' . $p['payment_ref'],
      $body,
      pp_storage_dir() . DIRECTORY_SEPARATOR . $p['screenshot_file']
    );
    $db->prepare("UPDATE payment_proofs SET admin_notified_at = NOW(), notify_error = NULL WHERE id = :id")
      ->execute([':id' => $id]);
  } catch (Throwable $e) {
    error_log('Payment notification email failed for #' . $id . ': ' . $e->getMessage());
    try {
      $db->prepare("UPDATE payment_proofs SET notify_error = :e WHERE id = :id")
        ->execute([':e' => mb_substr($e->getMessage(), 0, 480), ':id' => $id]);
    } catch (Throwable $ignored) {
    }
  }
}

/** Emails the admin about a payment proof using the dashboard workflow schema. */
function pp_notify_admin_workflow(PDO $db, int $id): void
{
  try {
    $st = $db->prepare(
      "SELECT pp.*, m.full_name AS member_name, u.email AS member_email, mp.plan_name
         FROM payment_proofs pp
         JOIN members m ON m.id = pp.member_id
         JOIN users u ON u.id = pp.user_id
         JOIN membership_plans mp ON mp.id = pp.plan_id
        WHERE pp.id = :id"
    );
    $st->execute([':id' => $id]);
    $p = $st->fetch();
    if (!$p) {
      throw new RuntimeException('Saved payment proof could not be loaded for notification.');
    }

    $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $body = '<h3>New payment awaiting verification</h3><table cellpadding="6">'
      . '<tr><td>Reference</td><td>' . $h($p['payment_ref']) . '</td></tr>'
      . '<tr><td>Member</td><td>' . $h($p['member_name']) . ' (ID ' . (int) $p['member_id'] . ')</td></tr>'
      . '<tr><td>Email</td><td>' . $h($p['member_email']) . '</td></tr>'
      . '<tr><td>Plan</td><td>' . $h($p['plan_name']) . '</td></tr>'
      . '<tr><td>Amount</td><td>' . $h($p['currency'] . ' ' . number_format((float) $p['amount'], 2)) . '</td></tr>'
      . '<tr><td>UTR</td><td>' . $h($p['transaction_id']) . '</td></tr>'
      . '<tr><td>Submitted</td><td>' . $h($p['created_at']) . '</td></tr>'
      . '<tr><td>Status</td><td>PENDING VERIFICATION</td></tr></table>'
      . '<p>Review this payment in the admin panel: admin/payment-proofs.php</p>';
    pp_send_mail(
      pp_admin_email(),
      'Payment pending verification - ' . $p['payment_ref'],
      $body,
      pp_storage_dir() . DIRECTORY_SEPARATOR . $p['screenshot_file']
    );
    $db->prepare('UPDATE payment_proofs SET email_sent = 1 WHERE id = :id')
      ->execute([':id' => $id]);
  } catch (Throwable $e) {
    error_log('Payment notification email failed for proof #' . $id . ': ' . $e->getMessage());
  }
}

/**
 * ==== ADJUST THIS FUNCTION TO YOUR MEMBERSHIP TABLES ====
 * Called INSIDE the verify transaction, so any error rolls the whole approval back.
 * Assumed schema:  members(id, user_id)  and
 *                  memberships(member_id, plan_id, start_date, end_date, status)
 * Extends from the current end date if still active, otherwise from today.
 */
function pp_activate_membership(PDO $db, int $userId, int $planId, int $durationDays): void
{
  $m = $db->prepare("SELECT id FROM members WHERE user_id = :u LIMIT 1 FOR UPDATE");
  $m->execute([':u' => $userId]);
  $memberId = $m->fetchColumn();
  if (!$memberId) {
    throw new RuntimeException('No member profile exists for this user, so membership cannot be activated.');
  }
  $cur = $db->prepare(
    "SELECT id, end_date FROM memberships
          WHERE member_id = :m AND status = 'active' ORDER BY end_date DESC LIMIT 1 FOR UPDATE"
  );
  $cur->execute([':m' => $memberId]);
  $row = $cur->fetch();
  $today = date('Y-m-d');
  $start = ($row && $row['end_date'] >= $today) ? $row['end_date'] : $today;
  $end = date('Y-m-d', strtotime($start . ' +' . $durationDays . ' days'));
  if ($row && $row['end_date'] >= $today) {
    $db->prepare("UPDATE memberships SET plan_id = :p, end_date = :e WHERE id = :id")
      ->execute([':p' => $planId, ':e' => $end, ':id' => $row['id']]);
  } else {
    $db->prepare(
      "INSERT INTO memberships (member_id, plan_id, start_date, end_date, status)
             VALUES (:m, :p, :s, :e, 'active')"
    )->execute([':m' => $memberId, ':p' => $planId, ':s' => $start, ':e' => $end]);
  }
}


