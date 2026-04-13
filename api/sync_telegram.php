<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

session_start();
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$telegramId = $_SESSION['user']['telegram_id'] ?? null;
if (!$telegramId) {
    echo json_encode(['success' => false, 'error' => 'No Telegram account linked']);
    exit;
}

$settings = loadSettings();
$botToken = $settings['telegram_bot_token'] ?? '';

if (empty($botToken)) {
    echo json_encode(['success' => false, 'error' => 'Telegram bot not configured']);
    exit;
}

// Get user profile from Telegram
function getUserProfilePhoto($userId, $botToken) {
    $apiUrl = "https://api.telegram.org/bot{$botToken}/getUserProfilePhotos?user_id={$userId}&limit=1";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $fileResponse = curl_exec($ch);
        curl_close($ch);
        
        $fileData = json_decode($fileResponse, true);
        if ($fileData && $fileData['ok']) {
            return "https://api.telegram.org/file/bot{$botToken}/" . $fileData['result']['file_path'];
        }
    }
    return null;
}

$photoUrl = getUserProfilePhoto($telegramId, $botToken);

if ($photoUrl) {
    $db = getMongoDB();
    if ($db) {
        $db->users->updateOne(
            ['telegram_id' => (int)$telegramId],
            ['$set' => ['photo_url' => $photoUrl]]
        );
        $_SESSION['user']['photo_url'] = $photoUrl;
        echo json_encode(['success' => true, 'photo_url' => $photoUrl]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Could not fetch profile photo']);
?>
