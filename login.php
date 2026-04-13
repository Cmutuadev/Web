<?php
require_once 'includes/config.php';

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
        'cookie_lifetime' => 86400
    ]);
}

// Regenerate session ID periodically
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 3600) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Generate CSRF token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check for logout/refresh
$showLogoutAnimation = isset($_GET['logout']) && $_GET['logout'] == 1;
$showRefreshAnimation = isset($_GET['refresh']) && $_GET['refresh'] == 1;

if ($showLogoutAnimation) {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
}

// If already logged in, redirect to dashboard with animation
if (isLoggedIn() && !$showLogoutAnimation && !$showRefreshAnimation) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Redirecting | APPROVED CHECKER</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Inter', sans-serif;
            }
            .loader-container { text-align: center; animation: fadeIn 0.5s ease; }
            .loader {
                width: 60px;
                height: 60px;
                margin: 0 auto 20px;
                border: 3px solid rgba(139,92,246,0.2);
                border-top-color: #8b5cf6;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
            }
            .loader-text { color: #c4b5fd; font-size: 14px; letter-spacing: 1px; }
            .loader-progress { width: 200px; height: 2px; background: rgba(255,255,255,0.1); margin: 20px auto 0; border-radius: 2px; overflow: hidden; }
            .loader-progress-bar { width: 0%; height: 100%; background: linear-gradient(90deg, #8b5cf6, #06b6d4); animation: progress 1s ease forwards; }
            @keyframes spin { to { transform: rotate(360deg); } }
            @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
            @keyframes progress { 0% { width: 0%; } 100% { width: 100%; } }
        </style>
    </head>
    <body>
        <div class="loader-container">
            <div class="loader"></div>
            <div class="loader-text">Redirecting to dashboard...</div>
            <div class="loader-progress"><div class="loader-progress-bar"></div></div>
        </div>
        <script>
            setTimeout(function() { window.location.href = 'index.php'; }, 800);
        </script>
    </body>
    </html>
    <?php
    exit;
}

$error = '';
$success = '';
$telegramBotUsername = htmlspecialchars($settings['telegram_bot_username'] ?? 'approvedchecker_bot', ENT_QUOTES, 'UTF-8');

// Rate limiting
$ip = $_SERVER['REMOTE_ADDR'];
$attemptsFile = __DIR__ . '/data/login_attempts.json';
$attempts = [];

if (file_exists($attemptsFile)) {
    $attempts = json_decode(file_get_contents($attemptsFile), true);
    if (!is_array($attempts)) $attempts = [];
}

foreach ($attempts as $key => $attempt) {
    if ($attempt['timestamp'] < time() - 900) unset($attempts[$key]);
}

$isBlocked = false;
$remainingTime = 0;
if (isset($attempts[$ip]) && $attempts[$ip]['count'] >= 5) {
    $isBlocked = true;
    $remainingTime = 900 - (time() - $attempts[$ip]['timestamp']);
    $error = "Too many failed attempts. Please try again after " . ceil($remainingTime / 60) . " minutes.";
}

// Handle Telegram auto-login
if (isset($_GET['tg_auth']) && isset($_GET['id']) && !$isBlocked) {
    $userId = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    $firstName = htmlspecialchars(urldecode($_GET['first_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $lastName = htmlspecialchars(urldecode($_GET['last_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $username = htmlspecialchars(urldecode($_GET['username'] ?? ''), ENT_QUOTES, 'UTF-8');
    $photoUrl = htmlspecialchars(urldecode($_GET['photo_url'] ?? ''), ENT_QUOTES, 'UTF-8');
    
    if ($userId) {
        $db = getMongoDB();
        if ($db) {
            $user = $db->users->findOne(['telegram_id' => (int)$userId]);
            
            if ($user) {
                if ($user['banned']) {
                    $error = "Your account has been banned.";
                } else {
                    $db->users->updateOne(['telegram_id' => (int)$userId], ['$set' => [
                        'telegram_username' => $username,
                        'photo_url' => $photoUrl,
                        'display_name' => trim($firstName . ' ' . $lastName),
                        'last_login' => new MongoDB\BSON\UTCDateTime()
                    ]]);
                    
                    $_SESSION['user'] = [
                        'name' => $user['username'],
                        'credits' => $user['credits'],
                        'is_admin' => (bool)$user['is_admin'],
                        'is_owner' => (bool)$user['is_owner'],
                        'banned' => (bool)$user['banned'],
                        'username' => $user['username'],
                        'display_name' => $user['display_name'] ?? trim($firstName . ' ' . $lastName),
                        'plan' => $user['plan'] ?? 'basic',
                        'user_id' => (string)$user['_id'],
                        'telegram_id' => (int)$userId,
                        'telegram_username' => $username,
                        'photo_url' => $photoUrl
                    ];
                    
                    updateOnlineStatus($user['username']);
                    
                    if (isset($attempts[$ip])) {
                        unset($attempts[$ip]);
                        file_put_contents($attemptsFile, json_encode($attempts));
                    }
                    
                    echo '<script>window.location.href = "index.php?refresh=1";</script>';
                    exit;
                }
            } else {
                // Create new user from Telegram
                $newUsername = 'tg_' . $userId;
                $displayName = trim($firstName . ' ' . $lastName);
                $dummyPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $apiKey = 'cxchk_' . bin2hex(random_bytes(32));
                
                $db->users->insertOne([
                    'username' => $newUsername,
                    'password_hash' => $dummyPassword,
                    'display_name' => $displayName,
                    'telegram_id' => (int)$userId,
                    'telegram_username' => $username,
                    'photo_url' => $photoUrl,
                    'credits' => 100,
                    'plan' => 'basic',
                    'api_key' => $apiKey,
                    'is_admin' => 0,
                    'is_owner' => 0,
                    'banned' => 0,
                    'created_at' => new MongoDB\BSON\UTCDateTime(),
                    'last_login' => new MongoDB\BSON\UTCDateTime()
                ]);
                
                $newUser = $db->users->findOne(['telegram_id' => (int)$userId]);
                
                $_SESSION['user'] = [
                    'name' => $newUsername,
                    'credits' => 100,
                    'is_admin' => false,
                    'is_owner' => false,
                    'banned' => false,
                    'username' => $newUsername,
                    'display_name' => $displayName,
                    'plan' => 'basic',
                    'user_id' => (string)$newUser['_id'],
                    'telegram_id' => (int)$userId,
                    'telegram_username' => $username,
                    'photo_url' => $photoUrl
                ];
                
                updateOnlineStatus($newUsername);
                echo '<script>window.location.href = "index.php?refresh=1";</script>';
                exit;
            }
        }
    }
}

// Handle regular login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isBlocked) {
    $action = $_POST['action'] ?? '';
    
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page.';
    } elseif ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Username and password are required';
        } elseif (authenticateUser($username, $password)) {
            if (isset($attempts[$ip])) {
                unset($attempts[$ip]);
                file_put_contents($attemptsFile, json_encode($attempts));
            }
            
            session_regenerate_id(true);
            $_SESSION['user']['login_ip'] = $ip;
            $_SESSION['user']['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $_SESSION['user']['login_time'] = date('Y-m-d H:i:s');
            
            echo '<script>window.location.href = "index.php?refresh=1";</script>';
            exit;
        } else {
            $error = 'Invalid username or password';
            
            if (!isset($attempts[$ip])) {
                $attempts[$ip] = ['count' => 0, 'timestamp' => time()];
            }
            $attempts[$ip]['count']++;
            $attempts[$ip]['timestamp'] = time();
            file_put_contents($attemptsFile, json_encode($attempts));
            
            if ($attempts[$ip]['count'] >= 5) {
                $error = "Too many failed attempts. Please try again after 15 minutes.";
            }
        }
    } elseif ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address';
        } else {
            if (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters';
            } elseif (!preg_match('/[A-Z]/', $password)) {
                $error = 'Password must contain at least one uppercase letter';
            } elseif (!preg_match('/[a-z]/', $password)) {
                $error = 'Password must contain at least one lowercase letter';
            } elseif (!preg_match('/[0-9]/', $password)) {
                $error = 'Password must contain at least one number';
            } elseif (strlen($username) < 3 || strlen($username) > 32) {
                $error = 'Username must be 3-32 characters';
            } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
                $error = 'Username can only contain letters, numbers, and underscore';
            } else {
                $result = registerUser($username, $password, $email);
                if ($result['success']) {
                    $success = 'Account created successfully! You can now log in.';
                } else {
                    $error = htmlspecialchars($result['error'], ENT_QUOTES, 'UTF-8');
                }
            }
        }
    }
}

