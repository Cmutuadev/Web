<?php
session_start();

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Timezone
date_default_timezone_set('UTC');

// MongoDB Connection
require_once __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

// Optimized MongoDB connection configuration
define('MONGODB_URI', 'mongodb+srv://myproject:myproject@myproject.wqcf3.mongodb.net/?retryWrites=true&w=majority&appName=myproject&connectTimeoutMS=5000&socketTimeoutMS=5000&serverSelectionTimeoutMS=5000');
define('MONGODB_DATABASE', 'MASTER_DATABASE');

// Global MongoDB client and database references (singleton pattern)
$mongoClient = null;
$mongoDatabase = null;
$cache = [];

// Optimized MongoDB connection with connection pooling
function getMongoDB() {
    global $mongoClient, $mongoDatabase;
    
    if ($mongoClient === null) {
        try {
            $mongoClient = new Client(MONGODB_URI, [
                'connectTimeoutMS' => 5000,
                'socketTimeoutMS' => 5000,
                'serverSelectionTimeoutMS' => 5000,
                'heartbeatFrequencyMS' => 10000,
                'retryWrites' => true,
                'retryReads' => true,
                'maxPoolSize' => 10,
                'minPoolSize' => 2,
                'waitQueueTimeoutMS' => 10000
            ]);
            $mongoDatabase = $mongoClient->selectDatabase(MONGODB_DATABASE);
        } catch (Exception $e) {
            error_log("MongoDB connection failed: " . $e->getMessage());
            return null;
        }
    }
    return $mongoDatabase;
}

// Helper function to convert MongoDB document to array with proper types
function documentToArray($doc) {
    if (!$doc) return null;
    $array = (array)$doc;
    if (isset($array['_id'])) {
        $array['_id'] = (string)$array['_id'];
    }
    if (isset($array['created_at']) && $array['created_at'] instanceof UTCDateTime) {
        $array['created_at'] = $array['created_at']->toDateTime()->format('Y-m-d H:i:s');
    }
    if (isset($array['updated_at']) && $array['updated_at'] instanceof UTCDateTime) {
        $array['updated_at'] = $array['updated_at']->toDateTime()->format('Y-m-d H:i:s');
    }
    if (isset($array['last_login']) && $array['last_login'] instanceof UTCDateTime) {
        $array['last_login'] = $array['last_login']->toDateTime()->format('Y-m-d H:i:s');
    }
    if (isset($array['last_activity']) && $array['last_activity'] instanceof UTCDateTime) {
        $array['last_activity'] = $array['last_activity']->toDateTime()->format('Y-m-d H:i:s');
    }
    return $array;
}

