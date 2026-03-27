<?php
require_once 'includes/config.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$tab = $_GET['tab'] ?? 'users';
$settings = loadSettings();
$gates = loadGates();
$users = loadUsers();
$topups = loadTopups();
$adverts = loadAdverts();
$apiKeys = loadApiKeys();
$creditHistory = loadCreditHistory();

// Load ingroup data properly
$ingroupConfig = loadIngroupConfig();
$ingroupGates = loadIngroupGates();
$ingroupGroups = loadIngroupGroups();

// Plan definitions
$plans = [
    'basic' => ['name' => 'Basic', 'credits' => 100, 'daily_limit' => 50, 'color' => '#6b7280', 'gates' => ['auth', 'charge', 'vbv']],
    'premium' => ['name' => 'Premium', 'credits' => 500, 'daily_limit' => 200, 'color' => '#f59e0b', 'gates' => ['auth', 'charge', 'auth-charge', 'stripe-auth', 'shopify', 'razorpay', 'vbv']],
    'gold' => ['name' => 'Gold', 'credits' => 1500, 'daily_limit' => 500, 'color' => '#fbbf24', 'gates' => ['auth', 'charge', 'auth-charge', 'stripe-auth', 'shopify', 'razorpay', 'stripe-checkout', 'stripe-invoice', 'stripe-inbuilt', 'vbv']],
    'platinum' => ['name' => 'Platinum', 'credits' => 5000, 'daily_limit' => 1500, 'color' => '#a855f7', 'gates' => ['all']],
    'lifetime' => ['name' => 'Lifetime', 'credits' => 999999, 'daily_limit' => 999999, 'color' => '#ec4899', 'gates' => ['all']]
];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Update user plan
    if ($action === 'update_plan') {
        $username = $_POST['username'] ?? '';
        $newPlan = $_POST['plan'] ?? 'basic';
        if (isset($users[$username]) && isset($plans[$newPlan])) {
            $users[$username]['plan'] = $newPlan;
            $users[$username]['credits'] = $plans[$newPlan]['credits'];
            saveUsers($users);
            $success = "Updated {$username} to {$plans[$newPlan]['name']} plan with {$plans[$newPlan]['credits']} credits";
        }
    }
    
    // Update user credits
    if ($action === 'update_credits') {
        $username = $_POST['username'] ?? '';
        $credits = floatval($_POST['credits'] ?? 0);
        if (isset($users[$username])) {
            $users[$username]['credits'] = $credits;
            saveUsers($users);
            $success = "Updated credits for {$username} to {$credits}";
        }
    }
    
    // Ban/Unban user
    if ($action === 'toggle_ban') {
        $username = $_POST['username'] ?? '';
        if (isset($users[$username])) {
            $users[$username]['banned'] = !($users[$username]['banned'] ?? false);
            saveUsers($users);
            $success = $users[$username]['banned'] ? "Banned {$username}" : "Unbanned {$username}";
        }
    }
    
    // Toggle admin role
    if ($action === 'toggle_admin') {
        $username = $_POST['username'] ?? '';
        if (isset($users[$username])) {
            $users[$username]['is_admin'] = !($users[$username]['is_admin'] ?? false);
            saveUsers($users);
            $success = $users[$username]['is_admin'] ? "Made {$username} admin" : "Removed admin from {$username}";
        }
    }
    
    // Approve top-up
    if ($action === 'approve_topup') {
        $topupId = $_POST['topup_id'] ?? '';
        foreach ($topups as $key => $topup) {
            if ($topup['id'] == $topupId && $topup['status'] === 'pending') {
                $topups[$key]['status'] = 'approved';
                $topups[$key]['reviewed_at'] = date('Y-m-d H:i:s');
                $topups[$key]['reviewed_by'] = $_SESSION['user']['name'] ?? 'Admin';
                addCredits($topup['user'], $topup['credits'], 'Top-up approved');
                saveTopups($topups);
                $success = "Top-up approved for {$topup['user']}";
                break;
            }
        }
    }
    
    // Reject top-up
    if ($action === 'reject_topup') {
        $topupId = $_POST['topup_id'] ?? '';
        foreach ($topups as $key => $topup) {
            if ($topup['id'] == $topupId && $topup['status'] === 'pending') {
                $topups[$key]['status'] = 'rejected';
                $topups[$key]['reviewed_at'] = date('Y-m-d H:i:s');
                $topups[$key]['reviewed_by'] = $_SESSION['user']['name'] ?? 'Admin';
                saveTopups($topups);
                $success = "Top-up rejected for {$topup['user']}";
                break;
            }
        }
    }
    
    // Save gateway settings with plan restrictions
    if ($action === 'save_gates') {
        foreach ($gates as $key => $gate) {
            $gates[$key]['enabled'] = isset($_POST['gate'][$key]);
            $gates[$key]['required_plan'] = $_POST['required_plan'][$key] ?? 'basic';
        }
        saveGates($gates);
        $success = "Gateway settings saved";
    }
    
    // Save system settings
    if ($action === 'save_settings') {
        $settings['binance_wallet'] = $_POST['binance_wallet'] ?? '';
        $settings['binance_network'] = $_POST['binance_network'] ?? 'BEP20';
        $settings['credits_per_usdt'] = floatval($_POST['credits_per_usdt'] ?? 100);
        $settings['default_credits'] = floatval($_POST['default_credits'] ?? 100);
        $settings['daily_rate_limit'] = intval($_POST['daily_rate_limit'] ?? 500);
        $settings['daily_credit_reset'] = floatval($_POST['daily_credit_reset'] ?? 100);
        $settings['site_announcement'] = $_POST['site_announcement'] ?? '';
        $settings['telegram_bot_token'] = $_POST['telegram_bot_token'] ?? '';
        $settings['telegram_group_id'] = $_POST['telegram_group_id'] ?? '';
        $settings['telegram_hits_enabled'] = isset($_POST['telegram_hits_enabled']) ? 'true' : 'false';
        $settings['telegram_bot_username'] = $_POST['telegram_bot_username'] ?? '';
        $settings['maintenance_mode'] = isset($_POST['maintenance_mode']) ? 'true' : 'false';
        saveSettings($settings);
        $success = "System settings saved";
    }
    
    // Generate API key
    if ($action === 'generate_api_key') {
        $newKey = generateApiKey();
        $apiKeys[] = $newKey;
        saveApiKeys($apiKeys);
        $generatedKey = $newKey;
        $success = "New API key generated";
    }
    
    // Delete API key
    if ($action === 'delete_api_key') {
        $keyToDelete = $_POST['api_key'] ?? '';
        $apiKeys = array_filter($apiKeys, function($k) use ($keyToDelete) {
            return $k !== $keyToDelete;
        });
        saveApiKeys(array_values($apiKeys));
        $success = "API key deleted";
    }
    
    // Create advert with instant broadcast
    if ($action === 'create_advert') {
        $advert = [
            'id' => uniqid(),
            'title' => $_POST['title'] ?? '',
            'content' => $_POST['content'] ?? '',
            'image_url' => $_POST['image_url'] ?? '',
            'link_url' => $_POST['link_url'] ?? '',
            'position' => $_POST['position'] ?? 'home',
            'is_active' => isset($_POST['is_active']),
            'created_at' => date('Y-m-d H:i:s')
        ];
        $adverts[] = $advert;
        saveAdverts($adverts);
        
        // INSTANT BROADCAST to all users via Telegram if enabled
        if (!empty($settings['telegram_bot_token']) && !empty($settings['telegram_group_id'])) {
            $message = "📢 <b>NEW ANNOUNCEMENT</b>\n\n";
            $message .= "🔔 <b>" . htmlspecialchars($advert['title']) . "</b>\n\n";
            $message .= htmlspecialchars($advert['content']) . "\n\n";
            if (!empty($advert['link_url'])) {
                $message .= "🔗 <a href=\"" . htmlspecialchars($advert['link_url']) . "\">Click here</a>\n\n";
            }
            $message .= "🔥 <b>APPROVED CHECKER</b>";
            
            // Send to all users with Telegram IDs
            $sentCount = 0;
            foreach ($users as $username => $user) {
                if (!empty($user['telegram_id'])) {
                    sendTelegramMessage($user['telegram_id'], $message);
                    $sentCount++;
                }
            }
            $success = "Advert created and broadcast to {$sentCount} Telegram users!";
        } else {
            $success = "Advert created! (Telegram not configured for broadcast)";
        }
    }
    
    // Toggle advert
    if ($action === 'toggle_advert') {
        $advertId = $_POST['advert_id'] ?? '';
        foreach ($adverts as $key => $ad) {
            if ($ad['id'] == $advertId) {
                $adverts[$key]['is_active'] = !$ad['is_active'];
                saveAdverts($adverts);
                $success = "Advert toggled";
                break;
            }
        }
    }
    
    // Delete advert
    if ($action === 'delete_advert') {
        $advertId = $_POST['advert_id'] ?? '';
        $adverts = array_filter($adverts, function($ad) use ($advertId) {
            return $ad['id'] != $advertId;
        });
        saveAdverts(array_values($adverts));
        $success = "Advert deleted";
    }
    
    // FIXED: Toggle maintenance for page with array check
    if ($action === 'toggle_page_maintenance') {
        $pagePath = $_POST['page_path'] ?? '';
        $key = 'maint_' . $pagePath;
        
        // Ensure maintenance_pages is an array
        if (!isset($settings['maintenance_pages']) || !is_array($settings['maintenance_pages'])) {
            $settings['maintenance_pages'] = [];
        }
        
        $settings['maintenance_pages'][$key] = isset($_POST['enabled']) ? 'true' : 'false';
        saveSettings($settings);
        $success = "Page maintenance toggled";
    }
    
    // Ingroup bot settings
    if ($action === 'save_ingroup') {
        $ingroupConfig = [
            'bot_token' => $_POST['ingroup_bot_token'] ?? '',
            'is_active' => isset($_POST['ingroup_active']),
            'rate_limit_per_user' => intval($_POST['rate_limit'] ?? 10),
            'mass_max_cards' => intval($_POST['mass_max_cards'] ?? 25),
            'premium_only_mass' => isset($_POST['premium_only_mass']),
            'buy_message' => $_POST['buy_message'] ?? '',
            'admin_telegram_ids' => $_POST['admin_telegram_ids'] ?? ''
        ];
        saveIngroupConfig($ingroupConfig);
        $success = "Ingroup bot settings saved";
    }
    
    // Toggle ingroup gate
    if ($action === 'toggle_ingroup_gate') {
        $gateKey = $_POST['gate_key'] ?? '';
        foreach ($ingroupGates as $key => $gate) {
            if ($gate['gate_key'] == $gateKey) {
                $ingroupGates[$key]['is_enabled'] = !$gate['is_enabled'];
                saveIngroupGates($ingroupGates);
                $success = "Gate toggled";
                break;
            }
        }
    }
    
    // Add ingroup group
    if ($action === 'add_ingroup_group') {
        $groupId = $_POST['group_id'] ?? '';
        $groupName = $_POST['group_name'] ?? '';
        $ingroupGroups[] = [
            'id' => uniqid(),
            'group_id' => $groupId,
            'group_name' => $groupName,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s')
        ];
        saveIngroupGroups($ingroupGroups);
        $success = "Group added";
    }
    
    // Delete ingroup group
    if ($action === 'delete_ingroup_group') {
        $groupId = $_POST['group_id'] ?? '';
        $ingroupGroups = array_filter($ingroupGroups, function($g) use ($groupId) {
            return $g['id'] != $groupId;
        });
        saveIngroupGroups(array_values($ingroupGroups));
        $success = "Group deleted";
    }
    
    // Toggle ingroup group
    if ($action === 'toggle_ingroup_group') {
        $groupId = $_POST['group_id'] ?? '';
        foreach ($ingroupGroups as $key => $g) {
            if ($g['id'] == $groupId) {
                $ingroupGroups[$key]['is_active'] = !$g['is_active'];
                saveIngroupGroups($ingroupGroups);
                $success = "Group toggled";
                break;
            }
        }
    }
    
    // Send broadcast
    if ($action === 'send_broadcast') {
        $message = $_POST['broadcast_message'] ?? '';
        if (!empty($message)) {
            $sent = 0;
            $failed = 0;
            foreach ($users as $username => $user) {
                if (!empty($user['telegram_id']) && !empty($settings['telegram_bot_token'])) {
                    $result = sendTelegramMessage($user['telegram_id'], $message);
                    if ($result) {
                        $sent++;
                    } else {
                        $failed++;
                    }
                }
            }
            $broadcastResult = ['sent' => $sent, 'failed' => $failed, 'total' => count($users)];
            $success = "Broadcast sent to {$sent} users";
        }
    }
}

