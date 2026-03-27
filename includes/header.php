<?php
$userName = $_SESSION['user']['display_name'] ?? $_SESSION['user']['name'];
$userAvatar = $_SESSION['user']['photo_url'] ?? null;
$credits = getUserCredits();
$isAdmin = isAdmin();

if (!$userAvatar) {
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
}
?>
<nav class="navbar">
    <div class="navbar-left">
        <button class="menu-btn" id="menuBtn">
            <i class="fas fa-bars"></i>
        </button>
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-credit-card"></i>
            </div>
            <div class="logo-text">
                <span>APPROVED</span>
                <span>CHECKER</span>
            </div>
        </div>
    </div>
    <div class="navbar-right">
        <button class="theme-btn" id="themeBtn">
            <i class="fas fa-moon"></i>
        </button>
        <div class="user-menu" id="userMenu">
            <img src="<?php echo $userAvatar; ?>" alt="Avatar" class="user-avatar-img">
            <div class="user-details">
                <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
                <span class="user-credits"><i class="fas fa-coins"></i> <?php echo $isAdmin ? '∞' : number_format($credits); ?> credits</span>
            </div>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="user-dropdown" id="userDropdown">
            <a href="index.php?page=home">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="topup.php">
                <i class="fas fa-wallet"></i> Top Up
            </a>
            <a href="adminaccess_panel.php">
                <i class="fas fa-crown"></i> Admin Panel
            </a>
            <hr>
            <a href="?logout=1">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</nav>

<style>
.navbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 60px;
    background: rgba(10, 14, 39, 0.85);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 1.5rem;
    z-index: 100;
}

[data-theme="light"] .navbar {
    background: rgba(248, 250, 252, 0.95);
    border-bottom-color: #e2e8f0;
}

.navbar-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.menu-btn {
    display: none;
    background: none;
    border: none;
    color: var(--text);
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 0.5rem;
}

.menu-btn:hover {
    background: rgba(255, 255, 255, 0.1);
}

.logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.logo-icon {
    width: 35px;
    height: 35px;
    background: linear-gradient(135deg, #8b5cf6, #06b6d4);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.logo-icon i {
    font-size: 1.2rem;
    color: white;
}

.logo-text {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
}

.logo-text span:first-child {
    font-size: 0.85rem;
    font-weight: 600;
    background: linear-gradient(135deg, #8b5cf6, #06b6d4);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.logo-text span:last-child {
    font-size: 0.7rem;
    color: var(--text-muted);
    letter-spacing: 1px;
}

.navbar-right {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.theme-btn {
    background: none;
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.5rem;
    cursor: pointer;
    color: var(--text-muted);
    transition: all 0.2s;
}

.theme-btn:hover {
    background: var(--card-hover);
    border-color: var(--primary);
}

.user-menu {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.4rem 1rem;
    background: var(--bg);
    border-radius: 2rem;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid var(--border);
}

.user-menu:hover {
    background: var(--card-hover);
    border-color: var(--primary);
}

.user-avatar-img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}

.user-details {
    display: flex;
    flex-direction: column;
}

.user-name {
    font-size: 0.85rem;
    font-weight: 600;
}

.user-credits {
    font-size: 0.65rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.user-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 0.5rem;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    padding: 0.5rem;
    min-width: 180px;
    display: none;
    z-index: 100;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.user-dropdown.show {
    display: block;
    animation: dropdownFade 0.2s ease;
}

@keyframes dropdownFade {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.user-dropdown a {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 0.75rem;
    border-radius: 0.5rem;
    color: var(--text);
    text-decoration: none;
    transition: all 0.2s;
    font-size: 0.85rem;
}

.user-dropdown a:hover {
    background: var(--bg);
}

.user-dropdown hr {
    margin: 0.5rem 0;
    border-color: var(--border);
}

@media (max-width: 768px) {
    .navbar {
        padding: 0 1rem;
    }
    .menu-btn {
        display: block;
    }
    .user-details {
        display: none;
    }
    .logo-text span:first-child {
        font-size: 0.75rem;
    }
    .logo-text span:last-child {
        display: none;
    }
}
</style>

<script>
// User dropdown
const userMenu = document.getElementById('userMenu');
const userDropdown = document.getElementById('userDropdown');

if (userMenu) {
    userMenu.addEventListener('click', (e) => {
        e.stopPropagation();
        userDropdown.classList.toggle('show');
    });
}

document.addEventListener('click', () => {
    if (userDropdown) userDropdown.classList.remove('show');
});
</script>