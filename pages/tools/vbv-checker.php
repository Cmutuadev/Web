<?php
require_once __DIR__ . "/../../includes/config.php";
if (!isLoggedIn()) { header('Location: /login.php'); exit; }
$pageTitle = ucfirst(str_replace('-', ' ', basename(__FILE__, '.php')));
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title><?php echo $pageTitle; ?> | APPROVED CHECKER</title>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{--bg:#0a0a0f;--card:#111114;--border:#1e1e24;--text:#fff;--text-muted:#6b6b76;--primary:#8b5cf6;}
[data-theme="light"]{--bg:#f8fafc;--card:#fff;--border:#e2e8f0;--text:#0f172a;--text-muted:#64748b;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
.navbar{position:fixed;top:0;left:0;right:0;height:55px;background:var(--card);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 1rem;z-index:100;}
.menu-btn{background:none;border:none;color:var(--text);font-size:1rem;cursor:pointer;display:none;}
.logo{display:flex;align-items:center;gap:0.5rem;}
.logo-icon{width:30px;height:30px;background:linear-gradient(135deg,var(--primary),#06b6d4);border-radius:8px;display:flex;align-items:center;justify-content:center;}
.logo-icon i{color:white;font-size:0.9rem;}
.logo-text span:first-child{font-weight:700;font-size:0.85rem;background:linear-gradient(135deg,var(--primary),#06b6d4);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.logo-text span:last-child{font-size:0.6rem;color:var(--text-muted);display:block;}
.user-menu{display:flex;align-items:center;gap:0.5rem;cursor:pointer;padding:0.2rem 0.6rem;border-radius:2rem;background:var(--bg);border:1px solid var(--border);}
.user-avatar{width:28px;height:28px;background:linear-gradient(135deg,var(--primary),#7c3aed);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:0.7rem;color:white;}
.theme-btn{background:none;border:1px solid var(--border);border-radius:0.4rem;padding:0.3rem 0.5rem;cursor:pointer;color:var(--text-muted);}
.sidebar{position:fixed;left:0;top:55px;bottom:0;width:260px;background:var(--card);border-right:1px solid var(--border);transform:translateX(-100%);transition:transform 0.2s;z-index:99;overflow-y:auto;}
.sidebar.open{transform:translateX(0);}
.sidebar-content{padding:1rem;}
.sidebar-user{display:flex;align-items:center;gap:0.7rem;padding-bottom:1rem;border-bottom:1px solid var(--border);margin-bottom:1rem;}
.sidebar-avatar{width:45px;height:45px;background:linear-gradient(135deg,var(--primary),#7c3aed);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;color:white;}
.nav-item{display:flex;align-items:center;gap:0.7rem;padding:0.5rem 0.7rem;border-radius:0.5rem;color:var(--text-muted);text-decoration:none;margin-bottom:0.2rem;}
.nav-item:hover{background:rgba(139,92,246,0.1);color:var(--primary);}
.nav-divider{font-size:0.6rem;color:var(--text-muted);padding:0.6rem 0.7rem 0.3rem;text-transform:uppercase;}
.logout-item{margin-top:0.5rem;border-top:1px solid var(--border);padding-top:0.7rem;color:var(--danger);}
.main{margin-left:0;margin-top:55px;padding:1.2rem;transition:margin-left 0.2s;}
.main.sidebar-open{margin-left:260px;}
@media (max-width:768px){.menu-btn{display:block;}.main.sidebar-open{margin-left:0;}}
.container{max-width:1400px;margin:0 auto;}
.page-header{margin-bottom:1rem;}
.page-title{font-size:1.6rem;font-weight:700;background:linear-gradient(135deg,var(--primary),#06b6d4);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.page-subtitle{color:var(--text-muted);font-size:0.75rem;margin-top:0.2rem;}
.card{background:var(--card);border:1px solid var(--border);border-radius:0.8rem;padding:2rem;text-align:center;}
.btn{padding:0.5rem 1rem;border-radius:0.5rem;font-weight:500;font-size:0.75rem;cursor:pointer;border:none;transition:all 0.2s;background:linear-gradient(135deg,var(--primary),#7c3aed);color:white;display:inline-block;text-decoration:none;}
</style>
</head>
<body data-theme="dark">
    <?php include __DIR__ . "/../../includes/header.php"; ?>
    <?php include __DIR__ . "/../../includes/sidebar.php"; ?>
    <main class="main"><div class="container"><div class="page-header"><h1 class="page-title"><?php echo $pageTitle; ?></h1><p class="page-subtitle">Coming soon - Under development</p></div><div class="card"><i class="fas fa-code" style="font-size:2rem;color:var(--primary);margin-bottom:1rem;"></i><p>This page is under development. Check back soon!</p><a href="/index.php?page=home" class="btn" style="margin-top:1rem;display:inline-block;">Return to Dashboard</a></div></div></main>
<script>
const savedTheme=localStorage.getItem('theme')||'dark';document.body.setAttribute('data-theme',savedTheme);
const themeBtn=document.getElementById('themeBtn');if(themeBtn){themeBtn.innerHTML=savedTheme==='dark'?'<i class="fas fa-sun"></i>':'<i class="fas fa-moon"></i>';themeBtn.addEventListener('click',()=>{const newTheme=document.body.getAttribute('data-theme')==='dark'?'light':'dark';document.body.setAttribute('data-theme',newTheme);localStorage.setItem('theme',newTheme);themeBtn.innerHTML=newTheme==='dark'?'<i class="fas fa-sun"></i>':'<i class="fas fa-moon"></i>';});}
const menuBtn=document.getElementById('menuBtn');const sidebar=document.getElementById('sidebar');const main=document.getElementById('main');if(menuBtn)menuBtn.addEventListener('click',()=>{sidebar.classList.toggle('open');main.classList.toggle('sidebar-open');});
</script>
</body>
</html>
