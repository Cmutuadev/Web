<?php
// Stripe Invoice Checker - Stripe.js Tokenization Method
header('Content-Type: application/json');

$card = $_POST['cc'] ?? $_GET['cc'] ?? '';
$sk = $_POST['sk'] ?? $_GET['sk'] ?? '';
$amount = $_POST['amount'] ?? $_GET['amount'] ?? '8.7';
$currency = $_POST['currency'] ?? $_GET['currency'] ?? 'usd';
$description = $_POST['description'] ?? $_GET['description'] ?? 'Test Purchase';
$proxy = $_POST['proxy'] ?? $_GET['proxy'] ?? '';

// Test key functionality
if (isset($_GET['test_key']) && $_GET['test_key'] == 1 && !empty($sk)) {
    $result = testStripeKey($sk, $proxy);
    echo json_encode($result);
    exit;
}

if (empty($card)) {
    echo json_encode(['Response' => 'ERROR: No card provided', 'status' => 'ERROR']);
    exit;
}

if (empty($sk)) {
    echo json_encode(['Response' => 'ERROR: Stripe secret key required', 'status' => 'ERROR']);
    exit;
}

$parts = explode('|', $card);
if (count($parts) < 4) {
    echo json_encode(['Response' => 'DECLINED: Invalid card format', 'status' => 'DECLINED']);
    exit;
}

list($cardNumber, $month, $year, $cvv) = $parts;
$month = str_pad($month, 2, '0', STR_PAD_LEFT);
$year = strlen($year) == 2 ? '20' . $year : $year;

function testStripeKey($sk, $proxy = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/account");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $sk . ":");
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
        $account = json_decode($response, true);
        
        // Get balance separately
        $balanceCh = curl_init();
        curl_setopt($balanceCh, CURLOPT_URL, "https://api.stripe.com/v1/balance");
        curl_setopt($balanceCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($balanceCh, CURLOPT_USERPWD, $sk . ":");
        curl_setopt($balanceCh, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($balanceCh, CURLOPT_TIMEOUT, 30);
        
        if (!empty($proxy)) {
            $proxyParts = explode(':', $proxy);
            if (count($proxyParts) >= 2) {
                curl_setopt($balanceCh, CURLOPT_PROXY, $proxyParts[0] . ':' . $proxyParts[1]);
                if (count($proxyParts) >= 4) {
                    curl_setopt($balanceCh, CURLOPT_PROXYUSERPWD, $proxyParts[2] . ':' . $proxyParts[3]);
                }
            }
        }
        
        $balanceResponse = curl_exec($balanceCh);
        $balanceHttpCode = curl_getinfo($balanceCh, CURLINFO_HTTP_CODE);
        curl_close($balanceCh);
        
        $balance = ['available' => 'N/A', 'pending' => 'N/A'];
        if ($balanceHttpCode === 200) {
            $balanceData = json_decode($balanceResponse, true);
            if (isset($balanceData['available'][0]['amount'])) {
                $balance['available'] = number_format($balanceData['available'][0]['amount'] / 100, 2);
            }
            if (isset($balanceData['pending'][0]['amount'])) {
                $balance['pending'] = number_format($balanceData['pending'][0]['amount'] / 100, 2);
            }
        }
        
        return [
            'status' => 'valid',
            'message' => 'Key is valid',
            'account' => [
                'name' => $account['business_name'] ?? $account['display_name'] ?? $account['email'] ?? 'N/A',
                'email' => $account['email'] ?? 'N/A',
                'country' => $account['country'] ?? 'N/A',
                'currency' => strtoupper($account['default_currency'] ?? 'USD'),
                'charges_enabled' => $account['charges_enabled'] ?? false,
                'livemode' => $account['livemode'] ?? true
            ],
            'balance' => $balance
        ];
    } else {
        $error = json_decode($response, true);
        $errorMsg = isset($error['error']['message']) ? $error['error']['message'] : 'Invalid Stripe key';
        return [
            'status' => 'invalid',
            'message' => $errorMsg,
            'code' => $httpCode
        ];
    }
}

function getBrowserHeaders() {
    $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ];
    return [
        'User-Agent: ' . $userAgents[array_rand($userAgents)],
        'Accept: application/json, text/plain, */*',
        'Accept-Language: en-US,en;q=0.9',
        'Content-Type: application/x-www-form-urlencoded',
        'Origin: https://js.stripe.com',
        'Referer: https://js.stripe.com/'
    ];
}