// Initialize database with indexes only once
function initDatabase() {
    static $initialized = false;
    if ($initialized) return;
    
    $db = getMongoDB();
    if (!$db) return;
    
    try {
        // Drop existing indexes and recreate with proper names
        try {
            $db->users->dropIndex('username_unique');
        } catch (Exception $e) {}
        try {
            $db->users->dropIndex('username_1');
        } catch (Exception $e) {}
        
        // Create indexes with unique names
        $db->users->createIndex(['username' => 1], ['unique' => true, 'name' => 'idx_username_unique']);
        $db->users->createIndex(['telegram_id' => 1], ['unique' => true, 'sparse' => true, 'name' => 'idx_telegram_unique']);
        $db->users->createIndex(['api_key' => 1], ['unique' => true, 'sparse' => true, 'name' => 'idx_apikey_unique']);
        $db->users->createIndex(['last_login' => -1], ['name' => 'idx_lastlogin']);
        
        $db->settings->createIndex(['key' => 1], ['unique' => true, 'name' => 'idx_settings_key']);
        $db->gates->createIndex(['key' => 1], ['unique' => true, 'name' => 'idx_gates_key']);
        $db->topup_requests->createIndex(['user' => 1, 'status' => 1], ['name' => 'idx_topup_user_status']);
        $db->credit_history->createIndex(['username' => 1, 'created_at' => -1], ['name' => 'idx_credit_username_created']);
        
        // Check if admin user exists
        $userCount = $db->users->countDocuments();
        if ($userCount == 0) {
            $apiKey = 'cxchk_' . bin2hex(random_bytes(32));
            $db->users->insertOne([
                'username' => 'admin',
                'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
                'is_admin' => 1,
                'is_owner' => 1,
                'credits' => 999999,
                'api_key' => $apiKey,
                'display_name' => 'Administrator',
                'plan' => 'lifetime',
                'email' => null,
                'banned' => 0,
                'telegram_id' => null,
                'telegram_username' => null,
                'photo_url' => null,
                'created_at' => new UTCDateTime(),
                'last_login' => null
            ]);
        }
        
        // Insert default settings if empty
        $settingsCount = $db->settings->countDocuments();
        if ($settingsCount == 0) {
            $defaultSettings = [
                'binance_wallet' => '',
                'binance_network' => 'BEP20',
                'credits_per_usdt' => '100',
                'default_credits' => '100',
                'daily_rate_limit' => '500',
                'daily_credit_reset' => '100',
                'site_announcement' => '',
                'telegram_bot_token' => '',
                'telegram_group_id' => '',
                'telegram_hits_enabled' => 'false',
                'telegram_bot_username' => '',
                'maintenance_mode' => 'false',
                'admin_telegram_username' => '',
                'maintenance_pages' => []
            ];
            foreach ($defaultSettings as $key => $value) {
                $db->settings->updateOne(
                    ['key' => $key],
                    ['$setOnInsert' => ['key' => $key, 'value' => $value, 'updated_at' => new UTCDateTime()]],
                    ['upsert' => true]
                );
            }
        }
        
        // Insert default gates if empty
        $gatesCount = $db->gates->countDocuments();
        if ($gatesCount == 0) {
            $defaultGates = [
                'shopify' => [
                    'key' => 'shopify', 'label' => 'Shopify', 'type' => 'auto_checker',
                    'api_endpoint' => 'https://onyxenvbot.up.railway.app/shopify/key=yashikaaa/cc={cc}',
                    'credit_cost' => 1, 'enabled' => 1, 'required_plan' => 'basic', 'description' => 'Shopify gateway'
                ],
                'stripe_auth' => [
                    'key' => 'stripe_auth', 'label' => 'Stripe Auth', 'type' => 'auto_checker',
                    'api_endpoint' => 'https://onyxenvbot.up.railway.app/stripe/key=yashikaaa/cc={cc}',
                    'credit_cost' => 1, 'enabled' => 1, 'required_plan' => 'basic', 'description' => 'Stripe authentication'
                ],
            ];
            foreach ($defaultGates as $key => $gate) {
                $db->gates->updateOne(
                    ['key' => $key],
                    ['$setOnInsert' => $gate],
                    ['upsert' => true]
                );
            }
        }
        
        $initialized = true;
    } catch (Exception $e) {
        error_log("Init database error: " . $e->getMessage());
    }
}

// ============================================
// LOAD FUNCTIONS WITH CACHING
// ============================================

function loadSettings() {
    global $cache;
    if (isset($cache['settings'])) return $cache['settings'];
    $db = getMongoDB();
    if (!$db) return [];
    $settings = [];
    $cursor = $db->settings->find();
    foreach ($cursor as $doc) {
        $settings[$doc['key']] = $doc['value'];
    }
    $cache['settings'] = $settings;
    return $settings;
}

function loadUsers() {
    global $cache;
    if (isset($cache['users'])) return $cache['users'];
    $db = getMongoDB();
    if (!$db) return [];
    $users = [];
    $cursor = $db->users->find();
    foreach ($cursor as $doc) {
        $user = documentToArray($doc);
        $users[$user['username']] = $user;
    }
    $cache['users'] = $users;
    return $users;
}

