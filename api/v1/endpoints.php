<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../includes/config.php';

// Get API key
$apiKey = $_GET['api_key'] ?? $_POST['api_key'] ?? null;

$db = getMongoDB();
$user = null;
$isAuthenticated = false;

if ($apiKey && $db) {
    $user = $db->users->findOne(['api_key' => $apiKey]);
    if ($user && !$user['banned']) {
        $isAuthenticated = true;
    }
}

$gates = loadGates();
$endpoints = [];

foreach ($gates as $key => $gate) {
    if ($gate['enabled']) {
        $endpoint = [
            'gateway' => $key,
            'name' => $gate['label'],
            'cost' => $gate['credit_cost'],
            'required_plan' => $gate['required_plan'] ?? 'basic',
            'method' => 'GET',
            'url' => "/api/v1/check.php?gateway={$key}&cc={card}&api_key={YOUR_KEY}"
        ];
        
        if (strpos($gate['api_endpoint'], '{site}') !== false) {
            $endpoint['params'][] = 'site';
        }
        if (strpos($gate['api_endpoint'], '{amount}') !== false) {
            $endpoint['params'][] = 'amount';
        }
        
        $endpoints[] = $endpoint;
    }
}

echo json_encode([
    'success' => true,
    'authenticated' => $isAuthenticated,
    'user' => $isAuthenticated ? [
        'username' => $user['username'],
        'plan' => $user['plan'] ?? 'basic',
        'credits' => $user['credits'] ?? 0
    ] : null,
    'total_endpoints' => count($endpoints),
    'endpoints' => $endpoints,
    'docs_url' => 'https://approvedchkr.store/api/docs'
], JSON_PRETTY_PRINT);
?>
