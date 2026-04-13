<?php
require_once 'includes/config.php';

if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    session_destroy();
    header('Location: login.php');
    exit;
}

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (isBanned()) {
    session_destroy();
    header('Location: login.php?banned=1');
    exit;
}

$page = $_GET['page'] ?? 'home';

$allowedPages = [
    'home', 'topup', 'universal',
    'shopify', 'stripe-auth', 'razorpay',
    'auth', 'charge', 'auth-charge',
    'stripe-checkout', 'stripe-invoice', 'stripe-inbuilt',
    'key-stripe', 'key-paypal',
    'address-gen', 'bin-lookup', 'cc-cleaner', 'cc-generator', 'proxy-checker', 'vbv-checker',
    'proxies', 'assets'
];

if (!in_array($page, $allowedPages)) {
    $page = 'home';
}

$user = $_SESSION['user'];

// Fix: Ensure user data is properly formatted
if (is_array($user['plan'] ?? null)) {
    $user['plan'] = 'basic';
}
if (is_array($user['display_name'] ?? null)) {
    $user['display_name'] = 'User';
}
if (is_array($user['name'] ?? null)) {
    $user['name'] = 'User';
}

$credits = getUserCredits();
$isAdmin = isAdmin();
$userPlan = is_string($user['plan'] ?? 'basic') ? $user['plan'] : 'basic';

$creditHistory = loadCreditHistory();
$userHistory = array_filter($creditHistory, function($h) use ($user) {
    return $h['username'] === $user['name'];
});
$totalChecks = count($userHistory);
$approvedChecks = count(array_filter($userHistory, function($h) {
    return stripos($h['reason'], 'approved') !== false || stripos($h['reason'], 'charged') !== false;
}));
$successRate = $totalChecks > 0 ? round(($approvedChecks / $totalChecks) * 100) . '%' : '0%';
$memberDays = isset($user['created_at']) ? floor((time() - strtotime($user['created_at'])) / 86400) : 0;
$recentActivity = array_slice(array_reverse($userHistory), 0, 5);

