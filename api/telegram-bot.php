<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../includes/config.php';

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
    
    // Get channel/group info from settings
    $requiredChannel = $settings['telegram_required_channel'] ?? 'abouttmexd';
    $requiredGroup = $settings['telegram_required_group'] ?? '';
    $adminIds = ['sunilxd', 'npnbit1'];
    
    $photoUrl = getUserProfilePhoto($userId, $botToken);
    
    $loginUrl = 'https://approvedchkr.store/login.php?tg_auth=1&id=' . $userId . 
                '&first_name=' . urlencode($firstName) . 
                '&last_name=' . urlencode($lastName) . 
                '&username=' . urlencode($username) . 
                '&photo_url=' . urlencode($photoUrl);
    
    error_log("Telegram user $userId ($username) - Photo URL: " . ($photoUrl ?: 'No photo found'));
    
    // Check if user is a member of required channel/group
    function checkMembership($botToken, $userId, $channelUsername) {
        if (empty($channelUsername)) return true;
        $channelUsername = ltrim($channelUsername, '@');
        $url = "https://api.telegram.org/bot{$botToken}/getChatMember?chat_id=@{$channelUsername}&user_id={$userId}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        if ($data && isset($data['ok']) && $data['ok']) {
            $status = $data['result']['status'] ?? '';
            return in_array($status, ['member', 'administrator', 'creator']);
        }
        return true;
    }
    
    $isMember = checkMembership($botToken, $userId, $requiredChannel);
    
    if ($text === '/start') {
        $db = getMongoDB();
        if ($db) {
            $user = $db->users->findOne(['telegram_id' => (int)$userId]);
            
            if (!$user) {
                // NEW USER - Register
                $newUsername = 'tg_' . $userId;
                $displayName = trim($firstName . ' ' . $lastName);
                $dummyPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $apiKey = 'cxchk_' . bin2hex(random_bytes(32));
                
                $db->users->insertOne([
                    'username' => $newUsername,
                    'password_hash' => $dummyPassword,
                    'display_name' => $displayName,
                    'telegram_id' => (int)$userId,
                    'telegram_username' => $username,
                    'photo_url' => $photoUrl,
                    'credits' => 100,
                    'plan' => 'basic',
                    'api_key' => $apiKey,
                    'is_admin' => 0,
                    'is_owner' => 0,
                    'banned' => 0,
                    'created_at' => new UTCDateTime(),
                    'last_login' => null
                ]);
                error_log("New user created: $newUsername");
                
                $message = "🎉 <b>Welcome to APPROVED CHECKER, {$firstName}!</b> 🎉\n\n"
                    . "✅ <b>Registration Successful!</b>\n"
                    . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
                    . "👤 <b>Your Account:</b>\n"
                    . "▪ Username: <code>{$newUsername}</code>\n"
                    . "▪ Credits: <b>100</b> (FREE)\n"
                    . "▪ Plan: Basic\n\n"
                    . "🔑 <b>API Key:</b>\n"
                    . "<code>{$apiKey}</code>\n\n"
                    . "🚀 <b>Get Started:</b>\n"
                    . "Click the button below to launch the app!\n\n"
                    . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                    . "👨‍💻 <b>Admins:</b> @sunilxd & @npnbit1\n"
                    . "📢 <b>Channel:</b> https://t.me/abouttmexd";
                
                $replyMarkup = [
                    'inline_keyboard' => [
                        [
                            ['text' => '🚀 Launch Web App', 'web_app' => ['url' => $loginUrl]]
                        ],
                        [
                            ['text' => '📢 Join Channel', 'url' => 'https://t.me/abouttmexd'],
                            ['text' => '💬 Contact Admin', 'url' => 'https://t.me/sunilxd']
                        ],
                        [
                            ['text' => '💰 Top Up Credits', 'web_app' => ['url' => 'https://approvedchkr.store/topup.php']]
                        ]
                    ]
                ];
                
            } else {
                // EXISTING USER - Update info and welcome back
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
                
                $credits = $user['credits'] ?? 0;
                $plan = ucfirst($user['plan'] ?? 'Basic');
                $apiKey = $user['api_key'] ?? '';
                
                $message = "👋 <b>Welcome back, {$firstName}!</b>\n"
                    . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
                    . "✅ You're already registered!\n\n"
                    . "👤 <b>Account Summary:</b>\n"
                    . "▪ Username: <code>{$user['username']}</code>\n"
                    . "▪ Credits: <b>{$credits}</b>\n"
                    . "▪ Plan: <b>{$plan}</b>\n\n"
                    . "🚀 Click the button below to continue using the web app.\n\n"
                    . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                    . "📢 <b>Join our updates:</b> https://t.me/abouttmexd";
                
                $replyMarkup = [
                    'inline_keyboard' => [
                        [
                            ['text' => '🚀 Continue to Dashboard', 'web_app' => ['url' => $loginUrl]]
                        ],
                        [
                            ['text' => '📢 Updates Channel', 'url' => 'https://t.me/abouttmexd'],
                            ['text' => '💬 Support', 'url' => 'https://t.me/sunilxd']
                        ],
                        [
                            ['text' => '💰 Top Up Credits', 'web_app' => ['url' => 'https://approvedchkr.store/topup.php']]
                        ]
                    ]
                ];
            }
            
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
}

http_response_code(200);
echo 'OK';
?>
