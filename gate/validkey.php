<?php
// Set content type to JSON
header('Content-Type: application/json');

// Prevent direct execution
if (basename($_SERVER['SCRIPT_FILENAME']) === 'validkey.php') {
    http_response_code(403);
    echo json_encode(['status' => 'rejected', 'response' => 'Oh look, another genius trying to bypass security. Cute.😂']);
    exit;
}

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Hardcoded API key
$expectedApiKey = 'kyarelwdeaagyafirseidhrgaandmarwaneqV9nP3kR8xM1cF2jG6tY5uZ0oL4iW7A9fK3pQz7XvB1mNcD8rJ5wL2yT6sU0aEA4bH7qV9nP3kR8xM1cF2jG6tY5uZ0oL4iW7';

// Validate X-API-KEY header
function validateApiKey() {
    global $expectedApiKey;
    $headers = getallheaders();
    
    // Log headers for debugging
    error_log("Headers for " . basename($_SERVER['SCRIPT_FILENAME']) . ": " . json_encode($headers));
    
    // Check for X-API-KEY case-insensitively
    $apiKey = null;
    foreach ($headers as $key => $value) {
        if (strcasecmp($key, 'X-API-KEY') === 0) {
            $apiKey = $value;
            break;
        }
    }

    if ($apiKey !== $expectedApiKey) {
        error_log("Unauthorized access attempt to " . basename($_SERVER['SCRIPT_FILENAME']) . ". Provided API key: " . ($apiKey ?? 'none'));
        http_response_code(401);
        echo json_encode(['status' => 'rejected', 'response' => 'Oh look, another genius trying to bypass security. Cute.😂']);
        exit;
    }
}
?>
