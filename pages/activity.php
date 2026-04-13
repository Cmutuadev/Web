<?php
require_once '../includes/config.php';
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$pageTitle = "Activity Log";
$creditHistory = loadCreditHistory();
$userHistory = array_filter($creditHistory, function($h) {
    return $h['username'] === $_SESSION['user']['name'];
});

// Pagination
$page = $_GET['page'] ?? 1;
$perPage = 20;
$total = count($userHistory);
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;
$history = array_slice(array_reverse($userHistory), $offset, $perPage);
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
        :root { --bg: #0a0a0f; --card: #111114; --border: #1e1e24; --text: #ffffff; --text-muted: #6b6b76; --primary: #8b5cf6; --success: #10b981; --danger: #ef4444; }
        [data-theme="light"] { --bg: #f8fafc; --card: #ffffff; --border: #e2e8f0; --text: #0f172a; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .main { margin-left: 280px; margin-top: 55px; padding: 1.5rem; }
        @media (max-width: 768px) { .main { margin-left: 0; } }
        .container { max-width: 1200px; margin: 0 auto; }
        .page-header { margin-bottom: 1.5rem; }
        .page-title { font-size: 1.6rem; font-weight: 700; background: linear-gradient(135deg, var(--primary), #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .activity-table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--border); }
        th { color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase; }
        td { font-size: 0.75rem; }
        .status-approved { color: var(--success); }
        .status-declined { color: var(--danger); }
        .pagination { display: flex; justify-content: center; gap: 0.5rem; margin-top: 1.5rem; }
        .page-link { padding: 0.3rem 0.7rem; background: var(--card); border: 1px solid var(--border); border-radius: 0.3rem; color: var(--text); text-decoration: none; font-size: 0.7rem; }
        .page-link.active { background: var(--primary); border-color: var(--primary); }
        .search-box { margin-bottom: 1rem; display: flex; gap: 0.5rem; }
        .search-box input { flex: 1; padding: 0.5rem; background: var(--bg); border: 1px solid var(--border); border-radius: 0.4rem; color: var(--text); }
    </style>
</head>
<body data-theme="dark">
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-history"></i> Activity Log</h1>
                <p class="page-subtitle">View your complete check history</p>
            </div>
            
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search by card number..." onkeyup="filterActivity()">
                <select id="statusFilter" onchange="filterActivity()">
                    <option value="all">All Status</option>
                    <option value="approved">Approved</option>
                    <option value="declined">Declined</option>
                </select>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="activity-table">
                    <thead>
                        <tr><th>Card</th><th>Status</th><th>Reason</th><th>Credits</th><th>Date</th></tr>
                    </thead>
                    <tbody id="activityBody">
                        <?php foreach ($history as $entry): 
                            $status = 'declined';
                            if (stripos($entry['reason'], 'approved') !== false || stripos($entry['reason'], 'charged') !== false) $status = 'approved';
                        ?>
                        <tr class="activity-row" data-status="<?php echo $status; ?>" data-card="<?php echo htmlspecialchars($entry['card_info'] ?? ''); ?>">
                            <td><code><?php echo htmlspecialchars($entry['card_info'] ?? '-'); ?></code></td>
                            <td class="status-<?php echo $status; ?>"><?php echo strtoupper($status); ?></td>
                            <td><?php echo htmlspecialchars(substr($entry['reason'], 0, 50)); ?></td>
                            <td><?php echo abs($entry['amount']); ?></td>
                            <td><?php echo date('M d, Y H:i:s', strtotime($entry['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </main>
    
    <script>
        function filterActivity() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const status = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('.activity-row');
            
            rows.forEach(row => {
                const card = row.dataset.card.toLowerCase();
                const rowStatus = row.dataset.status;
                let show = true;
                
                if (search && !card.includes(search)) show = false;
                if (status !== 'all' && rowStatus !== status) show = false;
                
                row.style.display = show ? '' : 'none';
            });
        }
        
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