function loadGates() {
    global $cache;
    if (isset($cache['gates'])) return $cache['gates'];
    $db = getMongoDB();
    if (!$db) return [];
    $gates = [];
    $cursor = $db->gates->find();
    foreach ($cursor as $doc) {
        $key = $doc['key'];
        $gates[$key] = [
            'key' => $key,
            'label' => $doc['label'] ?? $key,
            'enabled' => (bool)($doc['enabled'] ?? false),
            'type' => $doc['type'] ?? 'auto_checker',
            'api_endpoint' => $doc['api_endpoint'] ?? '',
            'credit_cost' => $doc['credit_cost'] ?? 1,
            'required_plan' => $doc['required_plan'] ?? 'basic',
            'description' => $doc['description'] ?? ''
        ];
    }
    $cache['gates'] = $gates;
    return $gates;
}

function loadTopups() {
    $db = getMongoDB();
    if (!$db) return [];
    $topups = [];
    $cursor = $db->topup_requests->find([], ['sort' => ['created_at' => -1], 'limit' => 100]);
    foreach ($cursor as $doc) {
        $topup = documentToArray($doc);
        // Ensure 'user' field exists for backward compatibility
        if (!isset($topup['user']) && isset($topup['username'])) {
            $topup['user'] = $topup['username'];
        }
        $topups[] = $topup;
    }
    return $topups;
}

function loadCreditHistory() {
    $db = getMongoDB();
    if (!$db) return [];
    $history = [];
    $cursor = $db->credit_history->find([], ['sort' => ['created_at' => -1], 'limit' => 500]);
    foreach ($cursor as $doc) {
        $history[] = documentToArray($doc);
    }
    return $history;
}

function loadApiKeys() {
    $db = getMongoDB();
    if (!$db) return [];
    $keys = [];
    $cursor = $db->api_keys->find();
    foreach ($cursor as $doc) {
        $keys[] = $doc['key'];
    }
    return $keys;
}

function loadAdverts() {
    $db = getMongoDB();
    if (!$db) return [];
    $adverts = [];
    $cursor = $db->adverts->find([], ['sort' => ['created_at' => -1]]);
    foreach ($cursor as $doc) {
        $adverts[] = documentToArray($doc);
    }
    return $adverts;
}

function loadOnlineUsers() {
    $db = getMongoDB();
    if (!$db) return [];
    $users = [];
    $thirtySecondsAgo = new UTCDateTime((time() - 30) * 1000);
    $cursor = $db->online_users->find(['last_activity' => ['$gt' => $thirtySecondsAgo]], ['limit' => 50]);
    foreach ($cursor as $doc) {
        $users[] = documentToArray($doc);
    }
    return $users;
}

function loadIngroupConfig() {
    $db = getMongoDB();
    if (!$db) return [];
    $config = $db->ingroup_config->findOne(['id' => 1]);
    if (!$config) {
        return [
            'bot_token' => '',
            'is_active' => 0,
            'rate_limit_per_user' => 10,
            'mass_max_cards' => 25,
            'premium_only_mass' => 1,
            'buy_message' => '⚠️ You have reached your daily limit. Upgrade to premium for unlimited checks!',
            'admin_telegram_ids' => ''
        ];
    }
    return documentToArray($config);
}

function loadIngroupGates() {
    $db = getMongoDB();
    if (!$db) return [];
    $gates = [];
    $cursor = $db->ingroup_gates->find([], ['sort' => ['created_at' => 1]]);
    foreach ($cursor as $doc) {
        $gates[] = documentToArray($doc);
    }
    return $gates;
}

function loadIngroupGroups() {
    $db = getMongoDB();
    if (!$db) return [];
    $groups = [];
    $cursor = $db->ingroup_groups->find([], ['sort' => ['created_at' => 1]]);
    foreach ($cursor as $doc) {
        $groups[] = documentToArray($doc);
    }
    return $groups;
}

