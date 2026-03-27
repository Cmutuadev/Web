<?php
$currentPage = $_SERVER['REQUEST_URI'];
$currentPage = strtok($currentPage, '?');
$isAdmin = isAdmin();
?>

<div class="sidebar" id="sidebar">
    <!-- User Profile Section -->
    <div class="sidebar-user">
        <div class="sidebar-user-avatar">
            <?php 
            $initials = '';
            $words = explode(' ', trim($_SESSION['user']['display_name'] ?? $_SESSION['user']['name']));
            foreach ($words as $word) {
                if (!empty($word)) {
                    $initials .= strtoupper(substr($word, 0, 1));
                    if (strlen($initials) >= 2) break;
                }
            }
            if (empty($initials)) $initials = 'U';
            echo $initials;
            ?>
        </div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['user']['display_name'] ?? $_SESSION['user']['name']); ?></div>
            <div class="sidebar-user-plan"><?php echo ucfirst($_SESSION['user']['plan'] ?? 'Basic'); ?> Plan</div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <!-- Dashboard -->
        <a href="index.php?page=home" class="nav-item <?php echo strpos($currentPage, '?page=home') !== false || $currentPage === '/index.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard</span>
        </a>

        <div class="nav-divider">PAYMENT</div>
        
        <!-- Top Up -->
        <a href="topup.php" class="nav-item <?php echo $currentPage === '/topup.php' ? 'active' : ''; ?>">
            <i class="fas fa-wallet"></i>
            <span>Top Up Credits</span>
            <?php if (getUserCredits() < 100): ?>
            <span class="nav-badge">Low</span>
            <?php endif; ?>
        </a>

        <div class="nav-divider">CHECKERS</div>

        <!-- Auto Checkers -->
        <div class="nav-group">
            <div class="nav-group-header" onclick="toggleGroup('auto-checkers')">
                <div class="nav-group-title">
                    <i class="fas fa-bolt"></i>
                    <span>Auto Checkers</span>
                </div>
                <i class="fas fa-chevron-down nav-group-arrow" id="arrow-auto-checkers"></i>
            </div>
            <div class="nav-group-content" id="group-auto-checkers">
                <a href="index.php?page=shopify" class="nav-subitem"><i class="fab fa-shopify"></i> Shopify</a>
                <a href="index.php?page=stripe-auth" class="nav-subitem"><i class="fab fa-stripe"></i> Stripe Auth</a>
                <a href="index.php?page=razorpay" class="nav-subitem"><i class="fas fa-rupee-sign"></i> Razorpay</a>
            </div>
        </div>

        <!-- Checkers -->
        <div class="nav-group">
            <div class="nav-group-header" onclick="toggleGroup('checkers')">
                <div class="nav-group-title">
                    <i class="fas fa-shield-alt"></i>
                    <span>Checkers</span>
                </div>
                <i class="fas fa-chevron-down nav-group-arrow" id="arrow-checkers"></i>
            </div>
            <div class="nav-group-content" id="group-checkers">
                <a href="index.php?page=auth" class="nav-subitem"><i class="fas fa-shield-alt"></i> Auth Checker</a>
                <a href="index.php?page=charge" class="nav-subitem"><i class="fas fa-bolt"></i> Charge Checker</a>
                <a href="index.php?page=auth-charge" class="nav-subitem"><i class="fas fa-layer-group"></i> Auth+Charge</a>
            </div>
        </div>

        <!-- Hitters -->
        <div class="nav-group">
            <div class="nav-group-header" onclick="toggleGroup('hitters')">
                <div class="nav-group-title">
                    <i class="fas fa-bullseye"></i>
                    <span>Hitters</span>
                </div>
                <i class="fas fa-chevron-down nav-group-arrow" id="arrow-hitters"></i>
            </div>
            <div class="nav-group-content" id="group-hitters">
                <a href="index.php?page=stripe-checkout" class="nav-subitem"><i class="fas fa-shopping-cart"></i> Stripe Checkout</a>
                <a href="index.php?page=stripe-invoice" class="nav-subitem"><i class="fas fa-file-invoice"></i> Stripe Invoice</a>
                <a href="index.php?page=stripe-inbuilt" class="nav-subitem"><i class="fas fa-code"></i> Stripe Inbuilt</a>
            </div>
        </div>

        <!-- Key Based -->
        <div class="nav-group">
            <div class="nav-group-header" onclick="toggleGroup('key-based')">
                <div class="nav-group-title">
                    <i class="fas fa-key"></i>
                    <span>Key Based</span>
                </div>
                <i class="fas fa-chevron-down nav-group-arrow" id="arrow-key-based"></i>
            </div>
            <div class="nav-group-content" id="group-key-based">
                <a href="index.php?page=key-stripe" class="nav-subitem"><i class="fab fa-stripe"></i> Stripe API</a>
                <a href="index.php?page=key-paypal" class="nav-subitem"><i class="fab fa-paypal"></i> PayPal API</a>
            </div>
        </div>

        <div class="nav-divider">TOOLS</div>

        <!-- Tools -->
        <div class="nav-group">
            <div class="nav-group-header" onclick="toggleGroup('tools')">
                <div class="nav-group-title">
                    <i class="fas fa-tools"></i>
                    <span>Tools</span>
                </div>
                <i class="fas fa-chevron-down nav-group-arrow" id="arrow-tools"></i>
            </div>
            <div class="nav-group-content" id="group-tools">
                <a href="index.php?page=address-gen" class="nav-subitem"><i class="fas fa-address-card"></i> Address Generator</a>
                <a href="index.php?page=bin-lookup" class="nav-subitem"><i class="fas fa-search"></i> BIN Lookup</a>
                <a href="index.php?page=cc-cleaner" class="nav-subitem"><i class="fas fa-broom"></i> CC Cleaner</a>
                <a href="index.php?page=cc-generator" class="nav-subitem"><i class="fas fa-magic"></i> CC Generator</a>
                <a href="index.php?page=proxy-checker" class="nav-subitem"><i class="fas fa-globe"></i> Proxy Checker</a>
                <a href="index.php?page=vbv-checker" class="nav-subitem"><i class="fas fa-shield-alt"></i> VBV Checker</a>
            </div>
        </div>

        <!-- Preferences -->
        <div class="nav-group">
            <div class="nav-group-header" onclick="toggleGroup('preferences')">
                <div class="nav-group-title">
                    <i class="fas fa-sliders-h"></i>
                    <span>Preferences</span>
                </div>
                <i class="fas fa-chevron-down nav-group-arrow" id="arrow-preferences"></i>
            </div>
            <div class="nav-group-content" id="group-preferences">
                <a href="index.php?page=proxies" class="nav-subitem"><i class="fas fa-network-wired"></i> Proxies</a>
                <a href="index.php?page=assets" class="nav-subitem"><i class="fas fa-database"></i> Assets</a>
            </div>
        </div>

        <div class="nav-divider">PLATFORM</div>

        <!-- ADMIN PANEL - CORRECT LINK -->
        <?php if ($isAdmin): ?>
        <a href="adminaccess_panel.php" class="nav-item admin-item <?php echo strpos($currentPage, 'adminaccess_panel.php') !== false ? 'active' : ''; ?>">
            <i class="fas fa-crown"></i>
            <span>Admin Panel</span>
            <span class="nav-badge admin">ADMIN</span>
        </a>
        <?php endif; ?>

        <!-- Logout -->
        <a href="?logout=1" class="nav-item logout-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
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
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 99;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.sidebar::-webkit-scrollbar {
    width: 4px;
}

