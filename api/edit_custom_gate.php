<?php
require_once '../includes/config.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success' => false, 'error' => 'Not logged in']); exit; }
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? '';
$name = trim($data['name'] ?? '');
$endpoint = trim($data['endpoint'] ?? '');
$cost = intval($data['cost'] ?? 5);
$enabled = intval($data['enabled'] ?? 1);
if (!$id || !$name || !$endpoint) { echo json_encode(['success' => false, 'error' => 'Missing fields']); exit; }
$db = getMongoDB();
if (!$db) { echo json_encode(['success' => false, 'error' => 'DB error']); exit; }
$username = $_SESSION['user']['name'];
try {
    $db->user_gates->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($id), 'username' => $username],
        ['$set' => ['label' => $name, 'api_endpoint' => $endpoint, 'credit_cost' => $cost, 'enabled' => $enabled]]
    );
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