$allUsers = loadUsers();
$leaderboard = [];
foreach ($allUsers as $username => $u) {
    $userStats = getUserStats($username);
    if ($userStats['approved_checks'] > 0) {
        $leaderboard[] = [
            'name' => $u['display_name'] ?? $username,
            'hits' => $userStats['approved_checks'],
            'plan' => is_string($u['plan'] ?? 'basic') ? $u['plan'] : 'basic'
        ];
    }
}
usort($leaderboard, fn($a, $b) => $b['hits'] - $a['hits']);
$topPerformers = array_slice($leaderboard, 0, 5);

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>APPROVED CHECKER | Premium Checker Suite</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg: #0a0a0f; --card: #111114; --border: #1e1e24; --text: #ffffff;
            --text-muted: #6b6b76; --primary: #8b5cf6; --success: #10b981;
            --danger: #ef4444; --warning: #f59e0b;
            --font-size: 14px;
        }
        [data-theme="light"] { --bg: #f8fafc; --card: #ffffff; --border: #e2e8f0; --text: #0f172a; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; overflow-x: hidden; position: relative; font-size: var(--font-size); }
        #particle-canvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; }
        
        /* Main Content */
        .main { margin-left: 0; margin-top: 60px; padding: 1.2rem; transition: margin-left 0.2s; min-height: calc(100vh - 60px); position: relative; z-index: 1; }
        .main.sidebar-open { margin-left: 280px; }
        @media (max-width: 768px) { .main.sidebar-open { margin-left: 0; } }
        .container { max-width: 1400px; margin: 0 auto; }
        
        /* Dashboard */
        .welcome-title { font-size: 1.6rem; font-weight: 700; background: linear-gradient(135deg, var(--primary), #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 0.3rem; }
        .welcome-subtitle { color: var(--text-muted); font-size: 0.8rem; margin-bottom: 1.5rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.8rem; margin-bottom: 1.5rem; }
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 0.8rem; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-icon { width: 35px; height: 35px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 0.5rem; background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-value { font-size: 1.3rem; font-weight: 700; }
        .stat-label { font-size: 0.6rem; color: var(--text-muted); text-transform: uppercase; }
        .quick-actions { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.8rem; margin-bottom: 1.5rem; }
        .action-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 0.8rem; text-align: center; cursor: pointer; transition: all 0.2s; }
        .action-card:hover { transform: translateY(-2px); border-color: var(--primary); }
        .action-icon { width: 40px; height: 40px; background: rgba(139,92,246,0.15); border-radius: 0.6rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.5rem; }
        .action-icon i { font-size: 1.1rem; color: var(--primary); }
        .action-title { font-weight: 600; font-size: 0.75rem; }
        .action-desc { font-size: 0.6rem; color: var(--text-muted); }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; margin-bottom: 1.2rem; }
        @media (max-width: 768px) { .two-col, .stats-grid, .quick-actions { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px) { .two-col, .stats-grid, .quick-actions { grid-template-columns: 1fr; } }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 0.8rem; }
        .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.6rem; }
        .card-title { font-weight: 600; font-size: 0.8rem; display: flex; align-items: center; gap: 0.4rem; }
        .card-title i { color: var(--primary); }
        .info-row { display: flex; align-items: center; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px solid var(--border); }
        .info-label { font-size: 0.6rem; color: var(--text-muted); }
        .info-value { font-size: 0.7rem; font-weight: 500; display: flex; align-items: center; gap: 0.4rem; }
        .copy-btn { background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 0.6rem; }
        .plan-badge { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 12px; font-size: 0.55rem; font-weight: 600; }
        .plan-basic { background: rgba(107,114,128,0.2); color: #9ca3af; }
        .plan-premium { background: rgba(245,158,11,0.2); color: #f59e0b; }
        .plan-gold { background: rgba(251,191,36,0.2); color: #fbbf24; }
        .plan-platinum { background: rgba(168,85,247,0.2); color: #a855f7; }
        .plan-lifetime { background: rgba(236,72,153,0.2); color: #ec4899; }
        .credits-display { background: linear-gradient(135deg, rgba(139,92,246,0.15), rgba(6,182,212,0.1)); border-radius: 0.6rem; padding: 0.6rem; text-align: center; }
        .credits-amount { font-size: 1.4rem; font-weight: 700; }
        .btn { display: flex; align-items: center; justify-content: center; gap: 0.3rem; padding: 0.4rem 0.6rem; border-radius: 0.4rem; font-weight: 500; font-size: 0.65rem; cursor: pointer; border: none; width: 100%; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), #7c3aed); color: white; }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text); }
        .activity-list { max-height: 260px; overflow-y: auto; }
        .activity-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem; border-bottom: 1px solid var(--border); }
        .activity-icon { width: 26px; height: 26px; border-radius: 0.4rem; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; }
        .activity-icon.charged { background: rgba(16,185,129,0.15); color: var(--success); }
        .activity-icon.approved { background: rgba(139,92,246,0.15); color: var(--primary); }
        .activity-icon.declined { background: rgba(239,68,68,0.15); color: var(--danger); }
        .activity-content { flex: 1; }
        .activity-card { font-size: 0.65rem; font-weight: 500; }
        .activity-status { font-size: 0.55rem; color: var(--text-muted); }
        .activity-time { font-size: 0.55rem; color: var(--text-muted); }
        .leaderboard-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0; border-bottom: 1px solid var(--border); }
        .leaderboard-rank { width: 24px; height: 24px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.65rem; color: white; }
        .leaderboard-name { font-weight: 600; font-size: 0.7rem; }
        .leaderboard-hits { font-size: 0.55rem; color: var(--text-muted); }
        .empty-state { text-align: center; padding: 1rem; color: var(--text-muted); }
        .empty-state i { font-size: 1.2rem; margin-bottom: 0.3rem; opacity: 0.5; }
        .telegram-banner { position: fixed; bottom: 1rem; right: 1rem; z-index: 100; background: var(--card); border: 1px solid rgba(139,92,246,0.3); border-radius: 0.6rem; padding: 0.4rem 0.7rem; display: flex; align-items: center; gap: 0.5rem; backdrop-filter: blur(10px); cursor: pointer; font-size: 0.65rem; }
        .telegram-icon { width: 26px; height: 26px; background: linear-gradient(135deg, var(--primary), #7c3aed); border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .telegram-icon i { color: white; font-size: 0.7rem; }
        .telegram-close { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 0.65rem; }
        .text-success { color: var(--success); }
        .mt-1 { margin-top: 0.25rem; }
        .mt-2 { margin-top: 0.5rem; }
    </style>
</head>
<body data-theme="dark">
    <canvas id="particle-canvas"></canvas>
    
    <!-- Header -->
    <?php include __DIR__ . "/includes/header.php"; ?>
    
    <!-- Sidebar -->
    <?php include __DIR__ . "/includes/sidebar.php"; ?>

    <!-- Main Content -->
    <main class="main" id="main">
        <div class="container">
            <?php if ($page === 'home'): ?>
                <div class="welcome-title">Welcome back, <?php echo htmlspecialchars($user['display_name'] ?? $user['name']); ?> 👋</div>
                <div class="welcome-subtitle">Your premium checking suite is ready. Track performance, manage credits, and stay ahead.</div>
                
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-icon"><i class="fas fa-chart-line"></i></div><div class="stat-value"><?php echo $totalChecks; ?></div><div class="stat-label">Total Checks</div></div>
                    <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#4facfe,#00f2fe);"><i class="fas fa-check-circle"></i></div><div class="stat-value text-success"><?php echo $successRate; ?></div><div class="stat-label">Success Rate</div></div>
                    <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#f093fb,#f5576c);"><i class="fas fa-coins"></i></div><div class="stat-value"><?php echo $isAdmin ? '∞' : number_format($credits); ?></div><div class="stat-label">Your Credits</div></div>
                    <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#f5af19,#f12711);"><i class="fas fa-calendar"></i></div><div class="stat-value"><?php echo $memberDays; ?></div><div class="stat-label">Member Days</div></div>
                </div>
                
                <div class="quick-actions">
                    <div class="action-card" onclick="window.location.href='topup.php'"><div class="action-icon"><i class="fas fa-wallet"></i></div><div class="action-title">Top Up</div><div class="action-desc">Add credits</div></div>
                    <div class="action-card" onclick="window.location.href='index.php?page=universal&gate=shopify'"><div class="action-icon"><i class="fab fa-shopify"></i></div><div class="action-title">Shopify</div><div class="action-desc">Check cards</div></div>
                    <div class="action-card" onclick="window.location.href='index.php?page=universal&gate=stripe_auth'"><div class="action-icon"><i class="fab fa-stripe"></i></div><div class="action-title">Stripe Auth</div><div class="action-desc">Auth checker</div></div>
                    <div class="action-card" onclick="window.location.href='index.php?page=universal&gate=razorpay'"><div class="action-icon"><i class="fas fa-rupee-sign"></i></div><div class="action-title">Razorpay</div><div class="action-desc">Auto checker</div></div>
                </div>
                
                <div class="two-col">
                    <div class="card">
                        <div class="card-header"><div class="card-title"><i class="fas fa-user-circle"></i> Account Overview</div><span class="plan-badge plan-<?php echo $userPlan; ?>"><?php echo ucfirst($userPlan); ?></span></div>
                        <div class="info-row"><span class="info-label">Username</span><span class="info-value"><?php echo htmlspecialchars($user['name']); ?></span></div>
                        <div class="info-row"><span class="info-label">Display Name</span><span class="info-value"><?php echo htmlspecialchars($user['display_name'] ?? $user['name']); ?></span></div>
                        <div class="info-row"><span class="info-label">API Key</span><span class="info-value"><?php echo isset($user['api_key']) ? '••••••••••••' : '-'; ?><?php if (isset($user['api_key'])): ?><button class="copy-btn" onclick="copyToClipboard('<?php echo $user['api_key']; ?>')"><i class="fas fa-copy"></i></button><?php endif; ?></span></div>
                        <div class="info-row"><span class="info-label">Member Since</span><span class="info-value"><?php echo isset($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : '-'; ?></span></div>
                        <div class="mt-1"><button class="btn btn-outline" onclick="window.location.href='profile.php'">Manage Account</button></div>
                    </div>
                    <div class="card">
                        <div class="card-header"><div class="card-title"><i class="fas fa-coins"></i> Credit Balance</div></div>
                        <div class="credits-display"><div class="credits-amount"><?php echo $isAdmin ? '∞' : number_format($credits); ?></div><div class="text-muted" style="font-size:0.6rem;">available credits</div></div>
                        <div class="mt-1"><button class="btn btn-primary" onclick="window.location.href='topup.php'"><i class="fas fa-coins"></i> Top Up Credits</button></div>
                    </div>
                </div>
                
                <div class="two-col">
                    <div class="card">
                        <div class="card-header"><div class="card-title"><i class="fas fa-history"></i> Recent Activity</div></div>
                        <div class="activity-list">
                            <?php if (empty($recentActivity)): ?>
                            <div class="empty-state"><i class="fas fa-inbox"></i><p>No activity yet</p></div>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $activity): 
                                    $status = 'DECLINED';
                                    if (stripos($activity['reason'], 'charged') !== false) $status = 'CHARGED';
                                    elseif (stripos($activity['reason'], 'approved') !== false || stripos($activity['reason'], 'live') !== false) $status = 'APPROVED';
                                    $statusClass = strtolower($status);
                                ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?php echo $statusClass; ?>"><?php if ($status === 'CHARGED'): ?><i class="fas fa-bolt"></i><?php elseif ($status === 'APPROVED'): ?><i class="fas fa-check-circle"></i><?php else: ?><i class="fas fa-times-circle"></i><?php endif; ?></div>
                                    <div class="activity-content"><div class="activity-card"><?php echo htmlspecialchars($activity['card_info'] ?? substr($activity['reason'], 0, 20)); ?></div><div class="activity-status"><?php echo $status; ?></div></div>
                                    <div class="activity-time"><?php echo date('H:i', strtotime($activity['created_at'])); ?></div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><div class="card-title"><i class="fas fa-trophy"></i> Top Performers</div></div>
                        <?php if (empty($topPerformers)): ?>
                        <div class="empty-state"><i class="fas fa-chart-simple"></i><p>No data yet</p></div>
                        <?php else: ?>
                            <?php $rank = 1; foreach ($topPerformers as $performer): ?>
                            <div class="leaderboard-item"><div class="leaderboard-rank"><?php echo $rank++; ?></div><div><div class="leaderboard-name"><?php echo htmlspecialchars($performer['name']); ?></div><div class="leaderboard-hits"><?php echo $performer['hits']; ?> hits</div></div><span class="plan-badge plan-<?php echo $performer['plan']; ?>" style="margin-left:auto;"><?php echo ucfirst($performer['plan']); ?></span></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($page === 'topup'): ?>
                <script>window.location.href = 'topup.php';</script>
            <?php elseif ($page === 'universal'): ?>
                <?php include 'pages/checker/universal.php'; ?>
            <?php else: ?>
                <?php
                $legacyFile = "pages/checker/{$page}.php";
                if (file_exists($legacyFile)) {
                    include $legacyFile;
                } else {
                ?>
                <div class="card" style="text-align:center; padding:2rem;">
                    <i class="fas fa-code" style="font-size:2rem; color:var(--primary); margin-bottom:0.5rem;"></i>
                    <h3><?php echo ucfirst(str_replace('-', ' ', $page)); ?></h3>
                    <p class="text-muted">Coming soon</p>
                    <button class="btn btn-outline" style="width:auto; margin-top:0.5rem;" onclick="window.location.href='index.php?page=home'">Return to Dashboard</button>
                </div>
                <?php } ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Telegram Banner -->
    <div class="telegram-banner" id="telegramBanner">
        <div class="telegram-icon"><i class="fab fa-telegram"></i></div>
        <div><div style="font-weight:500;">No Noise. Just Heavy Hitters.</div><div style="font-size:0.55rem;">Join for drops</div></div>
        <button class="telegram-close" id="closeBanner"><i class="fas fa-times"></i></button>
    </div>

    <script>
        // Particle Background
        const canvas = document.getElementById('particle-canvas');
        const ctx = canvas.getContext('2d');
        let particles = [];
        function resizeCanvas() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
        class Particle { constructor() { this.x = Math.random() * canvas.width; this.y = Math.random() * canvas.height; this.size = Math.random() * 2 + 0.5; this.speedX = (Math.random() - 0.5) * 0.4; this.speedY = (Math.random() - 0.5) * 0.4; this.opacity = Math.random() * 0.4 + 0.2; this.color = `rgba(139, 92, 246, ${this.opacity})`; } update() { this.x += this.speedX; this.y += this.speedY; if (this.x < 0) this.x = canvas.width; if (this.x > canvas.width) this.x = 0; if (this.y < 0) this.y = canvas.height; if (this.y > canvas.height) this.y = 0; } draw() { ctx.beginPath(); ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2); ctx.fillStyle = this.color; ctx.fill(); } }
        function initParticles() { particles = []; for (let i = 0; i < 30; i++) particles.push(new Particle()); }
        function animateParticles() { ctx.clearRect(0, 0, canvas.width, canvas.height); for (let p of particles) { p.update(); p.draw(); } requestAnimationFrame(animateParticles); }
        window.addEventListener('resize', () => { resizeCanvas(); initParticles(); });
        resizeCanvas(); initParticles(); animateParticles();
        
        // Sidebar toggle
        const menuBtn = document.getElementById('menuBtn');
        const sidebar = document.getElementById('sidebar');
        const main = document.getElementById('main');
        if (menuBtn) menuBtn.addEventListener('click', () => { sidebar.classList.toggle('open'); main.classList.toggle('sidebar-open'); });
        document.addEventListener('click', (e) => { if (window.innerWidth <= 768 && sidebar && !sidebar.contains(e.target) && menuBtn && !menuBtn.contains(e.target)) { sidebar.classList.remove('open'); main.classList.remove('sidebar-open'); } });
        
        // Theme toggle
        const themeBtn = document.getElementById('themeBtn');
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.body.setAttribute('data-theme', savedTheme);
        if (themeBtn) themeBtn.innerHTML = savedTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        if (themeBtn) themeBtn.addEventListener('click', () => { const newTheme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'; document.body.setAttribute('data-theme', newTheme); localStorage.setItem('theme', newTheme); themeBtn.innerHTML = newTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>'; });
        
        // Telegram banner
        const banner = document.getElementById('telegramBanner');
        const closeBannerBtn = document.getElementById('closeBanner');
        if (localStorage.getItem('tgBannerDismissed') === 'true' && banner) banner.style.display = 'none';
        if (closeBannerBtn) closeBannerBtn.addEventListener('click', (e) => { e.stopPropagation(); if (banner) banner.style.display = 'none'; localStorage.setItem('tgBannerDismissed', 'true'); });
        if (banner) banner.addEventListener('click', (e) => { if (e.target !== closeBannerBtn && !closeBannerBtn.contains(e.target)) window.open('https://t.me/approvedchecker', '_blank'); });
        
        function copyToClipboard(text) { navigator.clipboard.writeText(text); Swal.fire({ toast: true, icon: 'success', title: 'Copied!', showConfirmButton: false, timer: 1500 }); }
        
        // User dropdown
        const userMenu = document.getElementById('userMenu');
        const userDropdown = document.getElementById('userDropdown');
        if (userMenu) {
            userMenu.addEventListener('click', (e) => {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
            });
        }
        document.addEventListener('click', () => {
            if (userDropdown) userDropdown.classList.remove('show');
        });
    </script>
</body>
</html>
