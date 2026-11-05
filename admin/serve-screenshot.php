<?php
/**
 * Secure Screenshot Serving – Admin Only
 * Gym Management System – APEXFITNESS
 *
 * Streams the payment screenshot file from the non-public uploads directory.
 * Only authenticated admins may access this endpoint.
 * The real file path is never exposed to the browser.
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$proofId = (int)($_GET['id'] ?? 0);
if ($proofId <= 0) {
    http_response_code(400);
    exit('Invalid request.');
}

$db   = getDB();
$stmt = $db->prepare("SELECT screenshot_file FROM payment_proofs WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $proofId]);
$row = $stmt->fetch();

if (!$row || empty($row['screenshot_file'])) {
    http_response_code(404);
    exit('Screenshot not found.');
}

// Validate filename – must be hex + allowed extension only
$filename = (string)$row['screenshot_file'];
if (!preg_match('/^[a-f0-9]{32}\.(jpg|jpeg|png|webp)$/i', $filename)) {
    http_response_code(400);
    exit('Invalid screenshot reference.');
}

$uploadDir  = dirname(__DIR__) . '/uploads/payment_proofs';
$filePath   = $uploadDir . '/' . $filename;

if (!is_file($filePath)) {
    http_response_code(404);
    exit('Screenshot file does not exist on disk.');
}

// Detect MIME type from file content
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($filePath);

$allowed = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mime, $allowed, true)) {
    http_response_code(415);
    exit('Unsupported file type.');
}

// Stream file with security headers
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="payment-screenshot.' . pathinfo($filename, PATHINFO_EXTENSION) . '"');
header('Cache-Control: private, no-cache, no-store');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
