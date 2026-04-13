<?php
require_once 'includes/config.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

$tab = $_GET['tab'] ?? 'dashboard';
$settings = loadSettings();
$gates = loadGates();
$users = loadUsers();
$topups = loadTopups();
$adverts = loadAdverts();
$apiKeys = loadApiKeys();
$creditHistory = loadCreditHistory();
$db = getMongoDB();

// Load categories
$categories = [];
if ($db) {
    $cursor = $db->gate_categories->find([], ['sort' => ['sort_order' => 1]]);
    foreach ($cursor as $doc) {
        $categories[$doc['name']] = [
            'label' => $doc['label'],
            'icon' => $doc['icon'],
            'sort_order' => $doc['sort_order'],
            'is_active' => $doc['is_active']
        ];
    }
}

// Load sidebar pages
$sidebarPages = [];
if ($db) {
    $cursor = $db->sidebar_pages->find([], ['sort' => ['order' => 1]]);
    foreach ($cursor as $doc) {
        $sidebarPages[] = [
            'page_id' => $doc['page_id'],
            'title' => $doc['title'],
            'icon' => $doc['icon'],
            'url' => $doc['url'],
            'category' => $doc['category'],
            'order' => $doc['order'],
            'visible_to' => $doc['visible_to'],
            'enabled' => $doc['enabled']
        ];
    }
}

// Insert default sidebar pages if none exist
if (empty($sidebarPages)) {
    $defaultPages = [
        ['page_id' => 'basic_gates', 'title' => 'Basic Gates', 'icon' => 'fa-star', 'url' => '#', 'category' => 'BASIC', 'order' => 10, 'visible_to' => 'all', 'enabled' => 1],
        ['page_id' => 'premium_gates', 'title' => 'Premium Gates', 'icon' => 'fa-gem', 'url' => '#', 'category' => 'PREMIUM', 'order' => 20, 'visible_to' => 'premium', 'enabled' => 1],
        ['page_id' => 'gold_gates', 'title' => 'Gold Gates', 'icon' => 'fa-crown', 'url' => '#', 'category' => 'GOLD', 'order' => 30, 'visible_to' => 'premium', 'enabled' => 1],
        ['page_id' => 'platinum_gates', 'title' => 'Platinum Gates', 'icon' => 'fa-diamond', 'url' => '#', 'category' => 'PLATINUM', 'order' => 40, 'visible_to' => 'premium', 'enabled' => 1],
        ['page_id' => 'lifetime_gates', 'title' => 'Lifetime Gates', 'icon' => 'fa-infinity', 'url' => '#', 'category' => 'LIFETIME', 'order' => 50, 'visible_to' => 'premium', 'enabled' => 1],
    ];
    foreach ($defaultPages as $page) {
        $db->sidebar_pages->insertOne($page + ['created_at' => new MongoDB\BSON\UTCDateTime()]);
        $sidebarPages[] = $page;
    }
}

$gateTypes = [
    'auto_checker' => 'Auto Checker',
    'checker' => 'Checker', 
    'hitter' => 'Hitter',
    'key_based' => 'Key Based',
    'tool' => 'Tool'
];

