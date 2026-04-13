<?php
require_once dirname(__DIR__) . '/includes/config.php';

// Check admin authentication
if (!isLoggedIn() || !isAdmin()) {
    header('Location: /login.php');
    exit;
}

$db = getMongoDB();
if (!$db) {
    die("MongoDB connection failed");
}

// Handle gate actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Add new gate
    if ($action === 'add_gate') {
        $key = sanitizeKey($_POST['gate_key']);
        $label = trim($_POST['gate_label']);
        $category = $_POST['gate_category'];
        $type = $_POST['gate_type'];
        $credit_cost = intval($_POST['credit_cost']);
        $required_plan = $_POST['required_plan'];
        $api_endpoint = trim($_POST['api_endpoint']);
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $sort_order = intval($_POST['sort_order']);
        $description = trim($_POST['description']);
        
        try {
            $result = $db->gates->updateOne(
                ['key' => $key],
                ['$set' => [
                    'key' => $key,
                    'label' => $label,
                    'category' => $category,
                    'type' => $type,
                    'credit_cost' => $credit_cost,
                    'required_plan' => $required_plan,
                    'api_endpoint' => $api_endpoint,
                    'enabled' => $enabled,
                    'sort_order' => $sort_order,
                    'description' => $description,
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]],
                ['upsert' => true]
            );
            $success = "Gate '$label' added successfully!";
            // Clear cache
            unset($GLOBALS['cache']['gates']);
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    // Update gate
    if ($action === 'update_gate') {
        $key = $_POST['gate_key'];
        $label = trim($_POST['gate_label']);
        $category = $_POST['gate_category'];
        $type = $_POST['gate_type'];
        $credit_cost = intval($_POST['credit_cost']);
        $required_plan = $_POST['required_plan'];
        $api_endpoint = trim($_POST['api_endpoint']);
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $sort_order = intval($_POST['sort_order']);
        $description = trim($_POST['description']);
        
        try {
            $result = $db->gates->updateOne(
                ['key' => $key],
                ['$set' => [
                    'label' => $label,
                    'category' => $category,
                    'type' => $type,
                    'credit_cost' => $credit_cost,
                    'required_plan' => $required_plan,
                    'api_endpoint' => $api_endpoint,
                    'enabled' => $enabled,
                    'sort_order' => $sort_order,
                    'description' => $description,
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
            $success = "Gate '$label' updated!";
            unset($GLOBALS['cache']['gates']);
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    // Delete gate
    if ($action === 'delete_gate') {
        $key = $_POST['gate_key'];
        try {
            $result = $db->gates->deleteOne(['key' => $key]);
            $success = "Gate deleted!";
            unset($GLOBALS['cache']['gates']);
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    // Toggle gate status
    if ($action === 'toggle_gate') {
        $key = $_POST['gate_key'];
        try {
            $gate = $db->gates->findOne(['key' => $key]);
            if ($gate) {
                $newEnabled = $gate['enabled'] ? 0 : 1;
                $db->gates->updateOne(['key' => $key], ['$set' => ['enabled' => $newEnabled]]);
                $success = "Gate status toggled!";
                unset($GLOBALS['cache']['gates']);
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    // Add category
    if ($action === 'add_category') {
        $name = sanitizeKey($_POST['category_name']);
        $label = trim($_POST['category_label']);
        $icon = $_POST['category_icon'];
        $sort_order = intval($_POST['sort_order']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        try {
            $result = $db->gate_categories->updateOne(
                ['name' => $name],
                ['$set' => [
                    'name' => $name,
                    'label' => $label,
                    'icon' => $icon,
                    'sort_order' => $sort_order,
                    'is_active' => $is_active
                ]],
                ['upsert' => true]
            );
            $success = "Category '$label' added!";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    // Delete category
    if ($action === 'delete_category') {
        $name = $_POST['category_name'];
        try {
            $db->gate_categories->deleteOne(['name' => $name]);
            $success = "Category deleted!";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

function sanitizeKey($key) {
    return preg_replace('/[^a-z0-9_]/i', '', strtolower(str_replace(' ', '_', $key)));
}

// Load data from MongoDB
$categories = [];
$cursor = $db->gate_categories->find([], ['sort' => ['sort_order' => 1]]);
foreach ($cursor as $doc) {
    $categories[] = [
        'name' => $doc['name'],
        'label' => $doc['label'],
        'icon' => $doc['icon'],
        'sort_order' => $doc['sort_order'],
        'is_active' => $doc['is_active']
    ];
}

$gates = [];
$cursor = $db->gates->find([], ['sort' => ['category' => 1, 'sort_order' => 1, 'label' => 1]]);
foreach ($cursor as $doc) {
    $gates[] = [
        'key' => $doc['key'],
        'label' => $doc['label'],
        'category' => $doc['category'] ?? 'uncategorized',
        'type' => $doc['type'] ?? 'auto_checker',
        'credit_cost' => $doc['credit_cost'] ?? 1,
        'required_plan' => $doc['required_plan'] ?? 'basic',
        'api_endpoint' => $doc['api_endpoint'] ?? '',
        'enabled' => $doc['enabled'] ?? 1,
        'sort_order' => $doc['sort_order'] ?? 0,
        'description' => $doc['description'] ?? ''
    ];
}

$gateTypes = [
    'auto_checker' => 'Auto Checker',
    'checker' => 'Checker',
    'hitter' => 'Hitter',
    'key_based' => 'Key Based',
    'tool' => 'Tool',
    'flex_tool' => 'Flex Tool'
];

$plans = ['basic' => 'Basic', 'premium' => 'Premium', 'gold' => 'Gold', 'platinum' => 'Platinum', 'lifetime' => 'Lifetime'];
$icons = ['fa-bolt', 'fa-shield-alt', 'fa-bullseye', 'fa-key', 'fa-tools', 'fa-layer-group', 'fa-star', 'fa-cube', 'fa-credit-card'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gate Management | Admin Panel</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #0a0a0f; color: #fff; padding: 1rem; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        h1 { font-size: 1.5rem; background: linear-gradient(135deg, #8b5cf6, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: #111114; border: 1px solid #1e1e24; border-radius: 0.8rem; padding: 1rem; text-align: center; }
        .stat-number { font-size: 1.5rem; font-weight: 700; color: #8b5cf6; }
        .stat-label { font-size: 0.7rem; color: #6b6b76; margin-top: 0.3rem; }
        .card { background: #111114; border: 1px solid #1e1e24; border-radius: 0.8rem; padding: 1.2rem; margin-bottom: 1.5rem; }
        .card-title { font-size: 1rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .form-group { margin-bottom: 0.8rem; }
        label { display: block; font-size: 0.7rem; font-weight: 600; color: #6b6b76; margin-bottom: 0.3rem; text-transform: uppercase; }
        input, select, textarea { width: 100%; padding: 0.5rem; background: #0a0a0f; border: 1px solid #1e1e24; border-radius: 0.4rem; color: #fff; font-size: 0.8rem; }
        input:focus, select:focus { outline: none; border-color: #8b5cf6; }
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
        .btn { padding: 0.4rem 0.8rem; border-radius: 0.4rem; font-size: 0.7rem; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; }
        .btn-primary { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.65rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.75rem; }
        th, td { padding: 0.6rem; text-align: left; border-bottom: 1px solid #1e1e24; }
        th { color: #6b6b76; font-weight: 600; }
        .badge { display: inline-block; padding: 0.2rem 0.4rem; border-radius: 0.3rem; font-size: 0.6rem; font-weight: 600; }
        .badge-enabled { background: #10b981; color: white; }
        .badge-disabled { background: #ef4444; color: white; }
        .tabs { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; border-bottom: 1px solid #1e1e24; padding-bottom: 0.5rem; }
        .tab-btn { padding: 0.4rem 1rem; background: none; border: none; color: #6b6b76; cursor: pointer; font-size: 0.8rem; border-radius: 0.4rem; transition: all 0.2s; }
        .tab-btn.active { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }
        .tab-content { display: none; animation: fadeIn 0.3s ease; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .success-message, .error-message { padding: 0.5rem 1rem; border-radius: 0.4rem; margin-bottom: 1rem; font-size: 0.75rem; }
        .success-message { background: rgba(16,185,129,0.1); border: 1px solid #10b981; color: #10b981; }
        .error-message { background: rgba(239,68,68,0.1); border: 1px solid #ef4444; color: #ef4444; }
        @media (max-width: 768px) { .grid-2, .grid-3 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-plug"></i> Gate Management System</h1>
            <a href="/adminaccess_panel.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Admin Panel</a>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-number"><?php echo count($gates); ?></div><div class="stat-label">Total Gates</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo count(array_filter($gates, function($g) { return $g['enabled']; })); ?></div><div class="stat-label">Active Gates</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo count($categories); ?></div><div class="stat-label">Categories</div></div>
        </div>
        
        <?php if (isset($success)): ?>
        <div class="success-message">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
        <div class="error-message">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('gates')">📋 Manage Gates</button>
            <button class="tab-btn" onclick="showTab('categories')">📁 Categories</button>
            <button class="tab-btn" onclick="showTab('add-gate')">➕ Add New Gate</button>
        </div>
        
        <!-- Gates List Tab -->
        <div id="gatesTab" class="tab-content active">
            <div class="card">
                <div class="card-title"><i class="fas fa-list"></i> All Gates</div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr><th>Key</th><th>Label</th><th>Category</th><th>Type</th><th>Cost</th><th>Plan</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gates as $gate): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($gate['key']); ?></code></td>
                                <td><?php echo htmlspecialchars($gate['label']); ?></td>
                                <td><span class="badge"><?php echo htmlspecialchars($gate['category']); ?></span></td>
                                <td><?php echo $gateTypes[$gate['type']] ?? $gate['type']; ?></td>
                                <td><?php echo $gate['credit_cost']; ?></td>
                                <td><span class="badge"><?php echo ucfirst($gate['required_plan']); ?></span></td>
                                <td><span class="badge <?php echo $gate['enabled'] ? 'badge-enabled' : 'badge-disabled'; ?>"><?php echo $gate['enabled'] ? 'Enabled' : 'Disabled'; ?></span></td>
                                <td>
                                    <button onclick="editGate('<?php echo htmlspecialchars($gate['key']); ?>')" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i></button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this gate?')"><input type="hidden" name="action" value="delete_gate"><input type="hidden" name="gate_key" value="<?php echo htmlspecialchars($gate['key']); ?>"><button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button></form>
                                    <form method="POST" style="display: inline;"><input type="hidden" name="action" value="toggle_gate"><input type="hidden" name="gate_key" value="<?php echo htmlspecialchars($gate['key']); ?>"><button type="submit" class="btn btn-sm <?php echo $gate['enabled'] ? 'btn-danger' : 'btn-success'; ?>"><i class="fas <?php echo $gate['enabled'] ? 'fa-pause' : 'fa-play'; ?>"></i></button></form>
                                </td>
                             </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Categories Tab -->
        <div id="categoriesTab" class="tab-content">
            <div class="card">
                <div class="card-title"><i class="fas fa-folder"></i> Add Category</div>
                <form method="POST" class="grid-2">
                    <input type="hidden" name="action" value="add_category">
                    <div class="form-group"><label>Category Name</label><input type="text" name="category_name" placeholder="auto_checkers" required></div>
                    <div class="form-group"><label>Display Label</label><input type="text" name="category_label" placeholder="Auto Checkers" required></div>
                    <div class="form-group"><label>Icon</label><select name="category_icon"><?php foreach ($icons as $icon): ?><option value="<?php echo $icon; ?>"><?php echo $icon; ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" value="0"></div>
                    <div class="form-group"><label>Active</label><label><input type="checkbox" name="is_active" checked> Yes</label></div>
                    <div class="form-group"><button type="submit" class="btn btn-primary">Add Category</button></div>
                </form>
            </div>
            
            <div class="card">
                <div class="card-title"><i class="fas fa-list"></i> Existing Categories</div>
                <table>
                    <thead>
                        <tr><th>Name</th><th>Label</th><th>Icon</th><th>Order</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($cat['name']); ?></code></td>
                            <td><?php echo htmlspecialchars($cat['label']); ?></td>
                            <td><i class="fas <?php echo $cat['icon']; ?>"></i></td>
                            <td><?php echo $cat['sort_order']; ?></td>
                            <td><span class="badge <?php echo $cat['is_active'] ? 'badge-enabled' : 'badge-disabled'; ?>"><?php echo $cat['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete category?')"><input type="hidden" name="action" value="delete_category"><input type="hidden" name="category_name" value="<?php echo htmlspecialchars($cat['name']); ?>"><button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button></form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Add Gate Tab -->
        <div id="addGateTab" class="tab-content">
            <div class="card">
                <div class="card-title"><i class="fas fa-plus-circle"></i> Add New Gate</div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_gate">
                    <div class="grid-3">
                        <div class="form-group"><label>Gate Key</label><input type="text" name="gate_key" placeholder="my_custom_gate" required></div>
                        <div class="form-group"><label>Display Label</label><input type="text" name="gate_label" placeholder="My Custom Gate" required></div>
                        <div class="form-group"><label>Category</label><select name="gate_category"><?php foreach ($categories as $cat): if($cat['is_active']) echo '<option value="'.$cat['name'].'">'.$cat['label'].'</option>'; endforeach; ?></select></div>
                        <div class="form-group"><label>Type</label><select name="gate_type"><?php foreach ($gateTypes as $val => $label): ?><option value="<?php echo $val; ?>"><?php echo $label; ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label>Credit Cost</label><input type="number" name="credit_cost" value="1"></div>
                        <div class="form-group"><label>Required Plan</label><select name="required_plan"><?php foreach ($plans as $val => $label): ?><option value="<?php echo $val; ?>"><?php echo $label; ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label>API Endpoint</label><input type="text" name="api_endpoint" placeholder="https://api.example.com?cc={cc}"></div>
                        <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" value="0"></div>
                        <div class="form-group"><label>Description</label><textarea name="description" rows="2"></textarea></div>
                        <div class="form-group"><label>Enabled</label><label><input type="checkbox" name="enabled" checked> Yes</label></div>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Gate</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.8); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:#111114; border-radius:0.8rem; padding:1.5rem; width:90%; max-width:500px;">
            <h3>Edit Gate</h3>
            <form method="POST" id="editGateForm">
                <input type="hidden" name="action" value="update_gate">
                <input type="hidden" name="gate_key" id="edit_key">
                <div class="form-group"><label>Label</label><input type="text" name="gate_label" id="edit_label" required></div>
                <div class="form-group"><label>Category</label><select name="gate_category" id="edit_category"><?php foreach ($categories as $cat): ?><option value="<?php echo $cat['name']; ?>"><?php echo $cat['label']; ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Type</label><select name="gate_type" id="edit_type"><?php foreach ($gateTypes as $val => $label): ?><option value="<?php echo $val; ?>"><?php echo $label; ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Credit Cost</label><input type="number" name="credit_cost" id="edit_cost"></div>
                <div class="form-group"><label>Required Plan</label><select name="required_plan" id="edit_plan"><?php foreach ($plans as $val => $label): ?><option value="<?php echo $val; ?>"><?php echo $label; ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" id="edit_order"></div>
                <div class="form-group"><label>API Endpoint</label><input type="text" name="api_endpoint" id="edit_endpoint"></div>
                <div class="form-group"><label>Description</label><textarea name="description" id="edit_desc" rows="2"></textarea></div>
                <div class="form-group"><label>Enabled</label><label><input type="checkbox" name="enabled" id="edit_enabled"> Yes</label></div>
                <div style="display:flex; gap:0.5rem; margin-top:1rem;">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" onclick="closeModal()" class="btn btn-danger">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const gates = <?php echo json_encode($gates); ?>;
        
        function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tab + 'Tab').classList.add('active');
            event.target.classList.add('active');
        }
        
        function editGate(key) {
            const gate = gates.find(g => g.key === key);
            if (!gate) return;
            document.getElementById('edit_key').value = gate.key;
            document.getElementById('edit_label').value = gate.label;
            document.getElementById('edit_category').value = gate.category;
            document.getElementById('edit_type').value = gate.type;
            document.getElementById('edit_cost').value = gate.credit_cost;
            document.getElementById('edit_plan').value = gate.required_plan;
            document.getElementById('edit_order').value = gate.sort_order;
            document.getElementById('edit_endpoint').value = gate.api_endpoint || '';
            document.getElementById('edit_desc').value = gate.description || '';
            document.getElementById('edit_enabled').checked = gate.enabled == 1;
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
    </script>
</body>
</html>
