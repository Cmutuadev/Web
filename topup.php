<?php
require_once 'includes/config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (isBanned()) {
    session_destroy();
    header('Location: login.php?banned=1');
    exit;
}

$user = $_SESSION['user'] ?? [];
$username = $user['name'] ?? ($user['username'] ?? 'User');
$credits = getUserCredits();
$isAdmin = isAdmin();
$settings = loadSettings();

$walletAddress = $settings['binance_wallet'] ?? '';
$network = $settings['binance_network'] ?? 'BEP20';
$rate = $settings['credits_per_usdt'] ?? 100;
$adminTelegram = $settings['admin_telegram_username'] ?? 'sunilxd';

// Check if Telegram is configured
$telegramConfigured = !empty($settings['telegram_bot_token']) && !empty($settings['telegram_group_id']);

// Plan definitions with features
$plans = [
    ['name' => 'Basic', 'price' => 5, 'credits' => 500, 'color' => '#6b7280', 'icon' => 'fa-star', 'badge' => 'Starter', 'rating' => 4.0, 'features' => ['100 Checks/Day', 'Basic Support', '5 Gates Access']],
    ['name' => 'Premium', 'price' => 15, 'credits' => 1800, 'color' => '#f59e0b', 'icon' => 'fa-gem', 'badge' => 'Popular', 'rating' => 4.5, 'features' => ['500 Checks/Day', 'Priority Support', '10 Gates Access', 'Mass Check']],
    ['name' => 'Gold', 'price' => 30, 'credits' => 4000, 'color' => '#fbbf24', 'icon' => 'fa-crown', 'badge' => 'Best Value', 'rating' => 4.8, 'features' => ['1500 Checks/Day', 'VIP Support', 'All Gates', 'Mass Check', 'API Access']],
    ['name' => 'Platinum', 'price' => 60, 'credits' => 9000, 'color' => '#a855f7', 'icon' => 'fa-diamond', 'badge' => 'Pro', 'rating' => 4.9, 'features' => ['5000 Checks/Day', '24/7 Support', 'All Gates', 'Mass Check', 'API Access', 'Priority']],
    ['name' => 'Lifetime', 'price' => 150, 'credits' => 25000, 'color' => '#ec4899', 'icon' => 'fa-infinity', 'badge' => 'Unlimited', 'rating' => 5.0, 'features' => ['Unlimited Checks', 'Lifetime Support', 'All Gates', 'Mass Check', 'API Access', 'VIP Priority']]
];

// Load topups and check pending requests
$topups = loadTopups();
$userTopups = array_filter($topups, function($t) use ($username) {
    return ($t['user'] ?? '') === $username;
});

$hasPendingRequest = false;
foreach ($userTopups as $request) {
    if (($request['status'] ?? '') === 'pending') {
        $hasPendingRequest = true;
        break;
    }
}

