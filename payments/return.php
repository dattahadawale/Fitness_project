<?php

require_once __DIR__ . '/../includes/member_auth.php';

$merchantOrderId = $_GET['merchantOrderId'] ?? '';
if (!is_string($merchantOrderId) || $merchantOrderId === '') {
    set_flash('error', 'The returned payment reference is invalid.');
    header('Location: ' . base_url('member/payments.php'));
    exit;
}

header('Location: ' . base_url('payments/result.php?order=' . rawurlencode($merchantOrderId) . '&refresh=1'));
exit;
