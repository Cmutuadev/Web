<?php
require_once '../includes/config.php';
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$username = $_SESSION['user']['name'];
$userPlan = $_SESSION['user']['plan'] ?? 'basic';
$apiKey = $_SESSION['user']['api_key'] ?? '';
$db = getMongoDB();

// Only premium and above can access
$planPriority = ['basic' => 1, 'premium' => 2, 'gold' => 3, 'platinum' => 4, 'lifetime' => 5];
$canAccess = $planPriority[$userPlan] >= 2; // Premium and above

$gates = loadGates();
$apiBaseUrl = "https://approvedchkr.store/api/v1/check.php";

// Get all enabled gates for API display
$availableGates = [];
foreach ($gates as $key => $gate) {
    if ($gate['enabled']) {
        $availableGates[] = [
            'key' => $key,
            'label' => $gate['label'],
            'cost' => $gate['credit_cost'],
            'required_plan' => $gate['required_plan'] ?? 'basic',
            'description' => $gate['description'] ?? '',
            'method' => 'GET'
        ];
    }
}

// Sort gates by required plan (basic first, then premium, etc.)
usort($availableGates, function($a, $b) use ($planPriority) {
    return $planPriority[$a['required_plan']] - $planPriority[$b['required_plan']];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Endpoints | APPROVED CHECKER</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --bg: #0a0a0f; --card: #111114; --border: #1e1e24; --text: #ffffff; --text-muted: #6b6b76; --primary: #8b5cf6; --success: #10b981; --danger: #ef4444; --warning: #f59e0b; --gold: #fbbf24; --platinum: #a855f7; --lifetime: #ec4899; }
        [data-theme="light"] { --bg: #f8fafc; --card: #ffffff; --border: #e2e8f0; --text: #0f172a; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; font-size: 13px; }
        .main { margin-left: 280px; margin-top: 55px; padding: 1.2rem; transition: margin-left 0.2s; }
        @media (max-width: 768px) { .main { margin-left: 0; } }
        .container { max-width: 1200px; margin: 0 auto; }
        
        .page-header { margin-bottom: 1rem; }
        .page-title { font-size: 1.6rem; font-weight: 700; background: linear-gradient(135deg, var(--primary), #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .credit-info { font-size: 0.7rem; color: var(--text-muted); text-align: right; margin-bottom: 1rem; padding: 0.5rem; background: var(--card); border-radius: 0.5rem; }
        
        .upgrade-banner { background: linear-gradient(135deg, rgba(139,92,246,0.15), rgba(6,182,212,0.05)); border: 1px solid var(--primary); border-radius: 0.8rem; padding: 2rem; text-align: center; margin: 2rem auto; max-width: 500px; }
        .upgrade-banner i { font-size: 3rem; color: var(--primary); margin-bottom: 1rem; }
        .upgrade-banner h2 { margin-bottom: 0.5rem; }
        .upgrade-banner .btn { margin-top: 1rem; }
        
        .api-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; margin-bottom: 1rem; overflow: hidden; transition: all 0.2s; }
        .api-card:hover { border-color: var(--primary); }
        .api-header { background: var(--bg); padding: 0.8rem 1rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; }
        .api-title { font-size: 0.9rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
        .api-method { display: inline-block; padding: 0.15rem 0.4rem; border-radius: 0.3rem; font-size: 0.55rem; font-weight: 600; }
        .method-get { background: #10b981; color: white; }
        .api-cost { font-size: 0.6rem; color: var(--primary); background: rgba(139,92,246,0.15); padding: 0.15rem 0.4rem; border-radius: 0.3rem; }
        .api-plan { font-size: 0.55rem; padding: 0.15rem 0.4rem; border-radius: 0.3rem; font-weight: 600; }
        .plan-premium { background: rgba(245,158,11,0.2); color: #f59e0b; }
        .plan-gold { background: rgba(251,191,36,0.2); color: #fbbf24; }
        .plan-platinum { background: rgba(168,85,247,0.2); color: #a855f7; }
        .plan-lifetime { background: rgba(236,72,153,0.2); color: #ec4899; }
        .plan-basic { background: rgba(107,114,128,0.2); color: #9ca3af; }
        
        .api-body { padding: 1rem; }
        .api-endpoint { background: var(--bg); padding: 0.6rem; border-radius: 0.4rem; font-family: monospace; font-size: 0.7rem; word-break: break-all; margin-bottom: 0.5rem; border: 1px solid var(--border); }
        .api-description { font-size: 0.65rem; color: var(--text-muted); margin-bottom: 0.5rem; }
        .btn-copy { background: var(--primary); border: none; padding: 0.3rem 0.8rem; border-radius: 0.3rem; color: white; cursor: pointer; font-size: 0.6rem; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-copy:hover { opacity: 0.8; transform: translateY(-1px); }
        .btn-copy-outline { background: transparent; border: 1px solid var(--primary); color: var(--primary); }
        .btn-copy-outline:hover { background: var(--primary); color: white; }
        
        .api-key-box { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; background: var(--bg); padding: 0.5rem; border-radius: 0.4rem; margin-top: 0.5rem; }
        .api-key-box code { font-size: 0.7rem; word-break: break-all; flex: 1; }
        
        .filter-bar { display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .filter-btn { padding: 0.3rem 0.8rem; border-radius: 0.4rem; font-size: 0.65rem; cursor: pointer; background: var(--bg); border: 1px solid var(--border); color: var(--text-muted); transition: all 0.2s; }
        .filter-btn:hover, .filter-btn.active { background: var(--primary); border-color: var(--primary); color: white; }
        
        .stats-summary { display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .stat-badge { background: var(--card); padding: 0.3rem 0.8rem; border-radius: 0.4rem; font-size: 0.65rem; border: 1px solid var(--border); }
        
        @media (max-width: 768px) { .api-header { flex-direction: column; align-items: flex-start; } }
    </style>
</head>
<body data-theme="dark">
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-code"></i> API Endpoints</h1>
                <p class="page-subtitle">Complete API documentation for all available gateways</p>
            </div>
            
            <?php if (!$canAccess): ?>
            <!-- Upgrade Banner for Basic Users -->
            <div class="upgrade-banner">
                <i class="fas fa-lock"></i>
                <h2>Premium Feature</h2>
                <p>API endpoints are available for Premium users and above.</p>
                <p style="font-size: 0.7rem; margin-top: 0.5rem;">Upgrade to access all API endpoints and integrate with your applications.</p>
                <a href="/topup.php" class="btn btn-primary" style="display: inline-block; margin-top: 1rem;"><i class="fas fa-rocket"></i> Upgrade Now</a>
            </div>
            <?php else: ?>
            
            <!-- Your API Key Section -->
            <div class="credit-info">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                    <span><i class="fas fa-key"></i> Your API Key:</span>
                    <code style="background: var(--bg); padding: 0.3rem 0.6rem; border-radius: 0.3rem;"><?php echo substr($apiKey, 0, 25); ?>...</code>
                    <button class="btn-copy" onclick="copyFullApiKey()"><i class="fas fa-copy"></i> Copy Full Key</button>
                </div>
            </div>
            
            <!-- Filter Bar -->
            <div class="filter-bar">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="premium">Premium</button>
                <button class="filter-btn" data-filter="gold">Gold</button>
                <button class="filter-btn" data-filter="platinum">Platinum</button>
                <button class="filter-btn" data-filter="lifetime">Lifetime</button>
            </div>
            
            <!-- Stats Summary -->
            <div class="stats-summary">
                <div class="stat-badge"><i class="fas fa-plug"></i> Total Endpoints: <?php echo count($availableGates); ?></div>
                <div class="stat-badge"><i class="fas fa-coins"></i> Your Plan: <?php echo ucfirst($userPlan); ?></div>
                <div class="stat-badge"><i class="fas fa-key"></i> API Base URL: <code><?php echo $apiBaseUrl; ?></code></div>
            </div>
            
            <!-- API Endpoints List -->
            <?php foreach ($availableGates as $gate): 
                $planClass = 'plan-' . $gate['required_plan'];
                $canUseGate = $planPriority[$userPlan] >= $planPriority[$gate['required_plan']];
            ?>
            <div class="api-card" data-plan="<?php echo $gate['required_plan']; ?>">
                <div class="api-header">
                    <div class="api-title">
                        <span><?php echo htmlspecialchars($gate['label']); ?></span>
                        <span class="api-method method-get"><?php echo $gate['method']; ?></span>
                        <span class="api-cost"><i class="fas fa-coins"></i> <?php echo $gate['cost']; ?> credits</span>
                        <span class="api-plan <?php echo $planClass; ?>"><?php echo ucfirst($gate['required_plan']); ?></span>
                    </div>
                    <button class="btn-copy" onclick="copyEndpoint('<?php echo htmlspecialchars($gate['key']); ?>')"><i class="fas fa-copy"></i> Copy URL</button>
                </div>
                <div class="api-body">
                    <div class="api-endpoint" id="endpoint-<?php echo $gate['key']; ?>">
                        <?php 
                        $fullUrl = $apiBaseUrl . "?api_key=" . $apiKey . "&gateway=" . $gate['key'] . "&cc={card}";
                        echo htmlspecialchars($fullUrl);
                        ?>
                    </div>
                    <?php if ($gate['description']): ?>
                    <div class="api-description"><?php echo htmlspecialchars($gate['description']); ?></div>
                    <?php endif; ?>
                    <div class="api-description">
                        <strong>Example Request:</strong><br>
                        <code style="font-size: 0.6rem; word-break: break-all;">
                            <?php echo htmlspecialchars($apiBaseUrl . "?api_key=" . substr($apiKey, 0, 10) . "...&gateway=" . $gate['key'] . "&cc=4111111111111111|12|2025|123"); ?>
                        </code>
                    </div>
                    <div class="api-description" style="margin-top: 0.5rem;">
                        <strong>Example Response:</strong>
                        <pre style="background: var(--bg); padding: 0.4rem; border-radius: 0.3rem; font-size: 0.6rem; margin-top: 0.2rem;">{
    "success": true,
    "data": {
        "gateway": "<?php echo $gate['key']; ?>",
        "status": "APPROVED",
        "credits_used": <?php echo $gate['cost']; ?>,
        "credits_remaining": 95
    }
}</pre>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- Documentation Section -->
            <div class="api-card">
                <div class="api-header">
                    <div class="api-title"><i class="fas fa-book"></i> API Documentation</div>
                </div>
                <div class="api-body">
                    <div class="api-description">
                        <strong>Base URL:</strong> <code><?php echo $apiBaseUrl; ?></code>
                    </div>
                    <div class="api-description">
                        <strong>Authentication:</strong> Pass your API key via <code>api_key</code> parameter or <code>X-API-Key</code> header.
                    </div>
                    <div class="api-description">
                        <strong>Parameters:</strong>
                        <ul style="margin-left: 1rem; margin-top: 0.3rem;">
                            <li><code>api_key</code> - Your API key (required)</li>
                            <li><code>gateway</code> - Gateway name (<?php echo implode(', ', array_column($availableGates, 'key')); ?>)</li>
                            <li><code>cc</code> - Card in format: number|month|year|cvv</li>
                            <li><code>amount</code> - Amount to charge (optional, default: 1.00)</li>
                            <li><code>site</code> - Custom site URL (for some gateways)</li>
                        </ul>
                    </div>
                    <div class="api-description">
                        <strong>Rate Limits:</strong>
                        <ul style="margin-left: 1rem; margin-top: 0.3rem;">
                            <li>Premium: 500 requests/day</li>
                            <li>Gold: 1500 requests/day</li>
                            <li>Platinum: 5000 requests/day</li>
                            <li>Lifetime: Unlimited</li>
                        </ul>
                    </div>
                    <div class="api-description">
                        <strong>Error Codes:</strong>
                        <ul style="margin-left: 1rem; margin-top: 0.3rem;">
                            <li><code>401</code> - Invalid API key</li>
                            <li><code>402</code> - Insufficient credits</li>
                            <li><code>403</code> - Plan restriction / Account banned</li>
                            <li><code>404</code> - Gateway not found</li>
                            <li><code>429</code> - Rate limit exceeded</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        const fullApiKey = '<?php echo $apiKey; ?>';
        const userPlan = '<?php echo $userPlan; ?>';
        const planPriority = { basic: 1, premium: 2, gold: 3, platinum: 4, lifetime: 5 };
        
        function copyFullApiKey() {
            navigator.clipboard.writeText(fullApiKey);
            Swal.fire({ toast: true, icon: 'success', title: 'API Key copied!', showConfirmButton: false, timer: 1500 });
        }
        
        function copyEndpoint(gateway) {
            const endpointElement = document.getElementById('endpoint-' + gateway);
            if (endpointElement) {
                let endpoint = endpointElement.innerText;
                // Replace {card} with example
                endpoint = endpoint.replace('{card}', '4111111111111111|12|2025|123');
                navigator.clipboard.writeText(endpoint);
                Swal.fire({ toast: true, icon: 'success', title: 'Endpoint URL copied!', showConfirmButton: false, timer: 1500 });
            }
        }
        
        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const filter = this.dataset.filter;
                document.querySelectorAll('.api-card').forEach(card => {
                    if (filter === 'all') {
                        card.style.display = 'block';
                    } else {
                        const cardPlan = card.dataset.plan;
                        if (cardPlan === filter) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
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
