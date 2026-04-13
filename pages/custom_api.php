<?php
require_once '../includes/config.php';
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}
$username = $_SESSION['user']['name'];
$db = getMongoDB();
$userGates = [];
if ($db) {
    $cursor = $db->user_gates->find(['username' => $username]);
    foreach ($cursor as $doc) {
        $userGates[] = [
            'id' => (string)$doc['_id'],
            'label' => $doc['label'],
            'api_endpoint' => $doc['api_endpoint'],
            'credit_cost' => $doc['credit_cost'] ?? 5,
            'enabled' => $doc['enabled'] ?? 1,
            'created_at' => $doc['created_at'] ? $doc['created_at']->toDateTime()->format('Y-m-d H:i:s') : '-'
        ];
    }
}
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
        :root {
            --bg: #0a0a0f; --card: #111114; --border: #1e1e24; --text: #ffffff;
            --text-muted: #6b6b76; --primary: #8b5cf6; --success: #10b981;
            --danger: #ef4444; --warning: #f59e0b;
        }
        [data-theme="light"] { --bg: #f8fafc; --card: #ffffff; --border: #e2e8f0; --text: #0f172a; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; font-size: 14px; }
        .main { margin-left: 0; margin-top: 55px; padding: 1.2rem; transition: margin-left 0.2s; }
        .main.sidebar-open { margin-left: 280px; }
        @media (max-width: 768px) { .main.sidebar-open { margin-left: 0; } }
        .container { max-width: 1400px; margin: 0 auto; }
        .page-header { margin-bottom: 1.5rem; }
        .page-title { font-size: 1.6rem; font-weight: 700; background: linear-gradient(135deg, var(--primary), #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .glass-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 1.2rem; margin-bottom: 1.2rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.7rem; font-weight: 600; margin-bottom: 0.3rem; color: var(--text-muted); text-transform: uppercase; }
        .form-control { width: 100%; padding: 0.6rem; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; color: var(--text); font-size: 0.8rem; font-family: monospace; }
        .form-control:focus { outline: none; border-color: var(--primary); }
        .btn { padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600; font-size: 0.7rem; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), #7c3aed); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-secondary { background: var(--bg); border: 1px solid var(--border); color: var(--text); }
        .btn-success { background: var(--success); color: white; }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.65rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.7rem; }
        th, td { padding: 0.6rem; text-align: left; border-bottom: 1px solid var(--border); }
        th { font-weight: 600; color: var(--text-muted); text-transform: uppercase; }
        .badge { display: inline-block; padding: 0.15rem 0.4rem; border-radius: 20px; font-size: 0.6rem; font-weight: 600; }
        .badge-success { background: rgba(16,185,129,0.2); color: var(--success); }
        .badge-danger { background: rgba(239,68,68,0.2); color: var(--danger); }
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center; }
        .modal-content { background: var(--card); border-radius: 12px; padding: 1.5rem; width: 90%; max-width: 500px; border: 1px solid var(--border); }
        .test-result { margin-top: 0.5rem; padding: 0.4rem; border-radius: 6px; font-size: 0.65rem; display: none; }
        .test-success { background: rgba(16,185,129,0.1); color: var(--success); border: 1px solid var(--success); }
        .test-error { background: rgba(239,68,68,0.1); color: var(--danger); border: 1px solid var(--danger); }
    </style>
</head>
<body data-theme="dark">
    <?php include $_SERVER['DOCUMENT_ROOT'] . "/includes/header.php"; ?>
    <?php include $_SERVER['DOCUMENT_ROOT'] . "/includes/sidebar.php"; ?>
    <main class="main" id="main">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">Custom APIs</h1>
                <p>Add your own payment gateway endpoints. Use <code>{cc}</code> as placeholder for card data (e.g., <code>https://api.example.com?cc={cc}</code>).</p>
            </div>

            <!-- Add New API Form -->
            <div class="glass-card">
                <h3><i class="fas fa-plus-circle"></i> Add New Custom API</h3>
                <form id="addApiForm">
                    <div class="form-group">
                        <label>API Name (must be unique)</label>
                        <input type="text" id="apiName" class="form-control" placeholder="e.g., My Stripe Gate" required>
                    </div>
                    <div class="form-group">
                        <label>API Endpoint (use {cc})</label>
                        <input type="text" id="apiEndpoint" class="form-control" placeholder="https://api.example.com?cc={cc}" required>
                    </div>
                    <div class="form-group">
                        <label>Credit Cost (per check)</label>
                        <input type="number" id="apiCost" class="form-control" value="5" required>
                    </div>
                    <div class="form-group">
                        <label>Test Card (optional)</label>
                        <input type="text" id="testCard" class="form-control" placeholder="4100400157539308|08|2026|126">
                        <button type="button" id="testApiBtn" class="btn btn-secondary btn-sm" style="margin-top: 0.5rem;"><i class="fas fa-vial"></i> Test Endpoint</button>
                        <div id="testResult" class="test-result"></div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save API</button>
                </form>
            </div>

            <!-- Existing Custom APIs List -->
            <div class="glass-card">
                <h3><i class="fas fa-list"></i> Your Custom APIs</h3>
                <?php if (empty($userGates)): ?>
                <div class="empty-state" style="text-align:center; padding:2rem;"><i class="fas fa-inbox"></i><p>No custom APIs added yet.</p></div>
                <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr><th>Name</th><th>Endpoint</th><th>Cost</th><th>Status</th><th>Created</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userGates as $gate): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($gate['label']); ?></strong></td>
                                <td><code style="font-size:0.6rem;"><?php echo htmlspecialchars(substr($gate['api_endpoint'], 0, 50)); ?>...</code></td>
                                <td><?php echo $gate['credit_cost']; ?></td>
                                <td><?php echo $gate['enabled'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Disabled</span>'; ?></td>
                                <td><?php echo $gate['created_at']; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editApi('<?php echo $gate['id']; ?>', '<?php echo htmlspecialchars($gate['label']); ?>', '<?php echo htmlspecialchars($gate['api_endpoint']); ?>', <?php echo $gate['credit_cost']; ?>, <?php echo $gate['enabled']; ?>)"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteApi('<?php echo $gate['id']; ?>')"><i class="fas fa-trash"></i></button>
                                    <button class="btn btn-sm btn-secondary" onclick="testApi('<?php echo htmlspecialchars($gate['api_endpoint']); ?>')"><i class="fas fa-vial"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>Edit Custom API</h3>
            <form id="editApiForm">
                <input type="hidden" id="editId">
                <div class="form-group"><label>API Name</label><input type="text" id="editName" class="form-control" required></div>
                <div class="form-group"><label>API Endpoint (use {cc})</label><input type="text" id="editEndpoint" class="form-control" required></div>
                <div class="form-group"><label>Credit Cost</label><input type="number" id="editCost" class="form-control" required></div>
                <div class="form-group"><label>Enabled</label><select id="editEnabled" class="form-control"><option value="1">Active</option><option value="0">Disabled</option></select></div>
                <div style="display:flex; gap:0.5rem;"><button type="submit" class="btn btn-primary">Save Changes</button><button type="button" class="btn btn-danger" onclick="closeModal()">Cancel</button></div>
            </form>
        </div>
    </div>

    <script>
        function closeModal() { document.getElementById('editModal').style.display = 'none'; }
        function editApi(id, name, endpoint, cost, enabled) {
            document.getElementById('editId').value = id;
            document.getElementById('editName').value = name;
            document.getElementById('editEndpoint').value = endpoint;
            document.getElementById('editCost').value = cost;
            document.getElementById('editEnabled').value = enabled;
            document.getElementById('editModal').style.display = 'flex';
        }
        function deleteApi(id) {
            Swal.fire({ title: 'Delete?', text: 'This action cannot be undone.', icon: 'warning', showCancelButton: true }).then(result => {
                if (result.isConfirmed) {
                    fetch('/api/delete_custom_gate.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) })
                    .then(res => res.json()).then(data => { if (data.success) location.reload(); else Swal.fire('Error', data.error, 'error'); });
                }
            });
        }
        function testApi(endpoint) {
            const testCard = document.getElementById('testCard').value;
            if (!testCard) { Swal.fire('Error', 'Enter a test card first', 'error'); return; }
            const proxyUrl = '/api/proxy.php?url=' + encodeURIComponent(endpoint.replace('{cc}', encodeURIComponent(testCard)));
            Swal.fire({ title: 'Testing...', text: 'Please wait', icon: 'info', allowOutsideClick: false });
            fetch(proxyUrl).then(res => res.json()).then(data => {
                Swal.fire({ title: 'Test Result', html: `<pre>${JSON.stringify(data, null, 2)}</pre>`, icon: data.status === 'approved' ? 'success' : 'error' });
            }).catch(err => { Swal.fire('Error', err.message, 'error'); });
        }
        document.getElementById('testApiBtn').addEventListener('click', () => {
            const endpoint = document.getElementById('apiEndpoint').value;
            const testCard = document.getElementById('testCard').value;
            if (!endpoint || !testCard) { Swal.fire('Error', 'Fill endpoint and test card', 'error'); return; }
            const proxyUrl = '/api/proxy.php?url=' + encodeURIComponent(endpoint.replace('{cc}', encodeURIComponent(testCard)));
            const resultDiv = document.getElementById('testResult');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
            resultDiv.className = 'test-result';
            fetch(proxyUrl).then(res => res.json()).then(data => {
                resultDiv.innerHTML = `<i class="fas fa-${data.status === 'approved' ? 'check-circle' : 'times-circle'}"></i> Response: ${JSON.stringify(data)}`;
                resultDiv.className = `test-result ${data.status === 'approved' ? 'test-success' : 'test-error'}`;
            }).catch(err => { resultDiv.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Error: ${err.message}`; resultDiv.className = 'test-result test-error'; });
        });
        document.getElementById('addApiForm').addEventListener('submit', (e) => {
            e.preventDefault();
            const name = document.getElementById('apiName').value;
            const endpoint = document.getElementById('apiEndpoint').value;
            const cost = parseInt(document.getElementById('apiCost').value);
            fetch('/api/add_custom_gate.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name, endpoint, cost }) })
            .then(res => res.json()).then(data => { if (data.success) location.reload(); else Swal.fire('Error', data.error, 'error'); });
        });
        document.getElementById('editApiForm').addEventListener('submit', (e) => {
            e.preventDefault();
            const id = document.getElementById('editId').value;
            const name = document.getElementById('editName').value;
            const endpoint = document.getElementById('editEndpoint').value;
            const cost = parseInt(document.getElementById('editCost').value);
            const enabled = parseInt(document.getElementById('editEnabled').value);
            fetch('/api/edit_custom_gate.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, name, endpoint, cost, enabled }) })
            .then(res => res.json()).then(data => { if (data.success) location.reload(); else Swal.fire('Error', data.error, 'error'); });
        });
        // Theme and sidebar toggle (same as index)
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
