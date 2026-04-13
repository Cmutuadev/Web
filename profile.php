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

$user = $_SESSION['user'];
$credits = getUserCredits();
$isAdmin = isAdmin();
$userStats = getUserStats($user['name']);
$creditHistory = loadCreditHistory();
$userHistory = array_filter($creditHistory, function($h) use ($user) {
    return $h['username'] === $user['name'];
});

// Plan limits
$planLimits = [
    'basic' => ['daily' => 100, 'monthly' => 3000, 'api_calls' => 1000, 'price' => 0],
    'premium' => ['daily' => 500, 'monthly' => 15000, 'api_calls' => 5000, 'price' => 15],
    'gold' => ['daily' => 1500, 'monthly' => 45000, 'api_calls' => 15000, 'price' => 30],
    'platinum' => ['daily' => 5000, 'monthly' => 150000, 'api_calls' => 50000, 'price' => 60],
    'lifetime' => ['daily' => 999999, 'monthly' => 9999999, 'api_calls' => 999999, 'price' => 150]
];

$userPlan = $user['plan'] ?? 'basic';
$currentLimits = $planLimits[$userPlan];

// Calculate daily usage (last 24 hours)
$today = date('Y-m-d');
$dailyChecks = count(array_filter($userHistory, function($h) use ($today) {
    return date('Y-m-d', strtotime($h['created_at'])) === $today;
}));

// Calculate monthly usage (last 30 days)
$monthlyChecks = count(array_filter($userHistory, function($h) {
    return strtotime($h['created_at']) > strtotime('-30 days');
}));

// API usage (count from credit history with API flag)
$apiCalls = count(array_filter($userHistory, function($h) {
    return strpos($h['reason'], 'API') !== false;
}));

// Most used gateways
$gatewayUsage = [];
foreach ($userHistory as $h) {
    $gateway = $h['gateway'] ?? 'Unknown';
    if (!isset($gatewayUsage[$gateway])) {
        $gatewayUsage[$gateway] = 0;
    }
    $gatewayUsage[$gateway]++;
}
arsort($gatewayUsage);
$topGateways = array_slice($gatewayUsage, 0, 5);

// Hourly activity heatmap (last 30 days)
$hourlyActivity = array_fill(0, 24, 0);
foreach ($userHistory as $h) {
    $hour = date('H', strtotime($h['created_at']));
    $hourlyActivity[(int)$hour]++;
}

// Daily activity for chart
$dailyActivity = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $count = count(array_filter($userHistory, function($h) use ($date) {
        return date('Y-m-d', strtotime($h['created_at'])) === $date;
    }));
    $dailyActivity[] = ['date' => date('D', strtotime($date)), 'count' => $count];
}

// Favorite gate (most used)
$favoriteGate = !empty($topGateways) ? array_key_first($topGateways) : 'None';
$favoriteGateCount = $topGateways[$favoriteGate] ?? 0;

// Success rate per gateway
$gatewaySuccess = [];
foreach ($userHistory as $h) {
    $gateway = $h['gateway'] ?? 'Unknown';
    if (!isset($gatewaySuccess[$gateway])) {
        $gatewaySuccess[$gateway] = ['total' => 0, 'approved' => 0];
    }
    $gatewaySuccess[$gateway]['total']++;
    if (stripos($h['reason'], 'approved') !== false || stripos($h['reason'], 'charged') !== false) {
        $gatewaySuccess[$gateway]['approved']++;
    }
}

// Handle password change
$passwordMessage = '';
$passwordError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    $db = getMongoDB();
    if ($db) {
        $userDoc = $db->users->findOne(['username' => $user['name']]);
        if ($userDoc && password_verify($currentPassword, $userDoc['password_hash'])) {
            if ($newPassword === $confirmPassword) {
                if (strlen($newPassword) >= 8) {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $db->users->updateOne(
                        ['username' => $user['name']],
                        ['$set' => ['password_hash' => $newHash]]
                    );
                    $passwordMessage = "Password changed successfully!";
                } else {
                    $passwordError = "Password must be at least 8 characters";
                }
            } else {
                $passwordError = "New passwords do not match";
            }
        } else {
            $passwordError = "Current password is incorrect";
        }
    }
}

