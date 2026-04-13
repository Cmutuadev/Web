<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$name = trim($data['name'] ?? '');
$endpoint = trim($data['endpoint'] ?? '');
$cost = isset($data['cost']) ? max(5, intval($data['cost'])) : 5; // Enforce minimum 5

if (!$name || !$endpoint) {
    echo json_encode(['success' => false, 'error' => 'Missing fields']);
    exit;
}

$db = getMongoDB();
if (!$db) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

$username = $_SESSION['user']['name'];

try {
    $db->user_gates->insertOne([
        'username' => $username,
        'label' => $name,
        'api_endpoint' => $endpoint,
        'credit_cost' => $cost,
        'enabled' => 1,
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