// ============================================
// SAVE FUNCTIONS
// ============================================

function saveSettings($settings) {
    global $cache;
    unset($cache['settings']);
    $db = getMongoDB();
    if (!$db) return;
    foreach ($settings as $key => $value) {
        if (is_array($value)) {
            $value = json_encode($value);
        }
        $db->settings->updateOne(
            ['key' => $key],
            ['$set' => ['value' => $value, 'updated_at' => new UTCDateTime()]],
            ['upsert' => true]
        );
    }
}

function saveUsers($users) {
    global $cache;
    unset($cache['users']);
    $db = getMongoDB();
    if (!$db) return;
    foreach ($users as $username => $user) {
        $updateData = [
            'credits' => $user['credits'] ?? 0,
            'is_admin' => $user['is_admin'] ?? 0,
            'banned' => $user['banned'] ?? 0,
            'display_name' => $user['display_name'] ?? $username,
            'plan' => $user['plan'] ?? 'basic'
        ];
        if (isset($user['api_key'])) $updateData['api_key'] = $user['api_key'];
        $db->users->updateOne(['username' => $username], ['$set' => $updateData]);
    }
}

function saveGates($gates) {
    global $cache;
    unset($cache['gates']);
    $db = getMongoDB();
    if (!$db) return;
    foreach ($gates as $key => $gate) {
        $db->gates->updateOne(
            ['key' => $key],
            ['$set' => [
                'enabled' => $gate['enabled'] ? 1 : 0,
                'required_plan' => $gate['required_plan'] ?? 'basic',
                'credit_cost' => $gate['credit_cost'] ?? 1,
                'type' => $gate['type'] ?? 'auto_checker',
                'api_endpoint' => $gate['api_endpoint'] ?? '',
                'description' => $gate['description'] ?? ''
            ]]
        );
    }
}

function saveTopups($topups) {
    $db = getMongoDB();
    if (!$db) return;
    foreach ($topups as $topup) {
        // Ensure we have a user field
        $userName = $topup['user'] ?? $topup['username'] ?? null;
        if (!$userName) continue;
        
        $data = [
            'id' => $topup['id'],
            'user_id' => $topup['user_id'] ?? null,
            'user' => $userName,
            'amount' => $topup['amount'],
            'credits' => $topup['credits'],
            'tx_hash' => $topup['tx_hash'],
            'status' => $topup['status'],
            'plan' => $topup['plan'] ?? null,
            'reviewed_at' => isset($topup['reviewed_at']) ? new UTCDateTime(strtotime($topup['reviewed_at']) * 1000) : null,
            'reviewed_by' => $topup['reviewed_by'] ?? null,
            'created_at' => isset($topup['created_at']) ? new UTCDateTime(strtotime($topup['created_at']) * 1000) : new UTCDateTime()
        ];
        $db->topup_requests->updateOne(
            ['id' => $topup['id']], 
            ['$set' => $data], 
            ['upsert' => true]
        );
    }
}

function saveCreditHistory($history) {
    $db = getMongoDB();
    if (!$db) return;
    foreach ($history as $entry) {
        $isApproved = (stripos($entry["reason"] ?? "", "approved") !== false || stripos($entry["reason"] ?? "", "charged") !== false) ? 1 : 0;
        $data = [
            "user_id" => $entry["user_id"] ?? null,
            "username" => $entry["username"],
            "amount" => $entry["amount"],
            "reason" => $entry["reason"],
            "card_info" => $entry["card_info"] ?? null,
            "balance" => $entry["balance"] ?? null,
            "is_approved" => $isApproved,
            "created_at" => isset($entry["created_at"]) ? new UTCDateTime(strtotime($entry["created_at"]) * 1000) : new UTCDateTime()
        ];
        $db->credit_history->insertOne($data);
    }
}

