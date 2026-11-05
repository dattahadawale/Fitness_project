<?php

require_once __DIR__ . '/../includes/member_auth.php';
require_once __DIR__ . '/../includes/phonepe.php';

$merchantOrderId = $_GET['order'] ?? '';
if (!is_string($merchantOrderId) || !preg_match('/^APX-[A-Z0-9-]{10,60}$/', $merchantOrderId)) {
    http_response_code(400);
    exit('Invalid payment reference.');
}

$db = getDB();
$stmt = $db->prepare('
    SELECT o.*, mp.plan_name, p.amount AS payment_amount
    FROM phonepe_orders o
    JOIN payments p ON p.id = o.payment_id
    JOIN membership_plans mp ON mp.id = o.plan_id
    JOIN members m ON m.id = o.member_id
    WHERE o.merchant_order_id = :order_id AND m.user_id = :user_id
    LIMIT 1
');
$stmt->execute([':order_id' => $merchantOrderId, ':user_id' => (int)$_SESSION['user']['id']]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    exit('Payment transaction not found.');
}

$verificationMessage = '';
if (($_GET['refresh'] ?? '') === '1' && $order['status'] !== 'completed') {
    try {
        $providerStatus = phonepe_order_status($merchantOrderId);
        $newStatus = phonepe_complete_order($db, $merchantOrderId, $providerStatus);
        $order['status'] = $newStatus;
    } catch (Throwable $exception) {
        error_log('PhonePe return status verification failed: ' . $exception->getMessage());
        $verificationMessage = 'PhonePe could not confirm the status just now. Your payment remains pending until the server can verify it.';
    }
}

$pageTitle = 'Payment Status';
$bodyClass = 'auth-page';
include __DIR__ . '/../includes/header.php';
$isComplete = $order['status'] === 'completed';
$isFailed = in_array($order['status'], ['failed', 'expired'], true);
?>

<div class="auth-card">
    <div class="auth-header">
        <a href="<?= base_url('member/dashboard.php') ?>" class="auth-brand">
            <div class="brand-icon">⚡</div>
            <div class="brand-text">APEX<span>FITNESS</span></div>
        </a>
        <h2 class="auth-title"><?= $isComplete ? 'Payment Successful' : ($isFailed ? 'Payment Not Completed' : 'Payment Pending') ?></h2>
        <p class="auth-subtitle">
            <?= $isComplete
                ? 'PhonePe confirmed your payment and your membership is active.'
                : ($isFailed
                    ? 'PhonePe did not confirm this payment. No membership was activated.'
                    : 'We are waiting for PhonePe to confirm this transaction. Your membership will activate only after server-side confirmation.') ?>
        </p>
    </div>

    <?php if ($verificationMessage !== ''): ?>
        <div class="alert-toast alert-warning" style="position: static; margin-bottom: 20px; animation: none;">
            <span class="alert-icon">&#9888;</span><span class="alert-text"><?= e($verificationMessage) ?></span>
        </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom: 20px;">
        <div class="card-body" style="display: grid; gap: 12px;">
            <div><strong>Plan:</strong> <?= e($order['plan_name']) ?></div>
            <div><strong>Amount:</strong> <?= format_currency($order['payment_amount']) ?></div>
            <div><strong>Transaction:</strong> <code><?= e($order['merchant_order_id']) ?></code></div>
            <div><strong>Status:</strong> <?= e(ucfirst($order['status'])) ?></div>
        </div>
    </div>

    <?php if (!$isComplete && !$isFailed): ?>
        <a class="btn btn-primary" style="width: 100%; margin-bottom: 12px;"
           href="<?= base_url('payments/result.php?order=' . rawurlencode($merchantOrderId) . '&refresh=1') ?>">
            Check payment status
        </a>
    <?php endif; ?>
    <a class="btn btn-secondary" style="width: 100%;" href="<?= base_url('member/payments.php') ?>">View transaction history</a>
    <div class="auth-footer"><a href="<?= base_url('member/dashboard.php') ?>">Return to your dashboard</a></div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
