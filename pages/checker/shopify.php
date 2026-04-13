<?php
// Shopify Checker Page - PURE SHOPIFY (No Stripe)
require_once __DIR__ . "/../../includes/config.php";

if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$pageTitle = "Shopify Checker";
$user = $_SESSION['user'];
$credits = getUserCredits();
$isAdmin = isAdmin();
$userPlan = $user['plan'] ?? 'basic';

// Get Shopify gate from database
$gates = loadGates();
$shopifyGate = $gates['shopify'] ?? null;
$requiredPlan = $shopifyGate['required_plan'] ?? 'basic';
$planPriority = ['basic' => 1, 'premium' => 2, 'gold' => 3, 'platinum' => 4, 'lifetime' => 5];
$canAccess = $isAdmin || ($planPriority[$userPlan] >= $planPriority[$requiredPlan]);
$creditCost = $shopifyGate['credit_cost'] ?? 1;

// Check if Telegram hits are enabled
$settings = loadSettings();
$telegramHitsEnabled = ($settings['telegram_hits_enabled'] ?? 'false') === 'true';

// Shopify sites for Auto Mode (PURE SHOPIFY STORES)
$defaultSites = [
    'https://sariscycling.myshopify.com',
    'https://shopnemba.myshopify.com',
    'https://figpin2018.myshopify.com',
    'https://mosaic-makers-collective.myshopify.com',
    'https://paria-outdoor-products.myshopify.com',
    'https://snake-river-fly.myshopify.com',
    'https://electricwheel-store.myshopify.com',
    'https://power-calls.myshopify.com',
    'https://rockbottomje.myshopify.com',
    'https://glovestation.myshopify.com',
    'https://beadsandbrushstrokes.myshopify.com',
    'https://stamford-shades.myshopify.com',
    'https://jim-wendler.myshopify.com',
    'https://fekkaibrands.myshopify.com',
    'https://kwohtations.myshopify.com',
    'https://habitaware2018.myshopify.com',
    'https://airturn.myshopify.com',
    'https://kizingo.myshopify.com',
    'https://springer-pets.myshopify.com',
    'https://healthworkssafety.net'
];
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
        :root { --bg: #0a0a0f; --card: #111114; --border: #1e1e24; --text: #ffffff; --text-muted: #6b6b76; --primary: #8b5cf6; --success: #10b981; --danger: #ef4444; --warning: #f59e0b; }
        [data-theme="light"] { --bg: #f8fafc; --card: #ffffff; --border: #e2e8f0; --text: #0f172a; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; font-size: 14px; }
        .main { margin-left: 0; margin-top: 55px; padding: 1.2rem; transition: margin-left 0.2s; }
        .main.sidebar-open { margin-left: 280px; }
        @media (max-width: 768px) { .main.sidebar-open { margin-left: 0; } }
        .container { max-width: 1400px; margin: 0 auto; }
        
        .gate-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 10, 15, 0.95);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            backdrop-filter: blur(5px);
            transition: opacity 0.3s;
        }
        .gate-loader.hide { opacity: 0; pointer-events: none; }
        .loader-spinner { width: 50px; height: 50px; border: 3px solid var(--border); border-top-color: var(--primary); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loader-text { margin-top: 1rem; color: var(--primary); font-size: 0.8rem; font-weight: 500; }
        .loader-progress { width: 200px; height: 2px; background: var(--border); border-radius: 2px; margin-top: 1rem; overflow: hidden; }
        .loader-progress-bar { width: 0%; height: 100%; background: linear-gradient(90deg, var(--primary), #06b6d4); transition: width 0.3s; }
        
        .features-panel { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 1rem; margin-bottom: 1rem; }
        .features-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.8rem; }
        .feature-card { background: var(--bg); border: 1px solid var(--border); border-radius: 0.6rem; padding: 0.8rem; text-align: center; cursor: pointer; transition: all 0.2s; }
        .feature-card:hover { transform: translateY(-2px); border-color: var(--primary); background: rgba(139,92,246,0.05); }
        .feature-icon { font-size: 1.2rem; margin-bottom: 0.3rem; color: var(--primary); }
        .feature-title { font-size: 0.7rem; font-weight: 600; }
        .feature-desc { font-size: 0.55rem; color: var(--text-muted); margin-top: 0.2rem; }
        
        .batch-section { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 1rem; margin-bottom: 1rem; display: none; }
        .batch-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem; flex-wrap: wrap; gap: 0.5rem; }
        .page-header { margin-bottom: 1rem; }
        .page-title { font-size: 1.6rem; font-weight: 700; background: linear-gradient(135deg, var(--primary), #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .credit-info { font-size: 0.7rem; color: var(--text-muted); text-align: right; margin-bottom: 1rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.5rem; margin-bottom: 1rem; display: none; }
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.5rem; padding: 0.5rem; text-align: center; }
        .stat-value { font-size: 1.1rem; font-weight: 700; }
        .stat-label { font-size: 0.55rem; text-transform: uppercase; color: var(--text-muted); }
        .checker-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 1rem; margin-bottom: 1rem; }
        textarea { width: 100%; min-height: 120px; background: var(--bg); border: 1px solid var(--border); border-radius: 0.5rem; padding: 0.6rem; color: var(--text); font-family: monospace; font-size: 0.75rem; resize: vertical; }
        .action-buttons { display: flex; gap: 0.5rem; margin-top: 0.8rem; flex-wrap: wrap; }
        .btn { padding: 0.4rem 0.8rem; border-radius: 0.4rem; font-weight: 500; font-size: 0.65rem; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), #7c3aed); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-secondary { background: var(--bg); border: 1px solid var(--border); color: var(--text); }
        .btn-success { background: var(--success); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.6rem; }
        .results-section { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 1rem; margin-top: 1rem; display: none; }
        .results-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem; flex-wrap: wrap; gap: 0.5rem; }
        .filter-buttons { display: flex; gap: 0.3rem; flex-wrap: wrap; margin-bottom: 0.8rem; }
        .filter-btn { padding: 0.2rem 0.5rem; border-radius: 0.3rem; font-size: 0.55rem; cursor: pointer; background: var(--bg); border: 1px solid var(--border); color: var(--text-muted); }
        .filter-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        .result-item { display: flex; flex-direction: column; gap: 0.3rem; padding: 0.6rem; border-bottom: 1px solid var(--border); margin-bottom: 0.3rem; background: var(--bg); border-radius: 0.5rem; position: relative; }
        .result-item.approved { border-left: 3px solid var(--success); }
        .result-item.declined { border-left: 3px solid var(--danger); }
        .result-item.threeds { border-left: 3px solid var(--warning); }
        .result-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.3rem; }
        .result-card { font-family: monospace; font-size: 0.7rem; font-weight: 500; word-break: break-all; }
        .result-status { font-size: 0.6rem; padding: 0.15rem 0.4rem; border-radius: 0.3rem; }
        .result-status.approved { background: rgba(16,185,129,0.2); color: var(--success); }
        .result-status.declined { background: rgba(239,68,68,0.2); color: var(--danger); }
        .result-status.threeds { background: rgba(245,158,11,0.2); color: var(--warning); }
        .result-details { font-size: 0.55rem; color: var(--text-muted); margin-top: 0.2rem; }
        .result-actions { display: flex; gap: 0.3rem; margin-top: 0.3rem; flex-wrap: wrap; }
        .result-action-btn { background: none; border: 1px solid var(--border); border-radius: 0.3rem; padding: 0.15rem 0.4rem; font-size: 0.55rem; cursor: pointer; color: var(--text-muted); transition: all 0.2s; }
        .result-action-btn:hover { background: var(--primary); border-color: var(--primary); color: white; }
        .progress-bar { margin-top: 1rem; height: 3px; background: var(--border); border-radius: 2px; overflow: hidden; display: none; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary), #06b6d4); width: 0%; transition: width 0.3s; }
        .status-text { font-size: 0.6rem; color: var(--text-muted); margin-top: 0.5rem; text-align: center; display: none; }
        .credit-cost-badge { background: rgba(139,92,246,0.2); padding: 0.2rem 0.5rem; border-radius: 0.5rem; font-size: 0.65rem; }
        .site-mode-buttons, .proxy-mode-buttons { display: flex; gap: 1rem; margin-bottom: 0.5rem; flex-wrap: wrap; }
        .mode-btn { display: flex; align-items: center; gap: 0.3rem; padding: 0.3rem 0.8rem; border-radius: 0.5rem; background: var(--bg); border: 1px solid var(--border); cursor: pointer; font-size: 0.7rem; transition: all 0.2s; }
        .mode-btn.active { background: var(--primary); border-color: var(--primary); color: white; }
        .site-item, .proxy-item { background: var(--bg); border: 1px solid var(--border); border-radius: 0.4rem; padding: 0.3rem 0.6rem; margin-bottom: 0.3rem; font-size: 0.65rem; display: flex; justify-content: space-between; align-items: center; }
        .site-url, .proxy-string { font-family: monospace; word-break: break-all; flex: 1; }
        .delete-site, .delete-proxy { background: none; border: none; color: var(--danger); cursor: pointer; padding: 0.2rem; }
        .add-site-form, .add-proxy-form { display: flex; gap: 0.5rem; margin-top: 0.5rem; }
        .add-site-form input, .add-proxy-form input { flex: 1; padding: 0.3rem; background: var(--bg); border: 1px solid var(--border); border-radius: 0.3rem; color: var(--text); font-size: 0.65rem; }
        .batch-actions { display: flex; gap: 0.5rem; margin-top: 0.8rem; padding-top: 0.8rem; border-top: 1px solid var(--border); flex-wrap: wrap; }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } .features-grid { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body data-theme="dark">
    <div class="gate-loader" id="gateLoader" style="display: none;">
        <div class="loader-spinner"></div>
        <div class="loader-text" id="loaderText">Loading Shopify Checker...</div>
        <div class="loader-progress"><div class="loader-progress-bar" id="loaderProgress"></div></div>
    </div>

    <?php include $_SERVER['DOCUMENT_ROOT'] . "/includes/header.php"; ?>
    <?php include $_SERVER['DOCUMENT_ROOT'] . "/includes/sidebar.php"; ?>
    
    <main class="main" id="main">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title"><i class="fab fa-shopify"></i> <?php echo $pageTitle; ?></h1>
                <p class="page-subtitle">Test cards against Shopify stores | Auto & Self modes</p>
            </div>
            <div class="credit-info">
                <i class="fas fa-coins"></i> Credits: <span id="creditAmount"><?php echo $isAdmin ? '∞' : number_format($credits); ?></span>
                <span class="credit-cost-badge"><i class="fas fa-tachometer-alt"></i> Cost: <?php echo $creditCost; ?> credit(s) per check</span>
                <?php if ($telegramHitsEnabled): ?>
                <span class="credit-cost-badge" style="background: rgba(34,197,94,0.2);"><i class="fab fa-telegram"></i> Hits to TG</span>
                <?php endif; ?>
            </div>
            
            <div class="features-panel">
                <div class="features-grid">
                    <div class="feature-card" onclick="showBatchChecker()">
                        <div class="feature-icon"><i class="fas fa-layer-group"></i></div>
                        <div class="feature-title">Batch Checker</div>
                        <div class="feature-desc">Multi-thread processing</div>
                    </div>
                    <div class="feature-card" onclick="showSiteManager()">
                        <div class="feature-icon"><i class="fas fa-store"></i></div>
                        <div class="feature-title">Site Manager</div>
                        <div class="feature-desc">Manage Shopify sites</div>
                    </div>
                    <div class="feature-card" onclick="showProxyManager()">
                        <div class="feature-icon"><i class="fas fa-globe"></i></div>
                        <div class="feature-title">Proxy Manager</div>
                        <div class="feature-desc">Manage proxies</div>
                    </div>
                    <div class="feature-card" onclick="clearAllResults()">
                        <div class="feature-icon"><i class="fas fa-trash-alt"></i></div>
                        <div class="feature-title">Clear All</div>
                        <div class="feature-desc">Reset everything</div>
                    </div>
                </div>
            </div>
            
            <div class="batch-section" id="batchPanel">
                <div class="batch-header">
                    <div><i class="fas fa-tachometer-alt"></i> <strong>Batch Checker</strong> <span id="batchStatus" style="font-size:0.6rem;"></span></div>
                    <div><button class="btn btn-secondary btn-sm" onclick="closeBatchPanel()"><i class="fas fa-times"></i> Close</button></div>
                </div>
                <div class="stats-grid" style="margin-bottom:0.5rem; display:grid;">
                    <div class="stat-card"><div class="stat-value" id="batchApproved">0</div><div class="stat-label">Approved</div></div>
                    <div class="stat-card"><div class="stat-value" id="batchDeclined">0</div><div class="stat-label">Declined</div></div>
                    <div class="stat-card"><div class="stat-value" id="batchProcessed">0</div><div class="stat-label">Processed</div></div>
                </div>
                <div class="progress-bar" id="batchProgressBar" style="display:block;"><div class="progress-fill" id="batchProgressFill" style="width:0%;"></div></div>
            </div>
            
            <div class="batch-section" id="sitePanel" style="display:none;">
                <div class="batch-header">
                    <div><i class="fas fa-store"></i> <strong>Site Manager (Self Mode Shopify Sites)</strong></div>
                    <div><button class="btn btn-secondary btn-sm" onclick="closeSitePanel()"><i class="fas fa-times"></i> Close</button></div>
                </div>
                <div id="customSitesList"></div>
                <div class="add-site-form">
                    <input type="text" id="newSiteUrl" placeholder="https://example.myshopify.com">
                    <button class="btn btn-primary btn-sm" onclick="addCustomSite()"><i class="fas fa-plus"></i> Add Site</button>
                </div>
                <div style="margin-top:0.5rem;">
                    <button class="btn btn-secondary btn-sm" onclick="uploadSitesFile()"><i class="fas fa-upload"></i> Upload Sites (.txt)</button>
                    <input type="file" id="sitesFileInput" style="display:none;" accept=".txt">
                </div>
            </div>
            
            <div class="batch-section" id="proxyPanel" style="display:none;">
                <div class="batch-header">
                    <div><i class="fas fa-globe"></i> <strong>Proxy Manager</strong></div>
                    <div><button class="btn btn-secondary btn-sm" onclick="closeProxyPanel()"><i class="fas fa-times"></i> Close</button></div>
                </div>
                <div id="proxyListContainer"></div>
                <div class="add-proxy-form">
                    <input type="text" id="newProxy" placeholder="ip:port:user:pass">
                    <button class="btn btn-primary btn-sm" onclick="addProxy()"><i class="fas fa-plus"></i> Add Proxy</button>
                </div>
                <div style="margin-top:0.5rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <button class="btn btn-secondary btn-sm" onclick="uploadProxiesFile()"><i class="fas fa-upload"></i> Upload Proxies (.txt)</button>
                    <button class="btn btn-warning btn-sm" onclick="testAllProxies()"><i class="fas fa-vial"></i> Test All</button>
                    <button class="btn btn-success btn-sm" onclick="getRandomProxy()"><i class="fas fa-random"></i> Random Proxy</button>
                </div>
                <input type="file" id="proxiesFileInput" style="display:none;" accept=".txt">
                <div id="proxyTestResult" style="margin-top:0.5rem; font-size:0.6rem;"></div>
            </div>
            
            <div class="checker-card">
                <div style="margin-bottom: 1rem;">
                    <div class="site-mode-buttons">
                        <div class="mode-btn active" id="autoModeBtn" onclick="setMode('auto')">
                            <i class="fas fa-robot"></i> Auto Mode (Built-in Shopify Sites)
                        </div>
                        <div class="mode-btn" id="selfModeBtn" onclick="setMode('self')">
                            <i class="fas fa-user"></i> Self Mode (Your Shopify Sites)
                        </div>
                    </div>
                    <div id="autoSitesDisplay" style="font-size:0.65rem; color:var(--text-muted);">
                        <i class="fas fa-globe"></i> Using built-in Shopify stores
                    </div>
                    <div id="selfSitesDisplay" style="display:none; font-size:0.65rem; color:var(--text-muted);">
                        <i class="fas fa-store"></i> Using custom Shopify sites. <a href="#" onclick="showSiteManager(); return false;">Manage Sites</a>
                    </div>
                </div>
                
                <div class="proxy-section">
                    <div class="proxy-mode-buttons">
                        <div class="mode-btn active" id="noProxyBtn" onclick="setProxyMode('none')">
                            <i class="fas fa-globe"></i> No Proxy
                        </div>
                        <div class="mode-btn" id="customProxyBtn" onclick="setProxyMode('custom')">
                            <i class="fas fa-server"></i> Use Proxy
                        </div>
                        <div class="mode-btn" id="rotateProxyBtn" onclick="setProxyMode('rotate')">
                            <i class="fas fa-random"></i> Rotate Proxies
                        </div>
                    </div>
                    <div id="proxyDisplay" style="font-size:0.65rem; color:var(--text-muted); margin-top:0.3rem;">
                        <i class="fas fa-info-circle"></i> No proxy will be used
                    </div>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <label><i class="fas fa-credit-card"></i> Cards (Format: card|month|year|cvv)</label>
                    <div class="download-buttons">
                        <button class="btn btn-secondary btn-sm" id="uploadBtn"><i class="fas fa-upload"></i> Upload .txt</button>
                        <input type="file" id="fileInput" style="display: none;" accept=".txt">
                    </div>
                </div>
                <textarea id="cardsInput" placeholder="5509890034877216|06|2028|333&#10;4111111111111111|12|2025|123"></textarea>
                <div class="action-buttons">
                    <button class="btn btn-primary" id="startBtn"><i class="fas fa-play"></i> Start Check</button>
                    <button class="btn btn-danger" id="stopBtn" disabled><i class="fas fa-stop"></i> Stop</button>
                    <button class="btn btn-secondary" id="clearBtn"><i class="fas fa-trash"></i> Clear</button>
                    <button class="btn btn-warning" id="massCheckBtn"><i class="fas fa-rocket"></i> Mass Check</button>
                </div>
                <div class="progress-bar" id="progressBar"><div class="progress-fill" id="progressFill"></div></div>
                <div class="status-text" id="statusText">Ready...</div>
            </div>
            
            <div class="stats-grid" id="statsGrid" style="display: none;">
                <div class="stat-card"><div class="stat-value" id="statApproved">0</div><div class="stat-label">Approved</div></div>
                <div class="stat-card"><div class="stat-value" id="statDeclined">0</div><div class="stat-label">Declined</div></div>
                <div class="stat-card"><div class="stat-value" id="stat3DS">0</div><div class="stat-label">3DS</div></div>
                <div class="stat-card"><div class="stat-value" id="statTotal">0</div><div class="stat-label">Total</div></div>
            </div>
            
            <div class="results-section" id="resultsSection">
                <div class="results-header">
                    <div><i class="fas fa-list-check"></i> Results (<span id="resultsCount">0</span>)</div>
                    <div class="batch-actions">
                        <button class="btn btn-success btn-sm" id="copyAllApprovedBtn" disabled><i class="fas fa-copy"></i> Copy Approved</button>
                        <button class="btn btn-primary btn-sm" id="downloadAllBtn" disabled><i class="fas fa-download"></i> Download All</button>
                        <button class="btn btn-success btn-sm" id="downloadApprovedBtn" disabled><i class="fas fa-download"></i> DL Approved</button>
                        <button class="btn btn-info btn-sm" id="exportJSONBtn" disabled><i class="fas fa-file-code"></i> Export JSON</button>
                    </div>
                </div>
                <div class="filter-buttons">
                    <button class="filter-btn active" data-filter="all">All</button>
                    <button class="filter-btn" data-filter="approved">Approved</button>
                    <button class="filter-btn" data-filter="declined">Declined</button>
                    <button class="filter-btn" data-filter="threeds">3DS</button>
                </div>
                <div id="resultsList"></div>
            </div>
        </div>
    </main>
    
    <script>
        let isProcessing = false;
        let shouldStop = false;
        let currentCredits = <?php echo $credits; ?>;
        let creditCost = <?php echo $creditCost; ?>;
        let isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
        let results = [];
        let isMassChecking = false;
        let currentMode = 'auto';
        let proxyMode = 'none';
        let currentProxy = '';
        let customSites = [];
        let proxies = [];
        
        const defaultSites = <?php echo json_encode($defaultSites); ?>;
        
        function showLoader(text, duration) {
            const loader = document.getElementById('gateLoader');
            loader.style.display = 'flex';
            document.getElementById('loaderText').textContent = text || 'Loading...';
            if (duration) setTimeout(() => hideLoader(), duration);
        }
        
        function hideLoader() {
            document.getElementById('gateLoader').style.display = 'none';
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            showLoader('Loading Shopify Checker...', 800);
            loadCustomSites();
            loadProxies();
        });
        
        function setMode(mode) {
            currentMode = mode;
            document.getElementById('autoModeBtn').classList.toggle('active', mode === 'auto');
            document.getElementById('selfModeBtn').classList.toggle('active', mode === 'self');
            document.getElementById('autoSitesDisplay').style.display = mode === 'auto' ? 'block' : 'none';
            document.getElementById('selfSitesDisplay').style.display = mode === 'self' ? 'block' : 'none';
        }
        
        function setProxyMode(mode) {
            proxyMode = mode;
            document.getElementById('noProxyBtn').classList.toggle('active', mode === 'none');
            document.getElementById('customProxyBtn').classList.toggle('active', mode === 'custom');
            document.getElementById('rotateProxyBtn').classList.toggle('active', mode === 'rotate');
        }
        
        function getSite() {
            if (currentMode === 'auto') {
                return defaultSites[Math.floor(Math.random() * defaultSites.length)];
            } else {
                return customSites.length > 0 ? customSites[Math.floor(Math.random() * customSites.length)] : defaultSites[0];
            }
        }
        
        function getProxy() {
            if (proxyMode === 'none') return '';
            if (proxyMode === 'custom') return currentProxy;
            if (proxyMode === 'rotate' && proxies.length > 0) {
                return proxies[Math.floor(Math.random() * proxies.length)];
            }
            return '';
        }
        
        function loadCustomSites() {
            const saved = localStorage.getItem('shopify_custom_sites');
            if (saved) customSites = JSON.parse(saved);
            renderCustomSites();
        }
        
        function saveCustomSites() {
            localStorage.setItem('shopify_custom_sites', JSON.stringify(customSites));
            renderCustomSites();
        }
        
        function renderCustomSites() {
            const container = document.getElementById('customSitesList');
            if (!container) return;
            if (customSites.length === 0) {
                container.innerHTML = '<div class="site-item"><div class="site-url">No custom Shopify sites added yet</div></div>';
                return;
            }
            let html = '';
            customSites.forEach((site, index) => {
                html += `<div class="site-item"><div class="site-url">${escapeHtml(site)}</div><button class="delete-site" onclick="removeCustomSite(${index})"><i class="fas fa-trash"></i></button></div>`;
            });
            container.innerHTML = html;
        }
        
        function addCustomSite() {
            const url = document.getElementById('newSiteUrl').value.trim();
            if (!url) return Swal.fire('Error', 'Please enter a Shopify site URL', 'error');
            if (!url.startsWith('http')) return Swal.fire('Error', 'URL must start with http:// or https://', 'error');
            customSites.push(url);
            saveCustomSites();
            document.getElementById('newSiteUrl').value = '';
            Swal.fire('Added', 'Shopify site added successfully', 'success');
        }
        
        function removeCustomSite(index) {
            customSites.splice(index, 1);
            saveCustomSites();
        }
        
        function uploadSitesFile() {
            document.getElementById('sitesFileInput').click();
        }
        
        function loadProxies() {
            const saved = localStorage.getItem('shopify_proxies');
            if (saved) proxies = JSON.parse(saved);
            renderProxies();
        }
        
        function saveProxies() {
            localStorage.setItem('shopify_proxies', JSON.stringify(proxies));
            renderProxies();
        }
        
        function renderProxies() {
            const container = document.getElementById('proxyListContainer');
            if (!container) return;
            if (proxies.length === 0) {
                container.innerHTML = '<div class="proxy-item"><div class="proxy-string">No proxies added yet</div></div>';
                return;
            }
            let html = '';
            proxies.forEach((proxy, index) => {
                html += `<div class="proxy-item"><div class="proxy-string">${escapeHtml(proxy)}</div><button class="delete-proxy" onclick="removeProxy(${index})"><i class="fas fa-trash"></i></button></div>`;
            });
            container.innerHTML = html;
        }
        
        function addProxy() {
            const proxy = document.getElementById('newProxy').value.trim();
            if (!proxy) return Swal.fire('Error', 'Please enter a proxy', 'error');
            proxies.push(proxy);
            saveProxies();
            document.getElementById('newProxy').value = '';
            Swal.fire('Added', 'Proxy added successfully', 'success');
        }
        
        function removeProxy(index) {
            proxies.splice(index, 1);
            saveProxies();
        }
        
        function uploadProxiesFile() {
            document.getElementById('proxiesFileInput').click();
        }
        
        function testAllProxies() {
            Swal.fire('Info', 'Proxy testing feature coming soon', 'info');
        }
        
        function getRandomProxy() {
            if (proxies.length === 0) return Swal.fire('No Proxies', 'Add some proxies first', 'warning');
            currentProxy = proxies[Math.floor(Math.random() * proxies.length)];
            setProxyMode('custom');
            Swal.fire('Proxy Selected', currentProxy, 'success');
        }
        
        function showBatchChecker() {
            document.getElementById('batchPanel').style.display = 'block';
            setTimeout(() => document.getElementById('batchPanel').scrollIntoView({ behavior: 'smooth' }), 100);
        }
        
        function closeBatchPanel() { document.getElementById('batchPanel').style.display = 'none'; }
        
        function showSiteManager() {
            document.getElementById('sitePanel').style.display = 'block';
            renderCustomSites();
            setTimeout(() => document.getElementById('sitePanel').scrollIntoView({ behavior: 'smooth' }), 100);
        }
        
        function closeSitePanel() { document.getElementById('sitePanel').style.display = 'none'; }
        
        function showProxyManager() {
            document.getElementById('proxyPanel').style.display = 'block';
            renderProxies();
            setTimeout(() => document.getElementById('proxyPanel').scrollIntoView({ behavior: 'smooth' }), 100);
        }
        
        function closeProxyPanel() { document.getElementById('proxyPanel').style.display = 'none'; }
        
        function clearAllResults() {
            if (results.length === 0) return Swal.fire('No results', 'Nothing to clear', 'info');
            Swal.fire({
                title: 'Clear all results?',
                text: `This will clear ${results.length} results`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, clear all'
            }).then((result) => {
                if (result.isConfirmed) {
                    results = [];
                    document.getElementById('resultsList').innerHTML = '';
                    document.getElementById('resultsSection').style.display = 'none';
                    document.getElementById('statsGrid').style.display = 'none';
                    updateStats();
                    Swal.fire('Cleared!', 'All results cleared', 'success');
                }
            });
        }
        
        function updateStats() {
            let approved = 0, declined = 0, threeds = 0;
            results.forEach(r => {
                if (r.status === 'approved') approved++;
                else if (r.status === 'threeds') threeds++;
                else declined++;
            });
            document.getElementById('statApproved').innerText = approved;
            document.getElementById('statDeclined').innerText = declined;
            document.getElementById('stat3DS').innerText = threeds;
            document.getElementById('statTotal').innerText = results.length;
            document.getElementById('resultsCount').innerText = results.length;
            document.getElementById('statsGrid').style.display = 'grid';
            
            document.getElementById('copyAllApprovedBtn').disabled = approved === 0;
            document.getElementById('downloadAllBtn').disabled = results.length === 0;
            document.getElementById('downloadApprovedBtn').disabled = approved === 0;
            document.getElementById('exportJSONBtn').disabled = results.length === 0;
        }
        
        function copyApprovedCards() {
            const approvedCards = results.filter(r => r.status === 'approved').map(r => r.card);
            if (approvedCards.length === 0) return;
            navigator.clipboard.writeText(approvedCards.join('\n'));
            Swal.fire({ toast: true, icon: 'success', title: approvedCards.length + ' cards copied!', timer: 1500 });
        }
        
        function downloadAllResults() {
            if (results.length === 0) return;
            let text = '';
            results.forEach(r => { text += `Card: ${r.card}\nStatus: ${r.status}\nMessage: ${r.message}\nSite: ${r.site}\nTime: ${r.time}\n---\n\n`; });
            const blob = new Blob([text], { type: 'text/plain' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `shopify_results_${new Date().toISOString().slice(0,19)}.txt`;
            a.click();
            URL.revokeObjectURL(a.href);
        }
        
        function downloadApprovedCards() {
            const approvedCards = results.filter(r => r.status === 'approved').map(r => r.card);
            if (approvedCards.length === 0) return;
            const blob = new Blob([approvedCards.join('\n')], { type: 'text/plain' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `shopify_approved_${new Date().toISOString().slice(0,19)}.txt`;
            a.click();
            URL.revokeObjectURL(a.href);
        }
        
        function exportJSON() {
            if (results.length === 0) return;
            const exportData = { timestamp: new Date().toISOString(), gateway: 'shopify', mode: currentMode, total: results.length, results: results };
            const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `shopify_results_${new Date().toISOString().slice(0,19)}.json`;
            a.click();
            URL.revokeObjectURL(a.href);
        }
        
        function copySingleCard(card) {
            navigator.clipboard.writeText(card);
            Swal.fire({ toast: true, icon: 'success', title: 'Card copied!', timer: 1500 });
        }
        
        function addResult(card, status, message, site) {
            const statusClass = status;
            let statusText = status.toUpperCase();
            if (status === 'threeds') statusText = '3DS';
            
            const html = `<div class="result-item ${statusClass}" data-status="${status}">
                <div class="result-header">
                    <div class="result-card"><code>${escapeHtml(card)}</code></div>
                    <div class="result-status ${statusClass}">${statusText}</div>
                </div>
                <div class="result-details">${escapeHtml(message)}</div>
                ${site ? `<div class="result-details"><i class="fas fa-store"></i> Site: ${escapeHtml(site)}</div>` : ''}
                <div class="result-actions">
                    <button class="result-action-btn" onclick="copySingleCard('${escapeHtml(card)}')"><i class="fas fa-copy"></i> Copy Card</button>
                </div>
                <div class="result-time">${new Date().toLocaleTimeString()}</div>
            </div>`;
            document.getElementById('resultsList').insertAdjacentHTML('afterbegin', html);
            results.unshift({ card, status, message, time: new Date().toLocaleTimeString(), site });
            updateStats();
            document.getElementById('resultsSection').style.display = 'block';
            refreshCredits();
        }
        
        function escapeHtml(str) {
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
        
        function refreshCredits() {
            fetch('/api/get_credits.php', { cache: 'no-store' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentCredits = data.credits;
                        document.getElementById('creditAmount').innerText = data.credits_formatted;
                    }
                }).catch(err => console.log('Credit refresh failed:', err));
        }
        
        async function deductCreditsAPI(amount, card) {
            try {
                const response = await fetch('/api/deduct_credits.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ amount: amount, card: card })
                });
                const data = await response.json();
                if (data.success) {
                    currentCredits = data.new_credits;
                    document.getElementById('creditAmount').innerText = data.new_credits_formatted;
                    return true;
                }
                return false;
            } catch(err) {
                return false;
            }
        }
        
        async function processCards(isMass = false) {
            const cardsText = document.getElementById('cardsInput').value.trim();
            if (!cardsText) return Swal.fire('Error', 'Enter cards to check', 'error');
            
            let cards = cardsText.split('\n').filter(l => l.trim().length > 5);
            if (cards.length === 0) return Swal.fire('Error', 'No valid cards', 'error');
            
            if (isMass && cards.length > 100) {
                const confirm = await Swal.fire({
                    title: 'Mass Check Mode',
                    text: `You have ${cards.length} cards. Process up to 100?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes'
                });
                if (!confirm.isConfirmed) return;
                cards = cards.slice(0, 100);
            }
            
            const totalCost = cards.length * creditCost;
            if (!isAdmin && currentCredits < totalCost) {
                return Swal.fire('Insufficient Credits', `Need ${totalCost} credits`, 'error');
            }
            
            isProcessing = true;
            shouldStop = false;
            results = [];
            document.getElementById('resultsList').innerHTML = '';
            document.getElementById('resultsSection').style.display = 'block';
            document.getElementById('progressBar').style.display = 'block';
            document.getElementById('statusText').style.display = 'block';
            document.getElementById('startBtn').disabled = true;
            document.getElementById('stopBtn').disabled = false;
            document.getElementById('startBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            for (let i = 0; i < cards.length && !shouldStop; i++) {
                const card = cards[i];
                const percent = ((i+1)/cards.length)*100;
                document.getElementById('progressFill').style.width = percent+'%';
                document.getElementById('statusText').innerHTML = `Processing card ${i+1} of ${cards.length}...`;
                
                const deducted = await deductCreditsAPI(creditCost, card);
                if (!deducted && !isAdmin) break;
                
                const site = getSite();
                const proxy = getProxy();
                
                try {
                    let fetchUrl = '/gate/shopify.php?cc=' + encodeURIComponent(card) + '&url=' + encodeURIComponent(site);
                    if (proxy) fetchUrl += '&proxy=' + encodeURIComponent(proxy);
                    
                    const response = await fetch(fetchUrl);
                    const responseText = await response.text();
                    
                    let status = 'declined';
                    let message = responseText;
                    
                    if (responseText.includes('APPROVED') || responseText.includes('Order completed')) {
                        status = 'approved';
                        message = 'Order completed';
                    } else if (responseText.includes('3DS') || responseText.includes('3d secure')) {
                        status = 'threeds';
                        message = '3D Secure required';
                    } else if (responseText.includes('DECLINED')) {
                        status = 'declined';
                        const match = responseText.match(/DECLINED:\s*(.+)/);
                        message = match ? match[1] : 'Card declined';
                    }
                    
                    addResult(card, status, message, site);
                    
                } catch(err) {
                    addResult(card, 'declined', 'Request failed: ' + err.message, site);
                }
                await new Promise(r => setTimeout(r, 300));
            }
            
            document.getElementById('progressBar').style.display = 'none';
            document.getElementById('statusText').style.display = 'none';
            document.getElementById('startBtn').disabled = false;
            document.getElementById('stopBtn').disabled = true;
            document.getElementById('startBtn').innerHTML = '<i class="fas fa-play"></i> Start Check';
            isProcessing = false;
            
            const approvedCount = results.filter(r => r.status === 'approved').length;
            Swal.fire('Complete', `Processed ${cards.length} cards\nApproved: ${approvedCount}`, 'success');
            refreshCredits();
        }
        
        document.getElementById('startBtn').addEventListener('click', () => processCards(false));
        document.getElementById('massCheckBtn').addEventListener('click', () => processCards(true));
        document.getElementById('stopBtn').addEventListener('click', () => { shouldStop = true; });
        document.getElementById('clearBtn').addEventListener('click', () => { document.getElementById('cardsInput').value = ''; });
        document.getElementById('copyAllApprovedBtn').addEventListener('click', copyApprovedCards);
        document.getElementById('downloadAllBtn').addEventListener('click', downloadAllResults);
        document.getElementById('downloadApprovedBtn').addEventListener('click', downloadApprovedCards);
        document.getElementById('exportJSONBtn').addEventListener('click', exportJSON);
        document.getElementById('uploadBtn').addEventListener('click', () => document.getElementById('fileInput').click());
        document.getElementById('fileInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                const current = document.getElementById('cardsInput').value;
                document.getElementById('cardsInput').value = current + (current ? '\n' : '') + e.target.result;
                Swal.fire('Loaded', `Cards loaded from ${file.name}`, 'success');
            };
            reader.readAsText(file);
        });
        
        document.getElementById('sitesFileInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                const sites = e.target.result.split('\n').filter(s => s.trim() && s.startsWith('http'));
                sites.forEach(site => { if (!customSites.includes(site.trim())) customSites.push(site.trim()); });
                saveCustomSites();
                Swal.fire('Loaded', `${sites.length} Shopify sites added`, 'success');
            };
            reader.readAsText(file);
        });
        
        document.getElementById('proxiesFileInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                const newProxies = e.target.result.split('\n').filter(p => p.trim());
                newProxies.forEach(proxy => { if (!proxies.includes(proxy.trim())) proxies.push(proxy.trim()); });
                saveProxies();
                Swal.fire('Loaded', `${newProxies.length} proxies added`, 'success');
            };
            reader.readAsText(file);
        });
        
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
        
        setInterval(refreshCredits, 10000);
        
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
    </script>
</body>
</html>