// Handle redeem key
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getMongoDB();
    
    // Handle redeem key
    if (isset($_POST['action']) && $_POST['action'] === 'redeem_key') {
        $key = trim($_POST['redeem_key'] ?? '');
        
        if (empty($key)) {
            $error = "Please enter a redeem key";
        } else {
            // Check if key exists in database
            $stmt = $pdo->prepare("SELECT * FROM redeem_keys WHERE key_code = ? AND status = 'unused'");
            $stmt->execute([$key]);
            $redeemKey = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($redeemKey) {
                // Mark key as used
                $stmt = $pdo->prepare("UPDATE redeem_keys SET status = 'used', used_by = ?, used_at = CURRENT_TIMESTAMP WHERE key_code = ?");
                $stmt->execute([$username, $key]);
                
                // Add credits
                addCredits($username, $redeemKey['credits'], "Redeemed key: " . $key);
                
                // Update plan if premium key
                if ($redeemKey['plan'] && $redeemKey['plan'] !== 'basic') {
                    $users = loadUsers();
                    if (isset($users[$username])) {
                        $users[$username]['plan'] = $redeemKey['plan'];
                        saveUsers($users);
                        $_SESSION['user']['plan'] = $redeemKey['plan'];
                    }
                }
                
                $success = "✅ Key redeemed successfully! You received " . number_format($redeemKey['credits']) . " credits!";
                
                // Refresh user data
                $credits = getUserCredits();
                $userTopups = array_filter($topups, function($t) use ($username) {
                    return ($t['user'] ?? '') === $username;
                });
            } else {
                $error = "❌ Invalid or already used redeem key";
            }
        }
    }
    
    // Handle top-up submission
    if (isset($_POST['action']) && $_POST['action'] === 'submit_topup') {
        if ($hasPendingRequest) {
            $error = "⚠️ You have a pending top-up request. Please wait for admin approval before submitting a new one.";
        } else {
            $planIndex = intval($_POST['plan'] ?? -1);
            $amount = floatval($_POST['amount'] ?? 0);
            $txHash = trim($_POST['tx_hash'] ?? '');
            $selectedPlan = null;
            
            if ($planIndex >= 0 && $planIndex < count($plans)) {
                $selectedPlan = $plans[$planIndex];
                $amount = $selectedPlan['price'];
                $creditsToAdd = $selectedPlan['credits'];
            } elseif ($amount > 0) {
                $creditsToAdd = $amount * $rate;
            } else {
                $error = "Please select a plan or enter a valid amount";
            }
            
            if (empty($txHash)) {
                $error = "Transaction hash required";
            } elseif (!isset($error)) {
                $topups = loadTopups();
                $topups[] = [
                    'id' => uniqid(),
                    'user' => $username,
                    'user_id' => $user['user_id'] ?? null,
                    'amount' => $amount,
                    'credits' => $creditsToAdd,
                    'tx_hash' => $txHash,
                    'status' => 'pending',
                    'plan' => $selectedPlan ? $selectedPlan['name'] : null,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                saveTopups($topups);
                $success = "✅ Top-up request submitted! Admin will review shortly.";
                $hasPendingRequest = true;
                
                // Refresh user topups
                $userTopups = array_filter($topups, function($t) use ($username) {
                    return ($t['user'] ?? '') === $username;
                });
            }
        }
    }
    
    // Handle admin message
    if (isset($_POST['action']) && $_POST['action'] === 'send_message') {
        $message = trim($_POST['message'] ?? '');
        if (!empty($message) && !empty($settings['telegram_bot_token']) && !empty($settings['telegram_group_id'])) {
            $fullMessage = "📨 <b>NEW MESSAGE FROM USER</b>\n\n";
            $fullMessage .= "👤 <b>User:</b> {$username}\n";
            $fullMessage .= "💬 <b>Message:</b>\n{$message}\n\n";
            $fullMessage .= "🕐 <b>Time:</b> " . date('Y-m-d H:i:s') . " UTC";
            
            sendTelegramMessage($settings['telegram_group_id'], $fullMessage);
            $success = "✅ Message sent to admin! They will respond shortly.";
        } else {
            $error = "❌ Telegram is not configured. Please contact admin directly: @{$adminTelegram}";
        }
    }
}

