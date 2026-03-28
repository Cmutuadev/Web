<?php
require_once 'includes/config.php';

echo "<h2>🔧 Installing Database...</h2>";

// Create database file if not exists
$db_path = __DIR__ . '/database.db';
if (!file_exists($db_path)) {
    touch($db_path);
    chmod($db_path, 0666);
    echo "✅ Database file created<br>";
}

// Get database connection
$db = getDB();
if (!$db) {
    die("❌ Could not connect to database");
}
echo "✅ Database connected<br>";

// Create users table
$db->exec("CREATE TABLE IF NOT EXISTS users (
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
    plan TEXT DEFAULT 'basic',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME
)");
echo "✅ Users table created<br>";

// Create admin user
$hashed = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $db->prepare("INSERT OR IGNORE INTO users (username, password_hash, is_admin, credits, plan, display_name) VALUES (?, ?, 1, 999999, 'lifetime', 'Administrator')");
$stmt->execute(['admin', $hashed]);
echo "✅ Admin user created (admin / admin123)<br>";

// Create settings table
$db->exec("CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
echo "✅ Settings table created<br>";

// Insert default settings
$defaultSettings = [
    'binance_wallet' => '0x742d35Cc6634C0532925a3b844Bc9e1dC5e4b7A5',
    'binance_network' => 'BEP20',
    'credits_per_usdt' => '100',
    'default_credits' => '100',
    'daily_rate_limit' => '500',
    'site_announcement' => 'Welcome to Approved Checker!',
    'telegram_hits_enabled' => 'false',
    'maintenance_mode' => 'false'
];
foreach ($defaultSettings as $key => $value) {
    $stmt = $db->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
    $stmt->execute([$key, $value]);
}
echo "✅ Settings inserted<br>";

// Create gates table
$db->exec("CREATE TABLE IF NOT EXISTS gates (
    key TEXT PRIMARY KEY,
    label TEXT NOT NULL,
    enabled INTEGER DEFAULT 1,
    type TEXT,
    file TEXT,
    credit_cost INTEGER DEFAULT 1,
    required_plan TEXT DEFAULT 'basic',
    description TEXT
)");
echo "✅ Gates table created<br>";

// Insert default gates
$defaultGates = [
    'shopify' => ['Shopify Checker', 'auto_checker', 'gate/shopify.php', 4],
    'stripe_auth' => ['Stripe Auth', 'auto_checker', 'gate/stripe_auth.php', 3],
    'razorpay' => ['Razorpay', 'auto_checker', 'gate/razorpay.php', 3],
    'auth' => ['Auth Checker', 'checker', 'gate/auth_checker.php', 2],
    'charge' => ['Charge Checker', 'checker', 'gate/charge_checker.php', 3]
];
foreach ($defaultGates as $key => $gate) {
    $stmt = $db->prepare("INSERT OR IGNORE INTO gates (key, label, type, file, credit_cost) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$key, $gate[0], $gate[1], $gate[2], $gate[3]]);
}
echo "✅ Gates inserted<br>";

// Create credit_history table
$db->exec("CREATE TABLE IF NOT EXISTS credit_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    username TEXT,
    amount REAL,
    reason TEXT,
    card_info TEXT,
    balance REAL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
echo "✅ Credit history table created<br>";

// Create redeem_keys table
$db->exec("CREATE TABLE IF NOT EXISTS redeem_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key_code TEXT UNIQUE NOT NULL,
    credits INTEGER NOT NULL,
    plan TEXT,
    status TEXT DEFAULT 'unused',
    used_by TEXT,
    used_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
echo "✅ Redeem keys table created<br>";

// Create topup_requests table
$db->exec("CREATE TABLE IF NOT EXISTS topup_requests (
    id TEXT PRIMARY KEY,
    user_id INTEGER,
    username TEXT,
    amount REAL,
    credits INTEGER,
    tx_hash TEXT,
    status TEXT DEFAULT 'pending',
    plan TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME,
    reviewed_by TEXT
)");
echo "✅ Top-up requests table created<br>";

echo "<br><strong>✅ Installation Complete!</strong><br>";
echo "<a href='login.php'>Go to Login Page</a><br>";
echo "Username: <strong>admin</strong><br>";
echo "Password: <strong>admin123</strong><br>";
?>
