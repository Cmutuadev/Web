<?php
require_once dirname(__DIR__) . '/includes/config.php';
header('Content-Type: application/json');
if (!isLoggedIn()) exit(json_encode(['error'=>'Unauthorized']));
$input = json_decode(file_get_contents('php://input'), true);
$card = $input['card'] ?? '';
$status = $input['status'] ?? '';
$reason = $input['reason'] ?? '';
$gateway = $input['gateway'] ?? 'stripe_auth';
if (empty($card)) exit(json_encode(['error'=>'No card']));
$username = $_SESSION['user']['name'];
$cardInfo = substr($card, 0, 6) . '****' . substr($card, -4);
$pdo = getDB();
if ($pdo) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($userData) {
        $stmt = $pdo->prepare("INSERT INTO credit_history (user_id, username, amount, reason, card_info, balance) VALUES (?, ?, 0, ?, ?, (SELECT credits FROM users WHERE username = ?))");
        $stmt->execute([$userData['id'], $username, "{$gateway} - {$status}: {$reason}", $cardInfo, $username]);
    }
}
echo json_encode(['success'=>true]);
?>
