<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$card = $data['card'] ?? '';
$gateway = $data['gateway'] ?? '';
$status = $data['status'] ?? '';
$message = $data['message'] ?? '';
$user = $data['user'] ?? '';

$settings = loadSettings();

if (($settings['telegram_hits_enabled'] ?? 'false') !== 'true') {
    echo json_encode(['success' => false, 'error' => 'Telegram hits disabled']);
    exit;
}

$botToken = $settings['telegram_bot_token'] ?? '';
$groupId = $settings['telegram_group_id'] ?? '';

if (empty($botToken) || empty($groupId)) {
    echo json_encode(['success' => false, 'error' => 'Telegram not configured']);
    exit;
}

$cardDisplay = substr($card, 0, 6) . "••••" . substr($card, -4);
$statusDisplay = $status === 'approved' ? '✓ APPROVED' : ($status === 'declined' ? '✗ DECLINED' : '◉ ' . strtoupper($status));

$telegramMessage = "🎉<b>Hit Detected</b> 🎉\n";
$telegramMessage .= "<i>━━━━━━━━━━━━━━━━━━━━━━━━━━━━</i>\n";
$telegramMessage .= "<b>💎 Gate:</b> <i>" . strtoupper($gateway) . "</i>\n";
$telegramMessage .= "<b>💎 Card:</b> <i>" . $cardDisplay . "</i>\n";
$telegramMessage .= "<b>💎 User:</b> <i>" . $user . "</i>\n";
$telegramMessage .= "<b>💎 Status:</b> <i>" . $statusDisplay . "</i>\n";
$telegramMessage .= "<b>💎 Response:</b> <i>" . substr($message, 0, 80) . "</i>\n";
$telegramMessage .= "<b>💎 Time:</b> <i>" . date('H:i:s') . " UTC</i>\n";
$telegramMessage .= "<i>━━━━━━━━━━━━━━━━━━━━━━━━━━━━</i>\n";
$telegramMessage .= "<b>✦APPROVED CHECKER WEB✦</b>";

$url = "https://api.telegram.org/bot{$botToken}/sendMessage";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'chat_id' => $groupId,
    'text' => $telegramMessage,
    'parse_mode' => 'HTML'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$result = curl_exec($ch);
curl_close($ch);

echo json_encode(['success' => true]);
?>
