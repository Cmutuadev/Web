<?php
require_once '../includes/config.php';
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$username = $_SESSION['user']['name'];
$db = getMongoDB();
$userCustomGates = [];
$testResult = null;
$testCard = null;
$testEndpoint = null;

if ($db) {
    // Get all user's custom gates
    $cursor = $db->user_gates->find(['username' => $username]);
    foreach ($cursor as $doc) {
        $userCustomGates[] = [
            'id' => (string)$doc['_id'],
            'label' => $doc['label'] ?? 'Unnamed',
            'api_endpoint' => $doc['api_endpoint'] ?? '',
            'credit_cost' => isset($doc['credit_cost']) ? max(5, (int)$doc['credit_cost']) : 5,
            'enabled' => $doc['enabled'] ?? 1,
            'created_at' => isset($doc['created_at']) ? $doc['created_at']->toDateTime()->format('Y-m-d H:i:s') : date('Y-m-d H:i:s')
        ];
    }
}

// Handle test endpoint (before adding)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_api'])) {
    $testEndpoint = $_POST['test_endpoint'];
    $testCard = $_POST['test_card'] ?? '4111111111111111|12|2025|123';
    $testName = $_POST['test_name'] ?? 'Test API';
    
    // Replace {cc} with test card
    $apiUrl = str_replace('{cc}', urlencode($testCard), $testEndpoint);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Determine if endpoint is working
    $isWorking = $httpCode === 200 && !$curlError;
    
    // Parse response to determine status
    $detectedStatus = 'UNKNOWN';
    $responseUpper = strtoupper($response);
    if (strpos($responseUpper, 'APPROVED') !== false || strpos($responseUpper, 'CHARGED') !== false) {
        $detectedStatus = 'APPROVED';
    } elseif (strpos($responseUpper, 'DECLINED') !== false) {
        $detectedStatus = 'DECLINED';
    } elseif (strpos($responseUpper, 'INSUFFICIENT') !== false) {
        $detectedStatus = 'APPROVED (Insufficient)';
    } elseif (strpos($responseUpper, '3DS') !== false) {
        $detectedStatus = '3DS REQUIRED';
    }
    
    $testResult = [
        'success' => $isWorking,
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $curlError,
        'endpoint' => $apiUrl,
        'card' => $testCard,
        'name' => $testName,
        'raw_endpoint' => $testEndpoint,
        'detected_status' => $detectedStatus
    ];
}

// Handle add custom API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_api'])) {
    $name = trim($_POST['api_name']);
    $endpoint = trim($_POST['api_endpoint']);
    $cost = 5;
    
    if ($name && $endpoint) {
        try {
            $db->user_gates->insertOne([
                'username' => $username,
                'label' => $name,
                'api_endpoint' => $endpoint,
                'credit_cost' => $cost,
                'enabled' => 1,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ]);
            $addSuccess = true;
        } catch (Exception $e) {
            $addError = $e->getMessage();
        }
    } else {
        $addError = "Please fill all fields";
    }
    
    if (isset($addSuccess)) {
        echo '<script>setTimeout(function() { window.location.href = "custom_apis.php"; }, 1500);</script>';
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $db->user_gates->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id), 'username' => $username]);
    header('Location: custom_apis.php');
    exit;
}

// Handle toggle enable/disable
if (isset($_GET['toggle'])) {
    $id = $_GET['toggle'];
    $gate = $db->user_gates->findOne(['_id' => new MongoDB\BSON\ObjectId($id), 'username' => $username]);
    if ($gate) {
        $newStatus = ($gate['enabled'] ?? 1) ? 0 : 1;
        $db->user_gates->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($id)],
            ['$set' => ['enabled' => $newStatus]]
        );
    }
    header('Location: custom_apis.php');
    exit;
}

