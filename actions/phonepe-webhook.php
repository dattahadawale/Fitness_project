<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/phonepe.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !phonepe_validate_webhook_authorization()) {
    http_response_code(401);
    echo json_encode(['received' => false]);
    exit;
}

$callback = json_decode(file_get_contents('php://input'), true);
$merchantOrderId = $callback['payload']['merchantOrderId'] ?? $callback['merchantOrderId'] ?? null;
if (!is_string($merchantOrderId) || !preg_match('/^APX-[A-Z0-9-]{10,60}$/', $merchantOrderId)) {
    http_response_code(400);
    echo json_encode(['received' => false]);
    exit;
}

try {
    $providerStatus = phonepe_order_status($merchantOrderId);
    phonepe_complete_order(getDB(), $merchantOrderId, $providerStatus);
    echo json_encode(['received' => true]);
} catch (Throwable $exception) {
    error_log('PhonePe webhook processing failed: ' . $exception->getMessage());
    http_response_code(502);
    echo json_encode(['received' => false]);
}