$csrf_token = generateCSRFToken();
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
        
        /* Loading Overlay */
        .init-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #0a0a0f;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 20px;
            transition: opacity 0.5s ease;
        }
        .init-overlay.hide { opacity: 0; pointer-events: none; }
        .init-logo {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #8b5cf6, #06b6d4);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 1s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(139,92,246,0.4); }
            50% { transform: scale(1.05); box-shadow: 0 0 0 15px rgba(139,92,246,0); }
        }
        .init-logo i { font-size: 32px; color: white; }
        .init-text { font-size: 14px; font-weight: 500; color: #c4b5fd; letter-spacing: 1px; }
        .init-progress { width: 200px; height: 2px; background: rgba(255,255,255,0.1); border-radius: 2px; overflow: hidden; }
        .init-progress-bar { width: 0%; height: 100%; background: linear-gradient(90deg, #8b5cf6, #06b6d4); transition: width 0.3s ease; }
        
        /* Main Container */
        .container {
            position: relative;
            background: rgba(15, 15, 35, 0.92);
            backdrop-filter: blur(15px);
            border-radius: 24px;
            padding: 28px 24px;
            width: 100%;
            max-width: 420px;
            border: 1px solid rgba(167, 139, 250, 0.25);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            z-index: 10;
            animation: slideUp 0.5s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .logo-container { text-align: center; margin-bottom: 20px; }
        .logo {
            width: 65px;
            height: 65px;
            border-radius: 18px;
            object-fit: cover;
            box-shadow: 0 10px 30px rgba(139,92,246,0.3);
        }
        h1 {
            font-size: 1.3rem;
            font-weight: 800;
            text-align: center;
            background: linear-gradient(135deg, #fff, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-top: 12px;
            letter-spacing: -0.5px;
        }
        .subtitle {
            text-align: center;
            color: #94a3b8;
            font-size: 0.65rem;
            margin-bottom: 20px;
        }
        
        /* Login Options - Side by Side */
        .login-options-row {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }
        .login-option {
            flex: 1;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(167, 139, 250, 0.2);
            border-radius: 14px;
            padding: 10px 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .login-option:hover {
            background: rgba(139, 92, 246, 0.1);
            border-color: #8b5cf6;
            transform: translateY(-2px);
        }
        .login-option i { font-size: 20px; margin-bottom: 4px; display: block; }
        .login-option .option-title { font-size: 11px; font-weight: 600; color: #c4b5fd; }
        .login-option .option-desc { font-size: 9px; color: #6b7280; margin-top: 2px; }
        .telegram-option i { color: #0088cc; }
        
        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            margin: 16px 0;
            color: #94a3b8;
            font-size: 0.65rem;
        }
        .divider::before, .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }
        .divider span { padding: 0 12px; }
        
        /* Tabs */
        .tabs {
            display: flex;
            gap: 8px;
            margin: 16px 0 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 4px;
        }
        .tab-btn {
            flex: 1;
            padding: 8px;
            background: transparent;
            border: none;
            border-radius: 10px;
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .tab-btn.active {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
            box-shadow: 0 4px 12px rgba(59,130,246,0.3);
        }
        
        .form-wrapper { display: none; animation: fadeIn 0.3s ease; }
        .form-wrapper.active { display: block; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-group { margin-bottom: 16px; }
        label { color: #c4b5fd; font-weight: 500; font-size: 0.65rem; margin-bottom: 6px; display: block; letter-spacing: 0.3px; }
        .input-wrapper { position: relative; }
        .input-wrapper i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #6b7280; font-size: 0.8rem; }
        input {
            width: 100%;
            padding: 10px 12px 10px 38px;
            background: rgba(255, 255, 255, 0.06);
            border: 1.5px solid rgba(167, 139, 250, 0.25);
            border-radius: 10px;
            color: white;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        input:focus { outline: none; border-color: #8b5cf6; background: rgba(255, 255, 255, 0.08); }
        
        button[type="submit"] {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 8px;
        }
        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }
        
        .error {
            background: rgba(239,68,68,0.12);
            border: 1px solid rgba(239,68,68,0.25);
            color: #f87171;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 16px;
            text-align: center;
            font-size: 0.7rem;
        }
        .success {
            background: rgba(34,197,94,0.12);
            border: 1px solid rgba(34,197,94,0.25);
            color: #4ade80;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 16px;
            text-align: center;
            font-size: 0.7rem;
        }
        
        .security-note {
            text-align: center;
            margin-top: 16px;
            font-size: 0.5rem;
            color: #6b7280;
        }
        
        @media (max-width: 480px) {
            .container { padding: 22px 18px; max-width: 360px; }
            .login-option { padding: 8px 6px; }
            .login-option i { font-size: 18px; }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="init-overlay" id="initOverlay">
        <div class="init-logo"><i class="fas fa-credit-card"></i></div>
        <div class="init-text" id="initText">Initializing secure connection...</div>
        <div class="init-progress"><div class="init-progress-bar" id="initProgress"></div></div>
    </div>
    
    <div class="container">
        <div class="logo-container">
            <img src="https://i.ibb.co/6c0996jT/photo-5888528011563747370-c.jpg" alt="Logo" class="logo">
        </div>
        <h1>APPROVED CHECKER</h1>
        <p class="subtitle">Enterprise Security Suite</p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Login Options Row - Side by Side -->
        <div class="login-options-row">
            <div class="login-option" onclick="document.querySelector('.tab-btn:first-child').click(); document.getElementById('login-form').scrollIntoView({behavior:'smooth'});">
                <i class="fas fa-key"></i>
                <div class="option-title">Password</div>
                <div class="option-desc">Use credentials</div>
            </div>
            <div class="login-option telegram-option" id="telegramLoginBtn">
                <i class="fab fa-telegram"></i>
                <div class="option-title">Telegram</div>
                <div class="option-desc">Auto-login</div>
            </div>
        </div>
        
        <div class="divider">
            <span>OR</span>
        </div>
        
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('login')">Sign In</button>
            <button class="tab-btn" onclick="switchTab('register')">Create Account</button>
        </div>
        
        <div id="login-form" class="form-wrapper active">
            <form method="POST" id="loginForm">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="form-group">
                    <label>Username</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" required autocomplete="username">
                    </div>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" required autocomplete="current-password">
                    </div>
                </div>
                <button type="submit" id="loginSubmit">Access Dashboard</button>
            </form>
        </div>
        
        <div id="register-form" class="form-wrapper">
            <form method="POST" id="registerForm">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="form-group">
                    <label>Username</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" required minlength="3" pattern="[A-Za-z0-9_]{3,32}">
                    </div>
                </div>
                <div class="form-group">
                    <label>Email (optional)</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email">
                    </div>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-key"></i>
                        <input type="password" name="password" required minlength="8">
                    </div>
                </div>
                <button type="submit" id="registerSubmit">Create Account</button>
            </form>
        </div>
        
        <div class="security-note">
            <i class="fas fa-shield-alt"></i> 5 failed attempts = 15 min lockout • IP logged
        </div>
    </div>
    
    <script>
        // Loading Animation
        const initMessages = [
            "Initializing secure connection...",
            "Loading security modules...",
            "Establishing encrypted tunnel...",
            "Verifying system integrity...",
            "Ready. Welcome to APPROVED CHECKER."
        ];
        
        let progress = 0;
        let msgIndex = 0;
        const initOverlay = document.getElementById('initOverlay');
        const initText = document.getElementById('initText');
        const initProgress = document.getElementById('initProgress');
        
        function updateInit() {
            if (progress < 100) {
                progress += 20;
                initProgress.style.width = progress + '%';
                if (msgIndex < initMessages.length) {
                    initText.textContent = initMessages[msgIndex];
                    msgIndex++;
                }
                setTimeout(updateInit, 400);
            } else {
                initOverlay.classList.add('hide');
                setTimeout(() => { initOverlay.style.display = 'none'; }, 500);
            }
        }
        setTimeout(updateInit, 300);
        
        // Form submit with loading animation
        document.getElementById('loginForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            showFormLoader('Logging in...');
            setTimeout(() => { this.submit(); }, 500);
        });
        
        document.getElementById('registerForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            showFormLoader('Creating account...');
            setTimeout(() => { this.submit(); }, 500);
        });
        
        function showFormLoader(message) {
            const loader = document.createElement('div');
            loader.className = 'init-overlay';
            loader.style.display = 'flex';
            loader.innerHTML = `
                <div class="init-logo"><i class="fas fa-credit-card"></i></div>
                <div class="init-text">${message}</div>
                <div class="init-progress"><div class="init-progress-bar" style="width: 0%; animation: progress 0.8s ease forwards;"></div></div>
            `;
            document.body.appendChild(loader);
        }
        
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
        
        // Telegram Login
        document.getElementById('telegramLoginBtn')?.addEventListener('click', function() {
            showFormLoader('Connecting to Telegram...');
            
            const script = document.createElement('script');
            script.src = 'https://telegram.org/js/telegram-widget.js?22';
            script.setAttribute('data-telegram-login', '<?php echo $telegramBotUsername; ?>');
            script.setAttribute('data-size', 'large');
            script.setAttribute('data-radius', '12');
            script.setAttribute('data-onauth', 'onTelegramAuth');
            script.async = true;
            document.head.appendChild(script);
            
            setTimeout(() => {
                const widget = document.querySelector('.telegram-login-widget');
                if (widget) widget.click();
            }, 100);
        });
        
        function onTelegramAuth(user) {
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
    </script>
</body>
</html>