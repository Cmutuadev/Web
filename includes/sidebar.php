<?php
$currentPage = $_SERVER['REQUEST_URI'];
$currentPage = strtok($currentPage, '?');
$isAdmin = isAdmin();
$username = $_SESSION['user']['name'];
$userPlan = $_SESSION['user']['plan'] ?? 'basic';

$db = getMongoDB();

// Load user's custom gates
$userCustomGates = [];
if ($db) {
    $cursor = $db->user_gates->find(['username' => $username, 'enabled' => 1]);
    foreach ($cursor as $doc) {
        $userCustomGates[] = [
            'id' => (string)$doc['_id'],
            'label' => $doc['label'],
            'credit_cost' => $doc['credit_cost'] ?? 5
        ];
    }
}

// User avatar (same as header)
$userName = $_SESSION['user']['display_name'] ?? $_SESSION['user']['name'];
$initials = '';
$words = explode(' ', trim($userName));
foreach ($words as $word) {
    if (!empty($word)) {
        $initials .= strtoupper(substr($word, 0, 1));
        if (strlen($initials) >= 2) break;
    }
}
if (empty($initials)) $initials = 'U';
$userAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($initials) . '&background=8b5cf6&color=fff&size=64';
?>
<!-- Sidebar Loading Overlay -->
<div id="sidebarLoader" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(10,10,15,0.98); z-index:10000; align-items:center; justify-content:center; flex-direction:column; backdrop-filter:blur(10px);">
    <div class="loader-container" style="text-align:center;">
        <div class="loader-ring" style="width:80px; height:80px; margin:0 auto; position:relative;">
            <div style="position:absolute; top:0; left:0; width:100%; height:100%; border:3px solid rgba(139,92,246,0.1); border-radius:50%;"></div>
            <div style="position:absolute; top:0; left:0; width:100%; height:100%; border:3px solid transparent; border-top-color:var(--primary); border-radius:50%; animation: spin 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;"></div>
            <div style="position:absolute; top:10px; left:10px; width:60px; height:60px; border:2px solid rgba(6,182,212,0.2); border-radius:50%;"></div>
            <div style="position:absolute; top:10px; left:10px; width:60px; height:60px; border:2px solid transparent; border-bottom-color:#06b6d4; border-radius:50%; animation: spin 0.5s reverse infinite;"></div>
        </div>
        <div class="loader-text" style="margin-top:2rem; color:var(--primary); font-size:1rem; font-weight:600; letter-spacing:1px;" id="sidebarLoaderText">Loading gateway...</div>
        <div class="loader-dots" style="margin-top:0.8rem; display:flex; gap:0.5rem; justify-content:center;">
            <span style="width:8px; height:8px; background:var(--primary); border-radius:50%; animation: bounce 1.4s infinite ease-in-out both; animation-delay: -0.32s;"></span>
            <span style="width:8px; height:8px; background:var(--primary); border-radius:50%; animation: bounce 1.4s infinite ease-in-out both; animation-delay: -0.16s;"></span>
            <span style="width:8px; height:8px; background:var(--primary); border-radius:50%; animation: bounce 1.4s infinite ease-in-out both;"></span>
        </div>
        <div style="width:280px; height:2px; background:rgba(255,255,255,0.08); margin-top:1.5rem; border-radius:2px; overflow:hidden;">
            <div style="width:0%; height:100%; background:linear-gradient(90deg, var(--primary), #06b6d4, var(--primary)); background-size:200% 100%; animation: shimmer 1.5s infinite;" id="sidebarLoaderProgress"></div>
        </div>
    </div>
