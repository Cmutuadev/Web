<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'setup') {
        $settings = loadSettings();
        $botToken = $settings['telegram_bot_token'] ?? '';
        
        if (empty($botToken)) {
            echo json_encode(['ok' => false, 'error' => 'Bot token not configured']);
            exit;
        }
        
        $webhookUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/api/telegram-bot.php';
        
        $url = "https://api.telegram.org/bot{$botToken}/setWebhook?url=" . urlencode($webhookUrl);
        
        $result = @file_get_contents($url);
        
        if ($result === false) {
            echo json_encode(['ok' => false, 'error' => 'Failed to connect to Telegram API']);
            exit;
        }
        
        $data = json_decode($result, true);
        
        if ($data && $data['ok']) {
            echo json_encode(['ok' => true, 'message' => 'Webhook set successfully']);
        } else {
            echo json_encode(['ok' => false, 'error' => $data['description'] ?? 'Failed to set webhook']);
        }
        exit;
    }
}

echo json_encode(['ok' => false, 'error' => 'Invalid action']);
?>
