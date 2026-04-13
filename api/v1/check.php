<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

require_once '../../includes/config.php';

use MongoDB\BSON\UTCDateTime;

// Helper function to send JSON response
function sendResponse($success, $data = [], $error = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'error' => $error,
        'timestamp' => time()
    ]);
    exit;
}

// Get API key from headers or GET/POST
$apiKey = null;
if (isset($_SERVER['HTTP_X_API_KEY'])) {
    $apiKey = $_SERVER['HTTP_X_API_KEY'];
} elseif (isset($_GET['api_key'])) {
    $apiKey = $_GET['api_key'];
} elseif (isset($_POST['api_key'])) {
    $apiKey = $_POST['api_key'];
}

if (!$apiKey) {
    sendResponse(false, [], 'Missing API key. Provide via X-API-Key header or api_key parameter', 401);
}

// Validate API key and get user
$db = getMongoDB();
if (!$db) {
    sendResponse(false, [], 'Database connection failed', 500);
}

$user = $db->users->findOne(['api_key' => $apiKey]);
if (!$user) {
    sendResponse(false, [], 'Invalid API key', 401);
}

// Check if user is banned
if (isset($user['banned']) && $user['banned']) {
    sendResponse(false, [], 'Your account has been banned', 403);
}

// Get user's plan limits
$planLimits = [
    'basic' => ['daily' => 100, 'monthly' => 3000, 'price' => 0],
    'premium' => ['daily' => 500, 'monthly' => 15000, 'price' => 15],
    'gold' => ['daily' => 1500, 'monthly' => 45000, 'price' => 30],
    'platinum' => ['daily' => 5000, 'monthly' => 150000, 'price' => 60],
    'lifetime' => ['daily' => 999999, 'monthly' => 9999999, 'price' => 150]
];

$userPlan = $user['plan'] ?? 'basic';
$limits = $planLimits[$userPlan];

// Check daily limit
$today = date('Y-m-d');
$startOfDay = (new DateTime($today))->getTimestamp() * 1000;
$dailyUsage = $db->credit_history->countDocuments([
    'username' => $user['username'],
    'created_at' => ['$gte' => new UTCDateTime($startOfDay)]
]);

if ($dailyUsage >= $limits['daily']) {
    sendResponse(false, [], 'Daily limit reached. Upgrade your plan for more requests', 429);
}

// Check monthly limit
$monthAgo = (new DateTime('-30 days'))->getTimestamp() * 1000;
$monthlyUsage = $db->credit_history->countDocuments([
    'username' => $user['username'],
    'created_at' => ['$gte' => new UTCDateTime($monthAgo)]
]);

if ($monthlyUsage >= $limits['monthly']) {
    sendResponse(false, [], 'Monthly limit reached. Upgrade your plan for more requests', 429);
}

// Get parameters
$gateway = $_GET['gateway'] ?? $_POST['gateway'] ?? null;
$card = $_GET['cc'] ?? $_POST['cc'] ?? null;
$site = $_GET['site'] ?? $_POST['site'] ?? null;
$amount = $_GET['amount'] ?? $_POST['amount'] ?? '1.00';

if (!$gateway) {
    sendResponse(false, [], 'Missing gateway parameter', 400);
}

if (!$card) {
    sendResponse(false, [], 'Missing card parameter. Format: number|month|year|cvv', 400);
}

// Validate card format
$cardParts = explode('|', $card);
if (count($cardParts) != 4) {
    sendResponse(false, [], 'Invalid card format. Use: number|month|year|cvv', 400);
}

// Load gates
$gates = loadGates();
if (!isset($gates[$gateway])) {
    sendResponse(false, [], "Gateway '$gateway' not found", 404);
}

$gate = $gates[$gateway];
$creditCost = $gate['credit_cost'] ?? 1;

// Check if user has enough credits
$userCredits = $user['credits'] ?? 0;
if ($userCredits < $creditCost) {
    sendResponse(false, [], 'Insufficient credits', 402);
}

// Check plan requirement
$planPriority = ['basic' => 1, 'premium' => 2, 'gold' => 3, 'platinum' => 4, 'lifetime' => 5];
$requiredPlan = $gate['required_plan'] ?? 'basic';
if ($planPriority[$userPlan] < $planPriority[$requiredPlan]) {
    sendResponse(false, [], "Gateway '$gateway' requires {$requiredPlan} plan. Upgrade to access.", 403);
}

// Set session for the API user
$_SESSION['user'] = [
    'name' => $user['username'],
    'credits' => $userCredits,
    'is_admin' => false
];

// Deduct credits
$deducted = deductCredits($creditCost, "API Check via {$gateway}", substr($card, 0, 10) . '...');

if (!$deducted) {
    sendResponse(false, [], 'Failed to deduct credits', 500);
}

// Prepare API endpoint
$apiEndpoint = $gate['api_endpoint'];
$apiEndpoint = str_replace('{cc}', urlencode($card), $apiEndpoint);
if ($site) {
    $apiEndpoint = str_replace('{site}', urlencode($site), $apiEndpoint);
}
if ($amount) {
    $apiEndpoint = str_replace('{amount}', urlencode($amount), $apiEndpoint);
}

// Make request to gateway
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiEndpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Parse response
$status = 'DECLINED';
$message = $response;

$responseUpper = strtoupper($response);
if (strpos($responseUpper, 'APPROVED') !== false || strpos($responseUpper, 'CHARGED') !== false) {
    $status = 'APPROVED';
} elseif (strpos($responseUpper, 'INSUFFICIENT') !== false) {
    $status = 'APPROVED';
} elseif (strpos($responseUpper, '3DS') !== false) {
    $status = '3DS';
}

// Log to credit history with API flag
$db->credit_history->insertOne([
    'username' => $user['username'],
    'amount' => -$creditCost,
    'reason' => "API Check: {$gateway} - {$status}",
    'card_info' => substr($card, 0, 10) . '...',
    'gateway' => $gateway,
    'is_api_call' => true,
    'created_at' => new UTCDateTime()
]);

// Get updated credits
$updatedUser = $db->users->findOne(['username' => $user['username']]);
$remainingCredits = $updatedUser['credits'] ?? 0;

// Send response
sendResponse(true, [
    'gateway' => $gateway,
    'status' => $status,
    'message' => substr($message, 0, 200),
    'credits_used' => $creditCost,
    'credits_remaining' => $remainingCredits,
    'daily_usage' => $dailyUsage + 1,
    'daily_limit' => $limits['daily'],
    'monthly_usage' => $monthlyUsage + 1,
    'monthly_limit' => $limits['monthly']
]);
?>