</div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-user">
        <img src="<?php echo $userAvatar; ?>" alt="Avatar" class="sidebar-user-avatar">
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?php echo htmlspecialchars($userName); ?></div>
            <div class="sidebar-user-plan"><?php echo ucfirst($userPlan); ?> Plan</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="/index.php?page=home" class="nav-item sidebar-link-loader <?php echo strpos($currentPage, '?page=home') !== false || $currentPage === '/index.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i><span>Dashboard</span>
        </a>

        <div class="nav-divider">PAYMENT</div>
        <a href="/topup.php" class="nav-item sidebar-link-loader"><i class="fas fa-wallet"></i><span>Top Up Credits</span></a>

        <div class="nav-divider">AUTO CHECKERS</div>
        <div class="nav-group">
            <div class="nav-group-header" onclick="toggleGroup('auto-checkers')">
                <div class="nav-group-title"><i class="fas fa-bolt"></i><span>Auto Checkers</span></div>
                <i class="fas fa-chevron-down nav-group-arrow" id="arrow-auto-checkers"></i>
            </div>
            <div class="nav-group-content" id="group-auto-checkers">
                <a href="/index.php?page=shopify" class="nav-subitem sidebar-link-loader"><i class="fab fa-shopify"></i> Shopify</a>
                <a href="/index.php?page=stripe-auth" class="nav-subitem sidebar-link-loader"><i class="fab fa-stripe"></i> Stripe Auth</a>
                <a href="/index.php?page=razorpay" class="nav-subitem sidebar-link-loader"><i class="fas fa-rupee-sign"></i> Razorpay</a>
            </div>
        </div>

        <div class="nav-divider">CUSTOM APIS</div>
        <div class="nav-group">
            <div class="nav-group-header" onclick="toggleGroup('custom-apis')">
                <div class="nav-group-title"><i class="fas fa-plus-circle"></i><span>My Custom APIs</span></div>
                <i class="fas fa-chevron-down nav-group-arrow" id="arrow-custom-apis"></i>
            </div>
            <div class="nav-group-content" id="group-custom-apis">
                <?php if (!empty($userCustomGates)): ?>
                    <?php foreach ($userCustomGates as $gate): ?>
                    <a href="/index.php?page=universal&gate=custom_<?php echo $gate['id']; ?>" class="nav-subitem sidebar-link-loader">
                        <i class="fas fa-star"></i>
                        <span><?php echo htmlspecialchars($gate['label']); ?></span>
                        <?php if ($isAdmin): ?>
                        <span class="nav-badge"><?php echo $gate['credit_cost']; ?>c</span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="nav-subitem" style="color: var(--text-muted);">
                        <i class="fas fa-info-circle"></i> <span>No custom APIs yet</span>
                    </div>
                <?php endif; ?>
                <a href="/pages/custom_apis.php" class="nav-subitem sidebar-link-loader" style="color: var(--primary);">
                    <i class="fas fa-plus-circle"></i> <span>Manage Custom APIs</span>
                </a>
            </div>
        </div>

        <div class="nav-divider">PLATFORM</div>
        <a href="/pages/api_docs.php" class="nav-item sidebar-link-loader"><i class="fas fa-book"></i><span>API Docs</span></a>
        <a href="/pages/api_endpoints.php" class="nav-item sidebar-link-loader"><i class="fas fa-code"></i><span>API Endpoints</span></a>
        <a href="/pages/activity.php" class="nav-item sidebar-link-loader"><i class="fas fa-history"></i><span>Activity Log</span></a>
        
        <?php if ($isAdmin): ?>
        <a href="/adminaccess_panel.php" class="nav-item admin-item sidebar-link-loader"><i class="fas fa-crown"></i><span>Admin Panel</span></a>
        <?php endif; ?>
        
        <a href="/?logout=1" class="nav-item logout-item sidebar-link-loader"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </nav>
</div>

