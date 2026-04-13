<?php
require_once '../includes/config.php';
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$user = $_SESSION['user'];
$apiKey = $user['api_key'] ?? '';
$planLimits = [
    'basic' => ['daily' => 100, 'monthly' => 3000, 'api_calls' => 1000],
    'premium' => ['daily' => 500, 'monthly' => 15000, 'api_calls' => 5000],
    'gold' => ['daily' => 1500, 'monthly' => 45000, 'api_calls' => 15000],
    'platinum' => ['daily' => 5000, 'monthly' => 150000, 'api_calls' => 50000],
    'lifetime' => ['daily' => 999999, 'monthly' => 9999999, 'api_calls' => 999999]
];
$limits = $planLimits[$user['plan'] ?? 'basic'];
$gates = loadGates();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation | APPROVED CHECKER</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --bg: #0a0a0f; --card: #111114; --border: #1e1e24; --text: #ffffff; --text-muted: #6b6b76; --primary: #8b5cf6; --success: #10b981; --danger: #ef4444; --warning: #f59e0b; --font-size: 13px; }
        [data-theme="light"] { --bg: #f8fafc; --card: #ffffff; --border: #e2e8f0; --text: #0f172a; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; font-size: var(--font-size); }
        .main { margin-left: 280px; margin-top: 55px; padding: 1.2rem; transition: margin-left 0.2s; }
        @media (max-width: 768px) { .main { margin-left: 0; } }
        .container { max-width: 1200px; margin: 0 auto; }
        .api-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.6rem; padding: 1rem; margin-bottom: 1rem; }
        .api-method { display: inline-block; padding: 0.15rem 0.4rem; border-radius: 0.3rem; font-size: 0.6rem; font-weight: 600; margin-right: 0.5rem; }
        .method-get { background: #10b981; color: white; }
        .method-post { background: #3b82f6; color: white; }
        .endpoint { font-family: monospace; font-size: 0.7rem; color: var(--primary); word-break: break-all; }
        pre { background: var(--bg); padding: 0.8rem; border-radius: 0.4rem; overflow-x: auto; font-size: 0.65rem; margin-top: 0.5rem; border: 1px solid var(--border); white-space: pre-wrap; word-wrap: break-word; }
        code { font-family: monospace; font-size: 0.65rem; }
        .api-key-box { background: var(--bg); padding: 0.4rem; border-radius: 0.4rem; font-family: monospace; word-break: break-all; margin: 0.5rem 0; font-size: 0.7rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .limit-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.6rem; padding: 0.8rem; text-align: center; }
        .limit-value { font-size: 1.2rem; font-weight: 700; color: var(--primary); }
        .limit-label { font-size: 0.55rem; color: var(--text-muted); margin-top: 0.2rem; }
        .gateway-table { width: 100%; border-collapse: collapse; }
        .gateway-table th, .gateway-table td { padding: 0.5rem; text-align: left; border-bottom: 1px solid var(--border); font-size: 0.65rem; }
        .gateway-table th { color: var(--text-muted); font-weight: 600; }
        .copy-btn { background: none; border: none; cursor: pointer; color: var(--primary); font-size: 0.6rem; }
        .upgrade-banner { background: linear-gradient(135deg, rgba(139,92,246,0.2), rgba(6,182,212,0.1)); border: 1px solid var(--primary); border-radius: 0.6rem; padding: 1rem; text-align: center; margin-bottom: 1rem; }
        @media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } .gateway-table { display: block; overflow-x: auto; } }
    </style>
