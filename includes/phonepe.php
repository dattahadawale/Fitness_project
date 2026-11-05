<?php

function phonepe_config(): array
{
    $environment = strtolower((string)(getenv('PHONEPE_ENV') ?: 'sandbox'));
    if (!in_array($environment, ['sandbox', 'production'], true)) {
        throw new RuntimeException('PHONEPE_ENV must be sandbox or production.');
    }

    $sandbox = $environment === 'sandbox';
    $baseUrl = rtrim((string)(getenv('PHONEPE_API_BASE_URL') ?: (
        $sandbox
            ? 'https://api-preprod.phonepe.com/apis/pg-sandbox'
            : 'https://api.phonepe.com/apis/pg'
    )), '/');
    $authUrl = (string)(getenv('PHONEPE_AUTH_URL') ?: (
        $sandbox
            ? 'https://api-preprod.phonepe.com/apis/pg-sandbox/v1/oauth/token'
            : 'https://api.phonepe.com/apis/identity-manager/v1/oauth/token'
    ));

    foreach ([$baseUrl, $authUrl] as $url) {
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('PhonePe API endpoints must use valid HTTPS URLs.');
        }
    }

    $clientId = getenv('PHONEPE_CLIENT_ID');
    $clientSecret = getenv('PHONEPE_CLIENT_SECRET');
    $clientVersion = getenv('PHONEPE_CLIENT_VERSION');
    if (!$clientId || !$clientSecret || !$clientVersion) {
        throw new RuntimeException('PhonePe client credentials are not configured.');
    }

    return [
        'base_url' => $baseUrl,
        'auth_url' => $authUrl,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'client_version' => $clientVersion,
    ];
}

function phonepe_http_request(string $url, string $method, array $headers, ?string $body = null): array
{
    $handle = curl_init($url);
    if ($handle === false) {
        throw new RuntimeException('Unable to initialize the PhonePe API request.');
    }

    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($body !== null) {
        curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
    }

    $responseBody = curl_exec($handle);
    $statusCode = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);

    if ($responseBody === false) {
        throw new RuntimeException('PhonePe API connection failed: ' . $curlError);
    }
    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        throw new RuntimeException("PhonePe returned a non-JSON response (HTTP {$statusCode}).");
    }
    if ($statusCode < 200 || $statusCode >= 300) {
        $code = isset($decoded['code']) ? (string)$decoded['code'] : 'unknown';
        throw new RuntimeException("PhonePe API request failed with HTTP {$statusCode} ({$code}).");
    }

    return $decoded;
}

function phonepe_access_token(): string
{
    $config = phonepe_config();
    $form = http_build_query([
        'client_id' => $config['client_id'],
        'client_version' => $config['client_version'],
        'client_secret' => $config['client_secret'],
        'grant_type' => 'client_credentials',
    ]);
    $response = phonepe_http_request(
        $config['auth_url'],
        'POST',
        ['Content-Type: application/x-www-form-urlencoded'],
        $form
    );

    if (empty($response['access_token']) || !is_string($response['access_token'])) {
        throw new RuntimeException('PhonePe did not return an access token.');
    }
    return $response['access_token'];
}

function phonepe_create_order(string $merchantOrderId, int $amountPaise, string $redirectUrl): array
{
    $config = phonepe_config();
    $token = phonepe_access_token();
    $response = phonepe_http_request(
        $config['base_url'] . '/checkout/v2/pay',
        'POST',
        ['Content-Type: application/json', 'Authorization: O-Bearer ' . $token],
        json_encode([
            'merchantOrderId' => $merchantOrderId,
            'amount' => $amountPaise,
            'expireAfter' => 1200,
            'paymentFlow' => [
                'type' => 'PG_CHECKOUT',
                'message' => 'Apex Fitness membership',
                'merchantUrls' => ['redirectUrl' => $redirectUrl],
            ],
        ], JSON_THROW_ON_ERROR)
    );

    $redirectHost = strtolower((string)parse_url((string)($response['redirectUrl'] ?? ''), PHP_URL_HOST));
    if (!isset($response['redirectUrl']) || !is_string($response['redirectUrl'])
        || !filter_var($response['redirectUrl'], FILTER_VALIDATE_URL)
        || parse_url($response['redirectUrl'], PHP_URL_SCHEME) !== 'https'
        || !preg_match('/(^|\.)phonepe\.com$/', $redirectHost)) {
        throw new RuntimeException('PhonePe returned an invalid checkout URL.');
    }
    return $response;
}

function phonepe_order_status(string $merchantOrderId): array
{
    $config = phonepe_config();
    $token = phonepe_access_token();
    return phonepe_http_request(
        $config['base_url'] . '/checkout/v2/order/' . rawurlencode($merchantOrderId) . '/status',
        'GET',
        ['Authorization: O-Bearer ' . $token]
    );
}

