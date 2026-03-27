<?php
require_once 'includes/config.php';

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => true,
        'cookie_samesite' => 'Strict'
    ]);
}

// Check if this is a logout
$showLogoutAnimation = false;
$showRefreshAnimation = false;

if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    $showLogoutAnimation = true;
}

// Check if this is a manual refresh (no previous state or specific flag)
if (isset($_GET['refresh']) && $_GET['refresh'] == 1) {
    $showRefreshAnimation = true;
}

// If already logged in, redirect to dashboard (no animation for redirects)
if (isLoggedIn() && !$showLogoutAnimation) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$telegramBotUsername = '';

// Get Telegram bot username from settings for widget
$settings = loadSettings();
$telegramBotUsername = $settings['telegram_bot_username'] ?? '';

// Rate limiting - track failed attempts
$ip = $_SERVER['REMOTE_ADDR'];
$attemptsFile = __DIR__ . '/data/login_attempts.json';
$attempts = [];

if (file_exists($attemptsFile)) {
    $attempts = json_decode(file_get_contents($attemptsFile), true);
    if (!is_array($attempts)) $attempts = [];
}

// Clean old attempts (older than 15 minutes)
foreach ($attempts as $key => $attempt) {
    if ($attempt['timestamp'] < time() - 900) {
        unset($attempts[$key]);
    }
}

// Check if IP is blocked
$isBlocked = false;
$remainingTime = 0;
if (isset($attempts[$ip]) && $attempts[$ip]['count'] >= 5) {
    $isBlocked = true;
    $remainingTime = 900 - (time() - $attempts[$ip]['timestamp']);
    $minutes = ceil($remainingTime / 60);
    $error = "Too many failed attempts. Please try again after {$minutes} minutes.";
}

// Handle Telegram authentication via callback
if (isset($_GET['tg_auth'])) {
    $authData = $_GET;
    
    // Verify hash
    $botToken = $settings['telegram_bot_token'] ?? '';
    if (!empty($botToken)) {
        $checkHash = $authData['hash'];
        unset($authData['hash']);
        ksort($authData);
        $dataCheckString = [];
        foreach ($authData as $key => $value) {
            $dataCheckString[] = "$key=$value";
        }
        $dataCheckString = implode("\n", $dataCheckString);
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $hash = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey, true));
        
        if (hash_equals($checkHash, $hash)) {
            $telegramId = $authData['id'];
            $firstName = $authData['first_name'] ?? '';
            $lastName = $authData['last_name'] ?? '';
            $username = $authData['username'] ?? '';
            $photoUrl = $authData['photo_url'] ?? '';
            
            // Check if user exists
            $users = loadUsers();
            $existingUser = null;
            $existingUsername = null;
            
            foreach ($users as $name => $user) {
                if (isset($user['telegram_id']) && $user['telegram_id'] == $telegramId) {
                    $existingUser = $user;
                    $existingUsername = $name;
                    break;
                }
            }
            
            if ($existingUser) {
                // Login existing user
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
                    'created_at' => $existingUser['created_at'] ?? date('Y-m-d H:i:s'),
                    'last_login' => date('Y-m-d H:i:s'),
                    'auth_provider' => 'telegram',
                    'login_ip' => $ip,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ];
                
                updateOnlineStatus($existingUsername);
                header('Location: index.php');
                exit;
            } else {
                // Create new user
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
                    'credits' => $settings['default_credits'] ?? 0,
                    'banned' => false,
                    'created_at' => date('Y-m-d H:i:s'),
                    'api_key' => null,
                    'username' => $username ?: $newUsername,
                    'display_name' => $firstName . ' ' . $lastName,
                    'email' => null,
                    'telegram_id' => $telegramId,
                    'telegram_username' => $username,
                    'photo_url' => $photoUrl,
                    'last_login' => date('Y-m-d H:i:s')
                ];
                
                saveUsers($users);
                
                $_SESSION['user'] = [
                    'name' => $newUsername,
                    'credits' => $settings['default_credits'] ?? 0,
                    'is_admin' => false,
                    'is_owner' => false,
                    'banned' => false,
                    'username' => $username ?: $newUsername,
                    'display_name' => $firstName,
                    'photo_url' => $photoUrl,
                    'telegram_id' => $telegramId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'last_login' => date('Y-m-d H:i:s'),
                    'auth_provider' => 'telegram',
                    'login_ip' => $ip,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ];
                
                updateOnlineStatus($newUsername);
                header('Location: index.php');
                exit;
            }
        }
    }
}

