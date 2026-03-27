<?php
// PayPal Sandbox Mode - Complete Working Version
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

$card = $_POST['cc'] ?? $_GET['cc'] ?? '';
$clientId = $_POST['client_id'] ?? $_GET['client_id'] ?? '';
$clientSecret = $_POST['client_secret'] ?? $_GET['client_secret'] ?? '';
$amount = $_POST['amount'] ?? $_GET['amount'] ?? '5.00';
$currency = $_POST['currency'] ?? $_GET['currency'] ?? 'USD';
$proxy = $_POST['proxy'] ?? $_GET['proxy'] ?? '';

// Test credentials
if (isset($_GET['test_creds']) && $_GET['test_creds'] == 1 && !empty($clientId) && !empty($clientSecret)) {
    $result = testPayPalCredentials($clientId, $clientSecret, $proxy);
    echo json_encode($result);
    exit;
}

if (empty($card)) {
    echo json_encode(['Response' => 'ERROR: No card provided', 'status' => 'ERROR']);
    exit;
}

if (empty($clientId) || empty($clientSecret)) {
    echo json_encode(['Response' => 'ERROR: PayPal API credentials required', 'status' => 'ERROR']);
    exit;
}

$parts = explode('|', $card);
if (count($parts) < 4) {
    echo json_encode(['Response' => 'DECLINED: Invalid card format. Use: number|month|year|cvv', 'status' => 'DECLINED']);
    exit;
}

list($cardNumber, $month, $year, $cvv) = $parts;
$month = str_pad($month, 2, '0', STR_PAD_LEFT);
$year = strlen($year) == 2 ? '20' . $year : $year;

function testPayPalCredentials($clientId, $clientSecret, $proxy = null) {
    $auth = base64_encode($clientId . ':' . $clientSecret);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.sandbox.paypal.com/v1/oauth2/token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $auth,
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if (!empty($proxy)) {
        $proxyParts = explode(':', $proxy);
        if (count($proxyParts) >= 2) {
            curl_setopt($ch, CURLOPT_PROXY, $proxyParts[0] . ':' . $proxyParts[1]);
            if (count($proxyParts) >= 4) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyParts[2] . ':' . $proxyParts[3]);
            }
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return [
            'status' => 'valid',
            'message' => 'Sandbox credentials are valid',
            'token_type' => $data['token_type'] ?? 'Bearer',
            'expires_in' => $data['expires_in'] ?? 3600
        ];
    } else {
        $errorData = json_decode($response, true);
        $errorMsg = isset($errorData['error_description']) ? $errorData['error_description'] : 'Invalid credentials';
        return ['status' => 'invalid', 'message' => $errorMsg];
    }
}

function getPayPalAccessToken($clientId, $clientSecret, $proxy = null) {
    $auth = base64_encode($clientId . ':' . $clientSecret);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.sandbox.paypal.com/v1/oauth2/token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $auth,
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if (!empty($proxy)) {
        $proxyParts = explode(':', $proxy);
        if (count($proxyParts) >= 2) {
            curl_setopt($ch, CURLOPT_PROXY, $proxyParts[0] . ':' . $proxyParts[1]);
            if (count($proxyParts) >= 4) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyParts[2] . ':' . $proxyParts[3]);
            }
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return ['success' => true, 'token' => $data['access_token']];
    } else {
        $error = json_decode($response, true);
        $errorMsg = isset($error['error_description']) ? $error['error_description'] : 'Failed to get access token';
        return ['success' => false, 'message' => $errorMsg];
    }
}