function saveApiKeys($keys) {
    $db = getMongoDB();
    if (!$db) return;
    $db->api_keys->deleteMany([]);
    foreach ($keys as $key) {
        $db->api_keys->insertOne(['key' => $key, 'created_at' => new UTCDateTime()]);
    }
}

function saveAdverts($adverts) {
    $db = getMongoDB();
    if (!$db) return;
    foreach ($adverts as $ad) {
        $data = [
            'id' => $ad['id'],
            'title' => $ad['title'],
            'content' => $ad['content'] ?? '',
            'image_url' => $ad['image_url'] ?? '',
            'link_url' => $ad['link_url'] ?? '',
            'position' => $ad['position'],
            'is_active' => $ad['is_active'] ? 1 : 0,
            'created_at' => isset($ad['created_at']) ? new UTCDateTime(strtotime($ad['created_at']) * 1000) : new UTCDateTime()
        ];
        $db->adverts->updateOne(['id' => $ad['id']], ['$set' => $data], ['upsert' => true]);
    }
}

function saveOnlineUsers($users) {
    $db = getMongoDB();
    if (!$db) return;
    foreach ($users as $sessionId => $data) {
        $db->online_users->updateOne(
            ['session_id' => $sessionId],
            ['$set' => [
                'user_id' => $data['user_id'] ?? null,
                'username' => $data['username'] ?? '',
                'display_name' => $data['display_name'] ?? '',
                'photo_url' => $data['photo_url'] ?? '',
                'last_activity' => isset($data['last_activity']) ? new UTCDateTime(strtotime($data['last_activity']) * 1000) : new UTCDateTime()
            ]],
            ['upsert' => true]
        );
    }
    $thirtySecondsAgo = new UTCDateTime((time() - 30) * 1000);
    $db->online_users->deleteMany(['last_activity' => ['$lt' => $thirtySecondsAgo]]);
}

function saveIngroupConfig($config) {
    $db = getMongoDB();
    if (!$db) return;
    $db->ingroup_config->updateOne(
        ['id' => 1],
        ['$set' => [
            'bot_token' => $config['bot_token'],
            'is_active' => $config['is_active'] ? 1 : 0,
            'rate_limit_per_user' => $config['rate_limit_per_user'],
            'mass_max_cards' => $config['mass_max_cards'],
            'premium_only_mass' => $config['premium_only_mass'] ? 1 : 0,
            'buy_message' => $config['buy_message'],
            'admin_telegram_ids' => $config['admin_telegram_ids'],
            'updated_at' => new UTCDateTime()
        ]]
    );
}

function saveIngroupGates($gates) {
    $db = getMongoDB();
    if (!$db) return;
    foreach ($gates as $gate) {
        $db->ingroup_gates->updateOne(
            ['gate_key' => $gate['gate_key']],
            ['$set' => ['is_enabled' => $gate['is_enabled'] ? 1 : 0]]
        );
    }
}

function saveIngroupGroups($groups) {
    $db = getMongoDB();
    if (!$db) return;
    $db->ingroup_groups->deleteMany([]);
    foreach ($groups as $group) {
        $db->ingroup_groups->insertOne([
            'id' => $group['id'],
            'group_id' => $group['group_id'],
            'group_name' => $group['group_name'],
            'is_active' => $group['is_active'] ? 1 : 0,
            'created_at' => isset($group['created_at']) ? new UTCDateTime(strtotime($group['created_at']) * 1000) : new UTCDateTime()
        ]);
    }
}

// ============================================
// AUTHENTICATION FUNCTIONS
// ============================================