.sidebar::-webkit-scrollbar-track {
    background: var(--border);
}

.sidebar::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 4px;
}

.sidebar.open {
    transform: translateX(0);
}

/* User Section */
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
    background: linear-gradient(135deg, var(--primary), #7c3aed);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.2rem;
    color: white;
}

.sidebar-user-info {
    flex: 1;
}

.sidebar-user-name {
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 0.2rem;
}

.sidebar-user-plan {
    font-size: 0.65rem;
    color: var(--primary);
    background: rgba(139, 92, 246, 0.15);
    display: inline-block;
    padding: 0.15rem 0.5rem;
    border-radius: 20px;
}

/* Navigation */
.sidebar-nav {
    flex: 1;
    padding: 1rem 0.75rem;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 0.85rem;
    border-radius: 0.5rem;
    color: var(--text-muted);
    text-decoration: none;
    transition: all 0.2s;
    margin-bottom: 0.2rem;
    position: relative;
}

.nav-item:hover {
    background: rgba(139, 92, 246, 0.1);
    color: var(--primary);
}

.nav-item.active {
    background: linear-gradient(135deg, var(--primary), #7c3aed);
    color: white;
}

.nav-item i {
    width: 20px;
    font-size: 1rem;
}

.nav-item span {
    font-size: 0.85rem;
    font-weight: 500;
}

.nav-badge {
    margin-left: auto;
    font-size: 0.6rem;
    padding: 0.15rem 0.4rem;
    border-radius: 20px;
    background: var(--warning);
    color: white;
}

.nav-badge.admin {
    background: var(--danger);
}

.nav-divider {
    padding: 0.75rem 0.85rem 0.4rem;
    font-size: 0.6rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Nav Groups */
.nav-group {
    margin-bottom: 0.2rem;
}

.nav-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.6rem 0.85rem;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: all 0.2s;
    color: var(--text-muted);
}

.nav-group-header:hover {
    background: rgba(139, 92, 246, 0.1);
    color: var(--primary);
}

.nav-group-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.nav-group-title i {
    width: 20px;
    font-size: 1rem;
}

.nav-group-title span {
    font-size: 0.85rem;
    font-weight: 500;
}

.nav-group-arrow {
    font-size: 0.7rem;
    transition: transform 0.2s;
}

.nav-group-arrow.open {
    transform: rotate(180deg);
}

.nav-group-content {
    padding-left: 2rem;
    display: none;
}

.nav-subitem {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.45rem 0.85rem;
    border-radius: 0.5rem;
    color: var(--text-muted);
    text-decoration: none;
    font-size: 0.8rem;
    transition: all 0.2s;
}

.nav-subitem:hover {
    background: rgba(139, 92, 246, 0.1);
    color: var(--primary);
}

.nav-subitem i {
    width: 20px;
    font-size: 0.75rem;
}

.logout-item {
    margin-top: 1rem;
    border-top: 1px solid var(--border);
    border-radius: 0;
    padding-top: 0.8rem;
}

.logout-item:hover {
    color: var(--danger);
    background: rgba(239, 68, 68, 0.1);
}

.admin-item {
    border-left: 3px solid var(--danger);
}

@media (max-width: 768px) {
    .sidebar {
        width: 280px;
    }
}
</style>

<script>
function toggleGroup(groupId) {
    const content = document.getElementById('group-' + groupId);
    const arrow = document.getElementById('arrow-' + groupId);
    if (content.style.display === 'none' || content.style.display === '') {
        content.style.display = 'block';
        arrow.classList.add('open');
    } else {
        content.style.display = 'none';
        arrow.classList.remove('open');
    }
}

// Initialize all groups closed
['auto-checkers', 'checkers', 'hitters', 'key-based', 'tools', 'preferences'].forEach(group => {
    const content = document.getElementById('group-' + group);
    if (content) content.style.display = 'none';
});
</script>