// Get stats for hit stealing dashboard
$totalUsers = count($users);
$activeUsers = count(array_filter((array)$users, fn($u) => empty($u['banned'])));
$bannedUsers = $totalUsers - $activeUsers;
$pendingTopups = count(array_filter((array)$topups, fn($t) => $t['status'] === 'pending'));
$totalChecks = count($creditHistory);
$approvedChecks = count(array_filter((array)$creditHistory, fn($h) => stripos($h['reason'], 'approved') !== false || stripos($h['reason'], 'charged') !== false));
$activeGates = count(array_filter($gates, fn($g) => $g['enabled']));

// FIXED: Safely get maintenance pages
$maintenancePages = isset($settings['maintenance_pages']) && is_array($settings['maintenance_pages']) ? $settings['maintenance_pages'] : [];
$maintCount = is_array($maintenancePages) ? count(array_filter($maintenancePages, fn($v) => $v === 'true')) : 0;

// Get hit stealing data - last 24 hours hits by gate
$last24h = strtotime('-24 hours');
$gateHits = [];
foreach ($creditHistory as $h) {
    if (strtotime($h['created_at']) > $last24h && (stripos($h['reason'], 'approved') !== false || stripos($h['reason'], 'charged') !== false)) {
        $gate = explode(' ', $h['reason'])[0] ?? 'unknown';
        if (!isset($gateHits[$gate])) $gateHits[$gate] = 0;
        $gateHits[$gate]++;
    }
}
arsort($gateHits);
$topGates = array_slice($gateHits, 0, 10, true);