function generateApiKey() { return 'cxchk_' . bin2hex(random_bytes(32)); }
function isLoggedIn() { return isset($_SESSION['user']) && !empty($_SESSION['user']); }
function isAdmin() { return isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin'] === true; }
function isOwner() { return isset($_SESSION['user']['is_owner']) && $_SESSION['user']['is_owner'] === true; }
function isBanned() { return isset($_SESSION['user']['banned']) && $_SESSION['user']['banned'] === true; }
function getUserCredits() { return $_SESSION['user']['credits'] ?? 0; }

function addCredits($username, $amount, $reason) {
    $db = getMongoDB();
    if (!$db) return false;
    $result = $db->users->updateOne(['username' => $username], ['$inc' => ['credits' => $amount]]);
    if ($result->getModifiedCount() > 0) {
        $user = $db->users->findOne(['username' => $username]);
        if ($user) {
            $db->credit_history->insertOne([
                'user_id' => $user['_id'],
                'username' => $username,
                'amount' => $amount,
                'reason' => $reason,
                'balance' => $user['credits'],
                'created_at' => new UTCDateTime()
            ]);
            if (isset($_SESSION["user"]["name"]) && $_SESSION["user"]["name"] === $username) {
                $_SESSION["user"]["credits"] = $user['credits'];
            }
        }
        return true;
    }
    return false;
}

function deductCredits($amount, $reason, $cardInfo = '') {
    if (!isset($_SESSION['user'])) return false;
    $db = getMongoDB();
    if (!$db) return false;
    $username = $_SESSION['user']['name'] ?? '';
    
    $user = $db->users->findOne(['username' => $username]);
    if (!$user) return false;
    $currentCredits = $user['credits'] ?? 0;
    if ($currentCredits < $amount) return false;
    
    $newCredits = $currentCredits - $amount;
    $result = $db->users->updateOne(
        ['username' => $username],
        ['$set' => ['credits' => $newCredits]]
    );
    
    if ($result->getModifiedCount() > 0) {
        $db->credit_history->insertOne([
            'user_id' => $user['_id'],
            'username' => $username,
            'amount' => -$amount,
            'reason' => $reason,
            'card_info' => $cardInfo,
            'balance' => $newCredits,
            'created_at' => new UTCDateTime()
        ]);
        $_SESSION['user']['credits'] = $newCredits;
        return true;
    }
    return false;
}

function getUserStats($username) {
    $db = getMongoDB();
    if (!$db) return ["total_checks" => 0, "approved_checks" => 0, "success_rate" => 0, "credits_used" => 0];
    $total = $db->credit_history->countDocuments(["username" => $username]);
    $approved = $db->credit_history->countDocuments(["username" => $username, "is_approved" => 1]);
    return [
        "total_checks" => $total,
        "approved_checks" => $approved,
        "success_rate" => $total > 0 ? round(($approved / $total) * 100) : 0,
        "credits_used" => 0
    ];
}

function getOnlineUsers() {
    $db = getMongoDB();
    if (!$db) return [];
    $thirtySecondsAgo = new UTCDateTime((time() - 30) * 1000);
    $users = [];
    $cursor = $db->online_users->find(['last_activity' => ['$gt' => $thirtySecondsAgo]], ['limit' => 20]);
    foreach ($cursor as $row) {
        $users[] = [
            'name' => $row['display_name'] ?: $row['username'],
            'username' => $row['username'] ? '@' . $row['username'] : null,
            'photo_url' => $row['photo_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode(substr($row['display_name'] ?: $row['username'], 0, 1)) . '&background=8b5cf6&color=fff&size=64',
            'is_online' => true
        ];
    }
    return $users;
}

function updateOnlineStatus($username) {
    $db = getMongoDB();
    if (!$db) return;
    $user = $db->users->findOne(['username' => $username]);
    if ($user) {
        $sessionId = session_id();
        $db->online_users->updateOne(
            ['session_id' => $sessionId],
            ['$set' => [
                'user_id' => $user['_id'],
                'username' => $user['username'],
                'display_name' => $user['display_name'],
                'photo_url' => $user['photo_url'],
                'last_activity' => new UTCDateTime()
            ]],
            ['upsert' => true]
        );
    }
    $thirtySecondsAgo = new UTCDateTime((time() - 30) * 1000);
    $db->online_users->deleteMany(['last_activity' => ['$lt' => $thirtySecondsAgo]]);
}

function authenticateUser($username, $password) {
    $db = getMongoDB();
    if (!$db) return false;
    $user = $db->users->findOne(['username' => $username]);
    if (!$user) return false;
    if (!password_verify($password, $user['password_hash'])) return false;
    if ($user['banned']) return false;
    $_SESSION['user'] = [
        'name' => $user['username'],
        'credits' => $user['credits'],
        'is_admin' => (bool)$user['is_admin'],
        'is_owner' => (bool)$user['is_owner'],
        'banned' => (bool)$user['banned'],
        'username' => $user['username'],
        'display_name' => $user['display_name'] ?? $user['username'],
        'api_key' => $user['api_key'],
        'plan' => $user['plan'] ?? 'basic',
        'created_at' => isset($user['created_at']) ? $user['created_at']->toDateTime()->format('Y-m-d H:i:s') : null,
        'user_id' => (string)$user['_id']
    ];
    updateOnlineStatus($user['username']);
    return true;
}

function registerUser($username, $password, $email = null) {
    $db = getMongoDB();
    if (!$db) return ['success' => false, 'error' => 'Database error'];
    $existing = $db->users->findOne(['username' => $username]);
    if ($existing) return ['success' => false, 'error' => 'Username already exists'];
    if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) return ['success' => false, 'error' => 'Invalid username'];
    if (strlen($password) < 6) return ['success' => false, 'error' => 'Password too short'];
    $settings = loadSettings();
    $apiKey = generateApiKey();
    try {
        $db->users->insertOne([
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'credits' => $settings['default_credits'] ?? 100,
            'api_key' => $apiKey,
            'display_name' => $username,
            'email' => $email,
            'plan' => 'basic',
            'is_admin' => 0,
            'is_owner' => 0,
            'banned' => 0,
            'telegram_id' => null,
            'telegram_username' => null,
            'photo_url' => null,
            'created_at' => new UTCDateTime()
        ]);
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function sendTelegramMessage($chatId, $message, $parseMode = 'HTML') {
    $settings = loadSettings();
    if (empty($settings['telegram_bot_token'])) return false;
    $url = "https://api.telegram.org/bot" . $settings['telegram_bot_token'] . "/sendMessage";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['chat_id' => $chatId, 'text' => $message, 'parse_mode' => $parseMode]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

function notifyTopupStatus($username, $status, $credits, $amount) {
    $settings = loadSettings();
    if (empty($settings['telegram_bot_token'])) return false;
    
    // Get user's telegram ID
    $db = getMongoDB();
    if (!$db) return false;
    
    $user = $db->users->findOne(['username' => $username]);
    if (!$user || empty($user['telegram_id'])) return false;
    
    if ($status === 'approved') {
        $message = "✅ <b>Top-Up Approved!</b>\n\n";
        $message .= "💰 Amount: {$amount} USDT\n";
        $message .= "🎉 Credits Added: " . number_format($credits) . "\n";
        $message .= "📊 New Balance: " . number_format($user['credits'] + $credits) . "\n\n";
        $message .= "Thank you for using our service!";
    } else {
        $message = "❌ <b>Top-Up Rejected</b>\n\n";
        $message .= "Your top-up request for {$amount} USDT has been rejected.\n";
        $message .= "Please contact support if you believe this is an error.";
    }
    
    return sendTelegramMessage($user['telegram_id'], $message);
}

// Initialize database on load
initDatabase();

// Load global variables
$settings = loadSettings();
$gates = loadGates();

// Update online status if logged in
if (isLoggedIn() && !isBanned()) {
    updateOnlineStatus($_SESSION['user']['name'] ?? '');
}
?>