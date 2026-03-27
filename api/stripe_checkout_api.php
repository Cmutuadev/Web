<?php
// Stripe Checkout API Endpoint
// Handles all AJAX requests for stripe-checkout

require_once __DIR__ . '/../includes/config.php';

// Set JSON header first thing
header('Content-Type: application/json');

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check authentication
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$action = $_POST['action'] ?? '';
$gate = $_POST['gate'] ?? 'rylax';
$checkoutUrl = trim($_POST['checkout_url'] ?? '');
$card = trim($_POST['cc'] ?? '');
$proxy = trim($_POST['proxy'] ?? '');

if ($action === 'detect_checkout') {
    // Forward to detection gate
    $gateFile = __DIR__ . '/../gate/stripe_checkout_detect.php';
    
    if (!file_exists($gateFile)) {
        echo json_encode(['success' => false, 'error' => 'Detection gate not found']);
        exit;
    }
    
    // Include and call the detection function
    require_once $gateFile;
    // The detection gate will output JSON directly
    exit;
    
} elseif ($action === 'process_checkout') {
    if (empty($checkoutUrl) || empty($card)) {
        echo json_encode(['success' => false, 'error' => 'Missing checkout URL or card']);
        exit;
    }
    
    // Forward to appropriate processing gate
    $gateFile = $gate === 'rylax' 
        ? __DIR__ . '/../gate/stripe_checkout_rylax.php'
        : __DIR__ . '/../gate/stripe_checkout_stormx.php';
    
    if (!file_exists($gateFile)) {
        echo json_encode(['success' => false, 'error' => 'Gate file not found']);
        exit;
    }
    
    // Include and call the gate
    require_once $gateFile;
    exit;
    
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}
?>
