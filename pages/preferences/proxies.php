<?php
require_once __DIR__ . "/../../includes/config.php";
if (!isLoggedIn()) { 
    header('Location: /login.php'); 
    exit; 
}

$pageTitle = "Proxy Manager";
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
        
        .dashboard-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1rem; }
        @media (max-width: 768px) { .dashboard-grid { grid-template-columns: repeat(2, 1fr); } }
        
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 0.8rem; }
        .stat-number { font-size: 1.5rem; font-weight: 700; }
        .stat-label { font-size: 0.6rem; color: var(--text-muted); margin-top: 0.2rem; }
        
        .card-section { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 1rem; margin-bottom: 1rem; }
        .section-title { font-size: 0.85rem; font-weight: 600; margin-bottom: 0.8rem; display: flex; align-items: center; gap: 0.5rem; }
        
        .input-group { margin-bottom: 0.8rem; }
        .input-group label { display: block; font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.2rem; }
        .input-group input, .input-group select, .input-group textarea { width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 0.4rem; padding: 0.4rem 0.6rem; color: var(--text); font-size: 0.75rem; font-family: monospace; }
        
        .btn { padding: 0.4rem 0.8rem; border-radius: 0.4rem; font-weight: 500; font-size: 0.7rem; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), #7c3aed); color: white; }
        .btn-success { background: linear-gradient(135deg, var(--success), #059669); color: white; }
        .btn-danger { background: linear-gradient(135deg, var(--danger), #dc2626); color: white; }
        .btn-warning { background: linear-gradient(135deg, var(--warning), #d97706); color: white; }
        .btn-secondary { background: var(--bg); border: 1px solid var(--border); color: var(--text); }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.65rem; }
        
        .proxies-table { width: 100%; border-collapse: collapse; font-size: 0.7rem; }
        .proxies-table th, .proxies-table td { padding: 0.5rem; text-align: left; border-bottom: 1px solid var(--border); }
        .proxies-table th { color: var(--text-muted); font-weight: 600; }
        .status-badge { display: inline-block; padding: 0.15rem 0.4rem; border-radius: 0.25rem; font-size: 0.55rem; font-weight: 600; }
        .status-working { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .status-failed { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        .status-pending { background: rgba(245, 158, 11, 0.2); color: var(--warning); }
        
        .proxy-list { max-height: 400px; overflow-y: auto; }
        .proxy-item { padding: 0.4rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .proxy-item:hover { background: var(--bg); }
        
        .layout-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 768px) { .layout-2col { grid-template-columns: 1fr; } }
        
        .loading { opacity: 0.6; pointer-events: none; }
    </style>
</head>
<body data-theme="dark">
    <?php include __DIR__ . "/../../includes/header.php"; ?>
    <?php include __DIR__ . "/../../includes/sidebar.php"; ?>
    
    <main class="main" id="main">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">Proxy Manager</h1>
                <p class="page-subtitle">Manage and test proxies for all checkers</p>
            </div>
            
            <!-- Stats Dashboard -->
            <div class="dashboard-grid" id="statsGrid">
                <div class="stat-card"><div class="stat-number" id="totalProxies">0</div><div class="stat-label">Total Proxies</div></div>
                <div class="stat-card"><div class="stat-number" id="workingProxies">0</div><div class="stat-label">Working</div></div>
                <div class="stat-card"><div class="stat-number" id="failedProxies">0</div><div class="stat-label">Failed</div></div>
                <div class="stat-card"><div class="stat-number" id="avgResponse">0ms</div><div class="stat-label">Avg Response</div></div>
            </div>
            
            <div class="layout-2col">
                <!-- Add Proxies Section -->
                <div class="card-section">
                    <div class="section-title"><i class="fas fa-plus-circle"></i> Add Proxies</div>
                    <div class="input-group">
                        <label>Single Proxy</label>
                        <input type="text" id="singleProxy" placeholder="ip:port or ip:port:user:pass">
                        <button class="btn btn-primary btn-sm" id="addProxyBtn" style="margin-top: 0.3rem;"><i class="fas fa-plus"></i> Add Proxy</button>
                    </div>
                    
                    <div class="input-group">
                        <label>Bulk Add (one per line)</label>
                        <textarea id="bulkProxies" rows="5" placeholder="192.168.1.1:8080&#10;192.168.1.2:8080:user:pass"></textarea>
                        <button class="btn btn-primary btn-sm" id="addBulkBtn" style="margin-top: 0.3rem;"><i class="fas fa-layer-group"></i> Add Multiple</button>
                    </div>
                    
                    <div class="input-group">
                        <label>Quick Actions</label>
                        <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                            <button class="btn btn-secondary btn-sm" id="testAllBtn"><i class="fas fa-vial"></i> Test All</button>
                            <button class="btn btn-success btn-sm" id="exportBtn"><i class="fas fa-download"></i> Export TXT</button>
                            <button class="btn btn-warning btn-sm" id="clearDeadBtn"><i class="fas fa-trash"></i> Clear Dead</button>
                        </div>
                    </div>
                </div>
                
                <!-- Proxy List -->
                <div class="card-section">
                    <div class="section-title"><i class="fas fa-list"></i> Proxy List</div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span id="proxyCount">0 proxies</span>
                        <div>
                            <button class="btn btn-secondary btn-sm" id="refreshListBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
                        </div>
                    </div>
                    <div id="proxyListContainer" class="proxy-list">
                        <div style="text-align: center; padding: 1rem; color: var(--text-muted);">Loading proxies...</div>
                    </div>
                </div>
            </div>
            
            <!-- Usage Info -->
            <div class="card-section">
                <div class="section-title"><i class="fas fa-info-circle"></i> How to Use Proxies in Checkers</div>
                <div style="font-size: 0.7rem; color: var(--text-muted);">
                    <p><strong>In any checker page:</strong></p>
                    <ul style="margin-left: 1.5rem; margin-top: 0.3rem;">
                        <li><strong>Custom Proxy:</strong> Enter proxy directly in the proxy field</li>
                        <li><strong>Proxy Rotation:</strong> Select "Rotate Proxies" mode and the system will automatically use proxies from your list</li>
                        <li><strong>Format:</strong> ip:port or ip:port:username:password</li>
                    </ul>
                    <p style="margin-top: 0.5rem;"><strong>Proxies are automatically tested and tracked for performance.</strong></p>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        let proxyList = [];
        
        // Load statistics
        function loadStats() {
            $.ajax({
                url: '/gate/proxy-manager.php?action=stats',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        $('#totalProxies').text(response.stats.total);
                        $('#workingProxies').text(response.stats.working);
                        $('#failedProxies').text(response.stats.failed);
                        $('#avgResponse').text(response.stats.avg_response + 'ms');
                    }
                }
            });
        }
        
        // Load proxy list
        function loadProxyList() {
            $('#proxyListContainer').html('<div style="text-align: center; padding: 1rem;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');
            
            $.ajax({
                url: '/gate/proxy-manager.php?action=list',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        proxyList = response.proxies;
                        renderProxyList(proxyList);
                        $('#proxyCount').text(proxyList.length + ' proxies');
                        loadStats();
                    } else {
                        $('#proxyListContainer').html('<div style="text-align: center; padding: 1rem; color: var(--danger);">Failed to load proxies</div>');
                    }
                },
                error: function() {
                    $('#proxyListContainer').html('<div style="text-align: center; padding: 1rem; color: var(--danger);">Error loading proxies</div>');
                }
            });
        }
        
        // Render proxy list
        function renderProxyList(proxies) {
            if (proxies.length === 0) {
                $('#proxyListContainer').html('<div style="text-align: center; padding: 1rem; color: var(--text-muted);">No proxies added yet. Add your first proxy above.</div>');
                return;
            }
            
            let html = '';
            proxies.forEach(proxy => {
                let statusClass = '';
                let statusText = '';
                if (proxy.status === 'working') {
                    statusClass = 'status-working';
                    statusText = '✓ Working';
                } else if (proxy.status === 'failed') {
                    statusClass = 'status-failed';
                    statusText = '✗ Failed';
                } else {
                    statusClass = 'status-pending';
                    statusText = '⏳ Pending';
                }
                
                let responseTime = proxy.response_time ? `${proxy.response_time}ms` : '—';
                let added = new Date(proxy.added).toLocaleString();
                
                html += `
                    <div class="proxy-item">
                        <div style="flex: 1;">
                            <div><code style="font-size: 0.7rem;">${escapeHtml(proxy.address)}</code></div>
                            <div style="font-size: 0.55rem; color: var(--text-muted); margin-top: 0.2rem;">
                                Added: ${added} | Response: ${responseTime} | Success: ${proxy.success_count} | Fail: ${proxy.fail_count}
                            </div>
                        </div>
                        <div style="display: flex; gap: 0.3rem; align-items: center;">
                            <span class="status-badge ${statusClass}">${statusText}</span>
                            <button class="btn btn-secondary btn-sm test-proxy" data-proxy="${escapeHtml(proxy.address)}" style="padding: 0.15rem 0.4rem;"><i class="fas fa-vial"></i></button>
                            <button class="btn btn-danger btn-sm delete-proxy" data-proxy="${escapeHtml(proxy.address)}" style="padding: 0.15rem 0.4rem;"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                `;
            });
            
            $('#proxyListContainer').html(html);
            
            // Bind test buttons
            $('.test-proxy').click(function() {
                testProxy($(this).data('proxy'));
            });
            
            // Bind delete buttons
            $('.delete-proxy').click(function() {
                deleteProxy($(this).data('proxy'));
            });
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
        
        // Add single proxy
        function addProxy(proxy) {
            $.ajax({
                url: '/gate/proxy-manager.php',
                method: 'POST',
                data: { action: 'add', proxy: proxy },
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Success', response.message, 'success');
                        loadProxyList();
                        $('#singleProxy').val('');
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                }
            });
        }
        
        // Add bulk proxies
        function addBulkProxies(proxies) {
            $.ajax({
                url: '/gate/proxy-manager.php',
                method: 'POST',
                data: { action: 'add_bulk', proxies: proxies },
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Success', `Added ${response.added} proxies, Failed: ${response.failed}`, 'success');
                        loadProxyList();
                        $('#bulkProxies').val('');
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                }
            });
        }
        
        // Test proxy
        function testProxy(proxy) {
            Swal.fire({
                title: 'Testing Proxy',
                text: `Testing ${proxy}...`,
                icon: 'info',
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.ajax({
                url: '/gate/proxy-manager.php',
                method: 'POST',
                data: { action: 'test', proxy: proxy },
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Proxy Working!', `Response time: ${response.response_time}ms\nIP: ${response.ip || proxy}`, 'success');
                    } else {
                        Swal.fire('Proxy Failed', response.message, 'error');
                    }
                    loadProxyList();
                }
            });
        }
        
        // Delete proxy
        function deleteProxy(proxy) {
            Swal.fire({
                title: 'Delete Proxy',
                text: `Are you sure you want to delete ${proxy}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Delete'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '/gate/proxy-manager.php',
                        method: 'POST',
                        data: { action: 'delete', proxy: proxy },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Deleted', response.message, 'success');
                                loadProxyList();
                            } else {
                                Swal.fire('Error', response.message, 'error');
                            }
                        }
                    });
                }
            });
        }
        
        // Test all proxies
        function testAllProxies() {
            Swal.fire({
                title: 'Testing All Proxies',
                text: 'This may take a moment...',
                icon: 'info',
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.ajax({
                url: '/gate/proxy-manager.php?action=test_all',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        let working = response.results.filter(r => r.success).length;
                        Swal.fire('Test Complete', `${working}/${response.results.length} proxies working`, 'success');
                        loadProxyList();
                    } else {
                        Swal.fire('Error', 'Failed to test proxies', 'error');
                    }
                }
            });
        }
        
        // Export proxies
        function exportProxies() {
            window.location.href = '/gate/proxy-manager.php?action=export&format=txt';
        }
        
        // Clear dead proxies
        function clearDeadProxies() {
            Swal.fire({
                title: 'Clear Dead Proxies',
                text: 'Remove all failed proxies?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Clear'
            }).then((result) => {
                if (result.isConfirmed) {
                    let deadProxies = proxyList.filter(p => p.status === 'failed');
                    let count = 0;
                    
                    deadProxies.forEach(proxy => {
                        $.ajax({
                            url: '/gate/proxy-manager.php',
                            method: 'POST',
                            data: { action: 'delete', proxy: proxy.address },
                            async: false,
                            success: function() { count++; }
                        });
                    });
                    
                    Swal.fire('Done', `Removed ${count} dead proxies`, 'success');
                    loadProxyList();
                }
            });
        }
        
        // Event handlers
        $('#addProxyBtn').click(() => {
            let proxy = $('#singleProxy').val().trim();
            if (proxy) addProxy(proxy);
            else Swal.fire('Error', 'Enter proxy address', 'error');
        });
        
        $('#addBulkBtn').click(() => {
            let proxies = $('#bulkProxies').val().trim();
            if (proxies) addBulkProxies(proxies);
            else Swal.fire('Error', 'Enter proxy list', 'error');
        });
        
        $('#testAllBtn').click(() => testAllProxies());
        $('#exportBtn').click(() => exportProxies());
        $('#clearDeadBtn').click(() => clearDeadProxies());
        $('#refreshListBtn').click(() => loadProxyList());
        
        // Initial load
        loadProxyList();
        
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
    </script>
</body>
</html>
