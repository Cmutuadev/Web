<?php
require_once '../includes/config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not logged in', 'success' => false]);
    exit;
}

$credits = getUserCredits();
$isAdmin = isAdmin();

echo json_encode([
    'success' => true,
    'credits' => $credits,
    'credits_formatted' => $isAdmin ? '∞' : number_format($credits),
    'is_admin' => $isAdmin
]);
?>
