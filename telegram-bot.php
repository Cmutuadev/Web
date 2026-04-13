<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'includes/config.php';

use MongoDB\BSON\UTCDateTime;

// Function to get user's profile photo
function getUserProfilePhoto($userId, $botToken) {
    $photoUrl = '';
    
    $apiUrl = "https://api.telegram.org/bot{$botToken}/getUserProfilePhotos?user_id={$userId}&limit=1";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $photos = json_decode($response, true);
    if ($photos && $photos['ok'] && !empty($photos['result']['photos'])) {
        $fileId = $photos['result']['photos'][0][0]['file_id'];
        
        $fileUrl = "https://api.telegram.org/bot{$botToken}/getFile?file_id={$fileId}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fileUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $fileResponse = curl_exec($ch);
        curl_close($ch);
        
        $fileData = json_decode($fileResponse, true);
        if ($fileData && $fileData['ok']) {
            $photoUrl = "https://api.telegram.org/file/bot{$botToken}/" . $fileData['result']['file_path'];
        }
    }
    
    return $photoUrl;
}

$content = file_get_contents('php://input');
$update = json_decode($content, true);

if (!$update) {
    http_response_code(200);
    echo 'OK';
    exit;
}

if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $text = $update['message']['text'] ?? '';
    $firstName = $update['message']['from']['first_name'] ?? 'User';
    $lastName = $update['message']['from']['last_name'] ?? '';
    $username = $update['message']['from']['username'] ?? '';
    $userId = $update['message']['from']['id'] ?? '';
    
    $settings = loadSettings();
    $botToken = $settings['telegram_bot_token'] ?? '8087419884:AAH2YNYu4-LF8kn3j1NiSwR5n6IxOf3iJaM';
    
    $photoUrl = getUserProfilePhoto($userId, $botToken);
    
    $loginUrl = 'https://approvedchkr.store/login.php?tg_auth=1&id=' . $userId . 
                '&first_name=' . urlencode($firstName) . 
                '&last_name=' . urlencode($lastName) . 
                '&username=' . urlencode($username) . 
                '&photo_url=' . urlencode($photoUrl);
    
    error_log("Telegram user $userId ($username) - Photo URL: " . ($photoUrl ?: 'No photo found'));
    
    if ($text === '/start') {
        $db = getMongoDB();
        if ($db) {
            $user = $db->users->findOne(['telegram_id' => (int)$userId]);
            
            if (!$user) {
                $newUsername = 'tg_' . $userId;
                $displayName = trim($firstName . ' ' . $lastName);
                $dummyPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                
                $db->users->insertOne([
                    'username' => $newUsername,
                    'password_hash' => $dummyPassword,
                    'display_name' => $displayName,
                    'telegram_id' => (int)$userId,
                    'telegram_username' => $username,
                    'photo_url' => $photoUrl,
                    'credits' => 100,
                    'plan' => 'basic',
                    'is_admin' => 0,
                    'is_owner' => 0,
                    'banned' => 0,
                    'created_at' => new UTCDateTime(),
                    'last_login' => null
                ]);
                error_log("New user created: $newUsername");
            } else {
                $db->users->updateOne(
                    ['telegram_id' => (int)$userId],
                    ['$set' => [
                        'telegram_username' => $username,
                        'display_name' => trim($firstName . ' ' . $lastName),
                        'photo_url' => $photoUrl,
                        'last_login' => new UTCDateTime()
                    ]]
                );
                error_log("User updated: $userId");
            }
        }
        
        $message = "👋 <b>Welcome to APPROVED CHECKER, {$firstName}!</b>\n\n"
            . "✅ You have <b>100 free credits</b> to start!\n"
            . "⚡ Click the button below to launch the app.\n\n"
            . "🔐 <b>No password needed!</b> Auto-login enabled.";
        
        $replyMarkup = [
            'inline_keyboard' => [[
                ['text' => '🚀 Launch Web App', 'web_app' => ['url' => $loginUrl]]
            ]]
        ];
        
        $sendUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $postData = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($replyMarkup)
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $sendUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
        curl_close($ch);
    }
}

http_response_code(200);
echo 'OK';
?>