function getPublishableKey($sk) {
    // For live keys
    if (strpos($sk, 'sk_live_') === 0) {
        return 'pk_live_' . substr($sk, 7);
    }
    // For test keys
    if (strpos($sk, 'sk_test_') === 0) {
        return 'pk_test_' . substr($sk, 7);
    }
    return null;
}

function createStripeToken($cardNumber, $month, $year, $cvv, $pk, $proxy = null) {
    $yearShort = substr($year, -2);
    
    $stripeData = [
        'type' => 'card',
        'card[number]' => $cardNumber,
        'card[cvc]' => $cvv,
        'card[exp_year]' => $yearShort,
        'card[exp_month]' => $month,
        'billing_details[address][postal_code]' => rand(10000, 99999),
        'billing_details[address][country]' => 'US',
        'key' => $pk,
        '_stripe_version' => '2024-06-20'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/payment_methods");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($stripeData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, getBrowserHeaders());
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
        return ['success' => true, 'id' => $data['id']];
    } else {
        $error = json_decode($response, true);
        $errorMsg = isset($error['error']['message']) ? $error['error']['message'] : 'Failed to create payment method';
        return ['success' => false, 'message' => $errorMsg];
    }
}

function createPaymentIntent($paymentMethodId, $amount, $currency, $description, $sk, $proxy = null) {
    $intentData = [
        'amount' => round($amount * 100),
        'currency' => $currency,
        'description' => $description,
        'payment_method' => $paymentMethodId,
        'confirm' => 'true',
        'capture_method' => 'manual',
        'automatic_payment_methods[enabled]' => 'false'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/payment_intents");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($intentData));
    curl_setopt($ch, CURLOPT_USERPWD, $sk . ":");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
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
        if ($data['status'] === 'succeeded') {
            return ['success' => true, 'id' => $data['id'], 'message' => 'Payment successful'];
        } elseif ($data['status'] === 'requires_action') {
            return ['success' => false, 'message' => '3DS Authentication Required', 'status' => '3DS'];
        } elseif ($data['status'] === 'requires_payment_method') {
            return ['success' => false, 'message' => 'Invalid payment method'];
        } else {
            return ['success' => false, 'message' => 'Payment failed: ' . $data['status']];
        }
    } else {
        $error = json_decode($response, true);
        $errorMsg = isset($error['error']['message']) ? $error['error']['message'] : 'Payment intent failed';
        return ['success' => false, 'message' => $errorMsg];
    }
}

// Get publishable key from secret key
$pk = getPublishableKey($sk);
if (!$pk) {
    echo json_encode([
        'Response' => 'ERROR: Invalid Stripe key format. Use sk_live_xxx or sk_test_xxx',
        'status' => 'ERROR'
    ]);
    exit;
}

// Step 1: Create payment method using Stripe.js API
$tokenResult = createStripeToken($cardNumber, $month, $year, $cvv, $pk, $proxy);

if (!$tokenResult['success']) {
    echo json_encode([
        'Response' => 'DECLINED: ' . $tokenResult['message'],
        'CC' => $card,
        'Amount' => $amount,
        'Status' => 'DECLINED'
    ]);
    exit;
}

$paymentMethodId = $tokenResult['id'];

// Step 2: Create and confirm payment intent
$intentResult = createPaymentIntent($paymentMethodId, $amount, $currency, $description, $sk, $proxy);

if ($intentResult['success']) {
    echo json_encode([
        'Response' => 'APPROVED: ' . $intentResult['message'],
        'CC' => $card,
        'Amount' => $amount,
        'Currency' => strtoupper($currency),
        'PaymentMethod' => $paymentMethodId,
        'PaymentIntent' => $intentResult['id'] ?? '',
        'Status' => 'APPROVED'
    ]);
} elseif (isset($intentResult['status']) && $intentResult['status'] === '3DS') {
    echo json_encode([
        'Response' => '3DS: ' . $intentResult['message'],
        'CC' => $card,
        'Amount' => $amount,
        'Status' => '3DS'
    ]);
} else {
    echo json_encode([
        'Response' => 'DECLINED: ' . $intentResult['message'],
        'CC' => $card,
        'Amount' => $amount,
        'Status' => 'DECLINED'
    ]);
}
?>