// Page paths for maintenance
$ALL_PAGES = [
    ['path' => '/checker/auto-shopify', 'label' => 'Shopify', 'category' => 'Auto Checkers'],
    ['path' => '/checker/stripe-auth', 'label' => 'Stripe Auth', 'category' => 'Auto Checkers'],
    ['path' => '/checker/razorpay', 'label' => 'Razorpay', 'category' => 'Auto Checkers'],
    ['path' => '/checker/auth', 'label' => 'Auth', 'category' => 'Checkers'],
    ['path' => '/checker/charge', 'label' => 'Charge', 'category' => 'Checkers'],
    ['path' => '/checker/auth-charge', 'label' => 'Auth+Charge', 'category' => 'Checkers'],
    ['path' => '/checker/stripe-checkout', 'label' => 'Stripe Checkout', 'category' => 'Hitters'],
    ['path' => '/checker/stripe-invoice', 'label' => 'Stripe Invoice', 'category' => 'Hitters'],
    ['path' => '/checker/stripe-inbuilt', 'label' => 'Stripe Inbuilt', 'category' => 'Hitters'],
    ['path' => '/checker/key-stripe', 'label' => 'Stripe', 'category' => 'Key Based'],
    ['path' => '/checker/key-paypal', 'label' => 'PayPal', 'category' => 'Key Based'],
    ['path' => '/tools/address-gen', 'label' => 'Address Generator', 'category' => 'Tools'],
    ['path' => '/tools/bin-lookup', 'label' => 'Bin Lookup', 'category' => 'Tools'],
    ['path' => '/tools/cc-cleaner', 'label' => 'CC Cleaner', 'category' => 'Tools'],
    ['path' => '/tools/cc-generator', 'label' => 'CC Generator', 'category' => 'Tools'],
    ['path' => '/tools/proxy-checker', 'label' => 'Proxy Checker', 'category' => 'Tools'],
    ['path' => '/tools/vbv-checker', 'label' => 'VBV Checker', 'category' => 'Tools'],
];

$pagesByCategory = [];
foreach ($ALL_PAGES as $page) {
    $pagesByCategory[$page['category']][] = $page;
}

