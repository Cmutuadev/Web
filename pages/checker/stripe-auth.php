<?php
require_once __DIR__ . "/../../includes/config.php";

if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$pageTitle = "Stripe Auth Checker";
$user = $_SESSION['user'];
$credits = getUserCredits();
$isAdmin = isAdmin();
$userPlan = $user['plan'] ?? 'basic';

// Check gate access based on user plan
$gates = loadGates();
$stripeGate = $gates['stripe_auth'] ?? null;
$requiredPlan = $stripeGate['required_plan'] ?? 'basic';
$planPriority = ['basic' => 1, 'premium' => 2, 'gold' => 3, 'platinum' => 4, 'lifetime' => 5];
$canAccess = $isAdmin || ($planPriority[$userPlan] >= $planPriority[$requiredPlan]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?php echo $pageTitle; ?> | APPROVED CHECKER</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --bg: #0a0a0f; --card: #111114; --border: #1e1e24; --text: #ffffff; --text-muted: #6b6b76; --primary: #8b5cf6; --success: #10b981; --danger: #ef4444; --warning: #f59e0b; --info: #3b82f6; }
        [data-theme="light"] { --bg: #f8fafc; --card: #ffffff; --border: #e2e8f0; --text: #0f172a; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        
        .navbar { position: fixed; top: 0; left: 0; right: 0; height: 55px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 1rem; z-index: 100; }
        .menu-btn { background: none; border: none; color: var(--text); font-size: 1rem; cursor: pointer; display: none; }
        .logo { display: flex; align-items: center; gap: 0.5rem; }
        .logo-icon { width: 30px; height: 30px; background: linear-gradient(135deg, var(--primary), #06b6d4); border-radius: 8px; display: flex; align-items: center; justify-content: center; }
        .logo-icon i { color: white; font-size: 0.9rem; }
        .logo-text span:first-child { font-weight: 700; font-size: 0.85rem; background: linear-gradient(135deg, var(--primary), #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .logo-text span:last-child { font-size: 0.6rem; color: var(--text-muted); display: block; }
        .user-menu { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.2rem 0.6rem; border-radius: 2rem; background: var(--bg); border: 1px solid var(--border); }
        .user-avatar { width: 28px; height: 28px; background: linear-gradient(135deg, var(--primary), #7c3aed); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.7rem; color: white; }
        .theme-btn { background: none; border: 1px solid var(--border); border-radius: 0.4rem; padding: 0.3rem 0.5rem; cursor: pointer; color: var(--text-muted); }
        
        .sidebar { position: fixed; left: 0; top: 55px; bottom: 0; width: 260px; background: var(--card); border-right: 1px solid var(--border); transform: translateX(-100%); transition: transform 0.2s; z-index: 99; overflow-y: auto; }
        .sidebar.open { transform: translateX(0); }
        .sidebar-content { padding: 1rem; }
        .sidebar-user { display: flex; align-items: center; gap: 0.7rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); margin-bottom: 1rem; }
        .sidebar-avatar { width: 45px; height: 45px; background: linear-gradient(135deg, var(--primary), #7c3aed); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; color: white; }
        .nav-item { display: flex; align-items: center; gap: 0.7rem; padding: 0.5rem 0.7rem; border-radius: 0.5rem; color: var(--text-muted); text-decoration: none; margin-bottom: 0.2rem; }
        .nav-item:hover { background: rgba(139,92,246,0.1); color: var(--primary); }
        .nav-divider { font-size: 0.6rem; color: var(--text-muted); padding: 0.6rem 0.7rem 0.3rem; text-transform: uppercase; }
        .logout-item { margin-top: 0.5rem; border-top: 1px solid var(--border); padding-top: 0.7rem; color: var(--danger); }
        
        .main { margin-left: 0; margin-top: 55px; padding: 1.2rem; transition: margin-left 0.2s; }
        .main.sidebar-open { margin-left: 260px; }
        @media (max-width: 768px) { .menu-btn { display: block; } .main.sidebar-open { margin-left: 0; } }
        .container { max-width: 1400px; margin: 0 auto; }
        
        .page-header { margin-bottom: 1rem; }
        .page-title { font-size: 1.6rem; font-weight: 700; background: linear-gradient(135deg, var(--primary), #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .page-subtitle { color: var(--text-muted); font-size: 0.75rem; margin-top: 0.2rem; }
        .credit-info { font-size: 0.7rem; color: var(--text-muted); text-align: right; margin-bottom: 1rem; }
        .plan-badge { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 12px; font-size: 0.6rem; margin-left: 0.5rem; }
        .plan-basic { background: rgba(107,114,128,0.2); color: #9ca3af; }
        .plan-premium { background: rgba(245,158,11,0.2); color: #f59e0b; }
        .plan-gold { background: rgba(251,191,36,0.2); color: #fbbf24; }
        .plan-platinum { background: rgba(168,85,247,0.2); color: #a855f7; }
        .plan-lifetime { background: rgba(236,72,153,0.2); color: #ec4899; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.5rem; margin-bottom: 1rem; }
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.5rem; padding: 0.5rem; text-align: center; }
        .stat-card.approved { border-left: 3px solid var(--success); }
        .stat-card.declined { border-left: 3px solid var(--danger); }
        .stat-card.threeds { border-left: 3px solid var(--warning); }
        .stat-card.invalid { border-left: 3px solid var(--info); }
        .stat-card.expired { border-left: 3px solid #64748b; }
        .stat-value { font-size: 1.1rem; font-weight: 700; }
        .stat-label { font-size: 0.5rem; text-transform: uppercase; color: var(--text-muted); }
        
        .pool-selector { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .pool-option { flex: 1; padding: 0.5rem; border: 1px solid var(--border); border-radius: 0.5rem; cursor: pointer; text-align: center; }
        .pool-option.selected { border-color: var(--primary); background: rgba(139,92,246,0.1); }
        .pool-name { font-weight: 600; font-size: 0.75rem; }
        .pool-desc { font-size: 0.55rem; color: var(--text-muted); }
        
        .site-selector { margin-bottom: 1rem; }
        .site-options { display: flex; gap: 1rem; margin-bottom: 0.5rem; flex-wrap: wrap; }
        .site-option { display: flex; align-items: center; gap: 0.3rem; cursor: pointer; font-size: 0.7rem; }
        .site-input { margin-top: 0.5rem; display: none; }
        .site-input.show { display: block; }
        .site-input input { width: 100%; padding: 0.4rem; background: var(--bg); border: 1px solid var(--border); border-radius: 0.4rem; color: var(--text); font-size: 0.7rem; }
        
        .proxy-section { margin-bottom: 1rem; }
        .proxy-options { display: flex; gap: 1rem; margin-bottom: 0.5rem; flex-wrap: wrap; }
        .proxy-option { display: flex; align-items: center; gap: 0.3rem; cursor: pointer; font-size: 0.7rem; }
        .proxy-input { margin-top: 0.5rem; display: none; }
        .proxy-input.show { display: block; }
        .proxy-input input { width: 100%; padding: 0.4rem; background: var(--bg); border: 1px solid var(--border); border-radius: 0.4rem; color: var(--text); font-size: 0.7rem; font-family: monospace; }
        
        .checker-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 1rem; margin-bottom: 1rem; }
        textarea { width: 100%; min-height: 120px; background: var(--bg); border: 1px solid var(--border); border-radius: 0.5rem; padding: 0.6rem; color: var(--text); font-family: monospace; font-size: 0.75rem; resize: vertical; }
        .action-buttons { display: flex; gap: 0.5rem; margin-top: 0.8rem; flex-wrap: wrap; }
        .btn { padding: 0.4rem 0.8rem; border-radius: 0.4rem; font-weight: 500; font-size: 0.65rem; cursor: pointer; border: none; transition: all 0.2s; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), #7c3aed); color: white; }
        .btn-secondary { background: var(--bg); border: 1px solid var(--border); color: var(--text); }
        .btn-danger { background: var(--danger); color: white; }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.6rem; }
        
        .results-section { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 1rem; margin-top: 1rem; }
        .results-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem; flex-wrap: wrap; gap: 0.5rem; }
        .download-buttons { display: flex; gap: 0.3rem; flex-wrap: wrap; }
        .filter-buttons { display: flex; gap: 0.3rem; flex-wrap: wrap; margin-bottom: 0.8rem; }
        .filter-btn { padding: 0.2rem 0.5rem; border-radius: 0.3rem; font-size: 0.55rem; cursor: pointer; background: var(--bg); border: 1px solid var(--border); color: var(--text-muted); }
        .filter-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        
        .result-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; border-bottom: 1px solid var(--border); }
        .result-icon { width: 24px; height: 24px; border-radius: 0.3rem; display: flex; align-items: center; justify-content: center; font-size: 0.55rem; }
        .result-icon.approved { background: rgba(16,185,129,0.15); color: var(--success); }
        .result-icon.charged { background: rgba(16,185,129,0.15); color: var(--success); }
        .result-icon.threeds { background: rgba(245,158,11,0.15); color: var(--warning); }
        .result-icon.declined { background: rgba(239,68,68,0.15); color: var(--danger); }
        .result-icon.invalid_cvv { background: rgba(59,130,246,0.15); color: var(--info); }
        .result-icon.expired { background: rgba(100,116,139,0.15); color: #64748b; }
        .result-content { flex: 1; }
        .result-card { font-size: 0.65rem; font-weight: 500; font-family: monospace; }
        .result-status { font-size: 0.55rem; color: var(--text-muted); }
        .bin-info { font-size: 0.5rem; color: var(--text-muted); margin-top: 0.15rem; }
        .progress-bar { margin-top: 0.8rem; height: 3px; background: var(--border); border-radius: 2px; overflow: hidden; display: none; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary), #06b6d4); width: 0%; transition: width 0.3s; }
        .status-text { font-size: 0.6rem; color: var(--text-muted); margin-top: 0.5rem; text-align: center; display: none; }
        .gate-access-warning { background: rgba(245,158,11,0.1); border: 1px solid var(--warning); border-radius: 0.5rem; padding: 0.5rem; margin-bottom: 1rem; font-size: 0.7rem; color: var(--warning); text-align: center; }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } .sidebar { width: 280px; } }
    </style>
</head>
<body data-theme="dark">
    <?php include __DIR__ . "/../../includes/header.php"; ?>
    <?php include __DIR__ . "/../../includes/sidebar.php"; ?>

    <main class="main" id="main">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title"><?php echo $pageTitle; ?></h1>
                <p class="page-subtitle">Automated CC auth checking with CVV validation & Telegram hits</p>
            </div>
            
            <div class="credit-info">
                <i class="fas fa-coins"></i> Credits: <span id="creditAmount"><?php echo $isAdmin ? '∞' : number_format($credits); ?></span>
                <span class="plan-badge plan-<?php echo $userPlan; ?>"><?php echo ucfirst($userPlan); ?> Plan</span>
                <span style="margin-left: 1rem;"><i class="fas fa-tachometer-alt"></i> 1 credit/check</span>
                <span style="margin-left: 1rem;"><i class="fas fa-shield-alt"></i> Gate requires: <strong><?php echo ucfirst($requiredPlan); ?></strong></span>
            </div>
            
            <?php if (!$canAccess): ?>
            <div class="gate-access-warning">
                <i class="fas fa-exclamation-triangle"></i> This gate requires <?php echo ucfirst($requiredPlan); ?> plan. <a href="/topup.php" style="color: var(--primary);">Upgrade Now</a>
            </div>
            <?php endif; ?>
            
            <div class="stats-grid" id="statsGrid" style="display: none;">
                <div class="stat-card approved"><div class="stat-value" id="statApproved">0</div><div class="stat-label">Approved</div></div>
                <div class="stat-card declined"><div class="stat-value" id="statDeclined">0</div><div class="stat-label">Declined</div></div>
                <div class="stat-card threeds"><div class="stat-value" id="stat3DS">0</div><div class="stat-label">3DS</div></div>
                <div class="stat-card invalid"><div class="stat-value" id="statInvalidCVV">0</div><div class="stat-label">Invalid CVV</div></div>
                <div class="stat-card expired"><div class="stat-value" id="statExpired">0</div><div class="stat-label">Expired</div></div>
                <div class="stat-card"><div class="stat-value" id="statTotal">0</div><div class="stat-label">Total</div></div>
            </div>
            
            <div class="checker-card">
                <div class="pool-selector">
                    <div class="pool-option selected" data-pool="stap" onclick="selectPool('stap')"><div class="pool-name">STAP</div><div class="pool-desc">Standard Stripe Auth</div></div>
                    <div class="pool-option" data-pool="strat" onclick="selectPool('strat')"><div class="pool-name">STRAT</div><div class="pool-desc">Advanced Strategy</div></div>
                </div>
                <input type="hidden" id="selectedPool" value="stap">
                
                <div class="site-selector">
                    <div class="site-options">
                        <label class="site-option"><input type="radio" name="site_mode" value="default" checked onchange="toggleSiteInput()"> <i class="fas fa-globe"></i> Default Site</label>
                        <label class="site-option"><input type="radio" name="site_mode" value="custom" onchange="toggleSiteInput()"> <i class="fas fa-pen"></i> Custom Site</label>
                    </div>
                    <div class="site-input" id="customSiteInput">
                        <input type="text" id="customSite" placeholder="https://example-stripe-store.com">
                    </div>
                </div>
                
                <div class="proxy-section">
                    <div class="proxy-options">
                        <label class="proxy-option"><input type="radio" name="proxy_mode" value="none" checked onchange="toggleProxyInput()"> <i class="fas fa-globe"></i> No Proxy</label>
                        <label class="proxy-option"><input type="radio" name="proxy_mode" value="default" onchange="toggleProxyInput()"> <i class="fas fa-server"></i> Default Proxy</label>
                        <label class="proxy-option"><input type="radio" name="proxy_mode" value="custom" onchange="toggleProxyInput()"> <i class="fas fa-pen"></i> Custom Proxy</label>
                    </div>
                    <div class="proxy-input" id="customProxyInput">
                        <input type="text" id="customProxy" placeholder="http://user:pass@ip:port or socks5://ip:port">
                    </div>
                </div>
                
                <textarea id="cardsInput" placeholder="Enter card details (one per line):&#10;card|month|year|cvv&#10;4532123456789012|12|2025|123"></textarea>
                
                <div class="action-buttons">
                    <button class="btn btn-primary" id="startBtn" <?php echo !$canAccess ? 'disabled' : ''; ?>><i class="fas fa-play"></i> Start Check</button>
                    <button class="btn btn-danger" id="stopBtn" disabled><i class="fas fa-stop"></i> Stop</button>
                    <button class="btn btn-secondary" id="clearBtn"><i class="fas fa-trash"></i> Clear</button>
                </div>
                
                <div class="progress-bar" id="progressBar"><div class="progress-fill" id="progressFill"></div></div>
                <div class="status-text" id="statusText">Processing...</div>
            </div>
            
            <div class="results-section" id="resultsSection" style="display: none;">
                <div class="results-header">
                    <div class="results-title"><i class="fas fa-list-check"></i> Results (<span id="resultsCount">0</span>)</div>
                    <div class="download-buttons">
                        <button class="btn btn-secondary btn-sm" onclick="downloadResults('all')"><i class="fas fa-download"></i> All</button>
                        <button class="btn btn-secondary btn-sm" onclick="downloadResults('approved')"><i class="fas fa-check-circle"></i> Approved</button>
                        <button class="btn btn-secondary btn-sm" onclick="downloadResults('declined')"><i class="fas fa-times-circle"></i> Declined</button>
                        <button class="btn btn-secondary btn-sm" onclick="downloadResults('threeds')"><i class="fas fa-lock"></i> 3DS</button>
                        <button class="btn btn-secondary btn-sm" onclick="downloadResults('invalid_cvv')"><i class="fas fa-key"></i> Invalid CVV</button>
                        <button class="btn btn-secondary btn-sm" onclick="downloadResults('expired')"><i class="fas fa-calendar-times"></i> Expired</button>
                    </div>
                </div>
                
                <div class="filter-buttons">
                    <button class="filter-btn active" data-filter="all">All</button>
                    <button class="filter-btn" data-filter="approved">Approved</button>
                    <button class="filter-btn" data-filter="charged">Charged</button>
                    <button class="filter-btn" data-filter="threeds">3DS</button>
                    <button class="filter-btn" data-filter="declined">Declined</button>
                    <button class="filter-btn" data-filter="invalid_cvv">Invalid CVV</button>
                    <button class="filter-btn" data-filter="expired">Expired</button>
                </div>
                
                <div id="resultsList"></div>
            </div>
        </div>
    </main>
    
    <script>
        let isProcessing = false;
        let shouldStop = false;
        let currentResults = [];
        let currentCredits = <?php echo $credits; ?>;
        
        function selectPool(pool) {
            document.querySelectorAll('.pool-option').forEach(opt => opt.classList.remove('selected'));
            document.querySelector(`.pool-option[data-pool="${pool}"]`).classList.add('selected');
            document.getElementById('selectedPool').value = pool;
        }
        
        function toggleSiteInput() {
            const siteMode = document.querySelector('input[name="site_mode"]:checked').value;
            const customInput = document.getElementById('customSiteInput');
            customInput.classList.toggle('show', siteMode === 'custom');
        }
        
        function toggleProxyInput() {
            const proxyMode = document.querySelector('input[name="proxy_mode"]:checked').value;
            const customInput = document.getElementById('customProxyInput');
            customInput.classList.toggle('show', proxyMode === 'custom');
        }
        
        function updateStats() {
            let approved = 0, declined = 0, threeds = 0, invalid = 0, expired = 0;
            currentResults.forEach(r => {
                if (r.status === 'approved' || r.status === 'charged') approved++;
                else if (r.status === 'threeds') threeds++;
                else if (r.status === 'invalid_cvv') invalid++;
                else if (r.status === 'expired') expired++;
                else declined++;
            });
            document.getElementById('statApproved').textContent = approved;
            document.getElementById('statDeclined').textContent = declined;
            document.getElementById('stat3DS').textContent = threeds;
            document.getElementById('statInvalidCVV').textContent = invalid;
            document.getElementById('statExpired').textContent = expired;
            document.getElementById('statTotal').textContent = currentResults.length;
            document.getElementById('resultsCount').textContent = currentResults.length;
            document.getElementById('statsGrid').style.display = 'grid';
        }
        
        function addResult(card, status, message, binInfo) {
            const statusClass = status;
            const icon = status === 'approved' || status === 'charged' ? 'fa-check-circle' : 
                        (status === 'threeds' ? 'fa-lock' : 
                        (status === 'invalid_cvv' ? 'fa-key' : 
                        (status === 'expired' ? 'fa-calendar-times' : 'fa-times-circle')));
            
            const resultHtml = `<div class="result-item" data-status="${status}">
                <div class="result-icon ${status}"><i class="fas ${icon}"></i></div>
                <div class="result-content">
                    <div class="result-card">${escapeHtml(card)}</div>
                    <div class="result-status">${status.toUpperCase().replace('_', ' ')} - ${escapeHtml(message)}</div>
                    ${binInfo ? `<div class="bin-info">🏦 ${escapeHtml(binInfo.bank || 'Unknown')} 💳 ${escapeHtml(binInfo.brand || 'Unknown')} 🌍 ${escapeHtml(binInfo.country || 'XX')}</div>` : ''}
                </div>
                <div class="result-time">${new Date().toLocaleTimeString()}</div>
            </div>`;
            
            document.getElementById('resultsList').insertAdjacentHTML('afterbegin', resultHtml);
            currentResults.unshift({card, status, message, binInfo});
            updateStats();
            document.getElementById('resultsSection').style.display = 'block';
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        async function updateDashboardStats(card, status, message) {
            try {
                await fetch('/api/update-stats.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        card: card, 
                        status: status, 
                        reason: message,
                        gateway: 'stripe_auth'
                    })
                });
            } catch(e) {}
        }
        
        async function processCards() {
            if (isProcessing) {
                Swal.fire({ title: 'Processing', text: 'Please wait', icon: 'warning', toast: true });
                return;
            }
            
            const cardsText = document.getElementById('cardsInput').value.trim();
            if (!cardsText) {
                Swal.fire({ title: 'Error', text: 'Enter cards to check', icon: 'error' });
                return;
            }
            
            const cards = cardsText.split('\n').filter(l => l.trim().length > 5);
            if (cards.length === 0) {
                Swal.fire({ title: 'Error', text: 'No valid cards', icon: 'error' });
                return;
            }
            
            if (!<?php echo $isAdmin ? 'true' : 'false'; ?> && currentCredits < cards.length) {
                Swal.fire({ title: 'Insufficient Credits', text: 'Need ' + cards.length + ' credits', icon: 'error' });
                return;
            }
            
            isProcessing = true;
            shouldStop = false;
            currentResults = [];
            document.getElementById('resultsList').innerHTML = '';
            document.getElementById('resultsSection').style.display = 'block';
            document.getElementById('statsGrid').style.display = 'grid';
            document.getElementById('progressBar').style.display = 'block';
            document.getElementById('statusText').style.display = 'block';
            document.getElementById('startBtn').disabled = true;
            document.getElementById('stopBtn').disabled = false;
            document.getElementById('startBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            const selectedPool = document.getElementById('selectedPool').value;
            const siteMode = document.querySelector('input[name="site_mode"]:checked').value;
            const customSite = document.getElementById('customSite').value;
            const proxyMode = document.querySelector('input[name="proxy_mode"]:checked').value;
            const customProxy = document.getElementById('customProxy').value;
            
            let merchantDomain = 'https://peeteescollection.com';
            if (siteMode === 'custom' && customSite) merchantDomain = customSite;
            
            let proxy = '';
            if (proxyMode === 'default') proxy = 'default';
            if (proxyMode === 'custom' && customProxy) proxy = customProxy;
            
            let processed = 0;
            let creditsUsed = 0;
            
            for (let i = 0; i < cards.length && !shouldStop; i++) {
                const card = cards[i];
                const percent = ((i + 1) / cards.length) * 100;
                document.getElementById('progressFill').style.width = percent + '%';
                document.getElementById('statusText').innerHTML = `Processing card ${i + 1} of ${cards.length}...`;
                
                const pendingHtml = `<div class="result-item pending" data-card="${escapeHtml(card)}"><div class="result-icon"><i class="fas fa-spinner fa-spin"></i></div><div class="result-content"><div class="result-card">${escapeHtml(card)}</div><div class="result-status">Checking...</div></div><div class="result-time">${new Date().toLocaleTimeString()}</div></div>`;
                document.getElementById('resultsList').insertAdjacentHTML('afterbegin', pendingHtml);
                
                try {
                    const formData = new FormData();
                    formData.append('cc', card);
                    formData.append('merchant', merchantDomain);
                    formData.append('pool', selectedPool);
                    if (proxy) formData.append('proxy', proxy);
                    
                    const response = await fetch('/gate/stripe_auth.php', { method: 'POST', body: formData });
                    const output = await response.text();
                    
                    let status = 'declined';
                    let message = output.trim();
                    
                    if (output.indexOf('APPROVED') !== -1) { status = 'approved'; message = '✓ Card approved successfully'; creditsUsed++; }
                    else if (output.indexOf('CHARGED') !== -1) { status = 'charged'; message = '✓ Card charged successfully'; creditsUsed++; }
                    else if (output.indexOf('3DS') !== -1) { status = 'threeds'; message = '⚠ 3D Secure required'; }
                    else if (output.indexOf('INVALID CVV') !== -1) { status = 'invalid_cvv'; message = '✗ Invalid security code (CVV)'; }
                    else if (output.indexOf('EXPIRED') !== -1) { status = 'expired'; message = '✗ Card expired'; }
                    else if (output.indexOf('INCORRECT') !== -1) { status = 'declined'; message = '✗ Incorrect card number'; }
                    else if (output.indexOf('INSUFFICIENT') !== -1) { status = 'declined'; message = '✗ Insufficient funds'; }
                    else if (output.indexOf('FRAUD') !== -1) { status = 'declined'; message = '✗ Fraud suspected'; }
                    else if (output.indexOf('DECLINED') !== -1) { 
                        let clean = output.replace(/^DECLINED:\s*/i, '').trim();
                        if (clean.indexOf('insufficient') !== -1) message = '✗ Insufficient funds';
                        else if (clean.indexOf('fraud') !== -1) message = '✗ Fraud suspected';
                        else if (clean.indexOf('incorrect') !== -1) message = '✗ Incorrect card details';
                        else message = '✗ Card declined';
                    }
                    
                    const bin = card.substring(0, 6);
                    let binInfo = null;
                    try {
                        const binRes = await fetch(`/api/bin-lookup.php?bin=${bin}`);
                        binInfo = await binRes.json();
                    } catch(e) {}
                    
                    const pendingElement = document.querySelector(`.result-item.pending[data-card="${escapeHtml(card).replace(/"/g, '&quot;')}"]`);
                    if (pendingElement) pendingElement.remove();
                    addResult(card, status, message, binInfo);
                    
                    if (!<?php echo $isAdmin ? 'true' : 'false'; ?>) {
                        currentCredits--;
                        document.getElementById('creditAmount').textContent = currentCredits;
                    }
                    
                    processed++;
                    await updateDashboardStats(card, status, message);
                    
                } catch (err) {
                    const pendingElement = document.querySelector(`.result-item.pending[data-card="${escapeHtml(card).replace(/"/g, '&quot;')}"]`);
                    if (pendingElement) pendingElement.remove();
                    addResult(card, 'error', err.message || 'Request failed', null);
                }
                await new Promise(r => setTimeout(r, 200));
            }
            
            document.getElementById('progressBar').style.display = 'none';
            document.getElementById('statusText').style.display = 'none';
            document.getElementById('startBtn').disabled = false;
            document.getElementById('stopBtn').disabled = true;
            document.getElementById('startBtn').innerHTML = '<i class="fas fa-play"></i> Start Check';
            isProcessing = false;
            
            const resultMsg = shouldStop ? 'Stopped manually' : 'Completed';
            Swal.fire({ title: resultMsg, text: `Processed ${processed} cards. Approved: ${document.getElementById('statApproved').textContent}`, icon: 'success', toast: true, timer: 3000 });
        }
        
        document.getElementById('startBtn').addEventListener('click', processCards);
        document.getElementById('stopBtn').addEventListener('click', () => { shouldStop = true; });
        document.getElementById('clearBtn').addEventListener('click', () => {
            document.getElementById('cardsInput').value = '';
            Swal.fire({ toast: true, icon: 'success', title: 'Cleared!', timer: 1500 });
        });
        
        function downloadResults(type) {
            let text = '';
            document.querySelectorAll('.result-item').forEach(item => {
                if (type === 'all' || item.dataset.status === type) {
                    const card = item.querySelector('.result-card')?.innerText || '';
                    const status = item.querySelector('.result-status')?.innerText || '';
                    text += card + ' | ' + status + '\n';
                }
            });
            if (text) {
                const blob = new Blob([text], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `stripe-auth-${type}-${new Date().toISOString().slice(0,19)}.txt`;
                a.click();
                URL.revokeObjectURL(url);
            }
        }
        
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const filter = this.dataset.filter;
                document.querySelectorAll('.result-item').forEach(item => {
                    item.style.display = (filter === 'all' || item.dataset.status === filter) ? 'flex' : 'none';
                });
            });
        });
        
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
            });
        }
        
        const menuBtn = document.getElementById('menuBtn');
        const sidebar = document.getElementById('sidebar');
        const main = document.getElementById('main');
        if (menuBtn) menuBtn.addEventListener('click', () => { sidebar.classList.toggle('open'); main.classList.toggle('sidebar-open'); });
        toggleSiteInput();
        toggleProxyInput();
    </script>
</body>
</html>
