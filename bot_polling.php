<?php
require_once 'includes/config.php';

set_time_limit(0);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_log("Bot polling started");

$settings = loadSettings();
$botToken = $settings['telegram_bot_token'] ?? '8087419884:AAH2YNYu4-LF8kn3j1NiSwR5n6IxOf3iJaM';
$offset = 0;

// CHANGE THIS TO YOUR NGROK HTTPS URL
$WEBAPP_URL = 'https://YOUR_NGROK_URL';  // ← Replace with your ngrok URL

function botSendMessageWithButton($botToken, $chatId, $message, $buttonUrl) {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    
    $replyMarkup = [
        'inline_keyboard' => [
            [
                ['text' => '🚀 Open Web App', 'web_app' => ['url' => $buttonUrl]]
            ]
        ]
    ];
    
    $postData = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode($replyMarkup)
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("botSendMessageWithButton: HTTP $httpCode");
    return $result;
}

while (true) {
    $url = "https://api.telegram.org/bot{$botToken}/getUpdates?timeout=30&offset=" . ($offset + 1);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response === false) {
        error_log("Failed to get updates");
        sleep(2);
        continue;
    }
    
    $updates = json_decode($response, true);
    
    if ($updates && isset($updates['result']) && !empty($updates['result'])) {
        error_log("Received " . count($updates['result']) . " updates");
        
        foreach ($updates['result'] as $update) {
            $offset = $update['update_id'];
            
            if (isset($update['message'])) {
                $chatId = $update['message']['chat']['id'];
                $text = $update['message']['text'] ?? '';
                $firstName = $update['message']['from']['first_name'] ?? 'User';
                
                error_log("Message from chat {$chatId}: {$text}");
                
                if ($text === '/start') {
                    $welcomeMessage = "👋 <b>Welcome to APPROVED CHECKER, {$firstName}!</b>\n\n" .
                        "🔥 The fastest CC checker on the market.\n" .
                        "💳 Auth, Charge, Shopify, Stripe & more.\n" .
                        "⚡ Instant results with premium gateways.\n\n" .
                        "Tap the button below to open the app and start checking! 👇";
                    
                    botSendMessageWithButton($botToken, $chatId, $welcomeMessage, $WEBAPP_URL);
                }
            }
        }
    }
    sleep(1);
}
?>
