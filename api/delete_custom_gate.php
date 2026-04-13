<?php
require_once '../includes/config.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success' => false, 'error' => 'Not logged in']); exit; }
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? '';
if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing id']); exit; }
$db = getMongoDB();
if (!$db) { echo json_encode(['success' => false, 'error' => 'DB error']); exit; }
$username = $_SESSION['user']['name'];
try {
    $db->user_gates->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id), 'username' => $username]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