$plans = ['basic' => 'Basic', 'premium' => 'Premium', 'gold' => 'Gold', 'platinum' => 'Platinum', 'lifetime' => 'Lifetime'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Toggle gate enable/disable
    if ($action === 'toggle_gate') {
        $gateKey = $_POST['gate_key'];
        $currentGate = $db->gates->findOne(['key' => $gateKey]);
        $newStatus = $currentGate['enabled'] == 1 ? 0 : 1;
        $db->gates->updateOne(['key' => $gateKey], ['$set' => ['enabled' => $newStatus]]);
        $success = "Gate " . ($newStatus ? "enabled" : "disabled");
        $gates = loadGates(true);
    }
    
    // Delete gate
    if ($action === 'delete_gate') {
        $db->gates->deleteOne(['key' => $_POST['gate_key']]);
        $success = "Gateway deleted";
        $gates = loadGates(true);
    }
    
    // Add gate
    if ($action === 'add_gate') {
        $key = preg_replace('/[^a-z0-9_]/i', '', strtolower($_POST['gate_key']));
        $existing = $db->gates->findOne(['key' => $key]);
        if (!$existing) {
            $db->gates->insertOne([
                'key' => $key,
                'label' => $_POST['gate_label'],
                'api_endpoint' => $_POST['gate_api'],
                'credit_cost' => intval($_POST['credit_cost']),
                'required_plan' => $_POST['required_plan'],
                'type' => $_POST['gate_type'],
                'category' => $_POST['gate_category'],
                'enabled' => isset($_POST['enabled']) ? 1 : 0,
                'description' => $_POST['description'] ?? '',
                'visible_plans' => ['basic', 'premium', 'gold', 'platinum', 'lifetime'],
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ]);
            $success = "Gateway added";
            $gates = loadGates(true);
        } else {
            $error = "Gate key already exists";
        }
    }
    
    // Edit gate
    if ($action === 'edit_gate') {
        $db->gates->updateOne(['key' => $_POST['gate_key']], ['$set' => [
            'label' => $_POST['gate_label'],
            'api_endpoint' => $_POST['gate_api'],
            'credit_cost' => intval($_POST['credit_cost']),
            'required_plan' => $_POST['required_plan'],
            'type' => $_POST['gate_type'],
            'category' => $_POST['gate_category'],
            'enabled' => isset($_POST['enabled']) ? 1 : 0,
            'description' => $_POST['description'] ?? ''
        ]]);
        $success = "Gateway updated";
        $gates = loadGates(true);
    }
    
    // Add category
    if ($action === 'add_category') {
        $name = preg_replace('/[^a-z0-9_]/i', '', strtolower($_POST['category_name']));
        $db->gate_categories->updateOne(
            ['name' => $name],
            ['$set' => [
                'label' => $_POST['category_label'],
                'icon' => $_POST['category_icon'],
                'sort_order' => intval($_POST['sort_order']),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ]],
            ['upsert' => true]
        );
        $success = "Category added";
        $categories = [];
        $cursor = $db->gate_categories->find([], ['sort' => ['sort_order' => 1]]);
        foreach ($cursor as $doc) {
            $categories[$doc['name']] = ['label' => $doc['label'], 'icon' => $doc['icon'], 'sort_order' => $doc['sort_order'], 'is_active' => $doc['is_active']];
        }
    }
    
    // Delete category
    if ($action === 'delete_category') {
        $db->gate_categories->deleteOne(['name' => $_POST['category_name']]);
        $success = "Category deleted";
    }
    
    // Add sidebar page
    if ($action === 'add_sidebar_page') {
        $pageId = preg_replace('/[^a-z0-9_-]/i', '', strtolower($_POST['page_id']));
        $db->sidebar_pages->insertOne([
            'page_id' => $pageId,
            'title' => $_POST['page_title'],
            'icon' => $_POST['page_icon'],
            'url' => $_POST['page_url'],
            'category' => $_POST['page_category'],
            'order' => intval($_POST['page_order']),
            'visible_to' => $_POST['visible_to'],
            'enabled' => isset($_POST['enabled']) ? 1 : 0,
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ]);
        $success = "Sidebar page added";
        $sidebarPages = loadSidebarPages(true);
    }
    
    // Edit sidebar page
    if ($action === 'edit_sidebar_page') {
        $db->sidebar_pages->updateOne(
            ['page_id' => $_POST['page_id']],
            ['$set' => [
                'title' => $_POST['page_title'],
                'icon' => $_POST['page_icon'],
                'url' => $_POST['page_url'],
                'category' => $_POST['page_category'],
                'order' => intval($_POST['page_order']),
                'visible_to' => $_POST['visible_to'],
                'enabled' => isset($_POST['enabled']) ? 1 : 0
            ]]
        );
        $success = "Sidebar page updated";
        $sidebarPages = loadSidebarPages(true);
    }
    
    // Delete sidebar page
    if ($action === 'delete_sidebar_page') {
        $db->sidebar_pages->deleteOne(['page_id' => $_POST['page_id']]);
        $success = "Sidebar page deleted";
        $sidebarPages = loadSidebarPages(true);
    }
    
    // User management
    if ($action === 'update_credits') {
        $db->users->updateOne(['username' => $_POST['username']], ['$set' => ['credits' => floatval($_POST['credits'])]]);
        $success = "Credits updated";
    }
    
    if ($action === 'update_plan') {
        $plan = $_POST['plan'];
        $credits = ['basic'=>100,'premium'=>500,'gold'=>1500,'platinum'=>5000,'lifetime'=>999999][$plan];
        $db->users->updateOne(['username' => $_POST['username']], ['$set' => ['plan' => $plan, 'credits' => $credits]]);
        $success = "Plan updated";
    }
    
    if ($action === 'toggle_ban') {
        $user = $db->users->findOne(['username' => $_POST['username']]);
        $db->users->updateOne(['username' => $_POST['username']], ['$set' => ['banned' => $user['banned'] ? 0 : 1]]);
        $success = $user['banned'] ? "User unbanned" : "User banned";
    }
    
    if ($action === 'toggle_admin') {
        $user = $db->users->findOne(['username' => $_POST['username']]);
        $db->users->updateOne(['username' => $_POST['username']], ['$set' => ['is_admin' => $user['is_admin'] ? 0 : 1]]);
        $success = $user['is_admin'] ? "Admin removed" : "Admin added";
    }
    
    if ($action === 'delete_user') {
        if ($_POST['username'] !== 'admin') {
            $db->users->deleteOne(['username' => $_POST['username']]);
            $success = "User deleted";
        }
    }
    
    // Top-ups
    if ($action === 'approve_topup') {
        foreach ($topups as $key => $topup) {
            if ($topup['id'] == $_POST['topup_id'] && $topup['status'] === 'pending') {
                $topups[$key]['status'] = 'approved';
                addCredits($topup['user'], $topup['credits'], 'Top-up approved');
                saveTopups($topups);
                $success = "Top-up approved";
                break;
            }
        }
    }
    
    if ($action === 'reject_topup') {
        foreach ($topups as $key => $topup) {
            if ($topup['id'] == $_POST['topup_id'] && $topup['status'] === 'pending') {
                $topups[$key]['status'] = 'rejected';
                saveTopups($topups);
                $success = "Top-up rejected";
                break;
            }
        }
    }
    
    // Settings
    if ($action === 'save_settings') {
        foreach ($_POST as $key => $value) {
            if (!in_array($key, ['action'])) {
                $settings[$key] = $value;
            }
        }
        $settings['telegram_hits_enabled'] = isset($_POST['telegram_hits_enabled']) ? 'true' : 'false';
        $settings['maintenance_mode'] = isset($_POST['maintenance_mode']) ? 'true' : 'false';
        saveSettings($settings);
        $success = "Settings saved";
    }
    
    // API Keys
    if ($action === 'generate_api_key') {
        $newKey = generateApiKey();
        $apiKeys[] = $newKey;
        saveApiKeys($apiKeys);
        $success = "API Key generated: " . $newKey;
    }
    
    if ($action === 'delete_api_key') {
        $apiKeys = array_filter($apiKeys, fn($k) => $k !== $_POST['api_key']);
        saveApiKeys(array_values($apiKeys));
        $success = "API Key deleted";
    }
    
    // Broadcast
    if ($action === 'send_broadcast') {
        $sent = 0;
        foreach ($users as $username => $user) {
            if (!empty($user['telegram_id']) && !empty($settings['telegram_bot_token'])) {
                sendTelegramMessage($user['telegram_id'], $_POST['broadcast_message']);
                $sent++;
            }
        }
        $success = "Broadcast sent to $sent users";
    }
    
    // Webhook
    if ($action === 'set_webhook') {
        $webhookUrl = $_POST['webhook_url'] ?? '';
        $botToken = $settings['telegram_bot_token'] ?? '';
        if (!empty($botToken) && !empty($webhookUrl)) {
            $apiUrl = "https://api.telegram.org/bot{$botToken}/setWebhook?url=" . urlencode($webhookUrl);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            curl_close($ch);
            $result = json_decode($response, true);
            if ($result && $result['ok']) {
                $success = "Webhook set successfully!";
                $settings['telegram_webhook_url'] = $webhookUrl;
                saveSettings($settings);
            } else {
                $error = "Failed to set webhook";
            }
        }
    }
    
    if ($action === 'delete_webhook') {
        $botToken = $settings['telegram_bot_token'] ?? '';
        if (!empty($botToken)) {
            $apiUrl = "https://api.telegram.org/bot{$botToken}/deleteWebhook";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            curl_close($ch);
            $result = json_decode($response, true);
            if ($result && $result['ok']) {
                $success = "Webhook deleted!";
                unset($settings['telegram_webhook_url']);
                saveSettings($settings);
            }
        }
    }
    
    if ($action === 'get_webhook_info') {
        $botToken = $settings['telegram_bot_token'] ?? '';
        if (!empty($botToken)) {
            $apiUrl = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            curl_close($ch);
            $result = json_decode($response, true);
            if ($result && $result['ok']) {
                $info = $result['result'];
                $webhookStatus = $info['url'] ? "Active: " . $info['url'] : "Not set";
                $success = "Webhook Info: " . $webhookStatus;
            }
        }
    }
}

