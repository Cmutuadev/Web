<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$amount = $data['amount'] ?? 0;
$card = $data['card'] ?? '';

if ($amount <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid amount']);
    exit;
}

$username = $_SESSION['user']['name'];
$currentCredits = getUserCredits();

if ($currentCredits < $amount) {
    echo json_encode(['success' => false, 'error' => 'Insufficient credits']);
    exit;
}

$db = getMongoDB();
if (!$db) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$result = $db->users->updateOne(
    ['username' => $username],
    ['$inc' => ['credits' => -$amount]]
);

if ($result->getModifiedCount() > 0) {
    $user = $db->users->findOne(['username' => $username]);
    $newCredits = $user['credits'];
    $_SESSION['user']['credits'] = $newCredits;
    
    // Log to credit history
    $db->credit_history->insertOne([
        'username' => $username,
        'amount' => -$amount,
        'reason' => 'Card check - ' . substr($card, 0, 10) . '...',
        'card_info' => $card,
        'balance' => $newCredits,
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ]);
    
    echo json_encode([
        'success' => true,
        'new_credits' => $newCredits,
        'new_credits_formatted' => number_format($newCredits)
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update credits']);
}
?>