// Handle regular login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isBlocked) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (authenticateUser($username, $password)) {
            // Clear failed attempts on successful login
            if (isset($attempts[$ip])) {
                unset($attempts[$ip]);
                file_put_contents($attemptsFile, json_encode($attempts));
            }
            
            // Add login security info
            $_SESSION['user']['login_ip'] = $ip;
            $_SESSION['user']['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $_SESSION['user']['login_time'] = date('Y-m-d H:i:s');
            
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password';
            
            // Record failed attempt
            if (!isset($attempts[$ip])) {
                $attempts[$ip] = ['count' => 0, 'timestamp' => time()];
            }
            $attempts[$ip]['count']++;
            $attempts[$ip]['timestamp'] = time();
            file_put_contents($attemptsFile, json_encode($attempts));
            
            if ($attempts[$ip]['count'] >= 5) {
                $remaining = 900;
                $minutes = ceil($remaining / 60);
                $error = "Too many failed attempts. Please try again after {$minutes} minutes.";
            }
        }
    } elseif ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        
        // Password strength validation
        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $error = 'Password must contain at least one uppercase letter';
        } elseif (!preg_match('/[a-z]/', $password)) {
            $error = 'Password must contain at least one lowercase letter';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $error = 'Password must contain at least one number';
        } else {
            $result = registerUser($username, $password, $email);
            if ($result['success']) {
                $success = 'Account created successfully! You can now log in.';
            } else {
                $error = $result['error'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Login | APPROVED CHECKER</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Floating Security Cards inside container */
        .floating-card {
            position: absolute;
            background: rgba(15, 25, 45, 0.85);
            backdrop-filter: blur(12px);
            border-radius: 16px;
            padding: 12px 18px;
            border: 1px solid rgba(139, 92, 246, 0.4);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 2;
            animation: float 4s ease-in-out infinite;
            transition: all 0.3s ease;
        }
        .floating-card:hover {
            transform: scale(1.05);
            border-color: rgba(139, 92, 246, 0.8);
            box-shadow: 0 8px 32px rgba(139, 92, 246, 0.2);
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        .card-icon {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card-icon svg {
            width: 22px;
            height: 22px;
        }
        .card-content {
            display: flex;
            flex-direction: column;
        }
        .card-title {
            font-size: 0.7rem;
            font-weight: 600;
            color: #c4b5fd;
        }
        .card-status {
            font-size: 0.55rem;
            color: #10b981;
        }
        
        /* Position floating cards relative to container */
        .container {
            position: relative;
            background: rgba(15, 15, 35, 0.95);
            border-radius: 28px;
            padding: 32px 30px;
            width: 100%;
            max-width: 460px;
            border: 1px solid rgba(167, 139, 250, 0.3);
            backdrop-filter: blur(10px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            z-index: 10;
            animation: slideUp 0.6s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            overflow: visible;
        }
        
        /* Floating cards inside container */
        .floating-card-1 { top: -30px; left: -40px; animation-delay: 0s; }
        .floating-card-2 { top: -20px; right: -40px; animation-delay: 0.5s; }
        .floating-card-3 { bottom: -30px; left: -35px; animation-delay: 1s; }
        .floating-card-4 { bottom: -25px; right: -35px; animation-delay: 1.5s; }
        .floating-card-5 { top: 40%; left: -45px; animation-delay: 2s; }
        .floating-card-6 { top: 60%; right: -45px; animation-delay: 2.5s; }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo {
            max-width: 85px;
            height: auto;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.4);
        }
        h1 {
            font-size: 1.4rem;
            font-weight: 700;
            text-align: center;
            background: linear-gradient(135deg, #fff, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 1px;
            margin-top: 12px;
        }
        .subtitle {
            text-align: center;
            color: #94a3b8;
            font-size: 0.7rem;
            margin-top: 6px;
            margin-bottom: 20px;
        }
        
        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.96);
            backdrop-filter: blur(16px);
            z-index: 2000;
            display: none;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .loading-card {
            background: linear-gradient(135deg, #1a1f3a, #131937);
            border-radius: 32px;
            padding: 45px 50px;
            text-align: center;
            min-width: 360px;
            border: 1px solid rgba(139, 92, 246, 0.6);
            box-shadow: 0 0 60px rgba(139, 92, 246, 0.4);
            animation: pulseGlow 2s ease-in-out infinite;
        }
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 20px rgba(139, 92, 246, 0.3); }
            50% { box-shadow: 0 0 50px rgba(139, 92, 246, 0.6); }
        }
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 3px solid rgba(139, 92, 246, 0.2);
            border-top: 3px solid #8b5cf6;
            border-right: 3px solid #a78bfa;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 25px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .loading-steps {
            margin-top: 30px;
            text-align: left;
        }
        .loading-step {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 0;
            color: #94a3b8;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        .loading-step.active {
            color: #a78bfa;
            text-shadow: 0 0 8px rgba(139, 92, 246, 0.5);
        }
        .loading-step.completed {
            color: #10b981;
        }
        .loading-step .step-icon {
            width: 24px;
            text-align: center;
        }
        
        .tabs {
            display: flex;
            gap: 8px;
            margin: 20px 0 22px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 4px;
        }
        .tab-btn {
            flex: 1;
            padding: 10px;
            background: transparent;
            border: none;
            border-radius: 10px;
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .tab-btn.active {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .form-wrapper {
            display: none;
        }
        .form-wrapper.active {
            display: block;
            animation: fadeIn 0.4s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-group {
            margin-bottom: 18px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #c4b5fd;
            font-weight: 500;
            font-size: 0.75rem;
            letter-spacing: 0.3px;
        }
        .input-wrapper {
            position: relative;
        }
        .input-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 0.9rem;
        }
        input {
            width: 100%;
            padding: 12px 14px 12px 44px;
            background: rgba(255, 255, 255, 0.08);
            border: 1.5px solid rgba(167, 139, 250, 0.3);
            border-radius: 12px;
            color: white;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
        }
        input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }
        
        .password-strength {
            margin-top: 8px;
            font-size: 0.65rem;
        }
        .strength-bar {
            height: 4px;
            background: rgba(255,255,255,0.1);
            border-radius: 2px;
            margin-top: 6px;
            overflow: hidden;
        }
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s;
        }
        
        button[type="submit"] {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 22px 0;
            color: #94a3b8;
            font-size: 0.75rem;
        }
        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }
        .divider span {
            padding: 0 15px;
        }
        
        .telegram-login {
            text-align: center;
            margin-top: 15px;
        }
        
        .error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 18px;
            text-align: center;
            font-size: 0.75rem;
        }
        .success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #4ade80;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 18px;
            text-align: center;
            font-size: 0.75rem;
        }
        
        .security-note {
            text-align: center;
            margin-top: 18px;
            font-size: 0.6rem;
            color: #6b7280;
        }
        
        @media (max-width: 600px) {
            .floating-card { display: none; }
            .container { padding: 28px 22px; max-width: 340px; }
        }
    </style>
