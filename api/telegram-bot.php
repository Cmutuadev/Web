<?php
require_once '../includes/config.php';

$content = file_get_contents('php://input');
$update = json_decode($content, true);

if (!$update) {
    http_response_code(400);
    exit;
}

// Log the update for debugging
error_log("Telegram update: " . $content);

if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $text = $update['message']['text'] ?? '';
    $firstName = $update['message']['from']['first_name'] ?? 'User';
    $username = $update['message']['from']['username'] ?? null;

    // Get bot token from settings
    $settings = loadSettings();
    $botToken = $settings['telegram_bot_token'] ?? '';

    if (empty($botToken)) {
        error_log("No bot token configured");
        http_response_code(200);
        exit;
    }

    if ($text === '/start') {
        $welcomeMessage = "👋 <b>Welcome to APPROVED CHECKER, {$firstName}!</b>\n\n" .
            "🔥 The fastest CC checker on the market.\n" .
            "💳 Auth, Charge, Shopify, Stripe & more.\n" .
            "⚡ Instant results with premium gateways.\n\n" .
            "Tap the button below to open the app and start checking! 👇";

        $replyMarkup = json_encode([
            'inline_keyboard' => [[
                [
                    'text' => '🚀 Open Web App',
                    'web_app' => ['url' => 'http://185.227.111.153:8081']
                ]
            ]]
        ]);

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $postData = http_build_query([
            'chat_id' => $chatId,
            'text' => $welcomeMessage,
            'parse_mode' => 'HTML',
            'reply_markup' => $replyMarkup
        ]);

        file_get_contents($url . '?' . $postData);
    }
}

http_response_code(200);
echo 'OK';
?>
