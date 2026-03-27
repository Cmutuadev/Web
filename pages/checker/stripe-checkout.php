<?php
require_once __DIR__ . "/../../includes/config.php";

if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$pageTitle = "Stripe Checkout";
$user = $_SESSION['user'];
$credits = getUserCredits();
$isAdmin = isAdmin();
$userPlan = $user['plan'] ?? 'basic';

// Check gate access
$gates = loadGates();
$stripeCheckoutGate = $gates['stripe_checkout'] ?? null;
$requiredPlan = $stripeCheckoutGate['required_plan'] ?? 'premium';
$planPriority = ['basic' => 1, 'premium' => 2, 'gold' => 3, 'platinum' => 4, 'lifetime' => 5];
$canAccess = $isAdmin || ($planPriority[$userPlan] >= $planPriority[$requiredPlan]);

// This file only handles GET requests - no POST processing here
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
        
        .gate-selector { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .gate-card { flex: 1; min-width: 200px; background: var(--card); border: 2px solid var(--border); border-radius: 0.8rem; padding: 1rem; cursor: pointer; transition: all 0.2s; position: relative; overflow: hidden; }
        .gate-card:hover { transform: translateY(-2px); border-color: var(--primary); }
        .gate-card.selected { border-color: var(--primary); background: linear-gradient(135deg, rgba(139,92,246,0.1), rgba(6,182,212,0.05)); }
        .gate-card.selected::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--primary); }
        .gate-icon { font-size: 2rem; margin-bottom: 0.5rem; }
        .gate-name { font-weight: 700; font-size: 1rem; margin-bottom: 0.25rem; }
        .gate-desc { font-size: 0.65rem; color: var(--text-muted); }
        .gate-badge { display: inline-block; background: rgba(139,92,246,0.2); color: var(--primary); font-size: 0.55rem; padding: 0.15rem 0.4rem; border-radius: 0.3rem; margin-top: 0.3rem; }
        
        .checkout-preview { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 1rem; margin-bottom: 1.5rem; display: none; }
        .preview-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border); }
        .preview-icon { width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary), #06b6d4); border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; }
        .preview-icon i { font-size: 1.2rem; color: white; }
        .preview-title { flex: 1; }
        .preview-title h3 { font-size: 0.9rem; margin-bottom: 0.2rem; }
        .preview-title p { font-size: 0.65rem; color: var(--text-muted); }
        .preview-status { padding: 0.2rem 0.5rem; border-radius: 0.3rem; font-size: 0.6rem; font-weight: 600; }
        .preview-status.active { background: rgba(16,185,129,0.2); color: var(--success); }
        .preview-status.inactive { background: rgba(239,68,68,0.2); color: var(--danger); }
        .preview-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.8rem; margin-bottom: 1rem; }
        .preview-detail { background: var(--bg); border-radius: 0.5rem; padding: 0.5rem; }
        .detail-label { font-size: 0.55rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.2rem; }
        .detail-value { font-size: 0.7rem; font-weight: 500; word-break: break-all; font-family: monospace; }
        .keys-row { display: flex; gap: 0.5rem; margin-top: 0.5rem; flex-wrap: wrap; }
        .key-box { flex: 1; background: var(--bg); border-radius: 0.5rem; padding: 0.5rem; }
        .key-label { font-size: 0.55rem; color: var(--text-muted); margin-bottom: 0.2rem; }
        .key-value { font-size: 0.65rem; font-family: monospace; word-break: break-all; }
        .copy-key { background: none; border: none; color: var(--text-muted); cursor: pointer; margin-left: 0.3rem; font-size: 0.55rem; }
        .copy-key:hover { color: var(--primary); }
        
        .checker-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 1rem; margin-bottom: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.7rem; font-weight: 500; margin-bottom: 0.4rem; color: var(--text-muted); }
        .form-control { width: 100%; padding: 0.6rem; background: var(--bg); border: 1px solid var(--border); border-radius: 0.5rem; color: var(--text); font-size: 0.75rem; font-family: monospace; }
        textarea.form-control { min-height: 120px; resize: vertical; }
        .action-buttons { display: flex; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap; }
        .btn { padding: 0.5rem 1rem; border-radius: 0.5rem; font-weight: 500; font-size: 0.75rem; cursor: pointer; border: none; transition: all 0.2s; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), #7c3aed); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-secondary { background: var(--bg); border: 1px solid var(--border); color: var(--text); }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.6rem; }
        
        .results-section { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 1rem; margin-top: 1rem; display: none; }
        .results-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem; }
        .filter-buttons { display: flex; gap: 0.3rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .filter-btn { padding: 0.2rem 0.5rem; border-radius: 0.3rem; font-size: 0.55rem; cursor: pointer; background: var(--bg); border: 1px solid var(--border); color: var(--text-muted); }
        .filter-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        
        .result-item { display: flex; flex-direction: column; gap: 0.5rem; padding: 0.75rem; border-bottom: 1px solid var(--border); margin-bottom: 0.5rem; background: var(--bg); border-radius: 0.5rem; }
        .result-item.approved { border-left: 3px solid var(--success); }
        .result-item.declined { border-left: 3px solid var(--danger); }
        .result-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.3rem; }
        .result-card { font-family: monospace; font-size: 0.7rem; font-weight: 500; }
        .result-status { font-size: 0.65rem; padding: 0.15rem 0.4rem; border-radius: 0.3rem; }
        .result-status.approved { background: rgba(16,185,129,0.2); color: var(--success); }
        .result-status.declined { background: rgba(239,68,68,0.2); color: var(--danger); }
        .result-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.3rem; margin-top: 0.3rem; font-size: 0.6rem; }
        .result-detail { color: var(--text-muted); }
        .result-detail strong { color: var(--text); }
        .bin-info { margin-top: 0.3rem; font-size: 0.55rem; color: var(--text-muted); }
        .progress-bar { margin-top: 1rem; height: 3px; background: var(--border); border-radius: 2px; overflow: hidden; display: none; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary), #06b6d4); width: 0%; transition: width 0.3s; }
        .status-text { font-size: 0.6rem; color: var(--text-muted); margin-top: 0.5rem; text-align: center; display: none; }
        .gate-access-warning { background: rgba(245,158,11,0.1); border: 1px solid var(--warning); border-radius: 0.5rem; padding: 0.5rem; margin-bottom: 1rem; font-size: 0.7rem; color: var(--warning); text-align: center; }
        
        .proxy-section { margin-bottom: 1rem; }
        .proxy-options { display: flex; gap: 1rem; margin-bottom: 0.5rem; flex-wrap: wrap; }
        .proxy-option { display: flex; align-items: center; gap: 0.3rem; cursor: pointer; font-size: 0.7rem; }
        .proxy-input { margin-top: 0.5rem; display: none; }
        .proxy-input.show { display: block; }
        .proxy-input input { width: 100%; padding: 0.4rem; background: var(--bg); border: 1px solid var(--border); border-radius: 0.4rem; color: var(--text); font-size: 0.7rem; font-family: monospace; }
        
        .credit-info { font-size: 0.7rem; color: var(--text-muted); text-align: right; margin-bottom: 1rem; }
        .plan-badge { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 12px; font-size: 0.6rem; margin-left: 0.5rem; }
        .plan-basic { background: rgba(107,114,128,0.2); color: #9ca3af; }
        .plan-premium { background: rgba(245,158,11,0.2); color: #f59e0b; }
        
        .checkout-url-input { display: flex; gap: 0.5rem; }
        .checkout-url-input input { flex: 1; }
        .checkout-url-input button { width: auto; padding: 0.6rem 1rem; }
        
        .api-url-display { font-size: 0.6rem; color: var(--text-muted); margin-top: 0.5rem; text-align: center; display: none; }
    </style>
</head>
<body data-theme="dark">
    <?php include __DIR__ . "/../../includes/header.php"; ?>
    <?php include __DIR__ . "/../../includes/sidebar.php"; ?>

    <main class="main" id="main">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title"><?php echo $pageTitle; ?></h1>
                <p class="page-subtitle">Advanced Stripe checkout checker - Auto place orders</p>
            </div>
            
            <div class="credit-info">
                <i class="fas fa-coins"></i> Credits: <span id="creditAmount"><?php echo $isAdmin ? '∞' : number_format($credits); ?></span>
                <span class="plan-badge plan-<?php echo $userPlan; ?>"><?php echo ucfirst($userPlan); ?> Plan</span>
                <span style="margin-left: 1rem;"><i class="fas fa-tachometer-alt"></i> 1 credit/check</span>
            </div>
            
            <?php if (!$canAccess): ?>
            <div class="gate-access-warning">
                <i class="fas fa-exclamation-triangle"></i> This gate requires <?php echo ucfirst($requiredPlan); ?> plan. <a href="/topup.php" style="color: var(--primary);">Upgrade Now</a>
            </div>
            <?php endif; ?>
            
            <!-- Gate Selector Cards -->
            <div class="gate-selector">
                <div class="gate-card selected" data-gate="rylax" onclick="selectGate('rylax')">
                    <div class="gate-icon"><i class="fas fa-bolt" style="color: #8b5cf6;"></i></div>
                    <div class="gate-name">Rylax API</div>
                    <div class="gate-desc">Full checkout extraction with Stripe API</div>
                    <div class="gate-badge"><i class="fas fa-check-circle"></i> Advanced Mode</div>
                </div>
                <div class="gate-card" data-gate="stormx" onclick="selectGate('stormx')">
                    <div class="gate-icon"><i class="fas fa-cloud-upload-alt" style="color: #10b981;"></i></div>
                    <div class="gate-name">StormX Hitter</div>
                    <div class="gate-desc">Fast mass checkout hitter</div>
                    <div class="gate-badge"><i class="fas fa-rocket"></i> Speed Mode</div>
                </div>
            </div>
            
            <!-- Checkout Preview Card -->
            <div class="checkout-preview" id="checkoutPreview">
                <div class="preview-header">
                    <div class="preview-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="preview-title">
                        <h3>Checkout Preview</h3>
                        <p>Detected checkout information</p>
                    </div>
                    <div class="preview-status" id="previewStatus">Checking...</div>
                </div>
                <div class="preview-details" id="previewDetails">
                    <div class="preview-detail">
                        <div class="detail-label"><i class="fas fa-store"></i> Merchant</div>
                        <div class="detail-value" id="previewSite">--</div>
                    </div>
                    <div class="preview-detail">
                        <div class="detail-label"><i class="fas fa-dollar-sign"></i> Amount</div>
                        <div class="detail-value" id="previewAmount">--</div>
                    </div>
                    <div class="preview-detail">
                        <div class="detail-label"><i class="fas fa-tag"></i> Product</div>
                        <div class="detail-value" id="previewProduct">--</div>
                    </div>
                    <div class="preview-detail">
                        <div class="detail-label"><i class="fas fa-envelope"></i> Email</div>
                        <div class="detail-value" id="previewEmail">--</div>
                    </div>
                </div>
                <div class="keys-row" id="keysRow">
                    <div class="key-box">
                        <div class="key-label"><i class="fas fa-key"></i> PK Live <button class="copy-key" onclick="copyText('pkLiveValue')"><i class="fas fa-copy"></i></button></div>
                        <div class="key-value" id="pkLiveValue">--</div>
                    </div>
                    <div class="key-box">
                        <div class="key-label"><i class="fas fa-lock"></i> CS Live <button class="copy-key" onclick="copyText('csLiveValue')"><i class="fas fa-copy"></i></button></div>
                        <div class="key-value" id="csLiveValue">--</div>
                    </div>
                </div>
            </div>
            
            <div class="checker-card">
                <div class="form-group">
                    <label><i class="fas fa-link"></i> Stripe Checkout URL</label>
                    <div class="checkout-url-input">
                        <input type="text" id="checkoutUrl" class="form-control" placeholder="https://buy.stripe.com/..." value="https://buy.stripe.com/">
                        <button class="btn btn-primary" id="checkUrlBtn" onclick="checkCheckoutUrl()" style="width: auto;"><i class="fas fa-search"></i> Detect</button>
                    </div>
                </div>
                
                <div class="proxy-section">
                    <div class="proxy-options">
                        <label class="proxy-option"><input type="radio" name="proxy_mode" value="none" checked onchange="toggleProxyInput()"> <i class="fas fa-globe"></i> No Proxy</label>
                        <label class="proxy-option"><input type="radio" name="proxy_mode" value="custom" onchange="toggleProxyInput()"> <i class="fas fa-pen"></i> Custom Proxy</label>
                    </div>
                    <div class="proxy-input" id="customProxyInput">
                        <input type="text" id="customProxy" placeholder="http://user:pass@ip:port or socks5://ip:port">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-credit-card"></i> Cards (one per line)</label>
                    <textarea id="cardsInput" class="form-control" placeholder="card|month|year|cvv&#10;4532123456789012|12|2025|123&#10;5555555555554444|01|2026|789"></textarea>
                    <div class="text-sm text-muted mt-1">
                        <i class="fas fa-info-circle"></i> Format: card_number|month|year|cvv
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-primary" id="startBtn" <?php echo !$canAccess ? 'disabled' : ''; ?>><i class="fas fa-play"></i> Start Checkout</button>
                    <button class="btn btn-danger" id="stopBtn" disabled><i class="fas fa-stop"></i> Stop</button>
                    <button class="btn btn-secondary" id="clearBtn"><i class="fas fa-trash"></i> Clear</button>
                </div>
                
                <div class="progress-bar" id="progressBar"><div class="progress-fill" id="progressFill"></div></div>
                <div class="status-text" id="statusText">Ready...</div>
            </div>
            
            <div class="results-section" id="resultsSection">
                <div class="results-header">
                    <div class="results-title"><i class="fas fa-list-check"></i> Results (<span id="resultsCount">0</span>)</div>
                    <div>
                        <button class="btn btn-secondary btn-sm" onclick="downloadResults('all')"><i class="fas fa-download"></i> Download All</button>
                        <button class="btn btn-secondary btn-sm" onclick="downloadResults('approved')"><i class="fas fa-check-circle"></i> Download Approved</button>
                    </div>
                </div>
                
                <div class="filter-buttons">
                    <button class="filter-btn active" data-filter="all">All</button>
                    <button class="filter-btn" data-filter="approved">Approved ✅</button>
                    <button class="filter-btn" data-filter="declined">Declined ❌</button>
                </div>
                
                <div id="resultsList"></div>
            </div>
        </div>
    </main>
    
    <script>
        const API_BASE = '/api/stripe_checkout_api.php';
        let isProcessing = false;
        let shouldStop = false;
        let currentResults = [];
        let currentCredits = <?php echo $credits; ?>;
        let selectedGate = 'rylax';
        
        function selectGate(gate) {
            selectedGate = gate;
            document.querySelectorAll('.gate-card').forEach(card => {
                card.classList.remove('selected');
            });
            document.querySelector(`.gate-card[data-gate="${gate}"]`).classList.add('selected');
            
            const url = document.getElementById('checkoutUrl').value.trim();
            if (url && url !== 'https://buy.stripe.com/') {
                checkCheckoutUrl();
            }
        }
        
        function toggleProxyInput() {
            const proxyMode = document.querySelector('input[name="proxy_mode"]:checked').value;
            document.getElementById('customProxyInput').classList.toggle('show', proxyMode === 'custom');
        }
        
        async function checkCheckoutUrl() {
            const checkoutUrl = document.getElementById('checkoutUrl').value.trim();
            if (!checkoutUrl || checkoutUrl === 'https://buy.stripe.com/') {
                Swal.fire({ title: 'Error', text: 'Enter a valid Stripe checkout URL', icon: 'error' });
                return;
            }
            
            const preview = document.getElementById('checkoutPreview');
            preview.style.display = 'block';
            document.getElementById('previewStatus').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
            document.getElementById('previewStatus').className = 'preview-status';
            document.getElementById('previewSite').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Detecting...';
            document.getElementById('previewAmount').innerHTML = '--';
            document.getElementById('previewProduct').innerHTML = '--';
            document.getElementById('previewEmail').innerHTML = '--';
            document.getElementById('pkLiveValue').innerHTML = '--';
            document.getElementById('csLiveValue').innerHTML = '--';
            
            try {
                const formData = new FormData();
                formData.append('action', 'detect_checkout');
                formData.append('checkout_url', checkoutUrl);
                formData.append('gate', selectedGate);
                
                const proxyMode = document.querySelector('input[name="proxy_mode"]:checked').value;
                const customProxy = document.getElementById('customProxy').value;
                if (proxyMode === 'custom' && customProxy) {
                    formData.append('proxy', customProxy);
                }
                
                const response = await fetch(API_BASE, { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('previewStatus').innerHTML = '<i class="fas fa-check-circle"></i> LIVE';
                    document.getElementById('previewStatus').className = 'preview-status active';
                    document.getElementById('previewSite').innerHTML = `<i class="fas fa-store"></i> ${escapeHtml(data.site || 'Unknown')}`;
                    document.getElementById('previewAmount').innerHTML = `<i class="fas fa-dollar-sign"></i> ${escapeHtml(data.amount || 'N/A')}`;
                    document.getElementById('previewProduct').innerHTML = `<i class="fas fa-tag"></i> ${escapeHtml(data.product || 'N/A')}`;
                    document.getElementById('previewEmail').innerHTML = `<i class="fas fa-envelope"></i> ${escapeHtml(data.email || 'N/A')}`;
                    document.getElementById('pkLiveValue').innerHTML = escapeHtml(data.pk_live || 'N/A');
                    document.getElementById('csLiveValue').innerHTML = escapeHtml(data.cs_live || 'N/A');
                    
                    Swal.fire({ title: 'Checkout Detected!', text: `Merchant: ${data.site}\nAmount: ${data.amount}`, icon: 'success', toast: true, timer: 3000 });
                } else {
                    document.getElementById('previewStatus').innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + (data.error || 'Invalid');
                    document.getElementById('previewStatus').className = 'preview-status inactive';
                    Swal.fire({ title: 'Error', text: data.error || 'Checkout not detected', icon: 'error' });
                }
            } catch (err) {
                document.getElementById('previewStatus').innerHTML = '<i class="fas fa-exclamation-triangle"></i> Detection Failed';
                document.getElementById('previewStatus').className = 'preview-status inactive';
                Swal.fire({ title: 'Error', text: err.message, icon: 'error' });
            }
        }
        
        async function processCards() {
            if (isProcessing) {
                Swal.fire({ title: 'Processing', text: 'Please wait', icon: 'warning', toast: true });
                return;
            }
            
            const checkoutUrl = document.getElementById('checkoutUrl').value.trim();
            const cardsText = document.getElementById('cardsInput').value.trim();
            const proxyMode = document.querySelector('input[name="proxy_mode"]:checked').value;
            const customProxy = document.getElementById('customProxy').value;
            
            let proxy = '';
            if (proxyMode === 'custom' && customProxy) proxy = customProxy;
            
            if (!checkoutUrl || checkoutUrl === 'https://buy.stripe.com/') {
                Swal.fire({ title: 'Error', text: 'Enter a Stripe checkout URL', icon: 'error' });
                return;
            }
            
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
            document.getElementById('progressBar').style.display = 'block';
            document.getElementById('statusText').style.display = 'block';
            document.getElementById('startBtn').disabled = true;
            document.getElementById('stopBtn').disabled = false;
            document.getElementById('startBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            for (let i = 0; i < cards.length && !shouldStop; i++) {
                const card = cards[i];
                const percent = ((i + 1) / cards.length) * 100;
                document.getElementById('progressFill').style.width = percent + '%';
                document.getElementById('statusText').innerHTML = `Processing card ${i + 1} of ${cards.length} (Gate: ${selectedGate === 'rylax' ? 'Rylax' : 'StormX'})...`;
                
                const pendingHtml = `<div class="result-item pending" data-card="${escapeHtml(card)}">
                    <div class="result-header">
                        <div class="result-card"><code>${escapeHtml(card)}</code></div>
                        <div><i class="fas fa-spinner fa-spin"></i> Checking...</div>
                    </div>
                </div>`;
                document.getElementById('resultsList').insertAdjacentHTML('afterbegin', pendingHtml);
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'process_checkout');
                    formData.append('checkout_url', checkoutUrl);
                    formData.append('cc', card);
                    formData.append('gate', selectedGate);
                    if (proxy) formData.append('proxy', proxy);
                    
                    const response = await fetch(API_BASE, { method: 'POST', body: formData });
                    const data = await response.json();
                    
                    if (data.success) {
                        const bin = card.substring(0, 6);
                        const statusClass = data.status;
                        const statusIcon = data.status === 'approved' ? 'fa-check-circle' : 'fa-times-circle';
                        
                        let binInfo = '';
                        try {
                            const binRes = await fetch(`/api/bin-lookup.php?bin=${bin}`);
                            const binData = await binRes.json();
                            if (binData) {
                                binInfo = `<div class="bin-info">
                                    <i class="fas fa-search"></i> <strong>BIN ${bin}:</strong> 
                                    ${binData.bank || 'Unknown'} | ${binData.brand || 'Unknown'} | ${binData.country || 'XX'} ${binData.flag || ''}
                                    ${binData.type ? ` | ${binData.type}` : ''} ${binData.level ? ` | ${binData.level}` : ''}
                                </div>`;
                            }
                        } catch(e) {}
                        
                        const resultHtml = `<div class="result-item ${statusClass}" data-status="${data.status}">
                            <div class="result-header">
                                <div class="result-card"><code>${escapeHtml(card)}</code></div>
                                <div class="result-status ${statusClass}"><i class="fas ${statusIcon}"></i> ${data.status.toUpperCase()}</div>
                            </div>
                            <div class="result-details">
                                <div class="result-detail"><strong>Message:</strong> ${escapeHtml(data.message)}</div>
                                <div class="result-detail"><strong>Amount:</strong> ${escapeHtml(data.amount || 'N/A')}</div>
                                <div class="result-detail"><strong>Site:</strong> <code>${escapeHtml(data.site || 'N/A')}</code></div>
                                <div class="result-detail"><strong>Product:</strong> ${escapeHtml(data.product || 'N/A')}</div>
                                <div class="result-detail"><strong>Checkout Type:</strong> ${escapeHtml(data.checkout_type || 'N/A')}</div>
                                <div class="result-detail"><strong>Email:</strong> ${escapeHtml(data.email || 'N/A')}</div>
                                <div class="result-detail"><strong>Receipt:</strong> ${data.receipt && data.receipt !== 'N/A' ? '<a href="' + data.receipt + '" target="_blank">🔗 Link</a>' : 'N/A'}</div>
                            </div>
                            ${binInfo}
                        </div>`;
                        
                        const pendingElement = document.querySelector(`.result-item.pending[data-card="${escapeHtml(card).replace(/"/g, '&quot;')}"]`);
                        if (pendingElement) pendingElement.remove();
                        document.getElementById('resultsList').insertAdjacentHTML('afterbegin', resultHtml);
                        
                        currentResults.unshift({
                            card: card,
                            status: data.status,
                            message: data.message,
                            amount: data.amount,
                            site: data.site,
                            product: data.product,
                            receipt: data.receipt,
                            email: data.email,
                            checkout_type: data.checkout_type
                        });
                        document.getElementById('resultsCount').textContent = currentResults.length;
                        
                        if (!<?php echo $isAdmin ? 'true' : 'false'; ?> && data.status === 'approved') {
                            currentCredits--;
                            document.getElementById('creditAmount').textContent = currentCredits;
                        }
                    } else {
                        throw new Error(data.error || 'Processing failed');
                    }
                    
                } catch (err) {
                    const pendingElement = document.querySelector(`.result-item.pending[data-card="${escapeHtml(card).replace(/"/g, '&quot;')}"]`);
                    if (pendingElement) pendingElement.remove();
                    
                    const errorHtml = `<div class="result-item declined" data-status="declined">
                        <div class="result-header">
                            <div class="result-card"><code>${escapeHtml(card)}</code></div>
                            <div class="result-status declined"><i class="fas fa-times-circle"></i> ERROR</div>
                        </div>
                        <div class="result-details">
                            <div class="result-detail"><strong>Error:</strong> ${escapeHtml(err.message)}</div>
                        </div>
                    </div>`;
                    document.getElementById('resultsList').insertAdjacentHTML('afterbegin', errorHtml);
                    currentResults.unshift({card: card, status: 'declined', message: err.message});
                    document.getElementById('resultsCount').textContent = currentResults.length;
                }
                
                await new Promise(r => setTimeout(r, 300));
            }
            
            document.getElementById('progressBar').style.display = 'none';
            document.getElementById('statusText').style.display = 'none';
            document.getElementById('startBtn').disabled = false;
            document.getElementById('stopBtn').disabled = true;
            document.getElementById('startBtn').innerHTML = '<i class="fas fa-play"></i> Start Checkout';
            isProcessing = false;
            
            const approved = currentResults.filter(r => r.status === 'approved').length;
            Swal.fire({ title: 'Complete!', text: `Processed ${currentResults.length} cards. Approved: ${approved}`, icon: 'success', toast: true, timer: 3000 });
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function copyText(elementId) {
            const text = document.getElementById(elementId).innerText;
            if (text && text !== '--') {
                navigator.clipboard.writeText(text);
                Swal.fire({ toast: true, icon: 'success', title: 'Copied!', showConfirmButton: false, timer: 1500 });
            }
        }
        
        function downloadResults(type) {
            let text = '';
            currentResults.forEach(r => {
                if (type === 'all' || r.status === type) {
                    text += `Card: ${r.card}\n`;
                    text += `Status: ${r.status.toUpperCase()}\n`;
                    text += `Message: ${r.message}\n`;
                    text += `Amount: ${r.amount || 'N/A'}\n`;
                    text += `Site: ${r.site || 'N/A'}\n`;
                    text += `Product: ${r.product || 'N/A'}\n`;
                    text += `Receipt: ${r.receipt || 'N/A'}\n`;
                    text += `Email: ${r.email || 'N/A'}\n`;
                    text += `Checkout Type: ${r.checkout_type || 'N/A'}\n`;
                    text += `---\n\n`;
                }
            });
            
            if (text) {
                const blob = new Blob([text], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `stripe-checkout-${type}-${new Date().toISOString().slice(0,19)}.txt`;
                a.click();
                URL.revokeObjectURL(url);
            }
        }
        
        document.getElementById('startBtn').addEventListener('click', processCards);
        document.getElementById('stopBtn').addEventListener('click', () => { shouldStop = true; });
        document.getElementById('clearBtn').addEventListener('click', () => {
            document.getElementById('cardsInput').value = '';
            Swal.fire({ toast: true, icon: 'success', title: 'Cleared!', timer: 1500 });
        });
        
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const filter = this.dataset.filter;
                document.querySelectorAll('.result-item').forEach(item => {
                    if (filter === 'all') {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = item.dataset.status === filter ? 'flex' : 'none';
                    }
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
        toggleProxyInput();
        
        let urlInput = document.getElementById('checkoutUrl');
        urlInput.addEventListener('change', checkCheckoutUrl);
        urlInput.addEventListener('paste', function(e) {
            setTimeout(checkCheckoutUrl, 100);
        });
    </script>
</body>
</html>