$botUserCount = count(array_filter((array)$users, fn($u) => !empty($u['telegram_id'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Admin Panel | APPROVED CHECKER</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary-bg: #0a0e27;
            --secondary-bg: #131937;
            --card-bg: #1a1f3a;
            --accent-blue: #3b82f6;
            --accent-purple: #8b5cf6;
            --accent-green: #10b981;
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --border-color: #1e293b;
            --error: #ef4444;
            --warning: #f59e0b;
            --success: #22c55e;
            --font-size-base: 14px;
        }
        [data-theme="light"] {
            --primary-bg: #f8fafc;
            --secondary-bg: #ffffff;
            --card-bg: #ffffff;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --border-color: #e2e8f0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--primary-bg);
            color: var(--text-primary);
            min-height: 100vh;
            font-size: var(--font-size-base);
        }
        /* Font size controls */
        .font-size-control {
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 1000;
            background: var(--card-bg);
            border-radius: 30px;
            padding: 8px 12px;
            display: flex;
            gap: 8px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .font-size-control button {
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 4px 12px;
            cursor: pointer;
            font-weight: bold;
            color: var(--text-primary);
        }
        .font-size-control button:hover {
            background: var(--accent-blue);
            color: white;
        }
        .font-size-control span {
            font-size: 12px;
            color: var(--text-secondary);
        }
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(10,14,39,0.95);
            backdrop-filter: blur(10px);
            padding: 0.5rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            height: 50px;
        }
        [data-theme="light"] .navbar { background: rgba(248,250,252,0.95); }
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
            font-weight: 700;
            background: linear-gradient(135deg, #06b6d4, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .menu-toggle {
            color: var(--text-primary);
            font-size: 1rem;
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: rgba(255,255,255,0.1);
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.2rem 0.5rem;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
        }
        .user-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #3b82f6;
        }
        .user-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.75rem;
        }
        .theme-toggle {
            width: 36px;
            height: 18px;
            background: var(--secondary-bg);
            border-radius: 10px;
            cursor: pointer;
            border: 1px solid var(--border-color);
            position: relative;
        }
        .theme-toggle-slider {
            position: absolute;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            left: 2px;
            top: 1px;
            transition: transform 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.45rem;
        }
        [data-theme="light"] .theme-toggle-slider { transform: translateX(16px); }
        .sidebar {
            position: fixed;
            left: 0;
            top: 50px;
            bottom: 0;
            width: 240px;
            background: var(--card-bg);
            border-right: 1px solid var(--border-color);
            padding: 0.75rem 0;
            z-index: 999;
            overflow-y: auto;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        .sidebar.open { transform: translateX(0); }
        .sidebar-menu { list-style: none; }
        .sidebar-item { margin: 0.2rem 0.4rem; }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.6rem;
            color: var(--text-secondary);
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s;
        }
        .sidebar-link:hover { background: rgba(59,130,246,0.1); color: #3b82f6; }
        .sidebar-link.active {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
        }
        .sidebar-badge {
            font-size: 0.6rem;
            padding: 1px 5px;
            border-radius: 10px;
            margin-left: auto;
        }
        .badge-danger { background: #ef4444; color: white; }
        .badge-warning { background: #f59e0b; color: white; }
        .badge-success { background: #22c55e; color: white; }
        .main-content {
            margin-left: 0;
            margin-top: 50px;
            padding: 1rem;
            transition: margin-left 0.3s ease;
        }
        .main-content.sidebar-open { margin-left: 240px; }
        @media (max-width: 768px) {
            .main-content.sidebar-open { margin-left: 0; }
            .sidebar { width: 70vw; }
        }
        .admin-container { max-width: 1400px; margin: 0 auto; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 0.8rem;
            margin-bottom: 1rem;
        }
        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-value { font-size: 1.3rem; font-weight: 700; margin-bottom: 0.2rem; }
        .stat-label { font-size: 0.6rem; color: var(--text-secondary); text-transform: uppercase; }
        .hit-stealing-card {
            background: linear-gradient(135deg, rgba(139,92,246,0.1), rgba(6,182,212,0.05));
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0.75rem;
            margin-bottom: 1rem;
        }
        .hit-stealing-card h4 {
            font-size: 0.75rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .gate-hit-item {
            display: flex;
            justify-content: space-between;
            padding: 0.3rem 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.7rem;
        }
        .admin-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.3rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.5rem;
        }
        .tab-btn {
            padding: 0.4rem 0.8rem;
            background: transparent;
            border: none;
            border-radius: 6px;
            color: var(--text-secondary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.7rem;
        }
        .tab-btn:hover { background: rgba(59,130,246,0.1); color: #3b82f6; }
        .tab-btn.active {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
        }
        .tab-content { display: none; animation: fadeIn 0.3s ease; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .glass-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid var(--border-color);
            margin-bottom: 1rem;
        }
        .form-group { margin-bottom: 0.75rem; }
        .form-group label {
            display: block;
            margin-bottom: 0.3rem;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
        }
        .form-control {
            width: 100%;
            padding: 0.5rem;
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 0.8rem;
        }
        .form-control:focus { outline: none; border-color: #3b82f6; }
        .btn {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.7rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
        }
        .btn-primary:hover { transform: translateY(-1px); opacity: 0.9; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.65rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.7rem; }
        th, td { padding: 0.5rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { font-weight: 600; color: var(--text-secondary); }
        .badge {
            display: inline-block;
            padding: 0.15rem 0.4rem;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .badge-basic { background: rgba(107,114,128,0.2); color: #9ca3af; }
        .badge-premium { background: rgba(245,158,11,0.2); color: #f59e0b; }
        .badge-gold { background: rgba(251,191,36,0.2); color: #fbbf24; }
        .badge-platinum { background: rgba(168,85,247,0.2); color: #a855f7; }
        .badge-lifetime { background: rgba(236,72,153,0.2); color: #ec4899; }
        .switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 20px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.3s;
            border-radius: 20px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        input:checked + .slider { background: linear-gradient(135deg, #3b82f6, #8b5cf6); }
        input:checked + .slider:before { transform: translateX(20px); }
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
        @media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }
        .empty-state { text-align: center; padding: 1.5rem; color: var(--text-secondary); }
        .empty-state i { font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5; }
        .success-message {
            background: rgba(34,197,94,0.1);
            border: 1px solid rgba(34,197,94,0.3);
            color: #22c55e;
            padding: 0.5rem;
            border-radius: 6px;
            margin-bottom: 0.75rem;
            font-size: 0.7rem;
        }
        select.form-control { cursor: pointer; }
        textarea.form-control { resize: vertical; min-height: 60px; }
    </style>
</head>
<body data-theme="dark">
    <!-- Font Size Control -->
    <div class="font-size-control">
        <button id="fontMinus">A-</button>
        <span id="fontSizeDisplay">14px</span>
        <button id="fontPlus">A+</button>
    </div>

    <nav class="navbar">
        <div class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </div>
        <div class="navbar-brand">
            <i class="fas fa-crown"></i>
            <span>Admin Panel</span>
        </div>
        <div class="navbar-actions">
            <div class="theme-toggle" onclick="toggleTheme()">
                <div class="theme-toggle-slider"><i class="fas fa-moon"></i></div>
            </div>
            <div class="user-info">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user']['name'] ?? 'Admin'); ?>&background=8b5cf6&color=fff&size=64" class="user-avatar">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['user']['name'] ?? 'Admin'); ?></span>
            </div>
        </div>
    </nav>

    <aside class="sidebar" id="sidebar">
        <ul class="sidebar-menu">
            <li class="sidebar-item"><a class="sidebar-link <?php echo $tab === 'users' ? 'active' : ''; ?>" onclick="switchTab('users')"><i class="fas fa-users"></i> Users <span class="sidebar-badge badge-danger"><?php echo $totalUsers; ?></span></a></li>
            <li class="sidebar-item"><a class="sidebar-link <?php echo $tab === 'topups' ? 'active' : ''; ?>" onclick="switchTab('topups')"><i class="fas fa-wallet"></i> Top-Ups <?php if ($pendingTopups > 0): ?><span class="sidebar-badge badge-warning"><?php echo $pendingTopups; ?></span><?php endif; ?></a></li>
            <li class="sidebar-item"><a class="sidebar-link <?php echo $tab === 'gates' ? 'active' : ''; ?>" onclick="switchTab('gates')"><i class="fas fa-plug"></i> Gates <span class="sidebar-badge badge-success"><?php echo $activeGates; ?>/<?php echo count($gates); ?></span></a></li>
            <li class="sidebar-item"><a class="sidebar-link <?php echo $tab === 'pages' ? 'active' : ''; ?>" onclick="switchTab('pages')"><i class="fas fa-file-alt"></i> Pages <?php if ($maintCount > 0): ?><span class="sidebar-badge badge-warning"><?php echo $maintCount; ?></span><?php endif; ?></a></li>
            <li class="sidebar-item"><a class="sidebar-link <?php echo $tab === 'adverts' ? 'active' : ''; ?>" onclick="switchTab('adverts')"><i class="fas fa-ad"></i> Adverts</a></li>
            <li class="sidebar-item"><a class="sidebar-link <?php echo $tab === 'telegram' ? 'active' : ''; ?>" onclick="switchTab('telegram')"><i class="fab fa-telegram"></i> Telegram</a></li>
            <li class="sidebar-item"><a class="sidebar-link <?php echo $tab === 'ingroup' ? 'active' : ''; ?>" onclick="switchTab('ingroup')"><i class="fas fa-robot"></i> Ingroup Bot</a></li>
            <li class="sidebar-item"><a class="sidebar-link <?php echo $tab === 'broadcast' ? 'active' : ''; ?>" onclick="switchTab('broadcast')"><i class="fas fa-broadcast-tower"></i> Broadcast</a></li>
            <li class="sidebar-item"><a class="sidebar-link <?php echo $tab === 'settings' ? 'active' : ''; ?>" onclick="switchTab('settings')"><i class="fas fa-cog"></i> Settings</a></li>
            <li class="sidebar-item"><a href="adminaccess_panel.php?logout=1" class="sidebar-link logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            <li class="sidebar-item"><a href="index.php" class="sidebar-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></li>
        </ul>
    </aside>

    <main class="main-content" id="mainContent">
        <div class="admin-container">
            <?php if (isset($success)): ?>
            <div class="success-message">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <!-- Hit Stealing Dashboard -->
            <div class="hit-stealing-card">
                <h4><i class="fas fa-chart-line" style="color: var(--success);"></i> Hit Stealing Dashboard (Last 24 Hours)</h4>
                <div class="grid-2">
                    <div>
                        <div style="font-size:0.7rem; margin-bottom:0.5rem;"><strong>Top Performing Gates</strong></div>
                        <?php if (empty($topGates)): ?>
                        <div class="empty-state"><i class="fas fa-chart-simple"></i><span>No hits in last 24 hours</span></div>
                        <?php else: ?>
                        <?php foreach ($topGates as $gate => $hits): ?>
                        <div class="gate-hit-item">
                            <span><?php echo htmlspecialchars($gate); ?></span>
                            <span class="badge badge-success"><?php echo $hits; ?> hits</span>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div style="font-size:0.7rem; margin-bottom:0.5rem;"><strong>Total Statistics</strong></div>
                        <div class="gate-hit-item"><span>Total Checks Today</span><span class="badge badge-success"><?php echo $totalChecks; ?></span></div>
                        <div class="gate-hit-item"><span>Approved Hits Today</span><span class="badge badge-success"><?php echo $approvedChecks; ?></span></div>
                        <div class="gate-hit-item"><span>Success Rate</span><span class="badge badge-success"><?php echo $totalChecks > 0 ? round(($approvedChecks / $totalChecks) * 100) : 0; ?>%</span></div>
                        <div class="gate-hit-item"><span>Active Users</span><span class="badge badge-success"><?php echo $activeUsers; ?></span></div>
                    </div>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value"><?php echo $totalUsers; ?></div><div class="stat-label">Total Users</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $activeUsers; ?></div><div class="stat-label">Active Users</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $bannedUsers; ?></div><div class="stat-label">Banned Users</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $pendingTopups; ?></div><div class="stat-label">Pending Top-ups</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $totalChecks; ?></div><div class="stat-label">Total Checks</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $approvedChecks; ?></div><div class="stat-label">Approved Hits</div></div>
            </div>
            
            <!-- USERS TAB -->
            <div id="usersTab" class="tab-content <?php echo $tab === 'users' ? 'active' : ''; ?>">
                <div class="glass-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <h3 style="font-size: 0.9rem;"><i class="fas fa-users"></i> User Management</h3>
                        <input type="text" id="userSearch" placeholder="Search users..." class="form-control" style="width: 180px;">
                    </div>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr><th>User</th><th>Credits</th><th>Plan</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
                            </thead>
                            <tbody id="userTableBody">
                                <?php foreach ($users as $username => $user): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($username); ?></strong><?php if (!empty($user['is_admin'])): ?> <span class="badge badge-success">Admin</span><?php endif; ?></td>
                                    <td><?php echo number_format($user['credits'] ?? 0); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['plan'] ?? 'basic'; ?>">
                                            <?php echo ucfirst($user['plan'] ?? 'Basic'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo !empty($user['banned']) ? '<span class="badge badge-danger">Banned</span>' : '<span class="badge badge-success">Active</span>'; ?></td>
                                    <td><?php echo isset($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : '-'; ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_credits">
                                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                                            <input type="number" name="credits" value="<?php echo $user['credits'] ?? 0; ?>" style="width: 70px;" class="form-control" onchange="this.form.submit()">
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_plan">
                                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                                            <select name="plan" class="form-control" style="width: 80px; display: inline-block;" onchange="this.form.submit()">
                                                <?php foreach ($plans as $planKey => $plan): ?>
                                                <option value="<?php echo $planKey; ?>" <?php echo ($user['plan'] ?? 'basic') === $planKey ? 'selected' : ''; ?>><?php echo $plan['name']; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_admin">
                                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                                            <button type="submit" class="btn btn-sm btn-primary" title="Toggle Admin"><i class="fas fa-user-shield"></i></button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_ban">
                                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                                            <button type="submit" class="btn btn-sm <?php echo !empty($user['banned']) ? 'btn-primary' : 'btn-danger'; ?>" title="<?php echo !empty($user['banned']) ? 'Unban' : 'Ban'; ?>">
                                                <i class="fas <?php echo !empty($user['banned']) ? 'fa-user-check' : 'fa-ban'; ?>"></i>
                                            </button>
                                        </form>
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
                <div class="glass-card">
                    <h3><i class="fas fa-wallet"></i> Top-Up Requests</h3>
                    <?php if (empty($topups)): ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><h3>No top-up requests</h3></div>
                    <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead><tr><th>User</th><th>Amount</th><th>Credits</th><th>TX Hash</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach (array_reverse($topups) as $topup): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($topup['user'] ?? 'Unknown'); ?></td>
                                    <td><?php echo $topup['amount']; ?> USDT</td>
                                    <td><?php echo $topup['credits']; ?></td>
                                    <td><code><?php echo substr($topup['tx_hash'], 0, 20); ?>...</code></td>
                                    <td><?php echo $topup['created_at']; ?></td>
                                    <td><?php if ($topup['status'] === 'approved'): ?><span class="badge badge-success">Approved</span><?php elseif ($topup['status'] === 'rejected'): ?><span class="badge badge-danger">Rejected</span><?php else: ?><span class="badge badge-warning">Pending</span><?php endif; ?></td>
                                    <td>
                                        <?php if ($topup['status'] === 'pending'): ?>
                                        <form method="POST" style="display: inline;"><input type="hidden" name="action" value="approve_topup"><input type="hidden" name="topup_id" value="<?php echo $topup['id']; ?>"><button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-check"></i> Approve</button></form>
                                        <form method="POST" style="display: inline;"><input type="hidden" name="action" value="reject_topup"><input type="hidden" name="topup_id" value="<?php echo $topup['id']; ?>"><button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-times"></i> Reject</button></form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- GATES TAB with Plan Restrictions -->
            <div id="gatesTab" class="tab-content <?php echo $tab === 'gates' ? 'active' : ''; ?>">
                <div class="glass-card">
                    <h3><i class="fas fa-plug"></i> Gateway Management (with Plan Restrictions)</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_gates">
                        <div class="grid-2">
                            <?php foreach ($gates as $key => $gate): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; border-bottom: 1px solid var(--border-color);">
                                <div>
                                    <strong><?php echo htmlspecialchars($gate['label']); ?></strong>
                                    <span class="badge badge-success" style="margin-left: 0.5rem;"><?php echo $gate['type'] ?? 'checker'; ?></span>
                                    <div style="font-size: 0.65rem;">Cost: <?php echo $gate['credit_cost'] ?? 1; ?> credits</div>
                                    <select name="required_plan[<?php echo $key; ?>]" class="form-control" style="width: 100px; margin-top: 0.3rem;">
                                        <?php foreach ($plans as $planKey => $plan): ?>
                                        <option value="<?php echo $planKey; ?>" <?php echo ($gate['required_plan'] ?? 'basic') === $planKey ? 'selected' : ''; ?>><?php echo $plan['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="gate[<?php echo $key; ?>]" <?php echo $gate['enabled'] ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Save Gate Settings</button>
                    </form>
                </div>
            </div>
            
            <!-- PAGES TAB -->
            <div id="pagesTab" class="tab-content <?php echo $tab === 'pages' ? 'active' : ''; ?>">
                <div class="glass-card">
                    <h3><i class="fas fa-file-alt"></i> Page Maintenance Control</h3>
                    <p style="margin-bottom: 0.75rem; font-size: 0.7rem;">Toggle pages between Live and Maintenance mode.</p>
                    <?php foreach ($pagesByCategory as $category => $pages): ?>
                    <h4 style="margin: 0.75rem 0 0.4rem; color: var(--accent-blue); font-size: 0.75rem;"><?php echo $category; ?></h4>
                    <?php foreach ($pages as $page): ?>
                    <?php 
                    // FIXED: Safely check maintenance status
                    $isMaint = isset($settings['maintenance_pages']) && is_array($settings['maintenance_pages']) 
                        ? ($settings['maintenance_pages'][$page['path']] ?? 'false') === 'true' 
                        : false; 
                    ?>
                    <form method="POST" style="display: flex; justify-content: space-between; align-items: center; padding: 0.4rem; border-bottom: 1px solid var(--border-color);">
                        <input type="hidden" name="action" value="toggle_page_maintenance">
                        <input type="hidden" name="page_path" value="<?php echo $page['path']; ?>">
                        <div>
                            <strong style="font-size: 0.75rem;"><?php echo htmlspecialchars($page['label']); ?></strong>
                            <code style="font-size: 0.6rem; margin-left: 0.5rem;"><?php echo $page['path']; ?></code>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.8rem;">
                            <span style="font-size: 0.65rem; color: <?php echo $isMaint ? '#f59e0b' : '#22c55e'; ?>;"><?php echo $isMaint ? 'Maintenance' : 'Live'; ?></span>
                            <label class="switch"><input type="checkbox" name="enabled" <?php echo $isMaint ? 'checked' : ''; ?> onchange="this.form.submit()"><span class="slider"></span></label>
                        </div>
                    </form>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- ADVERTS TAB -->
            <div id="advertsTab" class="tab-content <?php echo $tab === 'adverts' ? 'active' : ''; ?>">
                <div class="glass-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <h3 style="font-size: 0.9rem;"><i class="fas fa-ad"></i> Advertisements</h3>
                        <button onclick="document.getElementById('newAdvertForm').style.display='block'" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Advert</button>
                    </div>
                    
                    <div id="newAdvertForm" style="display: none; margin-bottom: 1rem; padding: 0.8rem; border: 1px solid var(--border-color); border-radius: 8px;">
                        <form method="POST">
                            <input type="hidden" name="action" value="create_advert">
                            <div class="grid-2">
                                <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required></div>
                                <div class="form-group"><label>Position</label><select name="position" class="form-control"><option value="home">Home</option><option value="sidebar">Sidebar</option><option value="checker">Checker</option></select></div>
                                <div class="form-group"><label>Image URL</label><input type="text" name="image_url" class="form-control"></div>
                                <div class="form-group"><label>Link URL</label><input type="text" name="link_url" class="form-control"></div>
                                <div class="form-group"><label>Content</label><textarea name="content" class="form-control" rows="2"></textarea></div>
                                <div class="form-group"><label>Active</label><label class="switch"><input type="checkbox" name="is_active" checked><span class="slider"></span></label></div>
                            </div>
                            <button type="submit" class="btn btn-primary">Create & Broadcast</button>
                            <button type="button" onclick="document.getElementById('newAdvertForm').style.display='none'" class="btn btn-danger">Cancel</button>
                        </form>
                    </div>
                    
                    <?php if (empty($adverts)): ?>
                    <div class="empty-state"><i class="fas fa-ad"></i><h3>No adverts created</h3></div>
                    <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead><tr><th>Title</th><th>Position</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($adverts as $ad): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ad['title']); ?></td>
                                    <td><?php echo $ad['position']; ?></td>
                                    <td><?php echo $ad['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td>
                                    <td><?php echo $ad['created_at']; ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;"><input type="hidden" name="action" value="toggle_advert"><input type="hidden" name="advert_id" value="<?php echo $ad['id']; ?>"><button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-power-off"></i></button></form>
                                        <form method="POST" style="display: inline;"><input type="hidden" name="action" value="delete_advert"><input type="hidden" name="advert_id" value="<?php echo $ad['id']; ?>"><button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button></form>
                                    </td>
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
                <div class="glass-card">
                    <h3><i class="fab fa-telegram"></i> Telegram Settings</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_settings">
                        <div class="grid-2">
                            <div class="form-group"><label>Bot Token</label><input type="text" name="telegram_bot_token" value="<?php echo htmlspecialchars($settings['telegram_bot_token'] ?? ''); ?>" class="form-control"></div>
                            <div class="form-group"><label>Bot Username</label><input type="text" name="telegram_bot_username" value="<?php echo htmlspecialchars($settings['telegram_bot_username'] ?? ''); ?>" class="form-control"></div>
                            <div class="form-group"><label>Group / Channel ID</label><input type="text" name="telegram_group_id" value="<?php echo htmlspecialchars($settings['telegram_group_id'] ?? ''); ?>" class="form-control"></div>
                            <div class="form-group"><label>Enable Hit Notifications</label><label class="switch"><input type="checkbox" name="telegram_hits_enabled" <?php echo ($settings['telegram_hits_enabled'] ?? 'false') === 'true' ? 'checked' : ''; ?>><span class="slider"></span></label></div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Telegram Settings</button>
                    </form>
                    <hr style="margin: 1rem 0;">
                    <h4>Bot Webhook</h4>
                    <button id="setupWebhookBtn" class="btn btn-primary btn-sm"><i class="fas fa-link"></i> Activate Bot Webhook</button>
                    <div id="webhookStatus" style="margin-top: 0.5rem;"></div>
                </div>
            </div>
            
            <!-- INGROUP BOT TAB -->
            <div id="ingroupTab" class="tab-content <?php echo $tab === 'ingroup' ? 'active' : ''; ?>">
                <div class="glass-card">
                    <h3><i class="fas fa-robot"></i> Ingroup CC Checker Bot</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_ingroup">
                        <div class="grid-2">
                            <div class="form-group"><label>Bot Token</label><input type="text" name="ingroup_bot_token" value="<?php echo htmlspecialchars($ingroupConfig['bot_token'] ?? ''); ?>" class="form-control"></div>
                            <div class="form-group"><label>Admin Telegram IDs</label><input type="text" name="admin_telegram_ids" value="<?php echo htmlspecialchars($ingroupConfig['admin_telegram_ids'] ?? ''); ?>" class="form-control"></div>
                            <div class="form-group"><label>Rate Limit</label><input type="number" name="rate_limit" value="<?php echo $ingroupConfig['rate_limit_per_user'] ?? 10; ?>" class="form-control"></div>
                            <div class="form-group"><label>Max Cards per Mass</label><input type="number" name="mass_max_cards" value="<?php echo $ingroupConfig['mass_max_cards'] ?? 25; ?>" class="form-control"></div>
                            <div class="form-group"><label>Premium Only Mass</label><label class="switch"><input type="checkbox" name="premium_only_mass" <?php echo ($ingroupConfig['premium_only_mass'] ?? true) ? 'checked' : ''; ?>><span class="slider"></span></label></div>
                            <div class="form-group"><label>Bot Active</label><label class="switch"><input type="checkbox" name="ingroup_active" <?php echo ($ingroupConfig['is_active'] ?? false) ? 'checked' : ''; ?>><span class="slider"></span></label></div>
                            <div class="form-group"><label>Buy Message</label><textarea name="buy_message" class="form-control" rows="2"><?php echo htmlspecialchars($ingroupConfig['buy_message'] ?? ''); ?></textarea></div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Ingroup Config</button>
                    </form>
                    
                    <hr style="margin: 1rem 0;">
                    <h4>Commands & Gates</h4>
                    <div class="grid-2">
                        <?php foreach ($ingroupGates as $gate): ?>
                        <form method="POST" style="display: flex; justify-content: space-between; align-items: center; padding: 0.4rem;">
                            <input type="hidden" name="action" value="toggle_ingroup_gate"><input type="hidden" name="gate_key" value="<?php echo $gate['gate_key']; ?>">
                            <div><strong style="font-size: 0.7rem;"><?php echo htmlspecialchars($gate['display_name']); ?></strong><div><code style="font-size: 0.6rem;"><?php echo $gate['command']; ?></code> | <code style="font-size: 0.6rem;"><?php echo $gate['mass_command']; ?></code></div></div>
                            <label class="switch"><input type="checkbox" <?php echo $gate['is_enabled'] ? 'checked' : ''; ?> onchange="this.form.submit()"><span class="slider"></span></label>
                        </form>
                        <?php endforeach; ?>
                    </div>
                    
                    <hr style="margin: 1rem 0;">
                    <h4>Approved Groups</h4>
                    <form method="POST" style="display: flex; gap: 0.5rem; margin-bottom: 0.75rem;">
                        <input type="hidden" name="action" value="add_ingroup_group">
                        <input type="text" name="group_id" placeholder="Group ID" class="form-control" style="flex:2;">
                        <input type="text" name="group_name" placeholder="Name" class="form-control" style="flex:1;">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add</button>
                    </form>
                    <div class="grid-2">
                        <?php foreach ($ingroupGroups as $group): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.4rem;">
                            <div><strong style="font-size: 0.7rem;"><?php echo htmlspecialchars($group['group_name'] ?: $group['group_id']); ?></strong><div><code style="font-size: 0.6rem;"><?php echo $group['group_id']; ?></code></div></div>
                            <div style="display: flex; gap: 0.3rem;">
                                <form method="POST" style="display: inline;"><input type="hidden" name="action" value="toggle_ingroup_group"><input type="hidden" name="group_id" value="<?php echo $group['id']; ?>"><label class="switch"><input type="checkbox" <?php echo $group['is_active'] ? 'checked' : ''; ?> onchange="this.form.submit()"><span class="slider"></span></label></form>
                                <form method="POST" style="display: inline;"><input type="hidden" name="action" value="delete_ingroup_group"><input type="hidden" name="group_id" value="<?php echo $group['id']; ?>"><button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button></form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button id="setupIngroupWebhookBtn" class="btn btn-primary btn-sm" style="margin-top: 0.75rem;"><i class="fas fa-link"></i> Activate Ingroup Webhook</button>
                    <div id="ingroupWebhookStatus" style="margin-top: 0.5rem;"></div>
                </div>
            </div>
            
            <!-- BROADCAST TAB -->
            <div id="broadcastTab" class="tab-content <?php echo $tab === 'broadcast' ? 'active' : ''; ?>">
                <div class="glass-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <h3 style="font-size: 0.9rem;"><i class="fas fa-broadcast-tower"></i> Broadcast Message</h3>
                        <span class="badge badge-success"><?php echo $botUserCount; ?> subscribers</span>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="send_broadcast">
                        <div class="form-group"><label>Message (HTML supported)</label><textarea name="broadcast_message" class="form-control" rows="4" placeholder="🔥 <b>New Feature Alert!</b>"></textarea></div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Broadcast</button>
                    </form>
                </div>
            </div>
            
            <!-- SETTINGS TAB -->
            <div id="settingsTab" class="tab-content <?php echo $tab === 'settings' ? 'active' : ''; ?>">
                <div class="glass-card">
                    <h3><i class="fas fa-cog"></i> System Settings</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_settings">
                        <div class="grid-2">
                            <div class="form-group"><label>Binance Wallet</label><input type="text" name="binance_wallet" value="<?php echo htmlspecialchars($settings['binance_wallet'] ?? ''); ?>" class="form-control"></div>
                            <div class="form-group"><label>Network</label><select name="binance_network" class="form-control"><option value="BEP20">BEP20</option><option value="ERC20">ERC20</option><option value="TRC20">TRC20</option></select></div>
                            <div class="form-group"><label>Credits per USDT</label><input type="number" name="credits_per_usdt" value="<?php echo $settings['credits_per_usdt'] ?? 100; ?>" class="form-control"></div>
                            <div class="form-group"><label>Default Credits</label><input type="number" name="default_credits" value="<?php echo $settings['default_credits'] ?? 100; ?>" class="form-control"></div>
                            <div class="form-group"><label>Daily Rate Limit</label><input type="number" name="daily_rate_limit" value="<?php echo $settings['daily_rate_limit'] ?? 500; ?>" class="form-control"></div>
                            <div class="form-group"><label>Daily Credit Reset</label><input type="number" name="daily_credit_reset" value="<?php echo $settings['daily_credit_reset'] ?? 100; ?>" class="form-control"></div>
                            <div class="form-group"><label>Site Announcement</label><textarea name="site_announcement" class="form-control" rows="2"><?php echo htmlspecialchars($settings['site_announcement'] ?? ''); ?></textarea></div>
                            <div class="form-group"><label>Maintenance Mode</label><label class="switch"><input type="checkbox" name="maintenance_mode" <?php echo ($settings['maintenance_mode'] ?? 'false') === 'true' ? 'checked' : ''; ?>><span class="slider"></span></label></div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </form>
                    
                    <hr style="margin: 1rem 0;">
                    <h4>API Keys Management</h4>
                    <form method="POST" style="display: inline-block; margin-bottom: 0.75rem;"><input type="hidden" name="action" value="generate_api_key"><button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-key"></i> Generate New API Key</button></form>
                    <div class="grid-2">
                        <?php foreach ($apiKeys as $key): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.4rem;"><code style="font-size: 0.65rem;"><?php echo htmlspecialchars($key); ?></code><form method="POST"><input type="hidden" name="action" value="delete_api_key"><input type="hidden" name="api_key" value="<?php echo htmlspecialchars($key); ?>"><button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button></form></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        let currentTab = '<?php echo $tab; ?>';
        
        function switchTab(tab) {
            currentTab = tab;
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.sidebar-link').forEach(el => el.classList.remove('active'));
            document.getElementById(tab + 'Tab').classList.add('active');
            document.querySelector(`.sidebar-link[onclick="switchTab('${tab}')"]`).classList.add('active');
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            window.history.pushState({}, '', url);
        }
        
        function toggleTheme() {
            const body = document.body;
            const theme = body.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            document.querySelector('.theme-toggle-slider i').className = theme === 'light' ? 'fas fa-sun' : 'fas fa-moon';
        }
        
        // Font size controls
        let currentFontSize = 14;
        function updateFontSize(size) {
            currentFontSize = Math.min(20, Math.max(10, size));
            document.documentElement.style.setProperty('--font-size-base', currentFontSize + 'px');
            document.getElementById('fontSizeDisplay').innerText = currentFontSize + 'px';
            localStorage.setItem('fontSize', currentFontSize);
        }
        document.getElementById('fontPlus').addEventListener('click', () => updateFontSize(currentFontSize + 1));
        document.getElementById('fontMinus').addEventListener('click', () => updateFontSize(currentFontSize - 1));
        const savedFontSize = localStorage.getItem('fontSize');
        if (savedFontSize) updateFontSize(parseInt(savedFontSize));
        
        document.getElementById('userSearch')?.addEventListener('input', function(e) {
            const search = e.target.value.toLowerCase();
            document.querySelectorAll('#userTableBody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(search) ? '' : 'none';
            });
        });
        
        document.getElementById('setupWebhookBtn')?.addEventListener('click', function() {
            fetch('api/telegram-webhook.php?action=setup', { method: 'POST' }).then(res => res.json()).then(data => {
                document.getElementById('webhookStatus').innerHTML = data.ok ? '<div class="success-message">✅ Webhook activated!</div>' : '<div class="error-message">❌ ' + data.error + '</div>';
            }).catch(() => { document.getElementById('webhookStatus').innerHTML = '<div class="error-message">❌ Failed</div>'; });
        });
        
        document.getElementById('setupIngroupWebhookBtn')?.addEventListener('click', function() {
            fetch('api/telegram-group-webhook.php?action=setup', { method: 'POST' }).then(res => res.json()).then(data => {
                document.getElementById('ingroupWebhookStatus').innerHTML = data.ok ? '<div class="success-message">✅ Webhook activated!</div>' : '<div class="error-message">❌ ' + data.error + '</div>';
            }).catch(() => { document.getElementById('ingroupWebhookStatus').innerHTML = '<div class="error-message">❌ Failed</div>'; });
        });
        
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.body.setAttribute('data-theme', savedTheme);
        document.querySelector('.theme-toggle-slider i').className = savedTheme === 'light' ? 'fas fa-sun' : 'fas fa-moon';
        
        document.getElementById('menuToggle')?.addEventListener('click', function() {
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