<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

session_start();
if (!isLoggedIn()) {
    echo json_encode(['success' => false]);
    exit;
}

$db = getMongoDB();
if ($db) {
    $user = $db->users->findOne(['username' => $_SESSION['user']['name']]);
    if ($user && !empty($user['photo_url'])) {
        echo json_encode(['success' => true, 'photo_url' => $user['photo_url']]);
        exit;
    }
}

echo json_encode(['success' => false]);
?>