function phonepe_validate_webhook_authorization(): bool
{
    $username = getenv('PHONEPE_WEBHOOK_USERNAME');
    $password = getenv('PHONEPE_WEBHOOK_PASSWORD');
    if (!$username || !$password) {
        return false;
    }

    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($authorization === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $authorization = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    return hash_equals('Basic ' . base64_encode($username . ':' . $password), $authorization);
}

function phonepe_complete_order(PDO $db, string $merchantOrderId, array $providerStatus): string
{
    if (isset($providerStatus['merchantOrderId']) && $providerStatus['merchantOrderId'] !== $merchantOrderId) {
        throw new RuntimeException('PhonePe status response does not match this merchant order.');
    }

    $providerState = strtoupper((string)($providerStatus['state'] ?? ''));
    $stateMap = [
        'COMPLETED' => 'completed',
        'FAILED' => 'failed',
        'EXPIRED' => 'expired',
        'PENDING' => 'pending',
    ];
    if (!isset($stateMap[$providerState])) {
        throw new RuntimeException('PhonePe returned an unrecognized payment state.');
    }

    $db->beginTransaction();
    try {
        $orderStmt = $db->prepare('SELECT * FROM phonepe_orders WHERE merchant_order_id = :order_id FOR UPDATE');
        $orderStmt->execute([':order_id' => $merchantOrderId]);
        $order = $orderStmt->fetch();
        if (!$order) {
            throw new RuntimeException('PhonePe order was not found.');
        }
        if ($order['status'] === 'completed') {
            $db->commit();
            return 'completed';
        }
        if ((int)($providerStatus['amount'] ?? -1) !== (int)$order['amount_paise']) {
            throw new RuntimeException('PhonePe payment amount does not match the membership plan.');
        }

        $nextStatus = $stateMap[$providerState];
        $providerOrderId = isset($providerStatus['orderId']) ? (string)$providerStatus['orderId'] : null;
        $providerTransactionId = null;
        if (!empty($providerStatus['paymentDetails']) && is_array($providerStatus['paymentDetails'])) {
            foreach ($providerStatus['paymentDetails'] as $paymentDetail) {
                if (is_array($paymentDetail) && !empty($paymentDetail['transactionId'])
                    && is_string($paymentDetail['transactionId'])) {
                    $providerTransactionId = $paymentDetail['transactionId'];
                    break;
                }
            }
        }
        $updateOrder = $db->prepare('
            UPDATE phonepe_orders
            SET status = :status, provider_state = :provider_state,
                phonepe_order_id = COALESCE(:provider_order_id, phonepe_order_id),
                provider_transaction_id = COALESCE(:provider_transaction_id, provider_transaction_id),
                completed_at = CASE WHEN :is_completed = 1 THEN COALESCE(completed_at, CURRENT_TIMESTAMP) ELSE completed_at END
            WHERE id = :id
        ');
        $updateOrder->execute([
            ':status' => $nextStatus,
            ':provider_state' => $providerState,
            ':provider_order_id' => $providerOrderId,
            ':provider_transaction_id' => $providerTransactionId,
            ':is_completed' => $nextStatus === 'completed' ? 1 : 0,
            ':id' => $order['id'],
        ]);

        if ($nextStatus === 'completed') {
            $paymentStmt = $db->prepare('SELECT id FROM payments WHERE id = :id FOR UPDATE');
            $paymentStmt->execute([':id' => $order['payment_id']]);
            if (!$paymentStmt->fetch()) {
                throw new RuntimeException('Payment record for this PhonePe order is missing.');
            }

            $planStmt = $db->prepare('SELECT duration_days FROM membership_plans WHERE id = :id');
            $planStmt->execute([':id' => $order['plan_id']]);
            $durationDays = $planStmt->fetchColumn();
            if ($durationDays === false) {
                throw new RuntimeException('Membership plan for this PhonePe order is missing.');
            }

            $expiryStmt = $db->prepare("
                SELECT MAX(expiry_date)
                FROM memberships
                WHERE member_id = :member_id AND status = 'active' AND expiry_date >= CURRENT_DATE
            ");
            $expiryStmt->execute([':member_id' => $order['member_id']]);
            $existingExpiry = $expiryStmt->fetchColumn();
            $startDate = $existingExpiry
                ? date('Y-m-d', strtotime($existingExpiry . ' +1 day'))
                : date('Y-m-d');
            $expiryDate = date('Y-m-d', strtotime($startDate . ' +' . (int)$durationDays . ' days'));

            $membershipStmt = $db->prepare("
                INSERT INTO memberships (member_id, plan_id, start_date, expiry_date, total_amount, paid_amount, status)
                SELECT :member_id, :plan_id, :start_date, :expiry_date, amount, amount, 'active'
                FROM payments WHERE id = :payment_id
            ");
            $membershipStmt->execute([
                ':member_id' => $order['member_id'],
                ':plan_id' => $order['plan_id'],
                ':start_date' => $startDate,
                ':expiry_date' => $expiryDate,
                ':payment_id' => $order['payment_id'],
            ]);
            $membershipId = (int)$db->lastInsertId();

            $paymentUpdate = $db->prepare("
                UPDATE payments
                SET membership_id = :membership_id, status = 'completed', payment_date = CURRENT_DATE,
                    notes = 'PhonePe payment confirmed by server-side status verification'
                WHERE id = :payment_id AND status = 'pending'
            ");
            $paymentUpdate->execute([
                ':membership_id' => $membershipId,
                ':payment_id' => $order['payment_id'],
            ]);
            if ($paymentUpdate->rowCount() !== 1) {
                throw new RuntimeException('Payment was already processed or is no longer pending.');
            }
            $db->prepare("UPDATE members SET status = 'active' WHERE id = :id")
                ->execute([':id' => $order['member_id']]);
        } elseif ($nextStatus === 'failed' || $nextStatus === 'expired') {
            $db->prepare('UPDATE payments SET status = :status WHERE id = :payment_id AND status = "pending"')
                ->execute([':status' => $nextStatus === 'expired' ? 'failed' : $nextStatus, ':payment_id' => $order['payment_id']]);
        }

        $db->commit();
        return $nextStatus;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}
