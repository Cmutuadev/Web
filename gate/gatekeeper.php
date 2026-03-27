<?php
// gatekeeper.php - Centralized API Key Validation for /gate/* endpoints

// Disable error reporting for production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Define the expected API key
define('EXPECTED_API_KEY', 'a3lhIHJlIGxhd2RlIHlhaGkga2FhYXQgaGFpIGt5YSB0ZXJpIGtpIGR1c3JvIGthIGFwaSB1c2Uga3JuYSAxIGJhYXAga2EgaGFpIHRvIGtodWRrYSBibmEgaWRociBtdCB1c2Uga3Lwn5iC');

// Set CORS headers to allow X-API-KEY
header('Access-Control-Allow-Origin: *'); // Adjust to specific origin for production
header('Access-Control-Allow-Headers: X-API-KEY, Content-Type, Accept');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get headers
$headers = getallheaders();
$api_key = isset($headers['X-API-KEY']) ? $headers['X-API-KEY'] : (isset($headers['x-api-key']) ? $headers['x-api-key'] : null);

// Debug: Log headers and request details
file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " - Request: {$_SERVER['REQUEST_URI']}\nHeaders: " . print_r($headers, true) . "\nPOST: " . print_r($_POST, true) . "\nGET: " . print_r($_GET, true) . "\n", FILE_APPEND);

// Validate API key
if (!$api_key || $api_key !== EXPECTED_API_KEY) {
    http_response_code(401);
    echo json_encode(['status' => 'DECLINED', 'message' => 'Invalid or missing API key']);
    exit;
}

// Get the requested API script
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = basename($request_uri);

// Validate the requested script exists in /gate
$script_path = __DIR__ . '/' . $script_name;
if (!file_exists($script_path) || !preg_match('/\.php$/', $script_name)) {
    http_response_code(404);
    echo json_encode(['status' => 'ERROR', 'message' => 'API endpoint not found']);
    exit;
}

// Forward the request to the target script
chdir(__DIR__); // Ensure relative paths work
ob_start();
include $script_path;
$output = ob_get_clean();

// Ensure JSON output
echo $output;
?>