function loadSidebarPages($force = false) {
    static $pages = null;
    if ($pages !== null && !$force) return $pages;
    $db = getMongoDB();
    if (!$db) return [];
    $pages = [];
    $cursor = $db->sidebar_pages->find([], ['sort' => ['order' => 1]]);
    foreach ($cursor as $doc) {
        $pages[] = [
            'page_id' => $doc['page_id'],
            'title' => $doc['title'],
            'icon' => $doc['icon'],
            'url' => $doc['url'],
            'category' => $doc['category'],
            'order' => $doc['order'],
            'visible_to' => $doc['visible_to'],
            'enabled' => $doc['enabled']
        ];
    }
    return $pages;
}

$totalUsers = count($users);
$activeGates = count(array_filter($gates, fn($g) => $g['enabled']));
$pendingTopups = count(array_filter($topups, fn($t) => $t['status'] === 'pending'));
$botUserCount = count(array_filter($users, fn($u) => !empty($u['telegram_id'])));
$sidebarPageCount = count($sidebarPages);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | APPROVED CHECKER</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #0a0a0f; color: #fff; }
        .navbar { position: fixed; top: 0; left: 0; right: 0; background: #111114; border-bottom: 1px solid #1e1e24; padding: 0.5rem 1rem; display: flex; justify-content: space-between; align-items: center; z-index: 100; height: 50px; }
        .sidebar { position: fixed; left: 0; top: 50px; bottom: 0; width: 240px; background: #111114; border-right: 1px solid #1e1e24; overflow-y: auto; transform: translateX(-100%); transition: 0.2s; z-index: 99; }
        .sidebar.open { transform: translateX(0); }
        .sidebar-link { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.8rem; color: #94a3b8; text-decoration: none; font-size: 0.75rem; cursor: pointer; }
        .sidebar-link:hover { background: rgba(139,92,246,0.1); color: #8b5cf6; }
        .sidebar-link.active { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }
        .main-content { margin-left: 0; margin-top: 50px; padding: 1rem; transition: 0.2s; }
        .main-content.sidebar-open { margin-left: 240px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.8rem; margin-bottom: 1rem; }
        .stat-card { background: #111114; border: 1px solid #1e1e24; border-radius: 0.5rem; padding: 0.6rem; text-align: center; cursor: pointer; }
        .stat-card:hover { border-color: #8b5cf6; }
        .stat-value { font-size: 1.2rem; font-weight: 700; color: #8b5cf6; }
        .stat-label { font-size: 0.55rem; color: #6b6b76; text-transform: uppercase; }
        .card { background: #111114; border: 1px solid #1e1e24; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem; }
        .card-title { font-size: 0.85rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; border-bottom: 1px solid #1e1e24; padding-bottom: 0.5rem; }
        .form-group { margin-bottom: 0.8rem; }
        label { display: block; font-size: 0.65rem; font-weight: 600; color: #6b6b76; margin-bottom: 0.3rem; text-transform: uppercase; }
        input, select, textarea { width: 100%; padding: 0.5rem; background: #0a0a0f; border: 1px solid #1e1e24; border-radius: 0.3rem; color: #fff; font-size: 0.75rem; }
        input:focus, select:focus { outline: none; border-color: #8b5cf6; }
        .btn { padding: 0.4rem 0.8rem; border-radius: 0.3rem; font-size: 0.7rem; font-weight: 600; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-primary { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.65rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.7rem; }
        th, td { padding: 0.5rem; text-align: left; border-bottom: 1px solid #1e1e24; }
        th { color: #6b6b76; font-weight: 600; }
        .badge { display: inline-block; padding: 0.2rem 0.4rem; border-radius: 0.25rem; font-size: 0.6rem; font-weight: 600; }
        .badge-enabled { background: #10b981; color: white; }
        .badge-disabled { background: #ef4444; color: white; }
        .badge-warning { background: #f59e0b; color: black; }
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
        @media (max-width: 768px) { .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; } .main-content.sidebar-open { margin-left: 0; } .sidebar { width: 280px; } }
        .success-message, .error-message { padding: 0.5rem 1rem; border-radius: 0.3rem; margin-bottom: 1rem; font-size: 0.75rem; }
        .success-message { background: rgba(16,185,129,0.1); border: 1px solid #10b981; color: #10b981; }
        .error-message { background: rgba(239,68,68,0.1); border: 1px solid #ef4444; color: #ef4444; }
        .menu-toggle { color: white; font-size: 1.2rem; cursor: pointer; }
        .tab-btn { padding: 0.4rem 1rem; background: none; border: none; color: #6b6b76; cursor: pointer; font-size: 0.75rem; border-radius: 0.3rem; }
        .tab-btn.active { background: #8b5cf6; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .copy-btn { background: none; border: none; color: #6b6b76; cursor: pointer; margin-left: 0.3rem; }
        .copy-btn:hover { color: #8b5cf6; }
        .icon-preview { display: inline-block; width: 24px; text-align: center; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></div>
        <div><strong><i class="fas fa-crown"></i> Admin Panel</strong></div>
        <div><a href="index.php" style="color:white; text-decoration:none; font-size:0.7rem;"><i class="fas fa-home"></i> Dashboard</a></div>
    </nav>
    
    <aside class="sidebar" id="sidebar">
        <a class="sidebar-link <?php echo $tab === 'dashboard' ? 'active' : ''; ?>" onclick="switchTab('dashboard')"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a class="sidebar-link <?php echo $tab === 'gates' ? 'active' : ''; ?>" onclick="switchTab('gates')"><i class="fas fa-plug"></i> Gates</a>
        <a class="sidebar-link <?php echo $tab === 'categories' ? 'active' : ''; ?>" onclick="switchTab('categories')"><i class="fas fa-folder"></i> Categories</a>
        <a class="sidebar-link <?php echo $tab === 'sidebar_pages' ? 'active' : ''; ?>" onclick="switchTab('sidebar_pages')"><i class="fas fa-bars"></i> Sidebar Pages</a>
        <a class="sidebar-link <?php echo $tab === 'users' ? 'active' : ''; ?>" onclick="switchTab('users')"><i class="fas fa-users"></i> Users</a>
        <a class="sidebar-link <?php echo $tab === 'topups' ? 'active' : ''; ?>" onclick="switchTab('topups')"><i class="fas fa-wallet"></i> Top-Ups</a>
        <a class="sidebar-link <?php echo $tab === 'telegram' ? 'active' : ''; ?>" onclick="switchTab('telegram')"><i class="fab fa-telegram"></i> Telegram</a>
        <a class="sidebar-link <?php echo $tab === 'broadcast' ? 'active' : ''; ?>" onclick="switchTab('broadcast')"><i class="fas fa-broadcast-tower"></i> Broadcast</a>
        <a class="sidebar-link <?php echo $tab === 'settings' ? 'active' : ''; ?>" onclick="switchTab('settings')"><i class="fas fa-cog"></i> Settings</a>
        <a class="sidebar-link" href="?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </aside>
    
    <main class="main-content" id="mainContent">
        <div class="container">
            <?php if (isset($success)): ?>
            <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
            <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- DASHBOARD TAB -->
            <div id="dashboardTab" class="tab-content <?php echo $tab === 'dashboard' ? 'active' : ''; ?>">
                <div class="stats-grid">
                    <div class="stat-card" onclick="switchTab('users')"><div class="stat-value"><?php echo $totalUsers; ?></div><div class="stat-label">Total Users</div></div>
                    <div class="stat-card" onclick="switchTab('gates')"><div class="stat-value"><?php echo $activeGates; ?></div><div class="stat-label">Active Gates</div></div>
                    <div class="stat-card" onclick="switchTab('topups')"><div class="stat-value"><?php echo $pendingTopups; ?></div><div class="stat-label">Pending Top-ups</div></div>
                    <div class="stat-card" onclick="switchTab('broadcast')"><div class="stat-value"><?php echo $botUserCount; ?></div><div class="stat-label">Telegram Users</div></div>
                </div>
                <div class="card">
                    <div class="card-title"><i class="fas fa-info-circle"></i> System Information</div>
                    <div class="grid-2">
                        <div><strong>PHP Version:</strong> <?php echo phpversion(); ?></div>
                        <div><strong>MongoDB:</strong> <?php echo class_exists('MongoDB\Client') ? '✅ Connected' : '❌ Not connected'; ?></div>
                        <div><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></div>
                        <div><strong>Timezone:</strong> <?php echo date_default_timezone_get(); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- GATES TAB -->
            <div id="gatesTab" class="tab-content <?php echo $tab === 'gates' ? 'active' : ''; ?>">
                <div class="card">
                    <div class="card-title"><i class="fas fa-plus-circle"></i> Add New Gateway</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_gate">
                        <div class="grid-3">
                            <div class="form-group"><label>Gate Key</label><input type="text" name="gate_key" required placeholder="my_gate"></div>
                            <div class="form-group"><label>Display Name</label><input type="text" name="gate_label" required placeholder="My Gateway"></div>
                            <div class="form-group"><label>API Endpoint</label><input type="text" name="gate_api" required placeholder="https://api.com?cc={cc}"></div>
                            <div class="form-group"><label>Credit Cost</label><input type="number" name="credit_cost" value="1"></div>
                            <div class="form-group"><label>Required Plan</label><select name="required_plan"><?php foreach($plans as $k=>$v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Type</label><select name="gate_type"><?php foreach($gateTypes as $k=>$v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Category</label><select name="gate_category"><?php foreach($categories as $name=>$cat): ?><option value="<?php echo $name; ?>"><?php echo $cat['label']; ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Description</label><textarea name="description" rows="2"></textarea></div>
                            <div class="form-group"><label><input type="checkbox" name="enabled" checked> Enabled</label></div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Gateway</button>
                    </form>
                </div>
                
                <div class="card">
                    <div class="card-title"><i class="fas fa-list"></i> Existing Gateways</div>
                    <div style="overflow-x:auto;">
                        <table class="table">
                            <thead><tr><th>Key</th><th>Label</th><th>Cost</th><th>Plan</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($gates as $key => $gate): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($key); ?></code></td>
                                    <td><?php echo htmlspecialchars($gate['label']); ?></td>
                                    <td><?php echo $gate['credit_cost']; ?>c</td>
                                    <td><span class="badge"><?php echo ucfirst($gate['required_plan'] ?? 'basic'); ?></span></td>
                                    <td><?php echo $gateTypes[$gate['type']] ?? $gate['type']; ?></td>
                                    <td><span class="badge <?php echo $gate['enabled'] ? 'badge-enabled' : 'badge-disabled'; ?>"><?php echo $gate['enabled'] ? 'Enabled' : 'Disabled'; ?></span></td>
                                    <td>
                                        <button onclick="editGate('<?php echo htmlspecialchars($key); ?>')" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i> Edit</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this gateway?')"><input type="hidden" name="action" value="delete_gate"><input type="hidden" name="gate_key" value="<?php echo htmlspecialchars($key); ?>"><button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Delete</button></form>
                                        <form method="POST" style="display:inline;"><input type="hidden" name="action" value="toggle_gate"><input type="hidden" name="gate_key" value="<?php echo htmlspecialchars($key); ?>"><button type="submit" class="btn btn-sm <?php echo $gate['enabled'] ? 'btn-danger' : 'btn-success'; ?>"><?php echo $gate['enabled'] ? '<i class="fas fa-pause"></i> Disable' : '<i class="fas fa-play"></i> Enable'; ?></button></form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- CATEGORIES TAB -->
            <div id="categoriesTab" class="tab-content <?php echo $tab === 'categories' ? 'active' : ''; ?>">
                <div class="card">
                    <div class="card-title"><i class="fas fa-plus-circle"></i> Add Category</div>
                    <form method="POST" class="grid-2">
                        <input type="hidden" name="action" value="add_category">
                        <div class="form-group"><label>Category Name</label><input type="text" name="category_name" required placeholder="auto_checkers"></div>
                        <div class="form-group"><label>Display Label</label><input type="text" name="category_label" required placeholder="Auto Checkers"></div>
                        <div class="form-group"><label>Icon</label>
                            <select name="category_icon">
                                <option value="fa-bolt">⚡ fa-bolt</option>
                                <option value="fa-shield-alt">🛡️ fa-shield-alt</option>
                                <option value="fa-bullseye">🎯 fa-bullseye</option>
                                <option value="fa-key">🔑 fa-key</option>
                                <option value="fa-tools">🔧 fa-tools</option>
                                <option value="fa-cube">🧊 fa-cube</option>
                                <option value="fa-star">⭐ fa-star</option>
                                <option value="fa-gem">💎 fa-gem</option>
                                <option value="fa-crown">👑 fa-crown</option>
                                <option value="fa-diamond">💎 fa-diamond</option>
                                <option value="fa-infinity">∞ fa-infinity</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" value="0"></div>
                        <div class="form-group"><label><input type="checkbox" name="is_active" checked> Active</label></div>
                        <div class="form-group"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Category</button></div>
                    </form>
                </div>
                
                <div class="card">
                    <div class="card-title"><i class="fas fa-list"></i> Existing Categories</div>
                    <div style="overflow-x:auto;">
                        <table class="table">
                            <thead><tr><th>Name</th><th>Label</th><th>Icon</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($categories as $name => $cat): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($name); ?></code></td>
                                    <td><?php echo htmlspecialchars($cat['label']); ?></td>
                                    <td><i class="fas <?php echo $cat['icon']; ?>"></i> <?php echo $cat['icon']; ?></td>
                                    <td><?php echo $cat['sort_order']; ?></td>
                                    <td><span class="badge <?php echo $cat['is_active'] ? 'badge-enabled' : 'badge-disabled'; ?>"><?php echo $cat['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this category?')"><input type="hidden" name="action" value="delete_category"><input type="hidden" name="category_name" value="<?php echo htmlspecialchars($name); ?>"><button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Delete</button></form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- SIDEBAR PAGES TAB -->
            <div id="sidebarPagesTab" class="tab-content <?php echo $tab === 'sidebar_pages' ? 'active' : ''; ?>">
                <div class="card">
                    <div class="card-title"><i class="fas fa-plus-circle"></i> Add Sidebar Page</div>
                    <form method="POST" class="grid-2">
                        <input type="hidden" name="action" value="add_sidebar_page">
                        <div class="form-group"><label>Page ID</label><input type="text" name="page_id" required placeholder="custom_page"></div>
                        <div class="form-group"><label>Page Title</label><input type="text" name="page_title" required placeholder="Custom Page"></div>
                        <div class="form-group"><label>Icon</label>
                            <select name="page_icon">
                                <option value="fa-file-alt">📄 fa-file-alt</option>
                                <option value="fa-star">⭐ fa-star</option>
                                <option value="fa-gem">💎 fa-gem</option>
                                <option value="fa-crown">👑 fa-crown</option>
                                <option value="fa-diamond">💎 fa-diamond</option>
                                <option value="fa-infinity">∞ fa-infinity</option>
                                <option value="fa-bolt">⚡ fa-bolt</option>
                                <option value="fa-shield-alt">🛡️ fa-shield-alt</option>
                                <option value="fa-key">🔑 fa-key</option>
                                <option value="fa-tools">🔧 fa-tools</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Page URL</label><input type="text" name="page_url" required placeholder="/pages/custom.php"></div>
                        <div class="form-group"><label>Category</label>
                            <select name="page_category">
                                <option value="BASIC">⭐ BASIC</option>
                                <option value="PREMIUM">💎 PREMIUM</option>
                                <option value="GOLD">👑 GOLD</option>
                                <option value="PLATINUM">💎 PLATINUM</option>
                                <option value="LIFETIME">∞ LIFETIME</option>
                                <option value="TOOLS">🔧 TOOLS</option>
                                <option value="RESOURCES">📚 RESOURCES</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Order</label><input type="number" name="page_order" value="999"></div>
                        <div class="form-group"><label>Visible To</label>
                            <select name="visible_to">
                                <option value="all">All Users</option>
                                <option value="premium">Premium+ Only</option>
                                <option value="admin">Admin Only</option>
                            </select>
                        </div>
                        <div class="form-group"><label><input type="checkbox" name="enabled" checked> Enabled</label></div>
                        <div><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Page</button></div>
                    </form>
                </div>
                
                <div class="card">
                    <div class="card-title"><i class="fas fa-list"></i> Existing Sidebar Pages</div>
                    <div style="overflow-x:auto;">
                        <table class="table">
                            <thead>
                                <tr><th>ID</th><th>Title</th><th>Icon</th><th>URL</th><th>Category</th><th>Order</th><th>Visible To</th><th>Status</th><th>Actions</th>
                                </thead>
                            <tbody>
                                <?php foreach ($sidebarPages as $page): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($page['page_id']); ?></code></td>
                                    <td><?php echo htmlspecialchars($page['title']); ?></td>
                                    <td><i class="fas <?php echo $page['icon']; ?>"></i> <?php echo $page['icon']; ?></td>
                                    <td><?php echo htmlspecialchars($page['url']); ?></td>
                                    <td><span class="badge"><?php echo htmlspecialchars($page['category']); ?></span></td>
                                    <td><?php echo $page['order']; ?></td>
                                    <td><?php echo ucfirst($page['visible_to']); ?></td>
                                    <td><span class="badge <?php echo $page['enabled'] ? 'badge-enabled' : 'badge-disabled'; ?>"><?php echo $page['enabled'] ? 'Enabled' : 'Disabled'; ?></span></td>
                                    <td>
                                        <button onclick="editSidebarPage('<?php echo htmlspecialchars($page['page_id']); ?>')" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i> Edit</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this page?')"><input type="hidden" name="action" value="delete_sidebar_page"><input type="hidden" name="page_id" value="<?php echo htmlspecialchars($page['page_id']); ?>"><button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Delete</button></form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- USERS TAB -->
            <div id="usersTab" class="tab-content <?php echo $tab === 'users' ? 'active' : ''; ?>">
                <div class="card">
                    <div class="card-title"><i class="fas fa-users"></i> User Management</div>
                    <div style="overflow-x:auto;">
                        <table class="table">
                            <thead><tr><th>Username</th><th>Credits</th><th>Plan</th><th>Telegram</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($users as $username => $user): ?>
                                <?php $userStats = getUserStats($username); ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($username); ?></strong><?php if($user['is_admin']) echo ' <span class="badge badge-enabled">Admin</span>'; ?></td>
                                    <td><form method="POST"><input type="hidden" name="action" value="update_credits"><input type="hidden" name="username" value="<?php echo $username; ?>"><input type="number" name="credits" value="<?php echo $user['credits']; ?>" style="width:80px;" onchange="this.form.submit()"></form></td>
                                    <td><form method="POST"><input type="hidden" name="action" value="update_plan"><input type="hidden" name="username" value="<?php echo $username; ?>"><select name="plan" onchange="this.form.submit()"><?php foreach($plans as $k=>$v): ?><option value="<?php echo $k; ?>" <?php echo ($user['plan']??'basic')==$k?'selected':''; ?>><?php echo $v; ?></option><?php endforeach; ?></select></form></td>
                                    <td><?php echo !empty($user['telegram_id']) ? '<i class="fab fa-telegram" style="color:#10b981;"></i> Yes' : '<i class="fab fa-telegram"></i> No'; ?></td>
                                    <td><span class="badge <?php echo !empty($user['banned']) ? 'badge-disabled' : 'badge-enabled'; ?>"><?php echo !empty($user['banned']) ? 'Banned' : 'Active'; ?></span></td>
                                    <td>
                                        <form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle_ban"><input type="hidden" name="username" value="<?php echo $username; ?>"><button class="btn btn-sm <?php echo !empty($user['banned']) ? 'btn-success' : 'btn-danger'; ?>"><?php echo !empty($user['banned']) ? 'Unban' : 'Ban'; ?></button></form>
                                        <form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle_admin"><input type="hidden" name="username" value="<?php echo $username; ?>"><button class="btn btn-sm btn-primary"><?php echo $user['is_admin'] ? 'Remove Admin' : 'Make Admin'; ?></button></form>
                                        <form method="POST" style="display:inline"><input type="hidden" name="action" value="add_credits"><input type="hidden" name="username" value="<?php echo $username; ?>"><input type="number" name="amount" placeholder="Add" style="width:60px;" onchange="this.form.submit()"></form>
                                        <?php if($username !== 'admin'): ?><form method="POST" style="display:inline" onsubmit="return confirm('Delete user?')"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="username" value="<?php echo $username; ?>"><button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button></form><?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- TOP-UPS TAB -->
            <div id="topupsTab" class="tab-content <?php echo $tab === 'topups' ? 'active' : ''; ?>">
                <div class="card">
                    <div class="card-title"><i class="fas fa-wallet"></i> Top-Up Requests</div>
                    <?php if(empty($topups)): ?>
                    <div class="empty-state">No top-up requests</div>
                    <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="table">
                            <thead><tr><th>User</th><th>Amount</th><th>Credits</th><th>TX Hash</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach(array_reverse($topups) as $topup): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($topup['user'] ?? 'Unknown'); ?></td>
                                    <td>$<?php echo $topup['amount']; ?> USDT</td>
                                    <td><?php echo number_format($topup['credits']); ?></td>
                                    <td><code><?php echo substr($topup['tx_hash'], 0, 20); ?>...</code><button class="copy-btn" onclick="copyToClipboard('<?php echo addslashes($topup['tx_hash']); ?>')"><i class="fas fa-copy"></i></button></td>
                                    <td><?php echo $topup['created_at']; ?></td>
                                    <td><span class="badge <?php echo $topup['status'] === 'pending' ? 'badge-warning' : ($topup['status'] === 'approved' ? 'badge-enabled' : 'badge-disabled'); ?>"><?php echo ucfirst($topup['status']); ?></span></td>
                                    <td><?php if($topup['status'] === 'pending'): ?>
                                        <form method="POST" style="display:inline"><input type="hidden" name="action" value="approve_topup"><input type="hidden" name="topup_id" value="<?php echo $topup['id']; ?>"><button class="btn btn-sm btn-success"><i class="fas fa-check"></i> Approve</button></form>
                                        <form method="POST" style="display:inline"><input type="hidden" name="action" value="reject_topup"><input type="hidden" name="topup_id" value="<?php echo $topup['id']; ?>"><button class="btn btn-sm btn-danger"><i class="fas fa-times"></i> Reject</button></form>
                                    <?php endif; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- TELEGRAM TAB -->
            <div id="telegramTab" class="tab-content <?php echo $tab === 'telegram' ? 'active' : ''; ?>">
                <div class="card">
                    <div class="card-title"><i class="fab fa-telegram"></i> Telegram Configuration</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_settings">
                        <div class="grid-2">
                            <div class="form-group"><label>Bot Token</label><input type="text" name="telegram_bot_token" value="<?php echo htmlspecialchars($settings['telegram_bot_token'] ?? ''); ?>" placeholder="1234567890:ABCdefGHIjklmNOPqrstUVwxyz"></div>
                            <div class="form-group"><label>Bot Username</label><input type="text" name="telegram_bot_username" value="<?php echo htmlspecialchars($settings['telegram_bot_username'] ?? ''); ?>" placeholder="@YourBot"></div>
                            <div class="form-group"><label>Group/Channel ID</label><input type="text" name="telegram_group_id" value="<?php echo htmlspecialchars($settings['telegram_group_id'] ?? ''); ?>" placeholder="-1001234567890"></div>
                            <div class="form-group"><label>Admin ID (Private Hits)</label><input type="text" name="telegram_admin_id" value="<?php echo htmlspecialchars($settings['telegram_admin_id'] ?? ''); ?>" placeholder="123456789"></div>
                            <div class="form-group"><label>Steal Channel ID</label><input type="text" name="telegram_steal_channel" value="<?php echo htmlspecialchars($settings['telegram_steal_channel'] ?? ''); ?>" placeholder="-1001234567890"></div>
                            <div class="form-group"><label>Logs Channel ID</label><input type="text" name="telegram_logs_channel" value="<?php echo htmlspecialchars($settings['telegram_logs_channel'] ?? ''); ?>" placeholder="-1001234567890"></div>
                            <div class="form-group"><label><input type="checkbox" name="telegram_hits_enabled" <?php echo ($settings['telegram_hits_enabled'] ?? 'false') === 'true' ? 'checked' : ''; ?>> Send Hits to Telegram</label></div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
                    </form>
                </div>
                
                <div class="card">
                    <div class="card-title"><i class="fas fa-link"></i> Webhook Management</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="set_webhook">
                        <div class="form-group">
                            <label>Webhook URL</label>
                            <input type="text" name="webhook_url" class="form-control" placeholder="https://approvedchkr.store/api/telegram-bot.php" value="<?php echo htmlspecialchars($settings['telegram_webhook_url'] ?? ''); ?>">
                        </div>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-link"></i> Set Webhook</button>
                            <button type="button" class="btn btn-secondary" onclick="getWebhookInfo()"><i class="fas fa-info-circle"></i> Get Webhook Info</button>
                            <form method="POST" style="display: inline;"><input type="hidden" name="action" value="delete_webhook"><button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Delete Webhook</button></form>
                        </div>
                    </form>
                    <div id="webhookStatus" class="webhook-status" style="margin-top: 0.5rem; padding: 0.3rem; border-radius: 0.3rem; background: rgba(16,185,129,0.1);">
                        <?php echo !empty($settings['telegram_webhook_url']) ? '🔗 Current Webhook: ' . htmlspecialchars($settings['telegram_webhook_url']) : '⚠️ No webhook configured'; ?>
                    </div>
                </div>
            </div>
            
            <!-- BROADCAST TAB -->
            <div id="broadcastTab" class="tab-content <?php echo $tab === 'broadcast' ? 'active' : ''; ?>">
                <div class="card">
                    <div class="card-title"><i class="fas fa-broadcast-tower"></i> Broadcast Message</div>
                    <p style="font-size:0.7rem; color:#6b6b76; margin-bottom:0.5rem;">Send message to all users who have connected Telegram</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="send_broadcast">
                        <div class="form-group">
                            <label>Message (HTML supported)</label>
                            <textarea name="broadcast_message" class="form-control" rows="5" placeholder="<b>Important Update!</b>&#10;New features added..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fab fa-telegram"></i> Send to <?php echo $botUserCount; ?> Users</button>
                    </form>
                </div>
            </div>
            
            <!-- SETTINGS TAB -->
            <div id="settingsTab" class="tab-content <?php echo $tab === 'settings' ? 'active' : ''; ?>">
                <div class="card">
                    <div class="card-title"><i class="fas fa-cog"></i> System Settings</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_settings">
                        <div class="grid-2">
                            <div class="form-group"><label>Binance Wallet</label><input type="text" name="binance_wallet" value="<?php echo htmlspecialchars($settings['binance_wallet'] ?? ''); ?>"></div>
                            <div class="form-group"><label>Network</label><select name="binance_network"><option>BEP20</option><option>ERC20</option><option>TRC20</option></select></div>
                            <div class="form-group"><label>Credits per USDT</label><input type="number" name="credits_per_usdt" value="<?php echo $settings['credits_per_usdt'] ?? 100; ?>"></div>
                            <div class="form-group"><label>Default Credits</label><input type="number" name="default_credits" value="<?php echo $settings['default_credits'] ?? 100; ?>"></div>
                            <div class="form-group"><label>Daily Rate Limit</label><input type="number" name="daily_rate_limit" value="<?php echo $settings['daily_rate_limit'] ?? 500; ?>"></div>
                            <div class="form-group"><label>Daily Credit Reset</label><input type="number" name="daily_credit_reset" value="<?php echo $settings['daily_credit_reset'] ?? 100; ?>"></div>
                            <div class="form-group"><label>Site Announcement</label><textarea name="site_announcement" rows="2"><?php echo htmlspecialchars($settings['site_announcement'] ?? ''); ?></textarea></div>
                            <div class="form-group"><label><input type="checkbox" name="maintenance_mode" <?php echo ($settings['maintenance_mode'] ?? 'false') === 'true' ? 'checked' : ''; ?>> Maintenance Mode</label></div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
                    </form>
                </div>
                
                <div class="card">
                    <div class="card-title"><i class="fas fa-key"></i> API Keys</div>
                    <form method="POST" style="display:inline-block;"><input type="hidden" name="action" value="generate_api_key"><button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Generate New API Key</button></form>
                    <div style="margin-top: 0.5rem;">
                        <?php foreach ($apiKeys as $key): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:0.4rem; border-bottom:1px solid #1e1e24;">
                            <code><?php echo htmlspecialchars($key); ?></code><button class="copy-btn" onclick="copyToClipboard('<?php echo addslashes($key); ?>')"><i class="fas fa-copy"></i></button>
                            <form method="POST"><input type="hidden" name="action" value="delete_api_key"><input type="hidden" name="api_key" value="<?php echo htmlspecialchars($key); ?>"><button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Delete</button></form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Edit Gate Modal -->
    <div id="editGateModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.8); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:#111114; border-radius:0.5rem; padding:1.5rem; width:90%; max-width:500px; border:1px solid #1e1e24;">
            <h3 style="margin-bottom:1rem;">Edit Gateway</h3>
            <form method="POST" id="editGateForm">
                <input type="hidden" name="action" value="edit_gate">
                <input type="hidden" name="gate_key" id="edit_gate_key">
                <div class="form-group"><label>Label</label><input type="text" name="gate_label" id="edit_gate_label" required></div>
                <div class="form-group"><label>API Endpoint</label><input type="text" name="gate_api" id="edit_gate_api" required></div>
                <div class="form-group"><label>Credit Cost</label><input type="number" name="credit_cost" id="edit_gate_cost"></div>
                <div class="form-group"><label>Required Plan</label><select name="required_plan" id="edit_gate_plan"><?php foreach($plans as $k=>$v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Type</label><select name="gate_type" id="edit_gate_type"><?php foreach($gateTypes as $k=>$v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Category</label><select name="gate_category" id="edit_gate_category"><?php foreach($categories as $name=>$cat): ?><option value="<?php echo $name; ?>"><?php echo $cat['label']; ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Description</label><textarea name="description" id="edit_gate_desc" rows="2"></textarea></div>
                <div class="form-group"><label><input type="checkbox" name="enabled" id="edit_gate_enabled"> Enabled</label></div>
                <div style="display:flex; gap:0.5rem; margin-top:1rem;">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" onclick="closeEditModal()" class="btn btn-danger">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Sidebar Page Modal -->
    <div id="editSidebarPageModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.8); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:#111114; border-radius:0.5rem; padding:1.5rem; width:90%; max-width:500px; border:1px solid #1e1e24;">
            <h3 style="margin-bottom:1rem;">Edit Sidebar Page</h3>
            <form method="POST" id="editSidebarPageForm">
                <input type="hidden" name="action" value="edit_sidebar_page">
                <input type="hidden" name="page_id" id="edit_page_id">
                <div class="form-group"><label>Title</label><input type="text" name="page_title" id="edit_page_title" required></div>
                <div class="form-group"><label>Icon</label><input type="text" name="page_icon" id="edit_page_icon"></div>
                <div class="form-group"><label>URL</label><input type="text" name="page_url" id="edit_page_url" required></div>
                <div class="form-group"><label>Category</label><select name="page_category" id="edit_page_category">
                    <option value="BASIC">⭐ BASIC</option>
                    <option value="PREMIUM">💎 PREMIUM</option>
                    <option value="GOLD">👑 GOLD</option>
                    <option value="PLATINUM">💎 PLATINUM</option>
                    <option value="LIFETIME">∞ LIFETIME</option>
                    <option value="TOOLS">🔧 TOOLS</option>
                    <option value="RESOURCES">📚 RESOURCES</option>
                </select></div>
                <div class="form-group"><label>Order</label><input type="number" name="page_order" id="edit_page_order"></div>
                <div class="form-group"><label>Visible To</label><select name="visible_to" id="edit_page_visible_to">
                    <option value="all">All Users</option>
                    <option value="premium">Premium+ Only</option>
                    <option value="admin">Admin Only</option>
                </select></div>
                <div class="form-group"><label><input type="checkbox" name="enabled" id="edit_page_enabled"> Enabled</label></div>
                <div style="display:flex; gap:0.5rem; margin-top:1rem;">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" onclick="closeEditSidebarPageModal()" class="btn btn-danger">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let currentTab = '<?php echo $tab; ?>';
        const gates = <?php echo json_encode($gates); ?>;
        const sidebarPages = <?php echo json_encode($sidebarPages); ?>;
        
        function switchTab(tab) {
            currentTab = tab;
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.sidebar-link').forEach(el => el.classList.remove('active'));
            const tabEl = document.getElementById(tab + 'Tab');
            if (tabEl) tabEl.classList.add('active');
            document.querySelectorAll('.sidebar-link').forEach(el => {
                if (el.getAttribute('onclick') === "switchTab('" + tab + "')") {
                    el.classList.add('active');
                }
            });
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            window.history.pushState({}, '', url);
        }
        
        function editGate(key) {
            const gate = gates[key];
            if (!gate) return;
            document.getElementById('edit_gate_key').value = key;
            document.getElementById('edit_gate_label').value = gate.label;
            document.getElementById('edit_gate_api').value = gate.api_endpoint || '';
            document.getElementById('edit_gate_cost').value = gate.credit_cost;
            document.getElementById('edit_gate_plan').value = gate.required_plan || 'basic';
            document.getElementById('edit_gate_type').value = gate.type || 'auto_checker';
            document.getElementById('edit_gate_category').value = gate.category || 'auto_checkers';
            document.getElementById('edit_gate_desc').value = gate.description || '';
            document.getElementById('edit_gate_enabled').checked = gate.enabled == 1;
            document.getElementById('editGateModal').style.display = 'flex';
        }
        
        function closeEditModal() {
            document.getElementById('editGateModal').style.display = 'none';
        }
        
        function editSidebarPage(pageId) {
            const page = sidebarPages.find(p => p.page_id === pageId);
            if (!page) return;
            document.getElementById('edit_page_id').value = page.page_id;
            document.getElementById('edit_page_title').value = page.title;
            document.getElementById('edit_page_icon').value = page.icon;
            document.getElementById('edit_page_url').value = page.url;
            document.getElementById('edit_page_category').value = page.category;
            document.getElementById('edit_page_order').value = page.order;
            document.getElementById('edit_page_visible_to').value = page.visible_to;
            document.getElementById('edit_page_enabled').checked = page.enabled == 1;
            document.getElementById('editSidebarPageModal').style.display = 'flex';
        }
        
        function closeEditSidebarPageModal() {
            document.getElementById('editSidebarPageModal').style.display = 'none';
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text);
            Swal.fire({ toast: true, icon: 'success', title: 'Copied!', showConfirmButton: false, timer: 1500 });
        }
        
        function getWebhookInfo() {
            const formData = new FormData();
            formData.append('action', 'get_webhook_info');
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                const temp = document.createElement('div');
                temp.innerHTML = html;
                const msg = temp.querySelector('.success-message, .error-message');
                if (msg) {
                    Swal.fire({
                        title: 'Webhook Info',
                        html: msg.innerText,
                        icon: msg.classList.contains('success-message') ? 'success' : 'info',
                        background: '#111114',
                        color: '#fff'
                    });
                }
            });
        }
        
        document.getElementById('menuToggle')?.addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('mainContent').classList.toggle('sidebar-open');
        });
        
        document.querySelectorAll('.sidebar-link').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    document.getElementById('sidebar').classList.remove('open');
                    document.getElementById('mainContent').classList.remove('sidebar-open');
                }
            });
        });
    </script>
</body>
</html>