// Refresh data
$topups = loadTopups();
$userTopups = array_filter($topups, function($t) use ($username) {
    return ($t['user'] ?? '') === $username;
});
$hasPendingRequest = false;
foreach ($userTopups as $request) {
    if (($request['status'] ?? '') === 'pending') {
        $hasPendingRequest = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Top Up | APPROVED CHECKER</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg: #0a0a0f;
            --card: #111114;
            --border: #1e1e24;
            --text: #ffffff;
            --text-muted: #6b6b76;
            --primary: #8b5cf6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        [data-theme="light"] {
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #0f172a;
            --text-muted: #64748b;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: var(--card);
            border-bottom: 1px solid var(--border);
            padding: 0.75rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
            height: 60px;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .menu-btn {
            display: none;
            background: none;
            border: none;
            color: var(--text);
            font-size: 1.25rem;
            cursor: pointer;
        }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
        }
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #000000;
            border: 2px solid var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
            color: white;
        }
        .theme-btn {
            background: none;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            padding: 0.5rem;
            cursor: pointer;
            color: var(--text-muted);
        }
        .sidebar {
            position: fixed;
            left: 0;
            top: 60px;
            bottom: 0;
            width: 280px;
            background: var(--card);
            border-right: 1px solid var(--border);
            transform: translateX(-100%);
            transition: transform 0.2s;
            z-index: 99;
            overflow-y: auto;
        }
        .sidebar.open {
            transform: translateX(0);
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }
        .nav-item:hover {
            background: var(--bg);
            color: var(--text);
        }
        .nav-item.active {
            background: linear-gradient(135deg, var(--primary), #7c3aed);
            color: white;
        }
        .main {
            margin-left: 0;
            margin-top: 60px;
            padding: 1.5rem;
            transition: margin-left 0.2s;
            min-height: calc(100vh - 60px);
        }
        .main.sidebar-open {
            margin-left: 280px;
        }
        @media (max-width: 768px) {
            .menu-btn { display: block; }
            .main.sidebar-open { margin-left: 0; }
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .page-subtitle {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }
        /* Plans Grid - Smaller Text */
        .plans-grid-4 {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0.8rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 1024px) {
            .plans-grid-4 {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 640px) {
            .plans-grid-4 {
                grid-template-columns: 1fr;
            }
        }
        .plan-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            padding: 0.8rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        .plan-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
        }
        .plan-card.selected {
            border-color: var(--primary);
            background: rgba(139,92,246,0.1);
        }
        .plan-badge {
            position: absolute;
            top: -8px;
            right: 8px;
            background: linear-gradient(135deg, var(--primary), #7c3aed);
            color: white;
            font-size: 0.55rem;
            padding: 0.15rem 0.5rem;
            border-radius: 20px;
            font-weight: 600;
        }
        .plan-icon {
            font-size: 1.3rem;
            margin-bottom: 0.4rem;
        }
        .plan-name {
            font-size: 0.8rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
        }
        .plan-price {
            font-size: 1rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.2rem;
        }
        .plan-price small {
            font-size: 0.55rem;
            font-weight: normal;
            color: var(--text-muted);
        }
        .plan-credits {
            font-size: 0.6rem;
            color: var(--text-muted);
            margin-bottom: 0.4rem;
        }
        .rating {
            display: flex;
            justify-content: center;
            gap: 0.1rem;
            margin-bottom: 0.4rem;
        }
        .star {
            font-size: 0.6rem;
            color: #ffc107;
        }
        .rating-value {
            font-size: 0.55rem;
            color: var(--text-muted);
            margin-left: 0.2rem;
        }
        .features-list {
            text-align: left;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid var(--border);
        }
        .feature-item {
            font-size: 0.55rem;
            color: var(--text-muted);
            padding: 0.15rem 0;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .feature-item i {
            font-size: 0.5rem;
            color: var(--success);
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            padding: 1rem;
        }
        .card-title {
            font-weight: 600;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        .card-title i {
            color: var(--primary);
        }
        .info-row {
            margin-bottom: 0.8rem;
            padding-bottom: 0.6rem;
            border-bottom: 1px solid var(--border);
        }
        .info-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 0.2rem;
        }
        .info-value {
            font-size: 0.8rem;
            font-weight: 500;
            word-break: break-all;
        }
        .wallet-address {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .wallet-address code {
            flex: 1;
            font-size: 0.65rem;
            background: var(--bg);
            padding: 0.4rem;
            border-radius: 0.5rem;
            word-break: break-all;
        }
        .copy-btn {
            background: none;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            padding: 0.4rem;
            cursor: pointer;
            color: var(--text-muted);
        }
        .copy-btn:hover {
            color: var(--primary);
            border-color: var(--primary);
        }
        .form-group {
            margin-bottom: 0.8rem;
        }
        .form-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 500;
            margin-bottom: 0.4rem;
            color: var(--text-muted);
        }
        .form-control {
            width: 100%;
            padding: 0.6rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            color: var(--text);
            font-size: 0.8rem;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        textarea.form-control {
            resize: vertical;
            min-height: 70px;
        }
        .btn {
            width: 100%;
            padding: 0.6rem;
            background: linear-gradient(135deg, var(--primary), #7c3aed);
            border: none;
            border-radius: 0.5rem;
            color: white;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn-telegram {
            background: linear-gradient(135deg, #0088cc, #006699);
        }
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }
        .request-item {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            padding: 0.6rem;
            margin-bottom: 0.6rem;
            transition: all 0.3s;
        }
        .request-item.approved {
            border-left: 3px solid var(--success);
        }
        .request-item.rejected {
            border-left: 3px solid var(--danger);
        }
        .request-item.pending {
            border-left: 3px solid var(--warning);
        }
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.4rem;
        }
        .status-badge {
            font-size: 0.6rem;
            padding: 0.15rem 0.4rem;
            border-radius: 20px;
            font-weight: 500;
        }
        .status-badge.pending {
            background: rgba(245,158,11,0.2);
            color: var(--warning);
        }
        .status-badge.approved {
            background: rgba(16,185,129,0.2);
            color: var(--success);
        }
        .status-badge.rejected {
            background: rgba(239,68,68,0.2);
            color: var(--danger);
        }
        .request-date {
            font-size: 0.55rem;
            color: var(--text-muted);
        }
        .request-details {
            font-size: 0.7rem;
            margin-bottom: 0.2rem;
        }
        .tx-hash {
            font-size: 0.55rem;
            color: var(--text-muted);
            font-family: monospace;
        }
        .empty-state {
            text-align: center;
            padding: 1.5rem;
            color: var(--text-muted);
        }
        .empty-state i {
            font-size: 1.5rem;
            margin-bottom: 0.4rem;
            opacity: 0.5;
        }
        .success-message {
            background: rgba(16,185,129,0.1);
            border: 1px solid rgba(16,185,129,0.3);
            color: var(--success);
            padding: 0.6rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.8rem;
        }
        .error-message {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            color: var(--danger);
            padding: 0.6rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.8rem;
        }
        .request-history {
            max-height: 400px;
            overflow-y: auto;
        }
        .text-primary {
            color: var(--primary);
        }
        .text-sm {
            font-size: 0.65rem;
        }
        .text-muted {
            color: var(--text-muted);
        }
        .mt-1 { margin-top: 0.25rem; }
        .mt-2 { margin-top: 0.5rem; }
        .mb-1 { margin-bottom: 0.25rem; }
        .mb-2 { margin-bottom: 0.5rem; }
        .redeem-section {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }
        .flex {
            display: flex;
            gap: 0.5rem;
        }
        .flex-grow {
            flex: 1;
        }
        .chat-messages {
            max-height: 180px;
            overflow-y: auto;
            background: var(--bg);
            border-radius: 0.5rem;
            padding: 0.6rem;
            margin-bottom: 0.6rem;
        }
        .message-bubble {
            margin-bottom: 0.6rem;
            padding: 0.4rem;
            border-radius: 0.5rem;
            background: var(--card);
            border-left: 3px solid var(--primary);
        }
        .message-user {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.2rem;
        }
        .message-text {
            font-size: 0.7rem;
            word-break: break-word;
        }
        .message-time {
            font-size: 0.55rem;
            color: var(--text-muted);
            margin-top: 0.2rem;
        }
    </style>
</head>
<body data-theme="dark">
    <nav class="navbar">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <button class="menu-btn" id="menuBtn">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">
                <i class="fas fa-credit-card"></i>
                <span>APPROVED CHECKER</span>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 1rem;">
            <button class="theme-btn" id="themeBtn">
                <i class="fas fa-moon"></i>
            </button>
            <div class="user-menu" id="userMenu">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['display_name'] ?? $username, 0, 1)); ?>
                </div>
                <div>
                    <div style="font-size: 0.8rem; font-weight: 500;"><?php echo htmlspecialchars($user['display_name'] ?? $username); ?></div>
                    <div style="font-size: 0.65rem; color: var(--text-muted);"><?php echo number_format($credits); ?> credits</div>
                </div>
            </div>
        </div>
    </nav>

    <aside class="sidebar" id="sidebar">
        <div class="nav-item" onclick="window.location.href='index.php'">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </div>
        <div class="nav-item active" onclick="window.location.href='topup.php'">
            <i class="fas fa-wallet"></i>
            <span>Top Up</span>
        </div>
        <?php if ($isAdmin): ?>
        <div class="nav-item" onclick="window.location.href='adminaccess_panel.php'">
            <i class="fas fa-crown"></i>
            <span>Admin Panel</span>
        </div>
        <?php endif; ?>
        <div class="nav-item" onclick="window.location.href='?logout=1'">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </div>
    </aside>

    <main class="main" id="main">
        <div class="container">
            <h1><i class="fas fa-wallet" style="color: var(--primary);"></i> Top Up Credits</h1>
            <p class="page-subtitle">Choose a plan, pay with USDT, or redeem a premium key</p>

            <?php if (isset($success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <div class="grid-2">
                <!-- Left Column -->
                <div>
                    <!-- Premium Key Redemption -->
                    <div class="card" style="margin-bottom: 1rem;">
                        <div class="card-title">
                            <i class="fas fa-key"></i>
                            Redeem Premium Key
                        </div>
                        <div class="redeem-section">
                            <form method="POST" class="flex">
                                <input type="hidden" name="action" value="redeem_key">
                                <div class="flex-grow">
                                    <input type="text" name="redeem_key" class="form-control" placeholder="Enter your premium key" required>
                                </div>
                                <button type="submit" class="btn" style="width: auto; padding: 0.6rem 1.2rem;">
                                    <i class="fas fa-gift"></i> Redeem
                                </button>
                            </form>
                            <div class="text-sm text-muted mt-1">
                                <i class="fas fa-info-circle"></i> Premium keys give you bonus credits
                            </div>
                        </div>
                    </div>

                    <!-- Plans Section - 5 Column Grid -->
                    <div class="card" style="margin-bottom: 1rem;">
                        <div class="card-title">
                            <i class="fas fa-crown"></i>
                            Choose Your Plan
                        </div>
                        <div class="plans-grid-4">
                            <?php foreach ($plans as $index => $plan): ?>
                            <div class="plan-card" data-plan-index="<?php echo $index; ?>" onclick="selectPlan(<?php echo $index; ?>)">
                                <?php if ($plan['badge']): ?>
                                <div class="plan-badge"><?php echo $plan['badge']; ?></div>
                                <?php endif; ?>
                                <div class="plan-icon">
                                    <i class="fas <?php echo $plan['icon']; ?>" style="color: <?php echo $plan['color']; ?>"></i>
                                </div>
                                <div class="plan-name"><?php echo $plan['name']; ?></div>
                                <div class="plan-price">$<?php echo $plan['price']; ?><small> USDT</small></div>
                                <div class="plan-credits">+<?php echo number_format($plan['credits']); ?> credits</div>
                                
                                <div class="rating">
                                    <?php
                                    $fullStars = floor($plan['rating']);
                                    for($i = 1; $i <= 5; $i++):
                                        if($i <= $fullStars):
                                    ?>
                                    <i class="fas fa-star star"></i>
                                    <?php else: ?>
                                    <i class="far fa-star star"></i>
                                    <?php endif; endfor; ?>
                                    <span class="rating-value"><?php echo $plan['rating']; ?></span>
                                </div>
                                
                                <div class="features-list">
                                    <?php foreach(array_slice($plan['features'], 0, 2) as $feature): ?>
                                    <div class="feature-item">
                                        <i class="fas fa-check-circle"></i>
                                        <span><?php echo htmlspecialchars($feature); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if(count($plan['features']) > 2): ?>
                                    <div class="feature-item">
                                        <i class="fas fa-plus-circle"></i>
                                        <span>+<?php echo count($plan['features']) - 2; ?> more</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Payment Info Card -->
                    <div class="card">
                        <div class="card-title">
                            <i class="fas fa-credit-card"></i>
                            Complete Payment
                        </div>

                        <?php if (empty($walletAddress)): ?>
                        <div class="empty-state">
                            <i class="fas fa-exclamation-triangle"></i>
                            <p>Payment not configured yet. Contact admin.</p>
                        </div>
                        <?php else: ?>
                        <div class="info-row">
                            <div class="info-label">Network</div>
                            <div class="info-value"><?php echo htmlspecialchars($network); ?> (USDT)</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Wallet Address</div>
                            <div class="info-value">
                                <div class="wallet-address">
                                    <code><?php echo htmlspecialchars($walletAddress); ?></code>
                                    <button class="copy-btn" onclick="copyWallet()">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <form method="POST" id="topupForm">
                            <input type="hidden" name="action" value="submit_topup">
                            <input type="hidden" name="plan" id="selectedPlan" value="-1">

                            <div class="form-group">
                                <label>Selected Plan</label>
                                <div id="selectedPlanDisplay" class="info-value" style="padding: 0.4rem; background: var(--bg); border-radius: 0.5rem; font-size:0.75rem;">
                                    Click on a plan above to select
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Transaction Hash (TxID)</label>
                                <input type="text" name="tx_hash" placeholder="0x..." class="form-control" required>
                                <div class="text-sm text-muted mt-1">
                                    <i class="fas fa-info-circle"></i> Send exact amount and paste transaction hash
                                </div>
                            </div>

                            <button type="submit" class="btn" id="submitBtn" <?php echo $hasPendingRequest ? 'disabled' : ''; ?>>
                                Submit Top-Up Request
                            </button>
                        </form>
                        <?php if ($hasPendingRequest): ?>
                        <div class="text-sm text-muted mt-2" style="text-align: center;">
                            <i class="fas fa-clock"></i> You have a pending request. Please wait for admin approval.
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column -->
                <div>
                    <!-- Chat with Admin Card -->
                    <div class="card" style="margin-bottom: 1rem;">
                        <div class="card-title">
                            <i class="fab fa-telegram"></i>
                            Contact Admin
                        </div>
                        <div class="chat-messages" id="chatMessages">
                            <div class="message-bubble">
                                <div class="message-user">🤖 System</div>
                                <div class="message-text">Hello! Need help? Send a message to admin.</div>
                                <div class="message-time">Just now</div>
                            </div>
                        </div>
                        <form method="POST" id="messageForm">
                            <input type="hidden" name="action" value="send_message">
                            <div class="form-group">
                                <textarea name="message" class="form-control" placeholder="Type your message here..." rows="2" required></textarea>
                            </div>
                            <div class="flex" style="gap: 0.5rem;">
                                <button type="submit" class="btn btn-telegram" style="flex: 1;">
                                    <i class="fab fa-telegram"></i> Send Message
                                </button>
                                <button type="button" class="btn btn-outline" onclick="window.open('https://t.me/<?php echo htmlspecialchars($adminTelegram); ?>', '_blank')" style="flex: 1;">
                                    <i class="fab fa-telegram"></i> Open Telegram
                                </button>
                            </div>
                        </form>
                        <div class="text-sm text-muted mt-1">
                            <i class="fas fa-info-circle"></i> Messages go directly to admin
                        </div>
                    </div>

                    <!-- Request History Card -->
                    <div class="card">
                        <div class="card-title">
                            <i class="fas fa-history"></i>
                            Your Requests
                            <button onclick="refreshTopupStatus()" class="btn btn-sm" style="width: auto; padding: 0.2rem 0.5rem; margin-left: auto; font-size: 0.6rem;">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>

                        <div class="request-history" id="requestHistory">
                            <?php if (empty($userTopups)): ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No top-up requests yet</p>
                            </div>
                            <?php else: ?>
                                <?php foreach (array_reverse($userTopups) as $request): ?>
                                <div class="request-item <?php echo $request['status'] ?? 'pending'; ?>">
                                    <div class="request-header">
                                        <span class="status-badge <?php echo $request['status'] ?? 'pending'; ?>">
                                            <?php echo ucfirst($request['status'] ?? 'Pending'); ?>
                                        </span>
                                        <span class="request-date"><?php echo date('M d, H:i', strtotime($request['created_at'])); ?></span>
                                    </div>
                                    <div class="request-details">
                                        <?php if (isset($request['plan']) && $request['plan']): ?>
                                            <strong><?php echo htmlspecialchars($request['plan']); ?></strong> - 
                                        <?php endif; ?>
                                        $<?php echo $request['amount']; ?> USDT → <?php echo number_format($request['credits']); ?> credits
                                    </div>
                                    <div class="tx-hash">
                                        TX: <?php echo substr($request['tx_hash'], 0, 20); ?>...
                                    </div>
                                    <?php if (($request['status'] ?? '') === 'approved' && isset($request['reviewed_at'])): ?>
                                    <div class="text-sm text-muted mt-1">
                                        <i class="fas fa-check-circle"></i> Approved on <?php echo date('M d, H:i', strtotime($request['reviewed_at'])); ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (($request['status'] ?? '') === 'rejected' && isset($request['reviewed_at'])): ?>
                                    <div class="text-sm text-muted mt-1">
                                        <i class="fas fa-times-circle"></i> Rejected on <?php echo date('M d, H:i', strtotime($request['reviewed_at'])); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        let selectedPlanIndex = -1;
        const plans = <?php echo json_encode($plans); ?>;
        const hasPending = <?php echo $hasPendingRequest ? 'true' : 'false'; ?>;
        
        function selectPlan(index) {
            selectedPlanIndex = index;
            const plan = plans[index];
            
            document.querySelectorAll('.plan-card').forEach(card => card.classList.remove('selected'));
            document.querySelector(`.plan-card[data-plan-index="${index}"]`).classList.add('selected');
            
            document.getElementById('selectedPlanDisplay').innerHTML = `
                <strong>${plan.name}</strong> - $${plan.price} USDT for ${plan.credits.toLocaleString()} credits
            `;
            document.getElementById('selectedPlan').value = index;
            
            if (!hasPending) {
                document.getElementById('submitBtn').disabled = false;
            }
        }
        
        if (hasPending) {
            document.getElementById('submitBtn').disabled = true;
        }
        
        const menuBtn = document.getElementById('menuBtn');
        const sidebar = document.getElementById('sidebar');
        const main = document.getElementById('main');
        
        if (menuBtn) {
            menuBtn.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                main.classList.toggle('sidebar-open');
            });
        }
        
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('open');
                    main.classList.remove('sidebar-open');
                }
            });
        });
        
        const themeBtn = document.getElementById('themeBtn');
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.body.setAttribute('data-theme', savedTheme);
        if (themeBtn) {
            themeBtn.innerHTML = savedTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
            themeBtn.addEventListener('click', () => {
                const current = document.body.getAttribute('data-theme');
                const newTheme = current === 'dark' ? 'light' : 'dark';
                document.body.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                themeBtn.innerHTML = newTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
            });
        }
        
        function copyWallet() {
            const wallet = '<?php echo addslashes($walletAddress); ?>';
            navigator.clipboard.writeText(wallet);
            Swal.fire({
                toast: true,
                icon: 'success',
                title: 'Copied!',
                showConfirmButton: false,
                timer: 1500
            });
        }
        
        function refreshTopupStatus() {
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newHistory = doc.querySelector('.request-history');
                    const currentHistory = document.querySelector('.request-history');
                    if (newHistory && currentHistory) {
                        currentHistory.innerHTML = newHistory.innerHTML;
                    }
                    // Check if pending status changed
                    const hasPendingNow = doc.body.innerHTML.includes('You have a pending request');
                    if (!hasPendingNow && hasPending) {
                        location.reload();
                    }
                })
                .catch(console.error);
        }
        
        // Auto-refresh every 30 seconds if there are pending requests
        <?php if ($hasPendingRequest): ?>
        setInterval(refreshTopupStatus, 30000);
        <?php endif; ?>
    </script>
</body>
</html>