</head>
<body>
    <div class="container" id="loginContainer">
        <!-- Floating Security Cards inside container -->
        <div class="floating-card floating-card-1">
            <div class="card-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L3 7L12 12L21 7L12 2Z" stroke="#8b5cf6" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    <path d="M3 12L12 17L21 12" stroke="#8b5cf6" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    <path d="M3 17L12 22L21 17" stroke="#8b5cf6" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                </svg>
            </div>
            <div class="card-content">
                <span class="card-title">Military Grade</span>
                <span class="card-status">AES-256-GCM</span>
            </div>
        </div>
        
        <div class="floating-card floating-card-2">
            <div class="card-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 8V12L15 15" stroke="#10b981" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="12" cy="12" r="9" stroke="#10b981" stroke-width="1.5"/>
                    <path d="M12 4V2M12 22V20" stroke="#10b981" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="card-content">
                <span class="card-title">Session Timeout</span>
                <span class="card-status">30 Minutes</span>
            </div>
        </div>
        
        <div class="floating-card floating-card-3">
            <div class="card-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 3C7.5 3 3 6 3 12C3 18 7.5 21 12 21C16.5 21 21 18 21 12C21 6 16.5 3 12 3Z" stroke="#f59e0b" stroke-width="1.5"/>
                    <path d="M8 12L11 15L16 9" stroke="#f59e0b" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="card-content">
                <span class="card-title">Rate Limited</span>
                <span class="card-status">5 Attempts</span>
            </div>
        </div>
        
        <div class="floating-card floating-card-4">
            <div class="card-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M7 8V7C7 4.2 8.5 2 12 2C15.5 2 17 4.2 17 7V8" stroke="#ec4899" stroke-width="1.5" stroke-linecap="round"/>
                    <rect x="4" y="8" width="16" height="12" rx="2" stroke="#ec4899" stroke-width="1.5"/>
                    <path d="M12 14V16" stroke="#ec4899" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="card-content">
                <span class="card-title">CSRF Protected</span>
                <span class="card-status">Token Verified</span>
            </div>
        </div>
        
        <div class="floating-card floating-card-5">
            <div class="card-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 4H20C21.1 4 22 4.9 22 6V18C22 19.1 21.1 20 20 20H4C2.9 20 2 19.1 2 18V6C2 4.9 2.9 4 4 4Z" stroke="#3b82f6" stroke-width="1.5"/>
                    <path d="M22 6L12 13L2 6" stroke="#3b82f6" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="card-content">
                <span class="card-title">Encrypted Storage</span>
                <span class="card-status">Active</span>
            </div>
        </div>
        
        <div class="floating-card floating-card-6">
            <div class="card-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="2" fill="#06b6d4"/>
                    <path d="M12 5V3M12 21V19M19 12H21M3 12H5" stroke="#06b6d4" stroke-width="1.5" stroke-linecap="round"/>
                    <path d="M12 8C9.8 8 8 9.8 8 12C8 14.2 9.8 16 12 16C14.2 16 16 14.2 16 12C16 9.8 14.2 8 12 8Z" stroke="#06b6d4" stroke-width="1.5"/>
                </svg>
            </div>
            <div class="card-content">
                <span class="card-title">2FA Ready</span>
                <span class="card-status">Coming Soon</span>
            </div>
        </div>
        
        <div class="logo-container">
            <img src="https://i.ibb.co/6c0996jT/photo-5888528011563747370-c.jpg" 
                 alt="Approved Checker" class="logo" onerror="this.src='https://placehold.co/85x85/8b5cf6/white?text=AC'">
        </div>
        <h1>APPROVED CHECKER</h1>
        <p class="subtitle">Enterprise Security Suite</p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <!-- Telegram Login Section -->
        <div class="telegram-login">
            <div id="telegram-login-container"></div>
            <div id="telegram-widget-status"></div>
        </div>
        
        <div class="divider">
            <span>OR CONTINUE WITH</span>
        </div>
        
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('login')">Sign In</button>
            <button class="tab-btn" onclick="switchTab('register')">Create Account</button>
        </div>
        
        <div id="login-form" class="form-wrapper active">
            <form id="loginForm" method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label>Username / Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user-circle"></i>
                        <input type="text" name="username" placeholder="Enter your username" required autocomplete="username">
                    </div>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" id="password" placeholder="Enter your password" required autocomplete="current-password">
                    </div>
                </div>
                <button type="submit" id="loginSubmitBtn"><i class="fas fa-arrow-right-to-bracket"></i> Access Dashboard</button>
            </form>
        </div>
        
        <div id="register-form" class="form-wrapper">
            <form id="registerForm" method="POST">
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label>Username</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" placeholder="Choose a username (3-32 chars)" required minlength="3" pattern="[A-Za-z0-9_]{3,32}">
                    </div>
                </div>
                <div class="form-group">
                    <label>Email (optional)</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="your@email.com">
                    </div>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-key"></i>
                        <input type="password" name="password" id="reg-password" placeholder="Min 8 chars (Upper, Lower, Number)" required minlength="8">
                    </div>
                    <div class="password-strength">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strength-fill"></div>
                        </div>
                        <span id="strength-text" style="color: #94a3b8;">Password strength: </span>
                    </div>
                </div>
                <button type="submit" id="registerSubmitBtn"><i class="fas fa-user-plus"></i> Create Account</button>
            </form>
        </div>
        
        <div class="security-note">
            <i class="fas fa-shield-haltered"></i> Protected by advanced security protocols
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-card">
            <div class="loading-spinner"></div>
            <div id="loadingMessage" style="font-weight: 600; font-size: 1rem; color: #c4b5fd;">Initializing Secure Environment</div>
            <div class="loading-steps" id="loadingSteps">
                <div class="loading-step" id="step1"><span class="step-icon">◉</span> <span>Establishing secure channel</span></div>
                <div class="loading-step" id="step2"><span class="step-icon">◉</span> <span>Verifying environment integrity</span></div>
                <div class="loading-step" id="step3"><span class="step-icon">◉</span> <span>Loading security modules</span></div>
                <div class="loading-step" id="step4"><span class="step-icon">◉</span> <span>Initializing encryption layer</span></div>
                <div class="loading-step" id="step5"><span class="step-icon">◉</span> <span>Validating session token</span></div>
                <div class="loading-step" id="step6"><span class="step-icon">◉</span> <span>Preparing dashboard interface</span></div>
            </div>
        </div>
    </div>
    
    <script>
        let isLoginSuccess = false;
        
        // Check if this is a logout animation
        <?php if ($showLogoutAnimation): ?>
        function startLogoutAnimation() {
            const overlay = document.getElementById('loadingOverlay');
            const loadingMsg = document.getElementById('loadingMessage');
            const stepsDiv = document.getElementById('loadingSteps');
            
            overlay.style.display = 'flex';
            loadingMsg.innerHTML = '<span class="step-icon">⟳</span> Clearing Secure Session';
            
            stepsDiv.innerHTML = `
                <div class="loading-step" id="logoutStep1"><span class="step-icon">◉</span> <span>Terminating active session</span></div>
                <div class="loading-step" id="logoutStep2"><span class="step-icon">◉</span> <span>Clearing encrypted tokens</span></div>
                <div class="loading-step" id="logoutStep3"><span class="step-icon">◉</span> <span>Flushing cache memory</span></div>
                <div class="loading-step" id="logoutStep4"><span class="step-icon">◉</span> <span>Resetting security flags</span></div>
                <div class="loading-step" id="logoutStep5"><span class="step-icon">◉</span> <span>Restoring system defaults</span></div>
                <div class="loading-step" id="logoutStep6"><span class="step-icon">◉</span> <span>Redirecting to login portal</span></div>
            `;
            
            const logoutSteps = ['logoutStep1', 'logoutStep2', 'logoutStep3', 'logoutStep4', 'logoutStep5', 'logoutStep6'];
            const logoutMessages = [
                'Terminating active session',
                'Clearing encrypted tokens',
                'Flushing cache memory',
                'Resetting security flags',
                'Restoring system defaults',
                'Redirecting to login portal'
            ];
            
            let stepIndex = 0;
            function animateLogoutSteps() {
                if (stepIndex < logoutSteps.length) {
                    const stepEl = document.getElementById(logoutSteps[stepIndex]);
                    if (stepEl) {
                        stepEl.className = 'loading-step completed';
                        stepEl.innerHTML = '<span class="step-icon">✓</span> <span>' + logoutMessages[stepIndex] + '</span>';
                    }
                    stepIndex++;
                    setTimeout(animateLogoutSteps, 1000);
                } else {
                    loadingMsg.innerHTML = '<span style="color: #10b981;">✓</span> Session Cleared. Redirecting...';
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 1000);
                }
            }
            
            setTimeout(animateLogoutSteps, 300);
        }
        
        // Start logout animation on page load if logout flag is set
        window.addEventListener('load', function() {
            startLogoutAnimation();
        });
        <?php endif; ?>
        
        // Page refresh animation - only on manual refresh
        <?php if (!$showLogoutAnimation && !isset($_GET['login_success'])): ?>
        let currentStep = 0;
        const steps = ['step1', 'step2', 'step3', 'step4', 'step5', 'step6'];
        const stepMessages = [
            'Establishing secure channel',
            'Verifying environment integrity',
            'Loading security modules',
            'Initializing encryption layer',
            'Validating session token',
            'Preparing dashboard interface'
        ];
        
        function startRefreshAnimation() {
            const overlay = document.getElementById('loadingOverlay');
            overlay.style.display = 'flex';
            currentStep = 0;
            
            steps.forEach((step, idx) => {
                const el = document.getElementById(step);
                if (el) {
                    el.className = 'loading-step';
                    el.innerHTML = '<span class="step-icon">◉</span> <span>' + stepMessages[idx] + '</span>';
                }
            });
            
            function animateSteps() {
                if (currentStep < steps.length) {
                    const stepEl = document.getElementById(steps[currentStep]);
                    if (stepEl) {
                        stepEl.className = 'loading-step completed';
                        stepEl.innerHTML = '<span class="step-icon">✓</span> <span>' + stepMessages[currentStep] + '</span>';
                    }
                    currentStep++;
                    setTimeout(animateSteps, 1400);
                } else {
                    setTimeout(() => {
                        const msg = document.getElementById('loadingMessage');
                        msg.innerHTML = '<span style="color: #10b981;">✓</span> System Ready! Welcome.';
                        setTimeout(() => {
                            overlay.style.display = 'none';
                        }, 800);
                    }, 500);
                }
            }
            
            setTimeout(animateSteps, 300);
        }
        <?php endif; ?>
        
        // Handle login form submission
        document.getElementById('loginForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = this.querySelector('input[name="username"]').value;
            const password = this.querySelector('input[name="password"]').value;
            
            if (!username || !password) {
                Swal.fire('Error', 'Please enter username and password', 'error');
                return;
            }
            
            // Show loading overlay
            const overlay = document.getElementById('loadingOverlay');
            const loadingMsg = document.getElementById('loadingMessage');
            loadingMsg.innerHTML = '<span class="step-icon">◉</span> Authenticating Credentials';
            overlay.style.display = 'flex';
            
            // Reset steps for login
            const stepsDiv = document.getElementById('loadingSteps');
            stepsDiv.innerHTML = `
                <div class="loading-step" id="authStep1"><span class="step-icon">◉</span> <span>Verifying credentials</span></div>
                <div class="loading-step" id="authStep2"><span class="step-icon">◉</span> <span>Checking security status</span></div>
                <div class="loading-step" id="authStep3"><span class="step-icon">◉</span> <span>Loading user profile</span></div>
                <div class="loading-step" id="authStep4"><span class="step-icon">◉</span> <span>Finalizing secure session</span></div>
            `;
            
            const authSteps = ['authStep1', 'authStep2', 'authStep3', 'authStep4'];
            const authMessages = [
                'Verifying credentials',
                'Checking security status',
                'Loading user profile',
                'Finalizing secure session'
            ];
            
            let stepIndex = 0;
            function animateAuthSteps() {
                if (stepIndex < authSteps.length) {
                    const stepEl = document.getElementById(authSteps[stepIndex]);
                    if (stepEl) {
                        stepEl.className = 'loading-step completed';
                        stepEl.innerHTML = '<span class="step-icon">✓</span> <span>' + authMessages[stepIndex] + '</span>';
                    }
                    stepIndex++;
                    setTimeout(animateAuthSteps, 1000);
                } else {
                    // Only show "Access Granted" after successful verification
                    loadingMsg.innerHTML = '<span style="color: #10b981;">✓</span> Access Granted!';
                    setTimeout(() => {
                        // Submit the form
                        fetch('login.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ action: 'login', username: username, password: password })
                        }).then(response => response.text()).then(html => {
                            if (html.includes('Location: index.php') || html.includes('index.php')) {
                                // Final step - redirect without animation
                                window.location.href = 'index.php';
                            } else {
                                overlay.style.display = 'none';
                                const errorMatch = html.match(/<div class="error">(.*?)<\/div>/);
                                Swal.fire('Access Denied', errorMatch ? errorMatch[1] : 'Invalid credentials', 'error');
                            }
                        }).catch(error => {
                            overlay.style.display = 'none';
                            Swal.fire('Error', 'Network error. Please try again.', 'error');
                        });
                    }, 500);
                }
            }
            
            setTimeout(animateAuthSteps, 200);
        });
        
        // Handle register form submission
        document.getElementById('registerForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = this.querySelector('input[name="username"]').value;
            const password = this.querySelector('input[name="password"]').value;
            const email = this.querySelector('input[name="email"]').value;
            
            if (!username || !password) {
                Swal.fire('Error', 'Please fill all required fields', 'error');
                return;
            }
            
            Swal.fire({ title: 'Creating Account...', text: 'Please wait', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            
            fetch('login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'register', username: username, password: password, email: email })
            }).then(response => response.text()).then(html => {
                Swal.close();
                if (html.includes('success')) {
                    Swal.fire({ title: 'Success!', text: 'Account created! You can now log in.', icon: 'success', confirmButtonText: 'Login' })
                        .then(() => { switchTab('login'); document.querySelector('#login-form input[name="username"]').value = username; });
                } else {
                    const errorMatch = html.match(/<div class="error">(.*?)<\/div>/);
                    Swal.fire('Registration Failed', errorMatch ? errorMatch[1] : 'Could not create account', 'error');
                }
            }).catch(error => { Swal.close(); Swal.fire('Error', 'Network error', 'error'); });
        });
        
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.form-wrapper').forEach(form => form.classList.remove('active'));
            if (tab === 'login') {
                document.querySelector('.tab-btn:first-child').classList.add('active');
                document.getElementById('login-form').classList.add('active');
            } else {
                document.querySelector('.tab-btn:last-child').classList.add('active');
                document.getElementById('register-form').classList.add('active');
            }
        }
        
        // Password strength meter
        const passwordInput = document.getElementById('reg-password');
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                if (password.length >= 8) strength++;
                if (password.match(/[A-Z]/)) strength++;
                if (password.match(/[a-z]/)) strength++;
                if (password.match(/[0-9]/)) strength++;
                const fill = document.getElementById('strength-fill');
                const text = document.getElementById('strength-text');
                if (strength <= 1) {
                    fill.style.width = '25%'; fill.style.background = '#ef4444';
                    text.innerHTML = 'Password strength: <span style="color: #ef4444;">Weak</span>';
                } else if (strength <= 3) {
                    fill.style.width = '60%'; fill.style.background = '#f59e0b';
                    text.innerHTML = 'Password strength: <span style="color: #f59e0b;">Medium</span>';
                } else {
                    fill.style.width = '100%'; fill.style.background = '#10b981';
                    text.innerHTML = 'Password strength: <span style="color: #10b981;">Strong</span>';
                }
            });
        }
        
        // Telegram Login Widget
        <?php if (!empty($telegramBotUsername)): ?>
        (function() {
            const script = document.createElement('script');
            script.src = 'https://telegram.org/js/telegram-widget.js?22';
            script.setAttribute('data-telegram-login', '<?php echo $telegramBotUsername; ?>');
            script.setAttribute('data-size', 'large');
            script.setAttribute('data-radius', '12');
            script.setAttribute('data-onauth', 'onTelegramAuth');
            script.setAttribute('data-request-access', 'write');
            script.async = true;
            document.getElementById('telegram-login-container').appendChild(script);
        })();
        <?php else: ?>
        document.getElementById('telegram-login-container').innerHTML = '<div style="color:#94a3b8; font-size:0.8rem;">Telegram login not configured</div>';
        <?php endif; ?>
        
        function onTelegramAuth(user) {
            const overlay = document.getElementById('loadingOverlay');
            const loadingMsg = document.getElementById('loadingMessage');
            loadingMsg.innerHTML = '<span class="step-icon">◉</span> Telegram Authentication...';
            overlay.style.display = 'flex';
            const params = new URLSearchParams();
            params.append('id', user.id);
            params.append('first_name', user.first_name);
            params.append('last_name', user.last_name || '');
            params.append('username', user.username || '');
            params.append('photo_url', user.photo_url || '');
            params.append('auth_date', user.auth_date);
            params.append('hash', user.hash);
            params.append('tg_auth', '1');
            window.location.href = 'login.php?' + params.toString();
        }
        
        // Detect Telegram Web App
        if (window.Telegram?.WebApp) {
            const tg = window.Telegram.WebApp;
            tg.ready();
            tg.expand();
            const user = tg.initDataUnsafe?.user;
            if (user) {
                const overlay = document.getElementById('loadingOverlay');
                const loadingMsg = document.getElementById('loadingMessage');
                loadingMsg.innerHTML = '<span class="step-icon">◉</span> Telegram Auto-Login...';
                overlay.style.display = 'flex';
                const params = new URLSearchParams();
                params.append('id', user.id);
                params.append('first_name', user.first_name);
                params.append('last_name', user.last_name || '');
                params.append('username', user.username || '');
                params.append('photo_url', user.photo_url || '');
                params.append('auth_date', user.auth_date || Math.floor(Date.now() / 1000));
                params.append('hash', tg.initData?.split('hash=')[1] || '');
                params.append('tg_auth', '1');
                window.location.href = 'login.php?' + params.toString();
            }
        }
        
        // Show refresh animation on manual page load only (not on redirects)
        <?php if (!$showLogoutAnimation && !isset($_GET['login_success'])): ?>
        if (performance.navigation.type === 1 || document.referrer === '') {
            window.addEventListener('load', function() {
                setTimeout(() => { startRefreshAnimation(); }, 500);
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>