<style>
.sidebar { 
    position: fixed; 
    left: 0; 
    top: 60px; 
    bottom: 0; 
    width: 280px; 
    background: var(--card); 
    border-right: 1px solid var(--border); 
    transform: translateX(-100%); 
    transition: transform 0.3s; 
    z-index: 99; 
    overflow-y: auto; 
}
.sidebar.open { transform: translateX(0); }
.sidebar-user { 
    padding: 1.25rem; 
    border-bottom: 1px solid var(--border); 
    display: flex; 
    align-items: center; 
    gap: 0.75rem; 
}
.sidebar-user-avatar { 
    width: 48px; 
    height: 48px; 
    border-radius: 50%; 
    object-fit: cover; 
    border: 2px solid var(--primary); 
}
.sidebar-user-info { flex: 1; }
.sidebar-user-name { font-weight: 600; font-size: 0.9rem; margin-bottom: 0.2rem; }
.sidebar-user-plan { font-size: 0.65rem; color: var(--primary); background: rgba(139,92,246,0.15); display: inline-block; padding: 0.15rem 0.5rem; border-radius: 20px; }
.sidebar-nav { flex: 1; padding: 1rem 0.75rem; }
.nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0.85rem; border-radius: 0.5rem; color: var(--text-muted); text-decoration: none; transition: all 0.2s; margin-bottom: 0.2rem; }
.nav-item:hover { background: rgba(139,92,246,0.1); color: var(--primary); }
.nav-item.active { background: linear-gradient(135deg, var(--primary), #7c3aed); color: white; }
.nav-item i { width: 20px; font-size: 1rem; }
.nav-item span { font-size: 0.85rem; font-weight: 500; }
.nav-badge { margin-left: auto; font-size: 0.6rem; padding: 0.15rem 0.4rem; border-radius: 20px; background: linear-gradient(135deg, var(--primary), #7c3aed); color: white; }
.nav-divider { padding: 0.75rem 0.85rem 0.4rem; font-size: 0.6rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
.nav-group { margin-bottom: 0.2rem; }
.nav-group-header { display: flex; align-items: center; justify-content: space-between; padding: 0.6rem 0.85rem; border-radius: 0.5rem; cursor: pointer; transition: all 0.2s; color: var(--text-muted); }
.nav-group-header:hover { background: rgba(139,92,246,0.1); color: var(--primary); }
.nav-group-title { display: flex; align-items: center; gap: 0.75rem; }
.nav-group-title i { width: 20px; font-size: 1rem; }
.nav-group-title span { font-size: 0.85rem; font-weight: 500; }
.nav-group-arrow { font-size: 0.7rem; transition: transform 0.2s; }
.nav-group-arrow.open { transform: rotate(180deg); }
.nav-group-content { padding-left: 2rem; display: none; }
.nav-subitem { display: flex; align-items: center; gap: 0.75rem; padding: 0.45rem 0.85rem; border-radius: 0.5rem; color: var(--text-muted); text-decoration: none; font-size: 0.8rem; transition: all 0.2s; }
.nav-subitem:hover { background: rgba(139,92,246,0.1); color: var(--primary); }
.nav-subitem i { width: 20px; font-size: 0.75rem; }
.logout-item { margin-top: 1rem; border-top: 1px solid var(--border); border-radius: 0; padding-top: 0.8rem; }
.logout-item:hover { color: var(--danger); background: rgba(239,68,68,0.1); }
.admin-item { border-left: 3px solid var(--danger); }
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }
@keyframes bounce { 0%, 80%, 100% { transform: scale(0); opacity: 0.3; } 40% { transform: scale(1); opacity: 1; } }
@media (max-width: 768px) { .sidebar { width: 280px; } }
</style>

<script>
const loadingMessages = [
    "Initializing Secure Gateway",
    "Loading Security Modules",
    "Establishing Encrypted Tunnel",
    "Verifying System Integrity",
    "Preparing Checker Interface",
    "Loading Card Validator",
    "Starting Security Protocols",
    "Connecting to Gateway",
    "Almost Ready"
];

let currentMessageIndex = 0;
let messageInterval;
let progressInterval;

function showSidebarLoader(linkText) {
    const loader = document.getElementById('sidebarLoader');
    const loaderText = document.getElementById('sidebarLoaderText');
    const loaderProgress = document.getElementById('sidebarLoaderProgress');
    if (!loader) return;
    
    loader.style.display = 'flex';
    currentMessageIndex = 0;
    loaderText.innerHTML = loadingMessages[currentMessageIndex];
    loaderProgress.style.width = '0%';
    
    if (messageInterval) clearInterval(messageInterval);
    messageInterval = setInterval(() => {
        currentMessageIndex++;
        if (currentMessageIndex < loadingMessages.length) {
            loaderText.innerHTML = loadingMessages[currentMessageIndex];
        } else {
            clearInterval(messageInterval);
        }
    }, 500);
    
    let progress = 0;
    if (progressInterval) clearInterval(progressInterval);
    progressInterval = setInterval(() => {
        if (progress < 95) {
            progress += Math.random() * 3 + 1;
            if (progress > 95) progress = 95;
            loaderProgress.style.width = progress + '%';
        }
    }, 100);
}

function hideSidebarLoader() {
    const loader = document.getElementById('sidebarLoader');
    const loaderProgress = document.getElementById('sidebarLoaderProgress');
    if (loaderProgress) loaderProgress.style.width = '100%';
    if (messageInterval) clearInterval(messageInterval);
    if (progressInterval) clearInterval(progressInterval);
    setTimeout(() => { if (loader) loader.style.display = 'none'; }, 200);
}

document.addEventListener('DOMContentLoaded', function() {
    const links = document.querySelectorAll('.sidebar-link-loader');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href && href !== '#' && !href.includes('javascript:') && !href.includes('logout')) {
                e.preventDefault();
                const linkText = this.querySelector('span')?.innerText || 'gateway';
                showSidebarLoader(linkText);
                setTimeout(() => { window.location.href = href; }, 300);
            }
        });
    });
});

window.addEventListener('load', function() { hideSidebarLoader(); });
window.addEventListener('pageshow', function() { hideSidebarLoader(); });

function toggleGroup(groupId) {
    const content = document.getElementById('group-' + groupId);
    const arrow = document.getElementById('arrow-' + groupId);
    if (content) {
        if (content.style.display === 'none' || content.style.display === '') {
            content.style.display = 'block';
            if (arrow) arrow.classList.add('open');
        } else {
            content.style.display = 'none';
            if (arrow) arrow.classList.remove('open');
        }
    }
}

// Initially collapse groups
const autoGroup = document.getElementById('group-auto-checkers');
const customGroup = document.getElementById('group-custom-apis');
if (autoGroup) autoGroup.style.display = 'none';
if (customGroup) customGroup.style.display = 'none';
</script>