</head>
<body data-theme="dark">
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main">
        <div class="container">
            <!-- Upgrade Banner for Free Users -->
            <?php if (($user['plan'] ?? 'basic') === 'basic'): ?>
            <div class="upgrade-banner">
                <i class="fas fa-rocket" style="font-size: 1.2rem; color: var(--primary);"></i>
                <strong style="margin-left: 0.5rem;">Upgrade to Premium</strong>
                <span style="margin: 0 0.5rem;">•</span>
                <span>Get 5000+ API calls/month</span>
                <span style="margin: 0 0.5rem;">•</span>
                <span>Higher rate limits</span>
                <div style="margin-top: 0.5rem;">
                    <a href="/topup.php" class="btn btn-primary" style="padding: 0.3rem 0.8rem;">View Plans →</a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- API Key Section -->
            <div class="api-card">
                <h3 style="margin-bottom: 0.5rem;"><i class="fas fa-key"></i> Your API Credentials</h3>
                <div class="api-key-box">
                    <code id="apiKey"><?php echo $apiKey; ?></code>
                    <button class="copy-btn" onclick="copyApiKey()"><i class="fas fa-copy"></i> Copy</button>
                </div>
                <p style="font-size: 0.6rem; color: var(--text-muted);">Use this key in your API requests via the <code>api_key</code> parameter or <code>X-API-Key</code> header.</p>
            </div>
            
            <!-- Usage Limits -->
            <div class="grid-2">
                <div class="limit-card"><div class="limit-value"><?php echo $limits['daily']; ?></div><div class="limit-label">Daily API Limit</div></div>
                <div class="limit-card"><div class="limit-value"><?php echo $limits['monthly']; ?></div><div class="limit-label">Monthly API Limit</div></div>
                <div class="limit-card"><div class="limit-value"><?php echo ucfirst($user['plan'] ?? 'Basic'); ?></div><div class="limit-label">Current Plan</div></div>
                <div class="limit-card"><div class="limit-value"><a href="/topup.php" style="color: var(--primary);">Upgrade →</a></div><div class="limit-label">Get Higher Limits</div></div>
            </div>
            
            <!-- Main API Endpoint -->
            <div class="api-card">
                <h3 style="margin-bottom: 0.5rem;"><i class="fas fa-plug"></i> Main API Endpoint</h3>
                <div><span class="api-method method-get">GET</span><span class="api-method method-post">POST</span><span class="endpoint">https://approvedchkr.store/api/v1/check.php</span></div>
                <p style="margin-top: 0.5rem; font-size: 0.7rem;">Check a card using any gateway.</p>
                <pre><code>GET https://approvedchkr.store/api/v1/check.php?api_key=YOUR_KEY&gateway=shopify&cc=4111111111111111|12|2025|123&amount=1.00</code></pre>
                <p style="margin-top: 0.5rem; font-size: 0.6rem;"><strong>Parameters:</strong></p>
                <ul style="margin-left: 1rem; font-size: 0.65rem;">
                    <li><code>api_key</code> - Your API key (required)</li>
                    <li><code>gateway</code> - Gateway name (shopify, stripe_auth, razorpay, etc.)</li>
                    <li><code>cc</code> - Card in format: number|month|year|cvv</li>
                    <li><code>site</code> - Custom site URL (for some gateways)</li>
                    <li><code>amount</code> - Amount to charge (default: 1.00)</li>
                </ul>
            </div>
            
            <!-- Available Gateways -->
            <div class="api-card">
                <h3 style="margin-bottom: 0.5rem;"><i class="fas fa-list"></i> Available Gateways</h3>
                <div style="overflow-x: auto;">
                    <table class="gateway-table">
                        <thead>
                            <tr><th>Gateway Key</th><th>Name</th><th>Cost</th><th>Required Plan</th><th>Example</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gates as $key => $gate): ?>
                            <?php if ($gate['enabled']): ?>
                            <tr>
                                <td><code><?php echo $key; ?></code></td>
                                <td><?php echo htmlspecialchars($gate['label']); ?></td>
                                <td><?php echo $gate['credit_cost']; ?> credits</td>
                                <td><?php echo ucfirst($gate['required_plan'] ?? 'Basic'); ?></td>
                                <td><button class="copy-btn" onclick="copyExample('<?php echo $key; ?>')">Copy URL</button></td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Response Format -->
            <div class="api-card">
                <h3 style="margin-bottom: 0.5rem;"><i class="fas fa-code"></i> Response Format</h3>
                <pre><code>{
    "success": true,
    "data": {
        "gateway": "shopify",
        "status": "APPROVED",
        "message": "Card approved",
        "credits_used": 1,
        "credits_remaining": 99,
        "daily_usage": 45,
        "daily_limit": 500,
        "monthly_usage": 320,
        "monthly_limit": 15000
    },
    "timestamp": 1234567890
}</code></pre>
            </div>
            
            <!-- Error Codes -->
            <div class="api-card">
                <h3 style="margin-bottom: 0.5rem;"><i class="fas fa-exclamation-triangle"></i> Error Codes</h3>
                <ul style="margin-left: 1rem; font-size: 0.65rem;">
                    <li><code>401</code> - Invalid or missing API key</li>
                    <li><code>402</code> - Insufficient credits</li>
                    <li><code>403</code> - Account banned</li>
                    <li><code>404</code> - Gateway not found</li>
                    <li><code>400</code> - Invalid card format</li>
                    <li><code>429</code> - Rate limit exceeded (daily/monthly limit)</li>
                    <li><code>500</code> - Internal server error</li>
                </ul>
            </div>
            
            <!-- cURL Examples -->
            <div class="api-card">
                <h3 style="margin-bottom: 0.5rem;"><i class="fas fa-terminal"></i> cURL Examples</h3>
                <pre><code># Check card with Shopify gateway
curl -X GET "https://approvedchkr.store/api/v1/check.php?api_key=<?php echo $apiKey; ?>&gateway=shopify&cc=4111111111111111|12|2025|123"

# With custom site
curl -X GET "https://approvedchkr.store/api/v1/check.php?api_key=<?php echo $apiKey; ?>&gateway=shopify&cc=4111111111111111|12|2025|123&site=https://example.myshopify.com"

# Using POST with header
curl -X POST "https://approvedchkr.store/api/v1/check.php" \
  -H "X-API-Key: <?php echo $apiKey; ?>" \
  -d "gateway=shopify&cc=4111111111111111|12|2025|123"</code></pre>
            </div>
            
            <!-- Python Example -->
            <div class="api-card">
                <h3 style="margin-bottom: 0.5rem;"><i class="fab fa-python"></i> Python Example</h3>
                <pre><code>import requests

api_key = "<?php echo $apiKey; ?>"
url = "https://approvedchkr.store/api/v1/check.php"

params = {
    "api_key": api_key,
    "gateway": "shopify",
    "cc": "4111111111111111|12|2025|123"
}

response = requests.get(url, params=params)
data = response.json()

if data["success"]:
    print(f"Status: {data['data']['status']}")
    print(f"Credits remaining: {data['data']['credits_remaining']}")
else:
    print(f"Error: {data['error']}")</code></pre>
            </div>
        </div>
    </main>
    
    <script>
        function copyApiKey() {
            const apiKey = document.getElementById('apiKey').innerText;
            navigator.clipboard.writeText(apiKey);
            Swal.fire({ toast: true, icon: 'success', title: 'API Key copied!', showConfirmButton: false, timer: 1500 });
        }
        
        function copyExample(gateway) {
            const example = `https://approvedchkr.store/api/v1/check.php?api_key=<?php echo $apiKey; ?>&gateway=${gateway}&cc=4111111111111111|12|2025|123`;
            navigator.clipboard.writeText(example);
            Swal.fire({ toast: true, icon: 'success', title: 'URL copied!', showConfirmButton: false, timer: 1500 });
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
