<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'config') {
    $settings = loadSettings();
    echo json_encode([
        'bot_username' => $settings['telegram_bot_username'] ?? ''
    ]);
    exit;
}

$authType = $input['auth_type'] ?? '';

if ($authType === 'mini_app') {
    $initData = $input['init_data'] ?? '';
    $user = authenticateTelegramMiniApp($initData);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid authentication']);
        exit;
    }
    
    $telegramId = $user['id'];
    $firstName = $user['first_name'] ?? '';
    $lastName = $user['last_name'] ?? '';
    $username = $user['username'] ?? '';
    $photoUrl = $user['photo_url'] ?? '';
    
    $users = loadUsers();
    $existingUser = null;
    $existingUsername = null;
    
    foreach ($users as $name => $u) {
        if (isset($u['telegram_id']) && $u['telegram_id'] == $telegramId) {
            $existingUser = $u;
            $existingUsername = $name;
            break;
        }
    }
    
    if ($existingUser) {
        $_SESSION['user'] = [
            'name' => $existingUsername,
            'credits' => $existingUser['credits'] ?? 0,
            'is_admin' => $existingUser['is_admin'] ?? false,
            'is_owner' => $existingUser['is_owner'] ?? false,
            'banned' => $existingUser['banned'] ?? false,
            'username' => $existingUser['username'] ?? $username,
            'display_name' => $existingUser['display_name'] ?? $firstName,
            'photo_url' => $photoUrl,
            'telegram_id' => $telegramId,
            'auth_provider' => 'telegram'
        ];
        
        echo json_encode([
            'success' => true,
            'isNew' => false,
            'session' => [
                'access_token' => session_id(),
                'refresh_token' => session_id()
            ]
        ]);
    } else {
        $settings = loadSettings();
        $newUsername = 'tg_' . $telegramId;
        $baseUsername = $newUsername;
        $counter = 1;
        while (isset($users[$newUsername])) {
            $newUsername = $baseUsername . '_' . $counter;
            $counter++;
        }
        
        $users[$newUsername] = [
            'id' => count($users) + 1,
            'password_hash' => '',
            'is_admin' => false,
            'is_owner' => false,
            'credits' => $settings['default_credits'],
            'banned' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'api_key' => generateApiKey(),
            'username' => $username ?: $newUsername,
            'display_name' => $firstName,
            'email' => null,
            'telegram_id' => $telegramId,
            'telegram_username' => $username,
            'photo_url' => $photoUrl
        ];
        
        saveUsers($users);
        
        $_SESSION['user'] = [
            'name' => $newUsername,
            'credits' => $settings['default_credits'],
            'is_admin' => false,
            'is_owner' => false,
            'banned' => false,
            'username' => $username ?: $newUsername,
            'display_name' => $firstName,
            'photo_url' => $photoUrl,
            'telegram_id' => $telegramId,
            'auth_provider' => 'telegram'
        ];
        
        echo json_encode([
            'success' => true,
            'isNew' => true,
            'session' => [
                'access_token' => session_id(),
                'refresh_token' => session_id()
            ]
        ]);
    }
    exit;
}

// Widget authentication
$id = $input['id'] ?? 0;
$firstName = $input['first_name'] ?? '';
$lastName = $input['last_name'] ?? '';
$username = $input['username'] ?? '';
$photoUrl = $input['photo_url'] ?? '';
$authDate = $input['auth_date'] ?? '';
$hash = $input['hash'] ?? '';

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing user data']);
    exit;
}

$settings = loadSettings();
$botToken = $settings['telegram_bot_token'] ?? '';

// Verify hash
$checkArray = [
    'id' => $id,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'username' => $username,
    'photo_url' => $photoUrl,
    'auth_date' => $authDate
];

$checkArray = array_filter($checkArray, function($v) { return !empty($v); });
ksort($checkArray);

$dataCheckString = [];
foreach ($checkArray as $key => $value) {
    $dataCheckString[] = "$key=$value";
}
$dataCheckString = implode("\n", $dataCheckString);

$secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
$calculatedHash = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey, true));

if (!hash_equals($calculatedHash, $hash)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid hash']);
    exit;
}

$users = loadUsers();
$existingUser = null;
$existingUsername = null;

foreach ($users as $name => $u) {
    if (isset($u['telegram_id']) && $u['telegram_id'] == $id) {
        $existingUser = $u;
        $existingUsername = $name;
        break;
    }
}

if ($existingUser) {
    $_SESSION['user'] = [
        'name' => $existingUsername,
        'credits' => $existingUser['credits'] ?? 0,
        'is_admin' => $existingUser['is_admin'] ?? false,
        'is_owner' => $existingUser['is_owner'] ?? false,
        'banned' => $existingUser['banned'] ?? false,
        'username' => $existingUser['username'] ?? $username,
        'display_name' => $existingUser['display_name'] ?? $firstName,
        'photo_url' => $photoUrl,
        'telegram_id' => $id,
        'auth_provider' => 'telegram'
    ];
    
    echo json_encode([
        'success' => true,
        'isNew' => false,
        'session' => [
            'access_token' => session_id(),
            'refresh_token' => session_id()
        ]
    ]);
} else {
    $settings = loadSettings();
    $newUsername = 'tg_' . $id;
    $baseUsername = $newUsername;
    $counter = 1;
    while (isset($users[$newUsername])) {
        $newUsername = $baseUsername . '_' . $counter;
        $counter++;
    }
    
    $users[$newUsername] = [
        'id' => count($users) + 1,
        'password_hash' => '',
        'is_admin' => false,
        'is_owner' => false,
        'credits' => $settings['default_credits'],
        'banned' => false,
        'created_at' => date('Y-m-d H:i:s'),
        'api_key' => generateApiKey(),
        'username' => $username ?: $newUsername,
        'display_name' => $firstName,
        'email' => null,
        'telegram_id' => $id,
        'telegram_username' => $username,
        'photo_url' => $photoUrl
    ];
    
    saveUsers($users);
    
    $_SESSION['user'] = [
        'name' => $newUsername,
        'credits' => $settings['default_credits'],
        'is_admin' => false,
        'is_owner' => false,
        'banned' => false,
        'username' => $username ?: $newUsername,
        'display_name' => $firstName,
        'photo_url' => $photoUrl,
        'telegram_id' => $id,
        'auth_provider' => 'telegram'
    ];
    
    echo json_encode([
        'success' => true,
        'isNew' => true,
        'session' => [
            'access_token' => session_id(),
            'refresh_token' => session_id()
        ]
    ]);
}
?>