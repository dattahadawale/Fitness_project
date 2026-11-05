<?php

require_once __DIR__ . '/../includes/member_auth.php';
require_once __DIR__ . '/../includes/phonepe.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Security token mismatch. Please try again.');
    header('Location: ' . base_url('member/dashboard.php'));
    exit;
}

if (($_POST['action'] ?? '') !== 'start_checkout') {
    http_response_code(400);
    exit('Invalid payment action.');
}

$userId = (int) $_SESSION['user']['id'];
$planValue = $_POST['plan_id'] ?? '';
$planId = is_string($planValue) && ctype_digit($planValue) ? (int) $planValue : 0;
$db = getDB();
$memberStmt = $db->prepare('
    SELECT m.id, u.email_verified
    FROM members m
    JOIN users u ON u.id = m.user_id
    WHERE m.user_id = :user_id AND u.role = "member"
    LIMIT 1
');
$memberStmt->execute([':user_id' => $userId]);
$member = $memberStmt->fetch();
if (!$member || (int) $member['email_verified'] !== 1) {
    set_flash('error', 'Verify your member email before making a payment.');
    header('Location: ' . base_url('verify-email.php'));
    exit;
}

$planStmt = $db->prepare("SELECT id, plan_name, price FROM membership_plans WHERE id = :id AND status = 'active'");
$planStmt->execute([':id' => $planId]);
$plan = $planStmt->fetch();
if (!$plan) {
    set_flash('error', 'That membership plan is unavailable.');
    header('Location: ' . base_url('member/dashboard.php'));
    exit;
}

// ---------------------------------------------------------------------------
// QR mode (default): no PhonePe API credentials needed.
// Pay Now opens the gym's PhonePe QR popup on the dashboard (30 second wait),
// then the member sends the transaction ID + screenshot, which is emailed to the admin.
// To use the official PhonePe gateway instead, set PAYMENT_MODE=gateway in .env
// (and fill in the PhonePe credentials).
// ---------------------------------------------------------------------------
$paymentMode = strtolower((string) (getenv('PAYMENT_MODE') ?: 'qr'));
if ($paymentMode !== 'gateway') {
    $_SESSION['open_upi_qr'] = (int) $plan['id']; // read once by includes/upi-pay-modal.php
    header('Location: ' . base_url('member/dashboard.php'));
    exit;
}

try {
    $phonepeSettings = phonepe_config();
    $baseUrl = getenv('APP_BASE_URL');
    if ($baseUrl === false || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('APP_BASE_URL must be set to the public application URL.');
    }
    $baseUrl = rtrim($baseUrl, '/');
    $appScheme = parse_url($baseUrl, PHP_URL_SCHEME);
    $appHost = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
    if (
        parse_url($baseUrl, PHP_URL_USER) !== null || parse_url($baseUrl, PHP_URL_PASS) !== null
        || parse_url($baseUrl, PHP_URL_QUERY) !== null || parse_url($baseUrl, PHP_URL_FRAGMENT) !== null
        || $appHost === ''
    ) {
        throw new RuntimeException('APP_BASE_URL must be an application origin with an optional path only.');
    }
    $isLocal = in_array($appHost, ['localhost', '127.0.0.1', '::1'], true);
    if ($appScheme !== 'https' && !($phonepeSettings['base_url'] === 'https://api-preprod.phonepe.com/apis/pg-sandbox' && $isLocal)) {
        throw new RuntimeException('APP_BASE_URL must use HTTPS except for local sandbox testing.');
    }

    $price = number_format((float) $plan['price'], 2, '.', '');
    [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '00');
    $amountPaise = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    if ($amountPaise < 100) {
        throw new RuntimeException('The membership price must be at least ₹1.00 for PhonePe checkout.');
    }

    $merchantOrderId = 'APX-' . gmdate('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(8)));
    $db->beginTransaction();
    $paymentStmt = $db->prepare("
        INSERT INTO payments (member_id, membership_id, amount, payment_date, payment_method, transaction_ref, notes, status)
        VALUES (:member_id, NULL, :amount, CURRENT_DATE, 'UPI', :transaction_ref, 'PhonePe checkout created', 'pending')
    ");
    $paymentStmt->execute([
        ':member_id' => $member['id'],
        ':amount' => $price,
        ':transaction_ref' => $merchantOrderId,
    ]);
    $paymentId = (int) $db->lastInsertId();

    $orderStmt = $db->prepare("
        INSERT INTO phonepe_orders (member_id, plan_id, payment_id, merchant_order_id, amount_paise, status)
        VALUES (:member_id, :plan_id, :payment_id, :merchant_order_id, :amount_paise, 'created')
    ");
    $orderStmt->execute([
        ':member_id' => $member['id'],
        ':plan_id' => $plan['id'],
        ':payment_id' => $paymentId,
        ':merchant_order_id' => $merchantOrderId,
        ':amount_paise' => $amountPaise,
    ]);
    $db->commit();

    $redirectUrl = $baseUrl . '/payments/return.php?merchantOrderId=' . rawurlencode($merchantOrderId);
    $checkout = phonepe_create_order($merchantOrderId, $amountPaise, $redirectUrl);

    $updateOrder = $db->prepare("
        UPDATE phonepe_orders
        SET phonepe_order_id = :provider_order_id, provider_state = :provider_state,
            redirect_url = :redirect_url, status = 'pending'
        WHERE merchant_order_id = :merchant_order_id AND status = 'created'
    ");
    $updateOrder->execute([
        ':provider_order_id' => isset($checkout['orderId']) ? (string) $checkout['orderId'] : null,
        ':provider_state' => isset($checkout['state']) ? (string) $checkout['state'] : 'PENDING',
        ':redirect_url' => $checkout['redirectUrl'],
        ':merchant_order_id' => $merchantOrderId,
    ]);
    header('Location: ' . $checkout['redirectUrl'], true, 303);
    exit;
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('PhonePe checkout could not be started: ' . $exception->getMessage());
    if (isset($merchantOrderId)) {
        $db->prepare("
            UPDATE phonepe_orders
            SET status = 'pending'
            WHERE merchant_order_id = :merchant_order_id AND status = 'created'
        ")->execute([':merchant_order_id' => $merchantOrderId]);
        set_flash('warning', 'Payment status is being verified. Use the button below to check your transaction; do not start a duplicate payment.');
        header('Location: ' . base_url('payments/result.php?order=' . rawurlencode($merchantOrderId)));
        exit;
    }
    set_flash('error', $exception->getMessage());
    header('Location: ' . base_url('member/dashboard.php'));
    exit;
}