<?php
require_once __DIR__ . "/../../includes/config.php";
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$gateParam = $_GET['gate'] ?? '';
$isCustom = false;
$customGateId = null;

if (strpos($gateParam, 'custom_') === 0) {
    $isCustom = true;
    $customGateId = substr($gateParam, 7);
    $db = getMongoDB();
    if ($db) {
        $customGate = $db->user_gates->findOne([
            '_id' => new MongoDB\BSON\ObjectId($customGateId),
            'username' => $_SESSION['user']['name']
        ]);
        if (!$customGate) {
            die("Custom gate not found.");
        }
        $gate = [
            'key' => $gateParam,
            'label' => $customGate['label'],
            'api_endpoint' => $customGate['api_endpoint'],
            'credit_cost' => $customGate['credit_cost'] ?? 5,
            'enabled' => true,
            'required_plan' => 'basic',
            'description' => 'Custom API endpoint'
        ];
    } else {
        die("Database error.");
    }
} else {
    $gates = loadGates();
    if (!isset($gates[$gateParam])) {
        header('Location: index.php');
        exit;
    }
    $gate = $gates[$gateParam];
}

if (!$gate['enabled']) {
    die("Gateway disabled");
}

$userPlan = $_SESSION['user']['plan'] ?? 'basic';
$planPriority = ['basic' => 1, 'premium' => 2, 'gold' => 3, 'platinum' => 4, 'lifetime' => 5];
$requiredPlan = $gate['required_plan'] ?? 'basic';
$isAdmin = isAdmin();
if (!$isAdmin && $planPriority[$userPlan] < $planPriority[$requiredPlan]) {
    die("Your plan does not allow access to this gateway.");
}

$pageTitle = htmlspecialchars($gate['label']);
$creditCost = $gate['credit_cost'] ?? 1;
$credits = getUserCredits();
$apiEndpointTemplate = $gate['api_endpoint'] ?? '';
if (empty($apiEndpointTemplate)) {
    die("API endpoint not configured for this gateway.");
}

