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
$adminTelegram = $settings['admin_telegram_username'] ?? 'admin';

// Plan definitions with ratings and features
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
    $pdo = getDB();
    
    // Handle redeem key
    if (isset($_POST['action']) && $_POST['action'] === 'redeem_key') {
    $pdo = getDB();
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
        // Check for pending requests first
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
            }
        }
    }
    
    // Handle admin message
    if (isset($_POST['action']) && $_POST['action'] === 'send_message') {
        $message = trim($_POST['message'] ?? '');
        if (!empty($message) && !empty($settings['telegram_bot_token']) && !empty($settings['admin_telegram_id'])) {
            $fullMessage = "📨 <b>NEW MESSAGE FROM USER</b>\n\n";
            $fullMessage .= "👤 <b>User:</b> {$username}\n";
            $fullMessage .= "💬 <b>Message:</b>\n{$message}\n\n";
            $fullMessage .= "🕐 <b>Time:</b> " . date('Y-m-d H:i:s') . " UTC";
            
            sendTelegramMessage($settings['admin_telegram_id'], $fullMessage);
            $success = "✅ Message sent to admin! They will respond shortly.";
        } else {
            $error = "❌ Could not send message. Telegram not configured.";
        }
    }
}

// Refresh data
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
            background: linear-gradient(135deg, var(--primary), #06b6d4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
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
        /* 4 Column Grid for Plans */
        .plans-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
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
            border: 2px solid var(--border);
            border-radius: 1rem;
            padding: 1.25rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .plan-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(139,92,246,0.1), transparent);
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }
        .plan-card:hover::before {
            opacity: 1;
        }
        .plan-card:hover {
            transform: translateY(-6px);
            border-color: var(--primary);
            box-shadow: 0 10px 30px rgba(139,92,246,0.2);
        }
        .plan-card.selected {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(139,92,246,0.15), rgba(6,182,212,0.05));
            box-shadow: 0 0 20px rgba(139,92,246,0.3);
        }
        .plan-badge {
            position: absolute;
            top: -8px;
            right: 12px;
            background: linear-gradient(135deg, var(--primary), #7c3aed);
            color: white;
            font-size: 0.65rem;
            padding: 0.2rem 0.7rem;
            border-radius: 20px;
            font-weight: 600;
            z-index: 1;
        }
        .plan-icon {
            font-size: 2rem;
            margin-bottom: 0.75rem;
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }
        .plan-name {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .plan-price {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }
        .plan-price small {
            font-size: 0.7rem;
            font-weight: normal;
            color: var(--text-muted);
        }
        .plan-credits {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }
        /* Star Rating Styles */
        .rating {
            display: flex;
            justify-content: center;
            gap: 0.15rem;
            margin-bottom: 0.75rem;
        }
        .star {
            font-size: 0.8rem;
            color: #ffc107;
            text-shadow: 0 0 2px rgba(0,0,0,0.5);
            animation: twinkle 1.5s ease-in-out infinite;
        }
        @keyframes twinkle {
            0%, 100% { opacity: 0.6; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.1); }
        }
        .star.filled {
            color: #ffc107;
        }
        .star.half {
            color: #ffc107;
            position: relative;
        }
        .rating-value {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-left: 0.25rem;
        }
        .features-list {
            text-align: left;
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border);
        }
        .feature-item {
            font-size: 0.65rem;
            color: var(--text-muted);
            padding: 0.2rem 0;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .feature-item i {
            font-size: 0.6rem;
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
            padding: 1.25rem;
        }
        .card-title {
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
        }
        .card-title i {
            color: var(--primary);
        }
        .info-row {
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .info-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
        }
        .info-value {
            font-size: 0.9rem;
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
            font-size: 0.75rem;
            background: var(--bg);
            padding: 0.5rem;
            border-radius: 0.5rem;
            word-break: break-all;
        }
        .copy-btn {
            background: none;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            padding: 0.5rem;
            cursor: pointer;
            color: var(--text-muted);
        }
        .copy-btn:hover {
            color: var(--primary);
            border-color: var(--primary);
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
        }
        .form-control {
            width: 100%;
            padding: 0.75rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            color: var(--text);
            font-size: 0.875rem;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        .btn {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, var(--primary), #7c3aed);
            border: none;
            border-radius: 0.5rem;
            color: white;
            font-weight: 600;
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
            padding: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .status-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
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
            font-size: 0.65rem;
            color: var(--text-muted);
        }
        .request-details {
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
        }
        .tx-hash {
            font-size: 0.65rem;
            color: var(--text-muted);
            font-family: monospace;
        }
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
        }
        .empty-state i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            opacity: 0.5;
        }
        .success-message {
            background: rgba(16,185,129,0.1);
            border: 1px solid rgba(16,185,129,0.3);
            color: var(--success);
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        .error-message {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            color: var(--danger);
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        .request-history {
            max-height: 300px;
            overflow-y: auto;
        }
        .text-primary {
            color: var(--primary);
        }
        .text-sm {
            font-size: 0.75rem;
        }
        .mt-1 { margin-top: 0.25rem; }
        .mt-2 { margin-top: 0.5rem; }
        .mt-3 { margin-top: 0.75rem; }
        .mb-1 { margin-bottom: 0.25rem; }
        .mb-2 { margin-bottom: 0.5rem; }
        .redeem-section {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border);
        }
        .flex {
            display: flex;
            gap: 0.5rem;
        }
        .flex-grow {
            flex: 1;
        }
        .chat-messages {
            max-height: 200px;
            overflow-y: auto;
            background: var(--bg);
            border-radius: 0.5rem;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .message-bubble {
            margin-bottom: 0.75rem;
            padding: 0.5rem;
            border-radius: 0.5rem;
            background: var(--card);
            border-left: 3px solid var(--primary);
        }
        .message-user {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }
        .message-text {
            font-size: 0.8rem;
            word-break: break-word;
        }
        .message-time {
            font-size: 0.6rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }
        .floating-stars {
            position: absolute;
            top: 10px;
            left: 10px;
            pointer-events: none;
            opacity: 0.6;
        }
        .floating-stars i {
            font-size: 0.5rem;
            margin: 0 -2px;
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
                    <div style="font-size: 0.875rem; font-weight: 500;"><?php echo htmlspecialchars($user['display_name'] ?? $username); ?></div>
                    <div style="font-size: 0.7rem; color: var(--text-muted);"><?php echo number_format($credits); ?> credits</div>
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
                    <div class="card" style="margin-bottom: 1.5rem;">
                        <div class="card-title">
                            <i class="fas fa-key"></i>
                            Redeem Premium Key
                        </div>
                        <div class="redeem-section">
                            <form method="POST" class="flex">
                                <input type="hidden" name="action" value="redeem_key">
                                <div class="flex-grow">
                                    <input type="text" name="redeem_key" class="form-control" placeholder="Enter your premium key (XXXX-XXXX-XXXX-XXXX)" required>
                                </div>
                                <button type="submit" class="btn" style="width: auto; padding: 0.75rem 1.5rem;">
                                    <i class="fas fa-gift"></i> Redeem
                                </button>
                            </form>
                            <div class="text-sm text-muted mt-2">
                                <i class="fas fa-info-circle"></i> Premium keys give you bonus credits and may upgrade your plan
                            </div>
                        </div>
                    </div>

                    <!-- Plans Section - 4 Column Grid with Floating Stars -->
                    <div class="card" style="margin-bottom: 1.5rem;">
                        <div class="card-title">
                            <i class="fas fa-crown"></i>
                            Choose Your Plan
                        </div>
                        <div class="plans-grid-4">
                            <?php foreach ($plans as $index => $plan): ?>
                            <div class="plan-card" data-plan-index="<?php echo $index; ?>" onclick="selectPlan(<?php echo $index; ?>)">
                                <div class="floating-stars">
                                    <?php for($i = 0; $i < 3; $i++): ?>
                                    <i class="fas fa-star" style="color: #ffc107; font-size: 0.4rem;"></i>
                                    <?php endfor; ?>
                                </div>
                                <?php if ($plan['badge']): ?>
                                <div class="plan-badge"><?php echo $plan['badge']; ?></div>
                                <?php endif; ?>
                                <div class="plan-icon">
                                    <i class="fas <?php echo $plan['icon']; ?>" style="color: <?php echo $plan['color']; ?>"></i>
                                </div>
                                <div class="plan-name"><?php echo $plan['name']; ?></div>
                                <div class="plan-price">$<?php echo $plan['price']; ?><small> USDT</small></div>
                                <div class="plan-credits">+<?php echo number_format($plan['credits']); ?> credits</div>
                                
                                <!-- Star Rating -->
                                <div class="rating">
                                    <?php
                                    $fullStars = floor($plan['rating']);
                                    $halfStar = ($plan['rating'] - $fullStars) >= 0.5;
                                    for($i = 1; $i <= 5; $i++):
                                        if($i <= $fullStars):
                                    ?>
                                    <i class="fas fa-star star filled"></i>
                                    <?php elseif($halfStar && $i == $fullStars + 1): ?>
                                    <i class="fas fa-star-half-alt star half"></i>
                                    <?php else: ?>
                                    <i class="far fa-star star"></i>
                                    <?php endif; endfor; ?>
                                    <span class="rating-value">(<?php echo $plan['rating']; ?>)</span>
                                </div>
                                
                                <div class="features-list">
                                    <?php foreach(array_slice($plan['features'], 0, 3) as $feature): ?>
                                    <div class="feature-item">
                                        <i class="fas fa-check-circle"></i>
                                        <span><?php echo htmlspecialchars($feature); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if(count($plan['features']) > 3): ?>
                                    <div class="feature-item">
                                        <i class="fas fa-plus-circle"></i>
                                        <span>+<?php echo count($plan['features']) - 3; ?> more features</span>
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
                                <div id="selectedPlanDisplay" class="info-value" style="padding: 0.5rem; background: var(--bg); border-radius: 0.5rem;">
                                    Click on a plan above to select
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Transaction Hash (TxID)</label>
                                <input type="text" name="tx_hash" placeholder="0x..." class="form-control" required>
                                <div class="text-sm text-muted mt-1">
                                    <i class="fas fa-info-circle"></i> Send exactly the amount shown above and paste the transaction hash
                                </div>
                            </div>

                            <button type="submit" class="btn" id="submitBtn" disabled>
                                Submit Top-Up Request
                            </button>
                        </form>
                        <?php if ($hasPendingRequest): ?>
                        <div class="text-sm text-muted mt-2" style="text-align: center;">
                            <i class="fas fa-clock"></i> You have a pending request. Wait for approval before submitting a new one.
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column -->
                <div>
                    <!-- Chat with Admin Card -->
                    <div class="card" style="margin-bottom: 1.5rem;">
                        <div class="card-title">
                            <i class="fab fa-telegram"></i>
                            Contact Admin
                        </div>
                        <div class="chat-messages" id="chatMessages">
                            <div class="message-bubble">
                                <div class="message-user">🤖 System</div>
                                <div class="message-text">Hello! Need help? Send a message to admin. They will respond via Telegram.</div>
                                <div class="message-time">Just now</div>
                            </div>
                        </div>
                        <form method="POST" id="messageForm">
                            <input type="hidden" name="action" value="send_message">
                            <div class="form-group">
                                <textarea name="message" class="form-control" placeholder="Type your message here..." rows="3" required></textarea>
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
                        <div class="text-sm text-muted mt-2">
                            <i class="fas fa-info-circle"></i> Messages are sent directly to admin's Telegram. Please include your username if you need support.
                        </div>
                    </div>

                    <!-- Request History Card -->
                    <div class="card">
                        <div class="card-title">
                            <i class="fas fa-history"></i>
                            Your Requests
                        </div>

                        <div class="request-history">
                            <?php if (empty($userTopups)): ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No top-up requests yet</p>
                            </div>
                            <?php else: ?>
                                <?php foreach (array_reverse($userTopups) as $request): ?>
                                <div class="request-item">
                                    <div class="request-header">
                                        <span class="status-badge <?php echo $request['status'] ?? 'pending'; ?>">
                                            <?php echo ucfirst($request['status'] ?? 'Pending'); ?>
                                        </span>
                                        <span class="request-date"><?php echo date('M d, H:i', strtotime($request['created_at'])); ?></span>
                                    </div>
                                    <div class="request-details">
                                        <?php if (isset($request['plan']) && $request['plan']): ?>
                                            <strong><?php echo htmlspecialchars($request['plan']); ?></strong> Plan - 
                                        <?php endif; ?>
                                        $<?php echo $request['amount']; ?> USDT → <?php echo number_format($request['credits']); ?> credits
                                    </div>
                                    <div class="tx-hash">
                                        TX: <?php echo substr($request['tx_hash'], 0, 20); ?>...
                                    </div>
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
            
            // Update UI
            document.querySelectorAll('.plan-card').forEach(card => card.classList.remove('selected'));
            document.querySelector(`.plan-card[data-plan-index="${index}"]`).classList.add('selected');
            
            // Update display
            document.getElementById('selectedPlanDisplay').innerHTML = `
                <strong>${plan.name}</strong> - $${plan.price} USDT for ${plan.credits.toLocaleString()} credits
                <div style="font-size:0.7rem; margin-top:0.25rem;">
                    <i class="fas fa-star" style="color: #ffc107;"></i> ${plan.rating} rating
                </div>
            `;
            document.getElementById('selectedPlan').value = index;
            
            // Enable submit button if no pending request
            if (!hasPending) {
                document.getElementById('submitBtn').disabled = false;
            }
        }
        
        // Disable submit if pending
        if (hasPending) {
            document.getElementById('submitBtn').disabled = true;
        }
        
        // Sidebar toggle
        const menuBtn = document.getElementById('menuBtn');
        const sidebar = document.getElementById('sidebar');
        const main = document.getElementById('main');
        
        menuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            main.classList.toggle('sidebar-open');
        });
        
        // Close sidebar on link click (mobile)
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('open');
                    main.classList.remove('sidebar-open');
                }
            });
        });
        
        // Theme toggle
        const themeBtn = document.getElementById('themeBtn');
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.body.setAttribute('data-theme', savedTheme);
        themeBtn.innerHTML = savedTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        
        themeBtn.addEventListener('click', () => {
            const current = document.body.getAttribute('data-theme');
            const newTheme = current === 'dark' ? 'light' : 'dark';
            document.body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            themeBtn.innerHTML = newTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        });
        
        // Copy wallet address
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
        
        // Handle logout
        <?php if (isset($_GET['logout'])): ?>
        Swal.fire({
            title: 'Logged Out',
            text: 'You have been successfully logged out.',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        }).then(() => { window.location.href = 'login.php'; });
        <?php endif; ?>
    </script>
</body>
</html>
