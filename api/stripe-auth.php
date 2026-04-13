<?php
/**
 * Stripe Auth API Endpoint
 * Wrapper for the web-based Stripe Auth checker
 * 
 * Usage: 
 *   GET /api/stripe-auth.php?cc=5509890034877216|06|2028|333&api_key=YOUR_API_KEY
 *   POST with same parameters
 * 
 * Response: JSON with status, message, and details
 */

// Start session for cookies
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';

// Allow CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get parameters
$card = $_GET['cc'] ?? $_POST['cc'] ?? '';
$apiKey = $_GET['api_key'] ?? $_POST['api_key'] ?? '';

// Check API key
if (empty($apiKey)) {
    echo json_encode([
        'success' => false,
        'status' => 'ERROR',
        'message' => 'API key required',
        'code' => 'MISSING_API_KEY'
    ]);
    exit;
}

// Validate API key and get user
$db = getMongoDB();
if (!$db) {
    echo json_encode([
        'success' => false,
        'status' => 'ERROR',
        'message' => 'Database connection failed'
    ]);
    exit;
}

$user = $db->users->findOne(['api_key' => $apiKey]);

if (!$user) {
    echo json_encode([
        'success' => false,
        'status' => 'ERROR',
        'message' => 'Invalid API key',
        'code' => 'INVALID_API_KEY'
    ]);
    exit;
}

if ($user['banned']) {
    echo json_encode([
        'success' => false,
        'status' => 'ERROR',
        'message' => 'Account is banned',
        'code' => 'ACCOUNT_BANNED'
    ]);
    exit;
}

// Set user session for credit deduction
if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = [
        'name' => $user['username'],
        'credits' => $user['credits'],
        'is_admin' => (bool)$user['is_admin'],
        'plan' => $user['plan'] ?? 'basic',
        'user_id' => (string)$user['_id']
    ];
}

// Check if card is provided
if (empty($card)) {
    echo json_encode([
        'success' => false,
        'status' => 'ERROR',
        'message' => 'Card details required',
        'code' => 'MISSING_CARD'
    ]);
    exit;
}

// Parse card
$parts = explode('|', $card);
if (count($parts) < 4) {
    echo json_encode([
        'success' => false,
        'status' => 'ERROR',
        'message' => 'Invalid card format. Use: number|month|year|cvv',
        'code' => 'INVALID_CARD_FORMAT'
    ]);
    exit;
}

// Get gateway cost
$gates = loadGates();
$gateCost = $gates['stripe_auth']['credit_cost'] ?? 2;

// Check if user has enough credits
if ($user['credits'] < $gateCost) {
    echo json_encode([
        'success' => false,
        'status' => 'ERROR',
        'message' => 'Insufficient credits. Need ' . $gateCost . ' credits',
        'code' => 'INSUFFICIENT_CREDITS',
        'credits_available' => $user['credits'],
        'credits_needed' => $gateCost
    ]);
    exit;
}

// Process the card through the gate
ob_start();

// Set up the environment for the gate file
$_POST = ['cc' => $card];
$_GET = ['cc' => $card];

// Include the gate file
$gateFile = __DIR__ . '/../gate/stripe_auth.php';
if (!file_exists($gateFile)) {
    echo json_encode([
        'success' => false,
        'status' => 'ERROR',
        'message' => 'Gateway file not found'
    ]);
    exit;
}

include $gateFile;
$output = ob_get_clean();

// Deduct credits after check
deductCredits($gateCost, 'Stripe Auth check', substr($card, 0, 6) . '****');

// Parse the output
$outputUpper = strtoupper($output);
$status = 'DECLINED';
$message = $output;

if (strpos($outputUpper, 'APPROVED') !== false || 
    strpos($outputUpper, 'CHARGED') !== false ||
    strpos($outputUpper, 'SUCCESS') !== false ||
    strpos($outputUpper, 'LIVE') !== false) {
    $status = 'APPROVED';
} elseif (strpos($outputUpper, '3DS') !== false) {
    $status = '3DS';
}

// Extract decline reason if not approved
if ($status !== 'APPROVED' && $status !== '3DS') {
    // Try to get the actual decline message
    if (preg_match('/DECLINED[:\s]+(.+)/i', $output, $match)) {
        $message = trim($match[1]);
    } elseif (preg_match('/ERROR[:\s]+(.+)/i', $output, $match)) {
        $message = trim($match[1]);
    } else {
        $message = trim($output);
    }
    // Limit message length
    $message = substr($message, 0, 200);
}

// Record in credit history
$isApproved = ($status === 'APPROVED' || $status === 'CHARGED') ? 1 : 0;
$db->credit_history->insertOne([
    'username' => $user['username'],
    'user_id' => (string)$user['_id'],
    'amount' => -$gateCost,
    'reason' => 'Stripe Auth check - ' . $status,
    'card_info' => substr($card, 0, 6) . '****' . substr($card, -4),
    'balance' => $user['credits'] - $gateCost,
    'is_approved' => $isApproved,
    'created_at' => new MongoDB\BSON\UTCDateTime()
]);

// Update session credits
$_SESSION['user']['credits'] = $user['credits'] - $gateCost;

// Return JSON response
$response = [
    'success' => ($status === 'APPROVED'),
    'status' => $status,
    'message' => $message,
    'card_bin' => substr($card, 0, 6),
    'card_last4' => substr($card, -4),
    'credits_used' => $gateCost,
    'credits_remaining' => $user['credits'] - $gateCost,
    'timestamp' => date('Y-m-d H:i:s')
];

// If there's a transaction ID or other data, try to extract it
if (preg_match('/txn_id[:\s]+([a-zA-Z0-9_]+)/i', $output, $match)) {
    $response['transaction_id'] = $match[1];
}
if (preg_match('/charge[:\s]+([a-zA-Z0-9_]+)/i', $output, $match)) {
    $response['charge_id'] = $match[1];
}

echo json_encode($response);
?>
