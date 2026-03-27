<?php
require_once __DIR__ . "/../../includes/config.php";
if (!isLoggedIn()) { 
    header('Location: /login.php'); 
    exit; 
}

$pageTitle = "PayPal Key Based Checker";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | APPROVED CHECKER</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --bg: #0a0a0f; --card: #111114; --border: #1e1e24; --text: #ffffff; --text-muted: #6b6b76; --primary: #8b5cf6; --success: #10b981; --danger: #ef4444; --warning: #f59e0b; --paypal: #0070ba; }
        [data-theme="light"] { --bg: #f8fafc; --card: #ffffff; --border: #e2e8f0; --text: #0f172a; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; font-size: 12px; }
        .navbar { position: fixed; top: 0; left: 0; right: 0; height: 50px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 1rem; z-index: 100; }
        .menu-btn { background: none; border: none; color: var(--text); font-size: 0.9rem; cursor: pointer; display: none; }
        .logo-icon { width: 28px; height: 28px; background: linear-gradient(135deg, var(--primary), #06b6d4); border-radius: 6px; display: flex; align-items: center; justify-content: center; }
        .logo-icon i { color: white; font-size: 0.8rem; }
        .logo-text span:first-child { font-weight: 700; font-size: 0.8rem; background: linear-gradient(135deg, var(--primary), #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .logo-text span:last-child { font-size: 0.55rem; color: var(--text-muted); display: block; }
        .user-menu { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.2rem 0.6rem; border-radius: 2rem; background: var(--bg); border: 1px solid var(--border); }
        .user-avatar { width: 26px; height: 26px; background: linear-gradient(135deg, var(--primary), #7c3aed); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.65rem; color: white; }
        .theme-btn { background: none; border: 1px solid var(--border); border-radius: 0.3rem; padding: 0.2rem 0.4rem; cursor: pointer; color: var(--text-muted); font-size: 0.7rem; }
        .sidebar { position: fixed; left: 0; top: 50px; bottom: 0; width: 240px; background: var(--card); border-right: 1px solid var(--border); transform: translateX(-100%); transition: transform 0.2s; z-index: 99; overflow-y: auto; }
        .sidebar.open { transform: translateX(0); }
        .main { margin-left: 0; margin-top: 50px; padding: 1rem; transition: margin-left 0.2s; }
        .main.sidebar-open { margin-left: 240px; }
        @media (max-width: 768px) { .menu-btn { display: block; } .main.sidebar-open { margin-left: 0; } }
        .container { max-width: 1400px; margin: 0 auto; }
        .page-header { margin-bottom: 1rem; }
        .page-title { font-size: 1.3rem; font-weight: 700; background: linear-gradient(135deg, var(--primary), #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .page-subtitle { color: var(--text-muted); font-size: 0.7rem; margin-top: 0.2rem; }
        
        .checker-layout { display: grid; grid-template-columns: 380px 1fr; gap: 1rem; }
        @media (max-width: 768px) { .checker-layout { grid-template-columns: 1fr; } }
        
        .card-input-section, .settings-section { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 1rem; }
        
        .input-group { margin-bottom: 0.8rem; }
        .input-group label { display: block; font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.2rem; letter-spacing: 0.3px; font-weight: 600; }
        .input-group input, .input-group select, .input-group textarea { width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 0.4rem; padding: 0.4rem 0.6rem; color: var(--text); font-size: 0.75rem; font-family: monospace; }
        .input-group input:focus, .input-group select:focus { outline: none; border-color: var(--primary); }
        
        .btn { padding: 0.4rem 0.8rem; border-radius: 0.4rem; font-weight: 500; font-size: 0.7rem; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), #7c3aed); color: white; }
        .btn-danger { background: linear-gradient(135deg, var(--danger), #dc2626); color: white; }
        .btn-success { background: linear-gradient(135deg, var(--success), #059669); color: white; }
        .btn-paypal { background: linear-gradient(135deg, #0070ba, #003087); color: white; }
        .btn-secondary { background: var(--bg); border: 1px solid var(--border); color: var(--text); }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.65rem; }
        
        .results-table { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; overflow: hidden; margin-top: 1rem; }
        .results-header { padding: 0.6rem 1rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; }
        .results-header h3 { font-size: 0.8rem; }
        .filter-buttons { display: flex; gap: 0.3rem; }
        .filter-btn { background: none; border: none; color: var(--text-muted); padding: 0.15rem 0.5rem; border-radius: 0.3rem; cursor: pointer; font-size: 0.65rem; }
        .filter-btn.active { background: var(--primary); color: white; }
        
        .result-item { padding: 0.5rem 1rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; font-size: 0.7rem; }
        .result-item:last-child { border-bottom: none; }
        .result-item.approved { border-left: 3px solid var(--success); background: rgba(16, 185, 129, 0.05); }
        .result-item.declined { border-left: 3px solid var(--danger); }
        .result-status { font-weight: 600; font-size: 0.7rem; }
        .result-status.approved { color: var(--success); }
        .result-status.declined { color: var(--danger); }
        .result-details { font-family: monospace; font-size: 0.65rem; color: var(--text-muted); margin-top: 0.15rem; }
        .result-time { font-size: 0.55rem; color: var(--text-muted); }
        
        .stats { display: flex; gap: 0.8rem; margin-bottom: 1rem; }
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.6rem; padding: 0.5rem 0.8rem; flex: 1; }
        .stat-number { font-size: 1.2rem; font-weight: 700; }
        .stat-label { font-size: 0.6rem; color: var(--text-muted); }
        
        .badge { display: inline-block; padding: 0.15rem 0.4rem; border-radius: 0.25rem; font-size: 0.55rem; font-weight: 600; }
        .badge-success { background: var(--success); color: white; }
        .badge-danger { background: var(--danger); color: white; }
        
        .progress-bar { height: 2px; background: var(--border); margin-top: 0.8rem; border-radius: 2px; overflow: hidden; display: none; }
        .progress-fill { height: 100%; background: var(--primary); width: 0%; transition: width 0.3s; }
        
        .checkbox-group { display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.5rem; }
        .checkbox-group input { width: auto; margin-right: 0.2rem; }
        
        .credential-status { font-size: 0.65rem; padding: 0.3rem; border-radius: 0.3rem; margin-bottom: 0.5rem; }
        .credential-valid { background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid var(--success); }
        .credential-invalid { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid var(--danger); }
    </style>
</head>
<body data-theme="dark">
    <?php include __DIR__ . "/../../includes/header.php"; ?>
    <?php include __DIR__ . "/../../includes/sidebar.php"; ?>
    
    <main class="main" id="main">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">PayPal Key Based Checker</h1>
                <p class="page-subtitle">Test cards using PayPal API with your own credentials</p>
            </div>
            
            <div class="stats">
                <div class="stat-card"><div class="stat-number" id="totalCount">0</div><div class="stat-label">Total</div></div>
                <div class="stat-card"><div class="stat-number" id="approvedCount">0</div><div class="stat-label">Approved</div></div>
                <div class="stat-card"><div class="stat-number" id="declinedCount">0</div><div class="stat-label">Declined</div></div>
            </div>
            
            <div class="checker-layout">
                <div class="card-input-section">
                    <h3 style="margin-bottom: 0.8rem; font-size: 0.9rem;"><i class="fab fa-paypal"></i> PayPal Credentials</h3>
                    
                    <div class="input-group">
                        <label>Client ID</label>
                        <input type="text" id="clientId" placeholder="Ae2PqZx...">
                    </div>
                    
                    <div class="input-group">
                        <label>Client Secret</label>
                        <input type="password" id="clientSecret" placeholder="EKa7Yp...">
                        <button class="btn btn-secondary btn-sm" id="testCredBtn" style="margin-top: 0.3rem; width: 100%;"><i class="fas fa-vial"></i> Test Credentials</button>
                        <div id="credStatus" class="credential-status" style="margin-top: 0.3rem; display: none;"></div>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="saveCreds">
                        <label>Save credentials to localStorage</label>
                    </div>
                    
                    <div style="margin: 0.8rem 0; border-top: 1px solid var(--border);"></div>
                    
                    <h3 style="margin-bottom: 0.8rem; font-size: 0.9rem;"><i class="fas fa-credit-card"></i> Cards</h3>
                    
                    <div class="input-group">
                        <label>Cards (Number|Month|Year|CVV)</label>
                        <textarea id="cardInput" rows="5" placeholder="5509890034877216|06|2028|333&#10;4111111111111111|12|2025|123" style="font-size: 0.7rem;"></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 0.4rem; margin-bottom: 0.8rem;">
                        <button class="btn btn-secondary btn-sm" id="uploadFileBtn"><i class="fas fa-upload"></i> Upload</button>
                        <button class="btn btn-secondary btn-sm" id="clearCardsBtn"><i class="fas fa-trash"></i> Clear</button>
                        <input type="file" id="fileInput" style="display: none;" accept=".txt,.csv">
                    </div>
                    
                    <div class="input-group">
                        <label>Amount ($)</label>
                        <input type="text" id="amount" value="5.00">
                    </div>
                    
                    <div class="input-group">
                        <label>Currency</label>
                        <select id="currency">
                            <option value="USD">USD - US Dollar</option>
                            <option value="EUR">EUR - Euro</option>
                            <option value="GBP">GBP - British Pound</option>
                            <option value="CAD">CAD - Canadian Dollar</option>
                            <option value="AUD">AUD - Australian Dollar</option>
                        </select>
                    </div>
                    
                    <div class="input-group">
                        <label>Proxy (optional)</label>
                        <input type="text" id="proxy" placeholder="ip:port:user:pass">
                    </div>
                    
                    <div style="display: flex; gap: 0.4rem; margin-top: 0.8rem;">
                        <button class="btn btn-paypal" id="startBtn"><i class="fab fa-paypal"></i> Start Check</button>
                        <button class="btn btn-danger" id="stopBtn" disabled><i class="fas fa-stop"></i> Stop</button>
                        <button class="btn btn-success" id="downloadApprovedBtn" disabled><i class="fas fa-download"></i> DL</button>
                    </div>
                    
                    <div class="progress-bar" id="progressBar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h3 style="margin-bottom: 0.8rem; font-size: 0.9rem;"><i class="fas fa-sliders-h"></i> Settings</h3>
                    
                    <div class="input-group">
                        <label>Delay (ms)</label>
                        <input type="number" id="delay" value="500">
                    </div>
                    
                    <div class="input-group">
                        <label>Threads</label>
                        <select id="threads">
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="5">5</option>
                        </select>
                    </div>
                    
                    <div class="input-group">
                        <label>Proxy Mode</label>
                        <select id="proxyMode">
                            <option value="none">None</option>
                            <option value="custom">Custom Proxy</option>
                            <option value="rotate">Rotate Proxies</option>
                        </select>
                    </div>
                    
                    <div class="input-group" id="proxyListGroup" style="display: none;">
                        <label>Proxy List</label>
                        <textarea id="proxyList" rows="3" placeholder="ip:port:user:pass"></textarea>
                    </div>
                    
                    <div class="input-group">
                        <label>API Mode</label>
                        <select id="apiMode">
                            <option value="live">Live Mode (Real Payments)</option>
                            <option value="sandbox">Sandbox Mode (Test Payments)</option>
                        </select>
                    </div>
                    
                    <div class="info-box" style="background: rgba(0,112,186,0.1); padding: 0.5rem; border-radius: 0.4rem; margin-top: 0.5rem;">
                        <i class="fab fa-paypal" style="color: #0070ba;"></i>
                        <span style="font-size: 0.6rem; color: var(--text-muted);">Get API credentials from: https://developer.paypal.com/dashboard/</span>
                    </div>
                </div>
            </div>
            
            <div class="results-table">
                <div class="results-header">
                    <h3><i class="fas fa-list"></i> Results (<span id="resultCount">0</span>)</h3>
                    <div class="filter-buttons">
                        <button class="filter-btn active" data-filter="all">All</button>
                        <button class="filter-btn" data-filter="approved">Approved</button>
                        <button class="filter-btn" data-filter="declined">Declined</button>
                    </div>
                </div>
                <div id="resultsContainer">
                    <div class="result-item unknown"><div><div class="result-status">Ready</div><div class="result-details">Enter PayPal API credentials and cards, then click Start Check</div></div></div>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        let isRunning = false;
        let currentFilter = 'all';
        let results = [];
        let approvedCards = [];
        
        // Load saved settings
        $('#delay').val(localStorage.getItem('paypal_delay') || '500');
        $('#threads').val(localStorage.getItem('paypal_threads') || '1');
        $('#amount').val(localStorage.getItem('paypal_amount') || '5.00');
        $('#currency').val(localStorage.getItem('paypal_currency') || 'USD');
        $('#apiMode').val(localStorage.getItem('paypal_mode') || 'sandbox');
        
        const savedClientId = localStorage.getItem('paypal_client_id');
        const savedClientSecret = localStorage.getItem('paypal_client_secret');
        if (savedClientId) $('#clientId').val(savedClientId);
        if (savedClientSecret) $('#clientSecret').val(savedClientSecret);
        if (savedClientId && savedClientSecret) $('#saveCreds').prop('checked', true);
        
        $('#delay, #threads, #amount, #currency, #apiMode').on('change', function() {
            localStorage.setItem('paypal_delay', $('#delay').val());
            localStorage.setItem('paypal_threads', $('#threads').val());
            localStorage.setItem('paypal_amount', $('#amount').val());
            localStorage.setItem('paypal_currency', $('#currency').val());
            localStorage.setItem('paypal_mode', $('#apiMode').val());
        });
        
        $('#saveCreds').on('change', function() {
            if ($(this).is(':checked')) {
                localStorage.setItem('paypal_client_id', $('#clientId').val());
                localStorage.setItem('paypal_client_secret', $('#clientSecret').val());
            } else {
                localStorage.removeItem('paypal_client_id');
                localStorage.removeItem('paypal_client_secret');
            }
        });
        
        $('#clientId, #clientSecret').on('input', function() {
            if ($('#saveCreds').is(':checked')) {
                localStorage.setItem('paypal_client_id', $('#clientId').val());
                localStorage.setItem('paypal_client_secret', $('#clientSecret').val());
            }
        });
        
        // Test PayPal credentials
        $('#testCredBtn').on('click', async function() {
            const clientId = $('#clientId').val();
            const clientSecret = $('#clientSecret').val();
            const mode = $('#apiMode').val();
            
            if (!clientId || !clientSecret) {
                Swal.fire('Error', 'Please enter both Client ID and Secret', 'error');
                return;
            }
            
            $('#credStatus').show().html('<i class="fas fa-spinner fa-spin"></i> Testing credentials...').removeClass('credential-valid credential-invalid');
            
            const gate = mode === 'sandbox' ? '/gate/paypal-key-sandbox.php' : '/gate/paypal-key.php';
            
            try {
                const response = await $.ajax({
                    url: gate + '?test_creds=1&client_id=' + encodeURIComponent(clientId) + '&client_secret=' + encodeURIComponent(clientSecret),
                    method: 'GET',
                    timeout: 15000
                });
                
                if (response.status === 'valid') {
                    $('#credStatus').html('<i class="fas fa-check-circle"></i> Credentials are valid!').addClass('credential-valid').removeClass('credential-invalid');
                    Swal.fire('Success', 'PayPal credentials are valid', 'success');
                } else {
                    $('#credStatus').html('<i class="fas fa-exclamation-circle"></i> ' + response.message).addClass('credential-invalid').removeClass('credential-valid');
                    Swal.fire('Error', response.message, 'error');
                }
            } catch(e) {
                $('#credStatus').html('<i class="fas fa-exclamation-circle"></i> Failed to test credentials').addClass('credential-invalid').removeClass('credential-valid');
                Swal.fire('Error', 'Failed to test credentials', 'error');
            }
        });
        
        // Proxy mode toggle
        $('#proxyMode').on('change', function() {
            $('#proxyListGroup').toggle($(this).val() === 'rotate');
        });
        
        // File upload
        $('#uploadFileBtn').on('click', () => $('#fileInput').click());
        $('#fileInput').on('change', function(e) {
            const file = e.target.files[0];
            if(!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                const current = $('#cardInput').val();
                $('#cardInput').val(current + (current ? '\n' : '') + e.target.result);
                Swal.fire('Loaded', `Cards loaded from ${file.name}`, 'success');
            };
            reader.readAsText(file);
        });
        
        $('#clearCardsBtn').on('click', () => $('#cardInput').val(''));
        
        $('#downloadApprovedBtn').on('click', function() {
            if(approvedCards.length === 0) return Swal.fire('No approved cards', '', 'warning');
            const content = approvedCards.join('\n');
            const blob = new Blob([content], { type: 'text/plain' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `paypal_approved_${new Date().toISOString().slice(0,19)}.txt`;
            a.click();
            URL.revokeObjectURL(a.href);
            Swal.fire('Downloaded', `${approvedCards.length} cards`, 'success');
        });
        
        function updateStats() {
            $('#totalCount').text(results.length);
            $('#approvedCount').text(results.filter(r => r.status === 'APPROVED').length);
            $('#declinedCount').text(results.filter(r => r.status !== 'APPROVED').length);
            $('#downloadApprovedBtn').prop('disabled', approvedCards.length === 0);
        }
        
        function updateResultsDisplay() {
            let filtered = results;
            if (currentFilter === 'approved') filtered = results.filter(r => r.status === 'APPROVED');
            else if (currentFilter === 'declined') filtered = results.filter(r => r.status !== 'APPROVED');
            
            $('#resultCount').text(results.length);
            updateStats();
            
            if (filtered.length === 0) {
                $('#resultsContainer').html('<div class="result-item unknown"><div><div class="result-status">No results</div></div></div>');
                return;
            }
            
            let html = '';
            filtered.forEach(r => {
                let statusClass = r.status === 'APPROVED' ? 'approved' : 'declined';
                let badge = r.status === 'APPROVED' ? 'badge-success' : 'badge-danger';
                let badgeText = r.status === 'APPROVED' ? 'LIVE' : 'DIE';
                
                html += `
                    <div class="result-item ${statusClass}">
                        <div style="flex:1;">
                            <div class="result-status ${statusClass}">${r.status}: ${r.message}</div>
                            <div class="result-details">${r.cc} | $${r.amount} ${r.currency} | ${r.order_id || ''}</div>
                            <div class="result-time">${r.time}</div>
                        </div>
                        <div><span class="badge ${badge}">${badgeText}</span></div>
                    </div>
                `;
            });
            $('#resultsContainer').html(html);
        }
        
        function getProxy() {
            const mode = $('#proxyMode').val();
            if (mode === 'none') return '';
            if (mode === 'custom') return $('#proxy').val();
            if (mode === 'rotate') {
                const proxies = $('#proxyList').val().split('\n').filter(p => p.trim());
                if (proxies.length) return proxies[Math.floor(Math.random() * proxies.length)].trim();
            }
            return '';
        }
        
        async function checkCard(card, clientId, clientSecret, amount, currency) {
            const proxy = getProxy();
            const mode = $('#apiMode').val();
            const gate = mode === 'sandbox' ? '/gate/paypal-key-sandbox.php' : '/gate/paypal-key.php';
            
            return new Promise((resolve) => {
                const params = new URLSearchParams({ 
                    cc: card, 
                    client_id: clientId, 
                    client_secret: clientSecret, 
                    amount: amount,
                    currency: currency
                });
                if (proxy) params.append('proxy', proxy);
                
                $.ajax({
                    url: `${gate}?${params.toString()}`,
                    method: 'GET',
                    timeout: 30000,
                    success: (response) => resolve(response),
                    error: (xhr) => resolve({ Response: `Request failed: ${xhr.statusText}`, status: 'ERROR', CC: card })
                });
            });
        }
        
        async function runChecker() {
            const cards = $('#cardInput').val().trim().split('\n').filter(c => c.trim());
            if (!cards.length) return Swal.fire('Error', 'Enter cards', 'error');
            
            const clientId = $('#clientId').val().trim();
            const clientSecret = $('#clientSecret').val().trim();
            
            if (!clientId || !clientSecret) {
                return Swal.fire('Error', 'Enter PayPal API credentials (Client ID and Secret)', 'error');
            }
            
            const amount = $('#amount').val();
            const currency = $('#currency').val();
            const delay = parseInt($('#delay').val());
            const threads = parseInt($('#threads').val());
            
            $('#startBtn').prop('disabled', true);
            $('#stopBtn').prop('disabled', false);
            $('#progressBar').show();
            isRunning = true;
            
            let completed = 0;
            let currentIndex = 0;
            approvedCards = [];
            
            const updateProgress = () => {
                $('#progressFill').css('width', (completed / cards.length * 100) + '%');
            };
            
            const processCard = async (index) => {
                if (!isRunning || index >= cards.length) return;
                
                const card = cards[index];
                const time = new Date().toLocaleTimeString();
                
                results.unshift({ status: 'PENDING', message: 'Checking...', cc: card, amount: amount, currency: currency, time: time });
                updateResultsDisplay();
                
                try {
                    const response = await checkCard(card, clientId, clientSecret, amount, currency);
                    let status = 'DECLINED', message = '', orderId = '';
                    
                    if (response.Response) {
                        message = response.Response;
                        if (response.Response.startsWith('APPROVED')) {
                            status = 'APPROVED';
                            approvedCards.push(card);
                        } else {
                            status = 'DECLINED';
                        }
                        if (response.OrderId) orderId = response.OrderId;
                    } else if (response.status === 'APPROVED') {
                        status = 'APPROVED';
                        approvedCards.push(card);
                        message = response.message || '';
                    }
                    
                    results[0] = { 
                        status, 
                        message: message.replace(/^(APPROVED|DECLINED):\s*/, '').substring(0, 100), 
                        cc: card, 
                        amount: amount,
                        currency: currency,
                        order_id: orderId,
                        time: time 
                    };
                    
                    if (status === 'APPROVED') {
                        Swal.fire({ 
                            title: 'Approved!', 
                            html: `${card}<br>$${amount} ${currency}<br>Order: ${orderId}`, 
                            icon: 'success', 
                            toast: true, 
                            timer: 3000, 
                            showConfirmButton: false, 
                            position: 'top-end' 
                        });
                    }
                    
                } catch(e) {
                    results[0] = { status: 'ERROR', message: e.message, cc: card, amount: amount, currency: currency, time: time };
                }
                
                updateResultsDisplay();
                completed++;
                updateProgress();
                
                currentIndex++;
                if (currentIndex < cards.length && isRunning) {
                    setTimeout(() => processCard(currentIndex), delay);
                }
            };
            
            for (let i = 0; i < Math.min(threads, cards.length); i++) {
                processCard(currentIndex++);
            }
            
            const checkCompletion = setInterval(() => {
                if (completed >= cards.length || !isRunning) {
                    clearInterval(checkCompletion);
                    isRunning = false;
                    $('#startBtn').prop('disabled', false);
                    $('#stopBtn').prop('disabled', true);
                    $('#progressBar').hide();
                    Swal.fire('Done', `Checked ${cards.length} cards\nApproved: ${approvedCards.length}`, 'info');
                }
            }, 500);
        }
        
        $('#startBtn').on('click', () => { if(!isRunning) runChecker(); });
        $('#stopBtn').on('click', () => { isRunning = false; $('#startBtn').prop('disabled', false); $('#stopBtn').prop('disabled', true); Swal.fire('Stopped', '', 'warning'); });
        
        $('.filter-btn').on('click', function() {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            currentFilter = $(this).data('filter');
            updateResultsDisplay();
        });
        
        // Theme handling
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.body.setAttribute('data-theme', savedTheme);
        const themeBtn = document.getElementById('themeBtn');
        if(themeBtn){
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
        if(menuBtn) menuBtn.addEventListener('click', () => { sidebar.classList.toggle('open'); main.classList.toggle('sidebar-open'); });
        
        // Example
        if(!$('#cardInput').val()) $('#cardInput').val('5509890034877216|06|2028|333');
    </script>
</body>
</html>
