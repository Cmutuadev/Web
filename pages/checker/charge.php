<?php
require_once __DIR__ . "/../../includes/config.php";

if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$pageTitle = "Charge Checker";
$user = $_SESSION['user'];
$credits = getUserCredits();
$isAdmin = isAdmin();
$userPlan = $user['plan'] ?? 'basic';

$gates = loadGates();
$chargeGate = $gates['charge'] ?? null;
$requiredPlan = $chargeGate['required_plan'] ?? 'basic';
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
        :root { --bg: #0a0a0f; --card: #111114; --border: #1e1e24; --text: #ffffff; --text-muted: #6b6b76; --primary: #8b5cf6; --success: #10b981; --danger: #ef4444; --warning: #f59e0b; }
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
        
        .gate-selector-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.8rem;
            margin-bottom: 1.2rem;
        }
        .gate-card-mini {
            background: var(--card);
            border: 1.5px solid var(--border);
            border-radius: 0.6rem;
            padding: 0.6rem 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .gate-card-mini:hover { transform: translateY(-2px); border-color: var(--primary); }
        .gate-card-mini.selected { border-color: var(--primary); background: linear-gradient(135deg, rgba(139,92,246,0.1), rgba(6,182,212,0.05)); }
        .gate-card-mini.selected::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: var(--primary); border-radius: 0.6rem 0.6rem 0 0; }
        .gate-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.3rem; }
        .gate-icon-mini { width: 28px; height: 28px; background: linear-gradient(135deg, var(--primary), #7c3aed); border-radius: 0.4rem; display: flex; align-items: center; justify-content: center; }
        .gate-icon-mini i { font-size: 0.8rem; color: white; }
        .gate-name-mini { font-weight: 700; font-size: 0.8rem; flex: 1; }
        .gate-amount { font-size: 0.7rem; font-weight: 600; color: var(--primary); }
        .gate-desc-mini { font-size: 0.55rem; color: var(--text-muted); margin-top: 0.2rem; display: flex; gap: 0.3rem; flex-wrap: wrap; }
        .gate-badge-mini { background: rgba(139,92,246,0.15); color: var(--primary); font-size: 0.5rem; padding: 0.1rem 0.3rem; border-radius: 0.2rem; }
        
        @media (max-width: 768px) { .gate-selector-row { grid-template-columns: 1fr; } }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem; margin-bottom: 1rem; }
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.5rem; padding: 0.5rem; text-align: center; }
        .stat-value { font-size: 1rem; font-weight: 700; }
        .stat-label { font-size: 0.5rem; text-transform: uppercase; color: var(--text-muted); }
        
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
        
        .results-section { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 1rem; margin-top: 1rem; display: none; }
        .results-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem; }
        .filter-buttons { display: flex; gap: 0.3rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .filter-btn { padding: 0.2rem 0.5rem; border-radius: 0.3rem; font-size: 0.55rem; cursor: pointer; background: var(--bg); border: 1px solid var(--border); color: var(--text-muted); }
        .filter-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        
        .result-item { display: flex; flex-direction: column; gap: 0.5rem; padding: 0.75rem; border-bottom: 1px solid var(--border); margin-bottom: 0.5rem; background: var(--bg); border-radius: 0.5rem; }
        .result-item.approved { border-left: 3px solid var(--success); }
        .result-item.declined { border-left: 3px solid var(--danger); }
        .result-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.3rem; flex-wrap: wrap; gap: 0.3rem; }
        .result-card { font-family: monospace; font-size: 0.7rem; font-weight: 500; }
        .result-status { font-size: 0.65rem; padding: 0.15rem 0.4rem; border-radius: 0.3rem; }
        .result-status.approved { background: rgba(16,185,129,0.2); color: var(--success); }
        .result-status.declined { background: rgba(239,68,68,0.2); color: var(--danger); }
        .result-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.3rem; margin-top: 0.3rem; font-size: 0.55rem; }
        .result-detail { color: var(--text-muted); }
        .result-detail strong { color: var(--text); }
        .bin-info { margin-top: 0.3rem; font-size: 0.55rem; color: var(--text-muted); }
        .progress-bar { margin-top: 1rem; height: 3px; background: var(--border); border-radius: 2px; overflow: hidden; display: none; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary), #06b6d4); width: 0%; transition: width 0.3s; }
        .status-text { font-size: 0.6rem; color: var(--text-muted); margin-top: 0.5rem; text-align: center; display: none; }
        
        .proxy-section { margin-bottom: 1rem; }
        .proxy-options { display: flex; gap: 1rem; margin-bottom: 0.5rem; flex-wrap: wrap; }
        .proxy-option { display: flex; align-items: center; gap: 0.3rem; cursor: pointer; font-size: 0.7rem; }
        .proxy-input { margin-top: 0.5rem; display: none; }
        .proxy-input.show { display: block; }
        .proxy-input input { width: 100%; padding: 0.4rem; background: var(--bg); border: 1px solid var(--border); border-radius: 0.4rem; color: var(--text); font-size: 0.7rem; font-family: monospace; }
        
        .credit-info { font-size: 0.7rem; color: var(--text-muted); text-align: right; margin-bottom: 1rem; }
        .plan-badge { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 12px; font-size: 0.6rem; margin-left: 0.5rem; }
        
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body data-theme="dark">
    <?php include __DIR__ . "/../../includes/header.php"; ?>
    <?php include __DIR__ . "/../../includes/sidebar.php"; ?>

    <main class="main" id="main">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title"><?php echo $pageTitle; ?></h1>
                <p class="page-subtitle">Multi-gateway charge checker - PayPal AVS, Direct, Stripe Donate</p>
            </div>
            
            <div class="credit-info">
                <i class="fas fa-coins"></i> Credits: <span id="creditAmount"><?php echo $isAdmin ? '∞' : number_format($credits); ?></span>
                <span class="plan-badge plan-<?php echo $userPlan; ?>"><?php echo ucfirst($userPlan); ?> Plan</span>
                <span style="margin-left: 1rem;"><i class="fas fa-tachometer-alt"></i> 1 credit/check</span>
            </div>
            
            <div class="gate-selector-row">
                <div class="gate-card-mini selected" data-gate="payflow_avs" onclick="selectGate('payflow_avs')">
                    <div class="gate-header">
                        <div class="gate-icon-mini"><i class="fab fa-paypal"></i></div>
                        <span class="gate-name-mini">PayFlow AVS</span>
                        <span class="gate-amount">$3.99</span>
                    </div>
                    <div class="gate-desc-mini">
                        <span class="gate-badge-mini"><i class="fas fa-shield-alt"></i> AVS Check</span>
                        <span class="gate-badge-mini"><i class="fas fa-map-pin"></i> Address Verify</span>
                    </div>
                </div>
                <div class="gate-card-mini" data-gate="direct_charge" onclick="selectGate('direct_charge')">
                    <div class="gate-header">
                        <div class="gate-icon-mini"><i class="fab fa-paypal"></i></div>
                        <span class="gate-name-mini">Direct Charge</span>
                        <span class="gate-amount">$2.00</span>
                    </div>
                    <div class="gate-desc-mini">
                        <span class="gate-badge-mini"><i class="fas fa-bolt"></i> Fast Check</span>
                        <span class="gate-badge-mini"><i class="fas fa-credit-card"></i> GraphQL</span>
                    </div>
                </div>
                <div class="gate-card-mini" data-gate="stripe_donate" onclick="selectGate('stripe_donate')">
                    <div class="gate-header">
                        <div class="gate-icon-mini"><i class="fab fa-stripe"></i></div>
                        <span class="gate-name-mini">Stripe Donate</span>
                        <span class="gate-amount">$5.00</span>
                    </div>
                    <div class="gate-desc-mini">
                        <span class="gate-badge-mini"><i class="fas fa-heart"></i> Donation</span>
                        <span class="gate-badge-mini"><i class="fas fa-stripe"></i> Stripe API</span>
                    </div>
                </div>
            </div>
            
            <div class="stats-grid" id="statsGrid" style="display: none;">
                <div class="stat-card"><div class="stat-value" id="statApproved">0</div><div class="stat-label">Approved</div></div>
                <div class="stat-card"><div class="stat-value" id="statDeclined">0</div><div class="stat-label">Declined</div></div>
                <div class="stat-card"><div class="stat-value" id="statTotal">0</div><div class="stat-label">Total</div></div>
            </div>
            
            <div class="checker-card">
                <div class="proxy-section">
                    <div class="proxy-options">
                        <label class="proxy-option"><input type="radio" name="proxy_mode" value="none" checked onchange="toggleProxyInput()"> <i class="fas fa-globe"></i> No Proxy</label>
                        <label class="proxy-option"><input type="radio" name="proxy_mode" value="custom" onchange="toggleProxyInput()"> <i class="fas fa-pen"></i> Custom Proxy</label>
                    </div>
                    <div class="proxy-input" id="customProxyInput">
                        <input type="text" id="customProxy" placeholder="http://user:pass@ip:port">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-credit-card"></i> Cards (one per line)</label>
                    <textarea id="cardsInput" class="form-control" placeholder="card|month|year|cvv&#10;4532123456789012|12|2025|123"></textarea>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-primary" id="startBtn"><i class="fas fa-play"></i> Start Check</button>
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
                        <button class="btn btn-secondary btn-sm" onclick="downloadResults('all')"><i class="fas fa-download"></i> All</button>
                        <button class="btn btn-secondary btn-sm" onclick="downloadResults('approved')"><i class="fas fa-check-circle"></i> Approved</button>
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
        let isProcessing = false;
        let shouldStop = false;
        let currentResults = [];
        let currentCredits = <?php echo $credits; ?>;
        let selectedGate = 'payflow_avs';
        
        const GATE_MAP = {
            'payflow_avs': '/gate/payflow_avs.php',
            'direct_charge': '/gate/direct_charge.php',
            'stripe_donate': '/gate/stripe_donate.php'
        };
        
        function selectGate(gate) {
            selectedGate = gate;
            document.querySelectorAll('.gate-card-mini').forEach(card => {
                card.classList.remove('selected');
            });
            document.querySelector(`.gate-card-mini[data-gate="${gate}"]`).classList.add('selected');
        }
        
        function toggleProxyInput() {
            const proxyMode = document.querySelector('input[name="proxy_mode"]:checked').value;
            document.getElementById('customProxyInput').classList.toggle('show', proxyMode === 'custom');
        }
        
        function updateStats() {
            let approved = 0, declined = 0;
            currentResults.forEach(r => {
                if (r.status === 'approved') approved++;
                else if (r.status === 'declined') declined++;
            });
            document.getElementById('statApproved').textContent = approved;
            document.getElementById('statDeclined').textContent = declined;
            document.getElementById('statTotal').textContent = currentResults.length;
            document.getElementById('resultsCount').textContent = currentResults.length;
            document.getElementById('statsGrid').style.display = 'grid';
        }
        
        async function processCards() {
            if (isProcessing) {
                Swal.fire({ title: 'Processing', text: 'Please wait', icon: 'warning', toast: true });
                return;
            }
            
            const cardsText = document.getElementById('cardsInput').value.trim();
            const proxyMode = document.querySelector('input[name="proxy_mode"]:checked').value;
            const customProxy = document.getElementById('customProxy').value;
            
            let proxy = '';
            if (proxyMode === 'custom' && customProxy) proxy = customProxy;
            
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
                document.getElementById('statusText').innerHTML = `Processing card ${i + 1} of ${cards.length}...`;
                
                const pendingHtml = `<div class="result-item pending" data-card="${escapeHtml(card)}">
                    <div class="result-header">
                        <div class="result-card"><code>${escapeHtml(card)}</code></div>
                        <div><i class="fas fa-spinner fa-spin"></i> Checking...</div>
                    </div>
                </div>`;
                document.getElementById('resultsList').insertAdjacentHTML('afterbegin', pendingHtml);
                
                try {
                    const gateUrl = GATE_MAP[selectedGate];
                    const payload = { cc: card };
                    if (proxy) payload.proxy = proxy;
                    
                    const response = await fetch(gateUrl, { 
                        method: 'POST', 
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
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
                                    ${binData.bank || 'Unknown'} | ${binData.brand || 'Unknown'} | ${binData.country || 'XX'}
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
                                <div class="result-detail"><strong>Gateway:</strong> ${escapeHtml(data.gateway)}</div>
                                ${data.avs ? `<div class="result-detail"><strong>AVS:</strong> ${escapeHtml(data.avs)}</div>` : ''}
                                ${data.cvv ? `<div class="result-detail"><strong>CVV Match:</strong> ${escapeHtml(data.cvv)}</div>` : ''}
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
                            gateway: data.gateway,
                            avs: data.avs,
                            cvv: data.cvv
                        });
                        updateStats();
                        
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
                    updateStats();
                }
                
                await new Promise(r => setTimeout(r, 300));
            }
            
            document.getElementById('progressBar').style.display = 'none';
            document.getElementById('statusText').style.display = 'none';
            document.getElementById('startBtn').disabled = false;
            document.getElementById('stopBtn').disabled = true;
            document.getElementById('startBtn').innerHTML = '<i class="fas fa-play"></i> Start Check';
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
        
        function downloadResults(type) {
            let text = '';
            currentResults.forEach(r => {
                if (type === 'all' || r.status === type) {
                    text += `Card: ${r.card}\n`;
                    text += `Status: ${r.status.toUpperCase()}\n`;
                    text += `Message: ${r.message}\n`;
                    text += `Gateway: ${r.gateway}\n`;
                    if (r.avs) text += `AVS: ${r.avs}\n`;
                    if (r.cvv) text += `CVV Match: ${r.cvv}\n`;
                    text += `---\n\n`;
                }
            });
            
            if (text) {
                const blob = new Blob([text], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `charge-checker-${type}-${new Date().toISOString().slice(0,19)}.txt`;
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
    </script>
</body>
</html>