// Handle API key regeneration
$apiKeyMessage = '';
if (isset($_POST['regenerate_api_key'])) {
    $db = getMongoDB();
    if ($db) {
        $newApiKey = 'cxchk_' . bin2hex(random_bytes(32));
        $db->users->updateOne(
            ['username' => $user['name']],
            ['$set' => ['api_key' => $newApiKey]]
        );
        $_SESSION['user']['api_key'] = $newApiKey;
        $apiKeyMessage = "New API key generated successfully!";
    }
}

// Calculate remaining percentages
$dailyRemaining = max(0, $currentLimits['daily'] - $dailyChecks);
$dailyPercent = min(100, ($dailyChecks / $currentLimits['daily']) * 100);
$monthlyRemaining = max(0, $currentLimits['monthly'] - $monthlyChecks);
$monthlyPercent = min(100, ($monthlyChecks / $currentLimits['monthly']) * 100);
$apiRemaining = max(0, $currentLimits['api_calls'] - $apiCalls);
$apiPercent = min(100, ($apiCalls / $currentLimits['api_calls']) * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | APPROVED CHECKER</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --bg: #0a0a0f; --card: #111114; --border: #1e1e24; --text: #ffffff; --text-muted: #6b6b76; --primary: #8b5cf6; --success: #10b981; --danger: #ef4444; --warning: #f59e0b; --info: #3b82f6; --font-size: 13px; }
        [data-theme="light"] { --bg: #f8fafc; --card: #ffffff; --border: #e2e8f0; --text: #0f172a; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; font-size: var(--font-size); }
        .main { margin-left: 280px; margin-top: 55px; padding: 1.2rem; transition: margin-left 0.2s; }
        @media (max-width: 768px) { .main { margin-left: 0; } }
        .container { max-width: 1400px; margin: 0 auto; }
        
        .profile-header { background: var(--card); border-radius: 1rem; padding: 1.5rem; text-align: center; margin-bottom: 1.2rem; border: 1px solid var(--border); }
        .profile-avatar { width: 80px; height: 80px; background: #000000; border: 3px solid var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem; font-size: 2rem; font-weight: 700; color: white; }
        .profile-name { font-size: 1.2rem; font-weight: 700; margin-bottom: 0.2rem; }
        .profile-plan { display: inline-block; padding: 0.15rem 0.6rem; border-radius: 20px; font-size: 0.6rem; background: rgba(139,92,246,0.2); color: var(--primary); margin-top: 0.3rem; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.8rem; margin-bottom: 1.2rem; }
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.6rem; padding: 0.8rem; text-align: center; }
        .stat-value { font-size: 1.2rem; font-weight: 700; }
        .stat-label { font-size: 0.55rem; color: var(--text-muted); margin-top: 0.2rem; text-transform: uppercase; }
        
        .progress-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.6rem; padding: 0.8rem; margin-bottom: 0.8rem; }
        .progress-header { display: flex; justify-content: space-between; margin-bottom: 0.3rem; font-size: 0.65rem; }
        .progress-bar { height: 4px; background: var(--border); border-radius: 2px; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 2px; transition: width 0.3s; }
        .progress-fill.daily { background: linear-gradient(90deg, var(--info), var(--primary)); }
        .progress-fill.monthly { background: linear-gradient(90deg, var(--success), #06b6d4); }
        .progress-fill.api { background: linear-gradient(90deg, var(--warning), var(--danger)); }
        
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.8rem; margin-bottom: 1.2rem; }
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.8rem; margin-bottom: 1.2rem; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 0.6rem; padding: 0.8rem; }
        .card-title { font-weight: 600; font-size: 0.7rem; margin-bottom: 0.6rem; display: flex; align-items: center; gap: 0.3rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .gateway-item { display: flex; justify-content: space-between; align-items: center; padding: 0.4rem 0; border-bottom: 1px solid var(--border); font-size: 0.65rem; }
        .gateway-name { font-weight: 500; }
        .gateway-count { color: var(--primary); font-weight: 600; }
        .gateway-success { font-size: 0.55rem; color: var(--success); }
        
        .hour-bars { display: flex; gap: 0.2rem; margin-top: 0.5rem; }
        .hour-bar { flex: 1; text-align: center; }
        .bar { height: 30px; background: var(--primary); border-radius: 2px; margin-bottom: 0.2rem; transition: height 0.3s; opacity: 0.7; }
        .bar:hover { opacity: 1; }
        .hour-label { font-size: 0.45rem; color: var(--text-muted); }
        
        .info-row { display: flex; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px solid var(--border); font-size: 0.65rem; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: var(--text-muted); }
        .info-value { font-weight: 500; word-break: break-all; }
        
        .btn { padding: 0.4rem 0.8rem; border-radius: 0.4rem; font-weight: 500; font-size: 0.65rem; cursor: pointer; border: none; transition: all 0.2s; display: inline-block; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), #7c3aed); color: white; }
        .btn-secondary { background: var(--bg); border: 1px solid var(--border); color: var(--text); }
        .btn-danger { background: var(--danger); color: white; }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.55rem; }
        .copy-btn { background: none; border: none; cursor: pointer; color: var(--text-muted); margin-left: 0.3rem; font-size: 0.55rem; }
        
        .form-group { margin-bottom: 0.6rem; }
        .form-group label { display: block; font-size: 0.6rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.2rem; }
        .form-control { width: 100%; padding: 0.4rem; background: var(--bg); border: 1px solid var(--border); border-radius: 0.3rem; color: var(--text); font-size: 0.65rem; }
        
        .success-message { background: rgba(16,185,129,0.1); border: 1px solid var(--success); color: var(--success); padding: 0.4rem; border-radius: 0.3rem; margin-bottom: 0.6rem; font-size: 0.6rem; }
        .error-message { background: rgba(239,68,68,0.1); border: 1px solid var(--danger); color: var(--danger); padding: 0.4rem; border-radius: 0.3rem; margin-bottom: 0.6rem; font-size: 0.6rem; }
        
        @media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } .grid-3 { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .grid-3, .grid-2 { grid-template-columns: 1fr; } }
        
        canvas { max-height: 150px; width: 100%; }
        .chart-container { margin-top: 0.5rem; }
    </style>