// Check if Telegram hits are enabled
$settings = loadSettings();
$telegramHitsEnabled = ($settings['telegram_hits_enabled'] ?? 'false') === 'true';
$telegramGroupId = $settings['telegram_group_id'] ?? '';
$telegramBotToken = $settings['telegram_bot_token'] ?? '';
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
        :root { --bg: #0a0a0f; --card: #111114; --border: #1e1e24; --text: #ffffff; --text-muted: #6b6b76; --primary: #8b5cf6; --success: #10b981; --danger: #ef4444; --warning: #f59e0b; --info: #3b82f6; }
        [data-theme="light"] { --bg: #f8fafc; --card: #ffffff; --border: #e2e8f0; --text: #0f172a; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; font-size: 14px; }
        .main { margin-left: 0; margin-top: 55px; padding: 1.2rem; transition: margin-left 0.2s; }
        .main.sidebar-open { margin-left: 280px; }
        @media (max-width: 768px) { .main.sidebar-open { margin-left: 0; } }
        .container { max-width: 1400px; margin: 0 auto; }
        
        /* Loading Overlay */
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
        .gate-loader.hide {
            opacity: 0;
            pointer-events: none;
        }
        .loader-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .loader-text {
            margin-top: 1rem;
            color: var(--primary);
            font-size: 0.8rem;
            font-weight: 500;
        }
        .loader-progress {
            width: 200px;
            height: 2px;
            background: var(--border);
            border-radius: 2px;
            margin-top: 1rem;
            overflow: hidden;
        }
        .loader-progress-bar {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, var(--primary), #06b6d4);
            transition: width 0.3s;
        }
        
        /* Features Grid */
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
        .bin-info { font-size: 0.5rem; color: var(--text-muted); margin-top: 0.2rem; }
        .progress-bar { margin-top: 1rem; height: 3px; background: var(--border); border-radius: 2px; overflow: hidden; display: none; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary), #06b6d4); width: 0%; transition: width 0.3s; }
        .status-text { font-size: 0.6rem; color: var(--text-muted); margin-top: 0.5rem; text-align: center; display: none; }
        .credit-cost-badge { background: rgba(139,92,246,0.2); padding: 0.2rem 0.5rem; border-radius: 0.5rem; font-size: 0.65rem; }
        .batch-actions { display: flex; gap: 0.5rem; margin-top: 0.8rem; padding-top: 0.8rem; border-top: 1px solid var(--border); flex-wrap: wrap; }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } .features-grid { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body data-theme="dark">
    <!-- Loading Overlay -->
    <div class="gate-loader" id="gateLoader" style="display: none;">
        <div class="loader-spinner"></div>
        <div class="loader-text" id="loaderText">Loading gateway...</div>
        <div class="loader-progress"><div class="loader-progress-bar" id="loaderProgress"></div></div>
    </div>

    <?php include $_SERVER['DOCUMENT_ROOT'] . "/includes/header.php"; ?>
    <?php include $_SERVER['DOCUMENT_ROOT'] . "/includes/sidebar.php"; ?>
    
    <main class="main" id="main">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title"><?php echo $pageTitle; ?></h1>
                <p class="page-subtitle"><?php echo htmlspecialchars($gate['description'] ?? ''); ?></p>
            </div>
            <div class="credit-info">
                <i class="fas fa-coins"></i> Credits: <span id="creditAmount"><?php echo $isAdmin ? '∞' : number_format($credits); ?></span>
                <span class="credit-cost-badge"><i class="fas fa-tachometer-alt"></i> Cost: <?php echo $creditCost; ?> credit(s) per check</span>
                <?php if ($telegramHitsEnabled): ?>
                <span class="credit-cost-badge" style="background: rgba(34,197,94,0.2);"><i class="fab fa-telegram"></i> Hits to TG</span>
                <?php endif; ?>
            </div>
            
            <!-- Quick Features Panel -->
            <div class="features-panel">
                <div class="features-grid">
                    <div class="feature-card" onclick="showBatchChecker()">
                        <div class="feature-icon"><i class="fas fa-layer-group"></i></div>
                        <div class="feature-title">Batch Checker</div>
                        <div class="feature-desc">Multi-thread processing</div>
                    </div>
                    <div class="feature-card" onclick="showBINLookup()">
                        <div class="feature-icon"><i class="fas fa-search"></i></div>
                        <div class="feature-title">BIN Lookup</div>
                        <div class="feature-desc">Get card details</div>
                    </div>
                    <div class="feature-card" onclick="generateCards()">
                        <div class="feature-icon"><i class="fas fa-magic"></i></div>
                        <div class="feature-title">CC Generator</div>
                        <div class="feature-desc">Generate test cards</div>
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
            
            <!-- Batch Checker Panel -->
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
            
            <!-- BIN Lookup Panel -->
            <div class="batch-section" id="binPanel" style="display:none;">
                <div class="batch-header">
                    <div><i class="fas fa-search"></i> <strong>BIN Lookup</strong></div>
                    <div><button class="btn btn-secondary btn-sm" onclick="closeBinPanel()"><i class="fas fa-times"></i> Close</button></div>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" id="binInput" class="form-control" placeholder="Enter BIN (first 6 digits)" style="flex:1;">
                    <button class="btn btn-primary" onclick="lookupBIN()"><i class="fas fa-search"></i> Lookup</button>
                </div>
                <div id="binResult" style="margin-top:0.8rem; display:none;"></div>
            </div>
            
            <!-- CC Generator Panel -->
            <div class="batch-section" id="genPanel" style="display:none;">
                <div class="batch-header">
                    <div><i class="fas fa-magic"></i> <strong>CC Generator</strong></div>
                    <div><button class="btn btn-secondary btn-sm" onclick="closeGenPanel()"><i class="fas fa-times"></i> Close</button></div>
                </div>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <input type="text" id="genBin" placeholder="BIN (6 digits)" style="flex:1; padding:0.4rem; background:var(--bg); border:1px solid var(--border); border-radius:0.4rem; color:var(--text);">
                    <input type="number" id="genCount" value="10" style="width:80px; padding:0.4rem; background:var(--bg); border:1px solid var(--border); border-radius:0.4rem; color:var(--text);">
                    <button class="btn btn-primary" onclick="doGenerateCards()"><i class="fas fa-magic"></i> Generate</button>
                    <button class="btn btn-success" onclick="copyGeneratedCards()"><i class="fas fa-copy"></i> Copy</button>
                </div>
                <textarea id="generatedCards" style="margin-top:0.8rem; min-height:100px;" readonly placeholder="Generated cards will appear here..."></textarea>
            </div>
            
            <!-- Proxy Manager Panel -->
            <div class="batch-section" id="proxyPanel" style="display:none;">
                <div class="batch-header">
                    <div><i class="fas fa-globe"></i> <strong>Proxy Manager</strong></div>
                    <div><button class="btn btn-secondary btn-sm" onclick="closeProxyPanel()"><i class="fas fa-times"></i> Close</button></div>
                </div>
                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <textarea id="proxyList" style="flex:1; min-height:80px;" placeholder="ip:port:user:pass"></textarea>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-primary" onclick="addProxies()"><i class="fas fa-plus"></i> Add Proxies</button>
                    <button class="btn btn-warning" onclick="testProxies()"><i class="fas fa-vial"></i> Test All</button>
                    <button class="btn btn-success" onclick="getRandomProxy()"><i class="fas fa-random"></i> Random Proxy</button>
                </div>
                <div id="proxyStatus" style="margin-top:0.5rem; font-size:0.6rem;"></div>
            </div>
            
            <div class="stats-grid" id="statsGrid" style="display: none;">
                <div class="stat-card"><div class="stat-value" id="statApproved">0</div><div class="stat-label">Approved</div></div>
                <div class="stat-card"><div class="stat-value" id="statDeclined">0</div><div class="stat-label">Declined</div></div>
                <div class="stat-card"><div class="stat-value" id="stat3DS">0</div><div class="stat-label">3DS</div></div>
                <div class="stat-card"><div class="stat-value" id="statInsufficient">0</div><div class="stat-label">Insufficient</div></div>
                <div class="stat-card"><div class="stat-value" id="statTotal">0</div><div class="stat-label">Total</div></div>
            </div>
            
            <div class="checker-card">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <label><i class="fas fa-credit-card"></i> Cards (one per line)</label>
                    <div class="download-buttons">
                        <button class="btn btn-secondary btn-sm" id="uploadBtn"><i class="fas fa-upload"></i> Upload .txt</button>
                        <input type="file" id="fileInput" style="display: none;" accept=".txt">
                    </div>
                </div>
                <textarea id="cardsInput" placeholder="card|month|year|cvv&#10;4532123456789012|12|2025|123"></textarea>
                <div class="action-buttons">
                    <button class="btn btn-primary" id="startBtn"><i class="fas fa-play"></i> Start Check</button>
                    <button class="btn btn-danger" id="stopBtn" disabled><i class="fas fa-stop"></i> Stop</button>
                    <button class="btn btn-secondary" id="clearBtn"><i class="fas fa-trash"></i> Clear</button>
                    <button class="btn btn-warning" id="massCheckBtn"><i class="fas fa-rocket"></i> Mass Check</button>
                </div>
                <div class="progress-bar" id="progressBar"><div class="progress-fill" id="progressFill"></div></div>
                <div class="status-text" id="statusText">Ready...</div>
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
        
        // ============ LOADING ANIMATION ============
        function showLoader(text, duration = null) {
            const loader = document.getElementById('gateLoader');
            const loaderText = document.getElementById('loaderText');
            const loaderProgress = document.getElementById('loaderProgress');
            loader.style.display = 'flex';
            loader.classList.remove('hide');
            loaderText.textContent = text || 'Loading gateway...';
            loaderProgress.style.width = '0%';
            
            if (duration) {
                let progress = 0;
                const interval = setInterval(() => {
                    progress += 10;
                    loaderProgress.style.width = progress + '%';
                    if (progress >= 100) clearInterval(interval);
                }, duration / 10);
                setTimeout(() => hideLoader(), duration);
            }
        }
        
        function hideLoader() {
            const loader = document.getElementById('gateLoader');
            loader.classList.add('hide');
            setTimeout(() => {
                loader.style.display = 'none';
                loader.classList.remove('hide');
            }, 300);
        }
        
        // Show loader when page loads
        document.addEventListener('DOMContentLoaded', function() {
            showLoader('Initializing gateway...', 800);
        });
        
        // ============ FEATURE FUNCTIONS ============
        
        function showBatchChecker() {
            document.getElementById('batchPanel').style.display = 'block';
            document.getElementById('binPanel').style.display = 'none';
            document.getElementById('genPanel').style.display = 'none';
            document.getElementById('proxyPanel').style.display = 'none';
            document.getElementById('batchStatus').innerHTML = 'Ready for mass check';
            setTimeout(() => {
                document.getElementById('batchPanel').scrollIntoView({ behavior: 'smooth' });
            }, 100);
        }
        
        function closeBatchPanel() { document.getElementById('batchPanel').style.display = 'none'; }
        
        function showBINLookup() {
            document.getElementById('binPanel').style.display = 'block';
            document.getElementById('batchPanel').style.display = 'none';
            document.getElementById('genPanel').style.display = 'none';
            document.getElementById('proxyPanel').style.display = 'none';
            setTimeout(() => {
                document.getElementById('binPanel').scrollIntoView({ behavior: 'smooth' });
            }, 100);
        }
        
        function closeBinPanel() {
            document.getElementById('binPanel').style.display = 'none';
            document.getElementById('binResult').style.display = 'none';
        }
        
        function generateCards() {
            document.getElementById('genPanel').style.display = 'block';
            document.getElementById('batchPanel').style.display = 'none';
            document.getElementById('binPanel').style.display = 'none';
            document.getElementById('proxyPanel').style.display = 'none';
            setTimeout(() => {
                document.getElementById('genPanel').scrollIntoView({ behavior: 'smooth' });
            }, 100);
        }
        
        function closeGenPanel() { document.getElementById('genPanel').style.display = 'none'; }
        
        function showProxyManager() {
            document.getElementById('proxyPanel').style.display = 'block';
            document.getElementById('batchPanel').style.display = 'none';
            document.getElementById('binPanel').style.display = 'none';
            document.getElementById('genPanel').style.display = 'none';
            loadProxies();
            setTimeout(() => {
                document.getElementById('proxyPanel').scrollIntoView({ behavior: 'smooth' });
            }, 100);
        }
        
        function closeProxyPanel() { document.getElementById('proxyPanel').style.display = 'none'; }
        
        function loadProxies() {
            fetch('/api/get_proxies.php')
                .then(res => res.json())
                .then(data => {
                    if (data.proxies) {
                        document.getElementById('proxyList').value = data.proxies.join('\n');
                        document.getElementById('proxyStatus').innerHTML = `<span style="color:var(--success);">✅ ${data.proxies.length} proxies loaded</span>`;
                    }
                })
                .catch(err => console.log('Failed to load proxies'));
        }
        
        function addProxies() {
            const proxies = document.getElementById('proxyList').value;
            fetch('/api/add_proxies.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ proxies: proxies })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ toast: true, icon: 'success', title: `${data.added} proxies added!`, timer: 2000, showConfirmButton: false });
                    loadProxies();
                }
            });
        }
        
        function testProxies() {
            Swal.fire({ title: 'Testing proxies...', text: 'Please wait', allowOutsideClick: false });
            fetch('/api/test_proxies.php')
                .then(res => res.json())
                .then(data => {
                    Swal.fire({ title: 'Test Complete', html: `Working: ${data.working}<br>Failed: ${data.failed}`, icon: 'info' });
                    loadProxies();
                });
        }
        
        function getRandomProxy() {
            fetch('/api/random_proxy.php')
                .then(res => res.json())
                .then(data => {
                    if (data.proxy) {
                        Swal.fire({ title: 'Random Proxy', text: data.proxy, icon: 'success', toast: true, timer: 3000 });
                    } else {
                        Swal.fire({ title: 'No proxies', text: 'Add some proxies first', icon: 'warning' });
                    }
                });
        }
        
        function clearAllResults() {
            if (results.length > 0) {
                Swal.fire({
                    title: 'Clear all results?',
                    text: `This will clear ${results.length} results`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'Yes, clear all',
                    background: 'var(--card)',
                    color: 'var(--text)'
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
            } else {
                Swal.fire('No results', 'Nothing to clear', 'info');
            }
        }
        
        async function lookupBIN() {
            const bin = document.getElementById('binInput').value.trim();
            if (!bin || bin.length < 6) {
                Swal.fire('Error', 'Enter at least 6 digits for BIN', 'error');
                return;
            }
            const binCode = bin.substring(0,6);
            document.getElementById('binResult').style.display = 'block';
            document.getElementById('binResult').innerHTML = '<div style="text-align:center;"><i class="fas fa-spinner fa-spin"></i> Looking up BIN...</div>';
            
            try {
                const response = await fetch(`/api/bin-lookup.php?bin=${binCode}`);
                const data = await response.json();
                if (data && data.bank) {
                    document.getElementById('binResult').innerHTML = `
                        <div style="background:var(--bg); border-radius:0.5rem; padding:0.6rem;">
                            <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:0.3rem; font-size:0.65rem;">
                                <div><strong>Bank:</strong></div><div>${data.bank || 'Unknown'}</div>
                                <div><strong>Brand:</strong></div><div>${data.brand || 'Unknown'}</div>
                                <div><strong>Country:</strong></div><div>${data.country_name || data.country || 'Unknown'}</div>
                                <div><strong>Type:</strong></div><div>${data.type || 'Unknown'}</div>
                                <div><strong>Level:</strong></div><div>${data.level || 'Standard'}</div>
                                <div><strong>BIN:</strong></div><div><code>${binCode}</code></div>
                            </div>
                        </div>
                    `;
                } else {
                    document.getElementById('binResult').innerHTML = '<div style="color:var(--danger);">No information found for this BIN</div>';
                }
            } catch(e) {
                document.getElementById('binResult').innerHTML = '<div style="color:var(--danger);">Failed to lookup BIN</div>';
            }
        }
        
        function luhnCheck(cardNumber) {
            let sum = 0;
            let alternate = false;
            for (let i = cardNumber.length - 1; i >= 0; i--) {
                let n = parseInt(cardNumber.charAt(i), 10);
                if (alternate) {
                    n *= 2;
                    if (n > 9) n = (n % 10) + 1;
                }
                sum += n;
                alternate = !alternate;
            }
            return (sum % 10 === 0);
        }
        
        function generateCardNumber(bin, length = 16) {
            let cardNumber = bin;
            while (cardNumber.length < length - 1) {
                cardNumber += Math.floor(Math.random() * 10);
            }
            for (let i = 0; i <= 9; i++) {
                let testNumber = cardNumber + i;
                if (luhnCheck(testNumber)) {
                    return testNumber;
                }
            }
            return cardNumber + '0';
        }
        
        function doGenerateCards() {
            let bin = document.getElementById('genBin').value.trim();
            let count = parseInt(document.getElementById('genCount').value) || 10;
            
            let cards = [];
            if (!bin) {
                for (let i = 0; i < count; i++) {
                    let randomBin = Math.floor(Math.random() * 1000000).toString().padStart(6, '0');
                    let cardNum = generateCardNumber(randomBin);
                    let month = Math.floor(Math.random() * 12) + 1;
                    let year = new Date().getFullYear() + Math.floor(Math.random() * 5);
                    let cvv = Math.floor(Math.random() * 900) + 100;
                    cards.push(`${cardNum}|${month.toString().padStart(2,'0')}|${year}|${cvv}`);
                }
            } else {
                bin = bin.substring(0,6);
                for (let i = 0; i < count; i++) {
                    let cardNum = generateCardNumber(bin);
                    let month = Math.floor(Math.random() * 12) + 1;
                    let year = new Date().getFullYear() + Math.floor(Math.random() * 5);
                    let cvv = Math.floor(Math.random() * 900) + 100;
                    cards.push(`${cardNum}|${month.toString().padStart(2,'0')}|${year}|${cvv}`);
                }
            }
            document.getElementById('generatedCards').value = cards.join('\n');
            Swal.fire('Generated!', `${count} cards generated`, 'success');
        }
        
        function copyGeneratedCards() {
            const cards = document.getElementById('generatedCards').value;
            if (cards) {
                navigator.clipboard.writeText(cards);
                Swal.fire({ toast: true, icon: 'success', title: 'Cards copied!', showConfirmButton: false, timer: 1500 });
            }
        }
        
        // ============ CORE CHECKER FUNCTIONS ============
        
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
            
            const hasApproved = approved > 0;
            const hasResults = results.length > 0;
            document.getElementById('copyAllApprovedBtn').disabled = !hasApproved;
            document.getElementById('downloadAllBtn').disabled = !hasResults;
            document.getElementById('downloadApprovedBtn').disabled = !hasApproved;
            document.getElementById('exportJSONBtn').disabled = !hasResults;
        }
        
        function copyToClipboard(text, successMsg) {
            navigator.clipboard.writeText(text).then(() => {
                Swal.fire({ toast: true, icon: 'success', title: successMsg || 'Copied!', showConfirmButton: false, timer: 1500 });
            });
        }
        
        function copyApprovedCards() {
            const approvedCards = results.filter(r => r.status === 'approved').map(r => r.card);
            if (approvedCards.length === 0) return;
            copyToClipboard(approvedCards.join('\n'), approvedCards.length + ' approved cards copied!');
        }
        
        function downloadAllResults() {
            if (results.length === 0) return;
            let text = '';
            results.forEach(r => {
                text += `Card: ${r.card}\nStatus: ${r.status.toUpperCase()}\nMessage: ${r.message}\nTime: ${r.time}\n---\n\n`;
            });
            const blob = new Blob([text], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `checker_results_${new Date().toISOString().slice(0,19)}.txt`;
            a.click();
            URL.revokeObjectURL(url);
            Swal.fire({ toast: true, icon: 'success', title: 'Downloaded!', showConfirmButton: false, timer: 1500 });
        }
        
        function downloadApprovedCards() {
            const approvedCards = results.filter(r => r.status === 'approved').map(r => r.card);
            if (approvedCards.length === 0) return;
            const blob = new Blob([approvedCards.join('\n')], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `approved_cards_${new Date().toISOString().slice(0,19)}.txt`;
            a.click();
            URL.revokeObjectURL(url);
            Swal.fire({ toast: true, icon: 'success', title: `${approvedCards.length} approved cards downloaded!`, showConfirmButton: false, timer: 2000 });
        }
        
        function exportJSON() {
            if (results.length === 0) return;
            const exportData = {
                timestamp: new Date().toISOString(),
                gateway: '<?php echo $gateParam; ?>',
                total: results.length,
                approved: results.filter(r => r.status === 'approved').length,
                declined: results.filter(r => r.status === 'declined').length,
                threeds: results.filter(r => r.status === 'threeds').length,
                results: results
            };
            const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `checker_results_${new Date().toISOString().slice(0,19)}.json`;
            a.click();
            URL.revokeObjectURL(url);
            Swal.fire({ toast: true, icon: 'success', title: 'JSON Exported!', showConfirmButton: false, timer: 1500 });
        }
        
        function copySingleCard(card) {
            copyToClipboard(card, 'Card copied!');
        }
        
        async function getBinInfo(cardNumber) {
            try {
                const bin = cardNumber.substring(0,6);
                const resp = await fetch(`/api/bin-lookup.php?bin=${bin}`);
                return await resp.json();
            } catch(e) { return null; }
        }
        
        // Send hit notification to Telegram
        async function sendTelegramHit(card, status, message) {
            try {
                await fetch('/api/telegram_hit.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        card: card,
                        gateway: '<?php echo $gateParam; ?>',
                        status: status,
                        message: message,
                        user: '<?php echo addslashes($_SESSION['user']['name']); ?>'
                    })
                });
            } catch(e) { console.log('Telegram hit failed:', e); }
        }
        
        function addResult(card, status, message, binInfo) {
            const statusClass = status;
            let statusText = status.toUpperCase();
            if (status === 'threeds') statusText = '3DS';
            
            let binHtml = '';
            if (binInfo && binInfo.bank) {
                binHtml = `<div class="bin-info">Bank: ${binInfo.bank || 'Unknown'} | Brand: ${binInfo.brand || 'Unknown'} | Country: ${binInfo.country || 'XX'}</div>`;
            }
            
            const escapedCard = card.replace(/"/g, '&quot;');
            const html = `<div class="result-item ${statusClass}" data-status="${status}" data-card="${escapedCard}">
                <div class="result-header">
                    <div class="result-card"><code>${escapeHtml(card)}</code></div>
                    <div class="result-status ${statusClass}">${statusText}</div>
                </div>
                <div class="result-details">${escapeHtml(message)}</div>
                ${binHtml}
                <div class="result-actions">
                    <button class="result-action-btn" onclick="copySingleCard('${escapedCard}')"><i class="fas fa-copy"></i> Copy Card</button>
                </div>
                <div class="result-time">${new Date().toLocaleTimeString()}</div>
            </div>`;
            document.getElementById('resultsList').insertAdjacentHTML('afterbegin', html);
            results.unshift({card, status, message, time: new Date().toLocaleTimeString()});
            updateStats();
            document.getElementById('resultsSection').style.display = 'block';
            
            // Send Telegram notification for approved hits
            if (status === 'approved' || status === 'charged') {
                sendTelegramHit(card, status, message);
            }
            
            refreshCredits();
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
        
        function classifyResponse(responseText) {
            const lower = responseText.toLowerCase();
            if (lower.includes('insufficient') || lower.includes('funds') || lower.includes('limit')) return 'approved';
            if (lower.includes('3d secure') || lower.includes('authentication') || lower.includes('3ds')) return 'threeds';
            return 'declined';
        }
        
        function refreshCredits() {
            fetch('/api/get_credits.php', { cache: 'no-store' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentCredits = data.credits;
                        document.getElementById('creditAmount').innerText = data.credits_formatted;
                        const navbarCredits = document.querySelector('.user-credits');
                        if (navbarCredits) navbarCredits.innerHTML = '<i class="fas fa-coins"></i> ' + data.credits_formatted + ' credits';
                    }
                })
                .catch(err => console.log('Credit refresh failed:', err));
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
                console.error('API error:', err);
                return false;
            }
        }
        
        async function processCards(isMass = false) {
            const cardsText = document.getElementById('cardsInput').value.trim();
            if (!cardsText) {
                Swal.fire('Error', 'Enter cards to check', 'error');
                return;
            }
            let cards = cardsText.split('\n').filter(l => l.trim().length > 5);
            if (cards.length === 0) {
                Swal.fire('Error', 'No valid cards', 'error');
                return;
            }
            
            if (isMass && cards.length > 100) {
                const confirm = await Swal.fire({
                    title: 'Mass Check Mode',
                    text: `You have ${cards.length} cards. Mass check will process up to 100 cards. Continue?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, process 100',
                    background: 'var(--card)',
                    color: 'var(--text)'
                });
                if (!confirm.isConfirmed) return;
                cards = cards.slice(0, 100);
            }
            
            const totalCost = cards.length * creditCost;
            if (!isAdmin && currentCredits < totalCost) {
                Swal.fire('Insufficient Credits', `Need ${totalCost} credits, you have ${currentCredits}`, 'error');
                return;
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
            
            if (isMass) {
                document.getElementById('batchPanel').style.display = 'block';
                document.getElementById('batchApproved').innerText = '0';
                document.getElementById('batchDeclined').innerText = '0';
                document.getElementById('batchProcessed').innerText = '0';
                document.getElementById('batchProgressFill').style.width = '0%';
                document.getElementById('batchStatus').innerHTML = 'Processing...';
            }
            
            for (let i = 0; i < cards.length && !shouldStop; i++) {
                const card = cards[i];
                const percent = ((i+1)/cards.length)*100;
                document.getElementById('progressFill').style.width = percent+'%';
                document.getElementById('statusText').innerHTML = `Processing card ${i+1} of ${cards.length}...`;
                
                if (isMass) {
                    document.getElementById('batchProgressFill').style.width = percent+'%';
                }
                
                const deducted = await deductCreditsAPI(creditCost, card);
                if (!deducted && !isAdmin) {
                    Swal.fire('Error', 'Failed to deduct credits', 'error');
                    break;
                }
                
                let apiUrl = <?php echo json_encode($apiEndpointTemplate); ?>.replace('{cc}', encodeURIComponent(card));
                const proxyUrl = '/api/proxy.php?url=' + encodeURIComponent(apiUrl);
                
                try {
                    const response = await fetch(proxyUrl);
                    const data = await response.json();
                    let status = 'declined';
                    let message = data.response || data.message || 'No response';
                    
                    if (data.status === 'approved') {
                        status = 'approved';
                    } else if (data.status === 'auth_required' || message.toLowerCase().includes('3d')) {
                        status = 'threeds';
                    } else {
                        status = classifyResponse(message);
                    }
                    
                    const binInfo = await getBinInfo(card.split('|')[0]);
                    addResult(card, status, message, binInfo);
                    
                    if (isMass) {
                        const approved = results.filter(r => r.status === 'approved').length;
                        const declined = results.filter(r => r.status !== 'approved').length;
                        document.getElementById('batchApproved').innerText = approved;
                        document.getElementById('batchDeclined').innerText = declined;
                        document.getElementById('batchProcessed').innerText = results.length;
                    }
                    
                    fetch('/api/update-stats.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ card: card, status: status, reason: message, gateway: '<?php echo $gateParam; ?>' })
                    }).catch(e => console.log('Stats update failed'));
                    
                } catch(err) {
                    addResult(card, 'declined', 'Request failed: ' + err.message, null);
                    if (isMass) document.getElementById('batchProcessed').innerText = results.length;
                }
                await new Promise(r => setTimeout(r, 200));
            }
            
            document.getElementById('progressBar').style.display = 'none';
            document.getElementById('statusText').style.display = 'none';
            document.getElementById('startBtn').disabled = false;
            document.getElementById('stopBtn').disabled = true;
            document.getElementById('startBtn').innerHTML = '<i class="fas fa-play"></i> Start Check';
            isProcessing = false;
            
            if (isMass) {
                document.getElementById('batchStatus').innerHTML = 'Complete!';
                setTimeout(() => document.getElementById('batchPanel').style.display = 'none', 3000);
            }
            
            const approvedCount = results.filter(r => r.status === 'approved').length;
            Swal.fire('Complete', `Processed ${cards.length} cards\nApproved: ${approvedCount}`, 'success');
            refreshCredits();
        }
        
        // ============ EVENT LISTENERS ============
        
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
        
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const filter = this.dataset.filter;
                document.querySelectorAll('.result-item').forEach(item => {
                    if (filter === 'all') item.style.display = 'flex';
                    else item.style.display = item.dataset.status === filter ? 'flex' : 'none';
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
