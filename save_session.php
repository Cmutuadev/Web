<?php
session_start();
if (!isset($_SESSION['user_id'])) exit(json_encode(['success'=>false]));

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_data, created_at) VALUES (?, ?, NOW()) 
                       ON DUPLICATE KEY UPDATE session_data=VALUES(session_data), updated_at=NOW()");
$stmt->execute([$user_id, json_encode($data)]);
echo json_encode(['success'=>true]);
?>