</head>
<body data-theme="dark">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main">
        <div class="container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar"><?php echo strtoupper(substr($user['display_name'] ?? $user['name'], 0, 1)); ?></div>
                <div class="profile-name"><?php echo htmlspecialchars($user['display_name'] ?? $user['name']); ?></div>
                <div class="profile-plan"><?php echo ucfirst($userPlan); ?> Plan • $<?php echo $currentLimits['price']; ?>/month</div>
                <div style="margin-top: 0.8rem;">
                    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Dashboard</a>
                    <a href="topup.php" class="btn btn-primary"><i class="fas fa-coins"></i> Top Up</a>
                </div>
            </div>
            
            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value"><?php echo $isAdmin ? '∞' : number_format($credits); ?></div><div class="stat-label">Credits</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $userStats['total_checks']; ?></div><div class="stat-label">Total Checks</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $userStats['approved_checks']; ?></div><div class="stat-label">Approved</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $userStats['success_rate']; ?>%</div><div class="stat-label">Success Rate</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo number_format($credits / 100, 1); ?>K</div><div class="stat-label">Credits Used</div></div>
            </div>
            
            <!-- Usage Limits -->
            <div class="grid-3">
                <div class="progress-card">
                    <div class="progress-header"><span><i class="fas fa-calendar-day"></i> Daily Limit</span><span><?php echo $dailyChecks; ?> / <?php echo $currentLimits['daily']; ?></span></div>
                    <div class="progress-bar"><div class="progress-fill daily" style="width: <?php echo $dailyPercent; ?>%;"></div></div>
                    <div style="font-size: 0.55rem; margin-top: 0.3rem; color: var(--text-muted);"><?php echo $dailyRemaining; ?> remaining today</div>
                </div>
                <div class="progress-card">
                    <div class="progress-header"><span><i class="fas fa-calendar-month"></i> Monthly Limit</span><span><?php echo $monthlyChecks; ?> / <?php echo $currentLimits['monthly']; ?></span></div>
                    <div class="progress-bar"><div class="progress-fill monthly" style="width: <?php echo $monthlyPercent; ?>%;"></div></div>
                    <div style="font-size: 0.55rem; margin-top: 0.3rem; color: var(--text-muted);"><?php echo $monthlyRemaining; ?> remaining this month</div>
                </div>
                <div class="progress-card">
                    <div class="progress-header"><span><i class="fas fa-code"></i> API Calls</span><span><?php echo $apiCalls; ?> / <?php echo $currentLimits['api_calls']; ?></span></div>
                    <div class="progress-bar"><div class="progress-fill api" style="width: <?php echo $apiPercent; ?>%;"></div></div>
                    <div style="font-size: 0.55rem; margin-top: 0.3rem; color: var(--text-muted);"><?php echo $apiRemaining; ?> API calls left</div>
                </div>
            </div>
            
            <div class="grid-2">
                <!-- Most Used Gateways -->
                <div class="card">
                    <div class="card-title"><i class="fas fa-chart-simple"></i> Most Used Gateways</div>
                    <?php if (empty($topGateways)): ?>
                    <div style="text-align: center; padding: 1rem; color: var(--text-muted); font-size: 0.65rem;">No data yet</div>
                    <?php else: ?>
                        <?php $rank = 1; foreach ($topGateways as $gateway => $count): ?>
                        <div class="gateway-item">
                            <div><span class="gateway-name"><?php echo $rank++; ?>. <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $gateway))); ?></span></div>
                            <div><span class="gateway-count"><?php echo $count; ?> checks</span></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Favorite Gate & Success Rate -->
                <div class="card">
                    <div class="card-title"><i class="fas fa-star"></i> Favorite & Performance</div>
                    <div class="info-row"><span class="info-label">Favorite Gateway</span><span class="info-value"><strong><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $favoriteGate))); ?></strong> (<?php echo $favoriteGateCount; ?> checks)</span></div>
                    <div class="info-row"><span class="info-label">Best Performing Gate</span><span class="info-value">
                        <?php 
                        $bestGate = '';
                        $bestRate = 0;
                        foreach ($gatewaySuccess as $gate => $stats) {
                            if ($stats['total'] > 5) {
                                $rate = ($stats['approved'] / $stats['total']) * 100;
                                if ($rate > $bestRate) {
                                    $bestRate = $rate;
                                    $bestGate = $gate;
                                }
                            }
                        }
                        echo $bestGate ? htmlspecialchars(ucfirst(str_replace('_', ' ', $bestGate))) . " ({$bestRate}%)" : 'Not enough data';
                        ?>
                    </span></div>
                    <div class="info-row"><span class="info-label">Total API Calls</span><span class="info-value"><?php echo $apiCalls; ?></span></div>
                    <div class="info-row"><span class="info-label">Member Since</span><span class="info-value"><?php echo isset($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : '-'; ?></span></div>
                </div>
            </div>
            
            <!-- Activity Heatmap (Hourly) -->
            <div class="card">
                <div class="card-title"><i class="fas fa-chart-line"></i> Activity Heatmap (Last 7 Days)</div>
                <canvas id="activityChart" style="max-height: 180px;"></canvas>
            </div>
            
            <!-- Hourly Activity -->
            <div class="card">
                <div class="card-title"><i class="fas fa-clock"></i> Hourly Activity</div>
                <div class="hour-bars" style="display: flex; flex-wrap: wrap; gap: 0.2rem; justify-content: center;" id="hourBars"></div>
                <div style="text-align: center; margin-top: 0.5rem; font-size: 0.5rem; color: var(--text-muted);">Most active: 
                    <?php 
                    $maxHour = array_keys($hourlyActivity, max($hourlyActivity))[0] ?? 0;
                    echo date('g A', strtotime("$maxHour:00"));
                    ?>
                </div>
            </div>
            
            <div class="grid-2">
                <!-- API Key & Security -->
                <div class="card">
                    <div class="card-title"><i class="fas fa-key"></i> API Access</div>
                    <div class="info-row"><span class="info-label">API Key</span><span class="info-value"><code><?php echo isset($user['api_key']) ? substr($user['api_key'], 0, 20) . '...' : '-'; ?></code><button class="copy-btn" onclick="copyToClipboard('<?php echo $user['api_key']; ?>')"><i class="fas fa-copy"></i></button></span></div>
                    <div class="info-row"><span class="info-label">API Calls Used</span><span class="info-value"><?php echo $apiCalls; ?> / <?php echo $currentLimits['api_calls']; ?></span></div>
                    <div class="info-row"><span class="info-label">API Docs</span><span class="info-value"><a href="/pages/api_docs.php" style="color:var(--primary);">View Documentation →</a></span></div>
                    <div class="mt-1"><form method="POST" style="display:inline;"><button type="submit" name="regenerate_api_key" class="btn btn-danger btn-sm" onclick="return confirm('Regenerating API key will invalidate your old key. Continue?')">Regenerate Key</button></form></div>
                    <?php if ($apiKeyMessage): ?>
                    <div class="success-message mt-1"><?php echo $apiKeyMessage; ?></div>
                    <?php endif; ?>
                </div>
                
                <!-- Security Info -->
                <div class="card">
                    <div class="card-title"><i class="fas fa-shield-alt"></i> Security</div>
                    <div class="info-row"><span class="info-label">Last Login IP</span><span class="info-value"><?php echo $user['login_ip'] ?? '-'; ?></span></div>
                    <div class="info-row"><span class="info-label">Last Login Time</span><span class="info-value"><?php echo $user['login_time'] ?? '-'; ?></span></div>
                    <div class="info-row"><span class="info-label">Telegram Connected</span><span class="info-value"><?php echo !empty($user['telegram_id']) ? '<span style="color:var(--success);"><i class="fab fa-telegram"></i> Yes</span>' : 'No'; ?></span></div>
                    <div class="info-row"><span class="info-label">Telegram Username</span><span class="info-value"><?php echo !empty($user['telegram_username']) ? '@' . htmlspecialchars($user['telegram_username']) : '-'; ?></span></div>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="card">
                <div class="card-title"><i class="fas fa-lock"></i> Change Password</div>
                <?php if ($passwordMessage): ?>
                <div class="success-message"><?php echo $passwordMessage; ?></div>
                <?php endif; ?>
                <?php if ($passwordError): ?>
                <div class="error-message"><?php echo $passwordError; ?></div>
                <?php endif; ?>
                <form method="POST" class="grid-2" style="gap: 0.6rem;">
                    <div class="form-group"><label>Current Password</label><input type="password" name="current_password" class="form-control" required></div>
                    <div class="form-group"><label>New Password</label><input type="password" name="new_password" class="form-control" required minlength="8"></div>
                    <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" class="form-control" required></div>
                    <div class="form-group" style="display: flex; align-items: flex-end;"><button type="submit" name="change_password" class="btn btn-primary" style="width:100%;">Update Password</button></div>
                </form>
            </div>
        </div>
    </main>
    
    <script>
        // Activity Chart
        const ctx = document.getElementById('activityChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($dailyActivity, 'date')); ?>,
                datasets: [{
                    label: 'Checks',
                    data: <?php echo json_encode(array_column($dailyActivity, 'count')); ?>,
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#8b5cf6',
                    pointBorderColor: '#ffffff',
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { labels: { color: 'var(--text-muted)', font: { size: 10 } } }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'var(--border)' }, ticks: { color: 'var(--text-muted)', font: { size: 9 } } },
                    x: { grid: { display: false }, ticks: { color: 'var(--text-muted)', font: { size: 9 } } }
                }
            }
        });
        
        // Hourly Activity Bars
        const hourlyData = <?php echo json_encode($hourlyActivity); ?>;
        const maxHourly = Math.max(...hourlyData);
        const hourBars = document.getElementById('hourBars');
        
        for (let i = 0; i < 24; i++) {
            const height = maxHourly > 0 ? (hourlyData[i] / maxHourly) * 40 : 0;
            const barDiv = document.createElement('div');
            barDiv.className = "hour-bar"; barDiv.style.flex = "1"; barDiv.style.minWidth = "30px";;
            barDiv.innerHTML = `<div class="bar" style="height: ${height}px; background: var(--primary);"></div><div class="hour-label">${i}:00</div>`;
            hourBars.appendChild(barDiv);
        }
        
        function copyToClipboard(text) {
            if (!text) return;
            navigator.clipboard.writeText(text);
            Swal.fire({ toast: true, icon: 'success', title: 'Copied!', showConfirmButton: false, timer: 1500 });
        }
        
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.body.setAttribute('data-theme', savedTheme);
        const themeBtn = document.getElementById('themeBtn');
        if (themeBtn) {
            themeBtn.innerHTML = savedTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
            themeBtn.addEventListener('click', () => {
                const newTheme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                document.body.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                themeBtn.innerHTML = newTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
                location.reload();
            });
        }
        
        const menuBtn = document.getElementById('menuBtn');
        const sidebar = document.getElementById('sidebar');
        const main = document.getElementById('main');
        if (menuBtn) menuBtn.addEventListener('click', () => { sidebar.classList.toggle('open'); main.classList.toggle('sidebar-open'); });
    </script>
</body>
</html>
