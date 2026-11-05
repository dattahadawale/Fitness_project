<?php
/** Streams a screenshot from private storage. Admins: any. Members: only their own. */
require_once __DIR__ . '/../includes/functions.php';

// payment_proofs.php lives next to this file (payments/); fall back to includes/ if it is moved there later.
$ppHelper = is_file(__DIR__ . '/payment_proofs.php')
  ? __DIR__ . '/payment_proofs.php'
  : __DIR__ . '/../includes/payment_proofs.php';
require_once $ppHelper;

$role = $_SESSION['user']['role'] ?? '';
$uid = pp_current_user_id();
if (!$uid || !in_array($role, ['admin', 'member'], true)) {
  http_response_code(403);
  exit('Forbidden');
}

$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id) {
  http_response_code(404);
  exit('Not found');
}

$st = getDB()->prepare("SELECT user_id, screenshot_file FROM payment_proofs WHERE id = :id");
$st->execute([':id' => $id]);
$r = $st->fetch();
if (!$r || ($role !== 'admin' && (int) $r['user_id'] !== $uid)) {
  http_response_code(404);
  exit('Not found');
}

$path = pp_storage_dir() . DIRECTORY_SEPARATOR . basename($r['screenshot_file']);
if (!is_file($path)) {
  http_response_code(404);
  exit('File missing');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; img-src \'self\'');
header('Cache-Control: private, max-age=300');
readfile($path);

exit;