$credits = getUserCredits();
$isAdmin = isAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom APIs | APPROVED CHECKER</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --bg: #0a0a0f; --card: #111114; --border: #1e1e24; --text: #ffffff; --text-muted: #6b6b76; --primary: #8b5cf6; --success: #10b981; --danger: #ef4444; --warning: #f59e0b; --info: #3b82f6; }
        [data-theme="light"] { --bg: #f8fafc; --card: #ffffff; --border: #e2e8f0; --text: #0f172a; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; font-size: 13px; }
        .main { margin-left: 0; margin-top: 55px; padding: 1.2rem; transition: margin-left 0.2s; }
        .main.sidebar-open { margin-left: 280px; }
        @media (max-width: 768px) { .main.sidebar-open { margin-left: 0; } }
        .container { max-width: 1200px; margin: 0 auto; }
        
        .page-header { margin-bottom: 1rem; }
        .page-title { font-size: 1.6rem; font-weight: 700; background: linear-gradient(135deg, var(--primary), #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .credit-info { font-size: 0.7rem; color: var(--text-muted); text-align: right; margin-bottom: 1rem; }
        
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 1rem; margin-bottom: 1rem; }
        .card-title { font-weight: 600; font-size: 0.9rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; }
        .card-title i { color: var(--primary); }
        
        .form-group { margin-bottom: 0.8rem; }
        .form-group label { display: block; font-size: 0.65rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.3rem; }
        .form-control { width: 100%; padding: 0.5rem; background: var(--bg); border: 1px solid var(--border); border-radius: 0.4rem; color: var(--text); font-size: 0.75rem; }
        .form-control:focus { outline: none; border-color: var(--primary); }
        
        .btn { padding: 0.4rem 0.8rem; border-radius: 0.4rem; font-weight: 500; font-size: 0.65rem; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), #7c3aed); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-secondary { background: var(--bg); border: 1px solid var(--border); color: var(--text); }
        .btn-success { background: var(--success); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.6rem; }
        
        .gate-table { width: 100%; border-collapse: collapse; }
        .gate-table th, .gate-table td { padding: 0.6rem 0.4rem; text-align: left; border-bottom: 1px solid var(--border); font-size: 0.7rem; }
        .gate-table th { color: var(--text-muted); font-weight: 600; }
        .badge-enabled { background: var(--success); color: white; padding: 0.15rem 0.4rem; border-radius: 0.3rem; font-size: 0.55rem; }
        .badge-disabled { background: var(--danger); color: white; padding: 0.15rem 0.4rem; border-radius: 0.3rem; font-size: 0.55rem; }
        
        .test-result { background: var(--bg); border-radius: 0.5rem; padding: 0.8rem; margin-top: 0.8rem; border: 1px solid var(--border); }
        .test-status { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 0.3rem; font-size: 0.6rem; font-weight: 600; }
        .test-status.online { background: rgba(16,185,129,0.2); color: var(--success); }
        .test-status.offline { background: rgba(239,68,68,0.2); color: var(--danger); }
        .test-status.partial { background: rgba(245,158,11,0.2); color: var(--warning); }
        .test-response { 
            background: var(--card); 
            border-radius: 0.4rem; 
            padding: 0.6rem; 
            margin-top: 0.5rem; 
            font-family: monospace; 
            font-size: 0.65rem; 
            word-break: break-word;
            white-space: pre-wrap;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }
        
        .empty-state { text-align: center; padding: 2rem; color: var(--text-muted); }
        .empty-state i { font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5; }
        
        .endpoint-preview { font-size: 0.6rem; color: var(--text-muted); margin-top: 0.2rem; word-break: break-all; }
        .success-message { background: rgba(16,185,129,0.1); border: 1px solid var(--success); color: var(--success); padding: 0.5rem; border-radius: 0.4rem; margin-bottom: 1rem; font-size: 0.7rem; text-align: center; }
        .error-message { background: rgba(239,68,68,0.1); border: 1px solid var(--danger); color: var(--danger); padding: 0.5rem; border-radius: 0.4rem; margin-bottom: 1rem; font-size: 0.7rem; text-align: center; }
    </style>
</head>
<body data-theme="dark">
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main" id="main">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">Custom APIs</h1>
                <p class="page-subtitle">Test, validate, and add your custom API endpoints</p>
            </div>
            <div class="credit-info">
                <i class="fas fa-coins"></i> Credits: <span id="creditAmount"><?php echo $isAdmin ? '∞' : number_format($credits); ?></span>
            </div>
            
            <?php if (isset($addSuccess)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> Custom API added successfully! You can now use it from the sidebar.
            </div>
            <?php endif; ?>
            
            <?php if (isset($addError)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($addError); ?>
            </div>
            <?php endif; ?>
            
            <!-- TEST API SECTION - VISIBLE FIRST -->
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-flask"></i> Test Your API Endpoint
                    <span style="font-size:0.55rem; margin-left:auto; color:var(--text-muted);">Test before adding</span>
                </div>
                <form method="POST" class="grid-2">
                    <input type="hidden" name="test_api" value="1">
                    
                    <div class="form-group">
                        <label>API Name (for reference)</label>
                        <input type="text" name="test_name" class="form-control" placeholder="My Custom API" value="<?php echo htmlspecialchars($testResult['name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>API Endpoint URL</label>
                        <input type="url" name="test_endpoint" class="form-control" placeholder="https://api.example.com/check?cc={cc}" value="<?php echo htmlspecialchars($testResult['raw_endpoint'] ?? ''); ?>" required>
                        <div class="endpoint-preview">
                            <i class="fas fa-info-circle"></i> Use <code>{cc}</code> where the card should be inserted
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Test Card (format: number|month|year|cvv)</label>
                        <input type="text" name="test_card" class="form-control" value="<?php echo htmlspecialchars($testCard ?? '4111111111111111|12|2025|123'); ?>">
                    </div>
                    
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fas fa-vial"></i> Test Endpoint</button>
                    </div>
                </form>
                
                <?php if ($testResult): ?>
                <div class="test-result">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.8rem;">
                        <div>
                            <span class="test-status <?php echo $testResult['success'] ? ($testResult['http_code'] == 200 ? 'online' : 'partial') : 'offline'; ?>">
                                <i class="fas <?php echo $testResult['success'] ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                                <?php 
                                if ($testResult['success'] && $testResult['http_code'] == 200) echo 'ONLINE';
                                elseif ($testResult['success']) echo 'RESPONDING (HTTP ' . $testResult['http_code'] . ')';
                                else echo 'OFFLINE';
                                ?>
                            </span>
                            <span style="margin-left: 0.5rem; font-size:0.6rem;">HTTP <?php echo $testResult['http_code']; ?></span>
                            <?php if ($testResult['detected_status'] != 'UNKNOWN'): ?>
                            <span style="margin-left: 0.5rem; font-size:0.6rem; background:rgba(139,92,246,0.15); padding:0.15rem 0.4rem; border-radius:0.3rem;">
                                <i class="fas fa-chart-line"></i> Detected: <?php echo $testResult['detected_status']; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:0.55rem; color:var(--text-muted);">
                            <i class="fas fa-clock"></i> <?php echo date('H:i:s'); ?>
                        </div>
                    </div>
                    
                    <div style="margin-top: 0.5rem; font-size:0.6rem; background:var(--card); padding:0.4rem; border-radius:0.3rem;">
                        <strong>Request URL:</strong>
                        <code style="word-break:break-all; display:block; margin-top:0.2rem;"><?php echo htmlspecialchars($testResult['endpoint']); ?></code>
                    </div>
                    
                    <div style="margin-top: 0.5rem;">
                        <strong>Response:</strong>
                        <div class="test-response">
                            <?php 
                            if ($testResult['error']) {
                                echo '<span style="color:var(--danger);">Error: ' . htmlspecialchars($testResult['error']) . '</span>';
                            } else {
                                echo nl2br(htmlspecialchars(substr($testResult['response'], 0, 3000)));
                                if (strlen($testResult['response']) > 3000) {
                                    echo '<span style="color:var(--text-muted);">... (truncated)</span>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    
                    <?php if ($testResult['success'] && $testResult['http_code'] == 200): ?>
                    <div style="margin-top: 0.8rem; padding: 0.6rem; background: rgba(16,185,129,0.1); border-radius: 0.4rem; text-align: center;">
                        <i class="fas fa-check-circle" style="color:var(--success);"></i>
                        <strong>API is working!</strong> You can now add it below.
                    </div>
                    <?php elseif ($testResult['success']): ?>
                    <div style="margin-top: 0.8rem; padding: 0.6rem; background: rgba(245,158,11,0.1); border-radius: 0.4rem; text-align: center;">
                        <i class="fas fa-exclamation-triangle" style="color:var(--warning);"></i>
                        <strong>API responded but with HTTP <?php echo $testResult['http_code']; ?></strong> - It may still work for checking.
                    </div>
                    <?php else: ?>
                    <div style="margin-top: 0.8rem; padding: 0.6rem; background: rgba(239,68,68,0.1); border-radius: 0.4rem; text-align: center;">
                        <i class="fas fa-times-circle" style="color:var(--danger);"></i>
                        <strong>API is not responding.</strong> Check your endpoint URL and make sure it's accessible.
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- ADD API SECTION - Visible after testing -->
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-plus-circle"></i> Add Custom API
                    <span style="font-size:0.55rem; margin-left:auto; color:var(--text-muted);">Add after testing</span>
                </div>
                <form method="POST" class="grid-2">
                    <input type="hidden" name="add_api" value="1">
                    
                    <div class="form-group">
                        <label>API Name</label>
                        <input type="text" name="api_name" class="form-control" placeholder="My Custom API" value="<?php echo htmlspecialchars($testResult['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Credit Cost (Fixed at 5)</label>
                        <input type="number" name="api_cost" class="form-control" value="5" min="5" readonly disabled style="background:rgba(0,0,0,0.3); cursor:not-allowed;">
                    </div>
                    
                    <div class="form-group" style="grid-column: span 2;">
                        <label>API Endpoint URL</label>
                        <input type="url" name="api_endpoint" class="form-control" placeholder="https://api.example.com/check?cc={cc}" value="<?php echo htmlspecialchars($testResult['raw_endpoint'] ?? ''); ?>" required>
                        <div class="endpoint-preview">
                            <i class="fas fa-info-circle"></i> Use <code>{cc}</code> where the card should be inserted
                        </div>
                    </div>
                    
                    <div class="form-group" style="grid-column: span 2;">
                        <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fas fa-save"></i> Add Custom API</button>
                    </div>
                </form>
            </div>
            
            <!-- YOUR CUSTOM APIS LIST -->
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-list"></i> Your Custom APIs
                    <span style="font-size:0.55rem; margin-left:auto; color:var(--text-muted);">Click the play button to use</span>
                </div>
                <?php if (empty($userCustomGates)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No custom APIs yet. Test and add one above!</p>
                </div>
                <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="gate-table">
                        <thead>
                            <tr><th>Name</th><th>Endpoint</th><th>Cost</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userCustomGates as $gate): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($gate['label']); ?></strong></td>
                                <td><code style="font-size:0.6rem;"><?php echo htmlspecialchars(substr($gate['api_endpoint'], 0, 45)); ?>...</code></td>
                                <td><?php echo $gate['credit_cost']; ?>c</dev>
                                <td><span class="badge-<?php echo $gate['enabled'] ? 'enabled' : 'disabled'; ?>"><?php echo $gate['enabled'] ? 'Active' : 'Disabled'; ?></span></td>
                                <td>
                                    <button onclick="useGate('<?php echo $gate['id']; ?>')" class="btn btn-secondary btn-sm" title="Use this API"><i class="fas fa-play"></i></button>
                                    <a href="?toggle=<?php echo $gate['id']; ?>" class="btn btn-warning btn-sm" onclick="return confirm('Toggle this API?')" title="Toggle"><i class="fas fa-power-off"></i></a>
                                    <a href="?delete=<?php echo $gate['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this API?')" title="Delete"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- INFO CARD -->
            <div class="card" style="background: rgba(139,92,246,0.05); border-color: var(--primary);">
                <div class="card-title" style="border-bottom-color: rgba(139,92,246,0.2);">
                    <i class="fas fa-lightbulb"></i> How It Works
                </div>
                <div style="font-size: 0.7rem; line-height: 1.5;">
                    <p><i class="fas fa-1"></i> <strong>Step 1:</strong> Enter your API endpoint and test card above</p>
                    <p><i class="fas fa-2"></i> <strong>Step 2:</strong> Click "Test Endpoint" to verify it works</p>
                    <p><i class="fas fa-3"></i> <strong>Step 3:</strong> If working, click "Add Custom API" to save it</p>
                    <p><i class="fas fa-4"></i> <strong>Step 4:</strong> Your API will appear in the sidebar under "CUSTOM APIS"</p>
                    <p><i class="fas fa-5"></i> <strong>Step 5:</strong> Click the play button to start checking cards</p>
                    <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid var(--border);">
                        <i class="fas fa-key"></i> Each check costs <strong>5 credits</strong> (fixed). The API endpoint must accept <code>{cc}</code> as a placeholder for the card.
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        function useGate(gateId) {
            window.location.href = '../index.php?page=universalwindow.location.href = '/index.php?page=universal&gate=custom_' + gateId;gate=custom_' + gateId;
        }
        
        function refreshCredits() {
            fetch('/api/get_credits.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('creditAmount').innerText = data.credits_formatted;
                    }
                })
                .catch(err => console.log('Credit refresh failed:', err));
        }
        
        setInterval(refreshCredits, 30000);
        
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
<script>
function useGate(gateId) {
    window.location.href = '/index.php?page=universal&gate=custom_' + gateId;
}

function refreshCredits() {
    fetch('/api/get_credits.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('creditAmount').innerText = data.credits_formatted;
            }
        })
        .catch(err => console.log('Credit refresh failed:', err));
}

setInterval(refreshCredits, 30000);

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