function createPayPalOrder($accessToken, $amount, $currency, $cardNumber, $month, $year, $cvv, $proxy = null) {
    $requestId = uniqid('paypal_order_', true);
    
    $cardNumber = preg_replace('/\s+/', '', $cardNumber);
    $cvv = preg_replace('/\s+/', '', $cvv);
    $formattedAmount = number_format(floatval($amount), 2, '.', '');
    
    $orderData = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'amount' => [
                    'currency_code' => strtoupper($currency),
                    'value' => $formattedAmount
                ]
            ]
        ],
        'payment_source' => [
            'card' => [
                'number' => $cardNumber,
                'expiry' => $year . '-' . $month,
                'security_code' => $cvv
            ]
        ]
    ];
    
    $jsonData = json_encode($orderData);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.sandbox.paypal.com/v2/checkout/orders");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
        'Accept: application/json',
        'PayPal-Request-Id: ' . $requestId
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if (!empty($proxy)) {
        $proxyParts = explode(':', $proxy);
        if (count($proxyParts) >= 2) {
            curl_setopt($ch, CURLOPT_PROXY, $proxyParts[0] . ':' . $proxyParts[1]);
            if (count($proxyParts) >= 4) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyParts[2] . ':' . $proxyParts[3]);
            }
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201) {
        $data = json_decode($response, true);
        if (isset($data['id'])) {
            return ['success' => true, 'order' => $data];
        }
        return ['success' => false, 'message' => 'No order ID in response'];
    } else {
        $error = json_decode($response, true);
        $errorMsg = 'Order creation failed';
        if (isset($error['message'])) {
            $errorMsg = $error['message'];
        }
        if (isset($error['details'][0]['issue'])) {
            $errorMsg = $error['details'][0]['issue'];
        }
        return ['success' => false, 'message' => $errorMsg];
    }
}

function capturePayPalOrder($accessToken, $orderId, $proxy = null) {
    $requestId = uniqid('paypal_capture_', true);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.sandbox.paypal.com/v2/checkout/orders/{$orderId}/capture");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
        'Accept: application/json',
        'PayPal-Request-Id: ' . $requestId
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if (!empty($proxy)) {
        $proxyParts = explode(':', $proxy);
        if (count($proxyParts) >= 2) {
            curl_setopt($ch, CURLOPT_PROXY, $proxyParts[0] . ':' . $proxyParts[1]);
            if (count($proxyParts) >= 4) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyParts[2] . ':' . $proxyParts[3]);
            }
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201) {
        $data = json_decode($response, true);
        if (isset($data['status']) && $data['status'] === 'COMPLETED') {
            return ['success' => true, 'capture' => $data];
        }
        return ['success' => true, 'capture' => $data];
    } else {
        $error = json_decode($response, true);
        $errorMsg = isset($error['message']) ? $error['message'] : 'Failed to capture payment';
        return ['success' => false, 'message' => $errorMsg];
    }
}

// Main processing
$tokenResult = getPayPalAccessToken($clientId, $clientSecret, $proxy);
if (!$tokenResult['success']) {
    echo json_encode([
        'Response' => 'DECLINED: ' . $tokenResult['message'],
        'CC' => $card,
        'Status' => 'DECLINED'
    ]);
    exit;
}

$orderResult = createPayPalOrder($tokenResult['token'], $amount, $currency, $cardNumber, $month, $year, $cvv, $proxy);
if (!$orderResult['success']) {
    echo json_encode([
        'Response' => 'DECLINED: ' . $orderResult['message'],
        'CC' => $card,
        'Status' => 'DECLINED'
    ]);
    exit;
}

// Try to capture the order
$captureResult = capturePayPalOrder($tokenResult['token'], $orderResult['order']['id'], $proxy);

if ($captureResult['success']) {
    echo json_encode([
        'Response' => 'APPROVED: Payment successful',
        'CC' => $card,
        'Amount' => $amount,
        'Currency' => $currency,
        'OrderId' => $orderResult['order']['id'],
        'Status' => 'APPROVED'
    ]);
} else {
    // Order created but capture failed - still counts as card is valid
    echo json_encode([
        'Response' => 'APPROVED: Card authorized (capture pending)',
        'CC' => $card,
        'Amount' => $amount,
        'Currency' => $currency,
        'OrderId' => $orderResult['order']['id'],
        'Status' => 'APPROVED'
    ]);
}
?>
