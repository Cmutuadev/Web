<?php
require_once __DIR__ . '/load_all_gates.php';

// Load all gates from all sources
$allGates = loadAllGates();
$groupedGates = groupGatesByCategory($allGates);

// Sort categories by order
uksort($groupedGates, function($a, $b) {
    $orderA = getCategoryInfo($a)['order'];
    $orderB = getCategoryInfo($b)['order'];
    return $orderA - $orderB;
});
?>

<!-- Add Custom Gate Button -->
<div class="nav-group">
    <div class="nav-group-header" onclick="toggleGroup('custom-manage')">
        <div class="nav-group-title">
            <i class="fas fa-cog"></i>
            <span>Manage Gates</span>
        </div>
        <i class="fas fa-chevron-down nav-group-arrow" id="arrow-custom-manage"></i>
    </div>
    <div class="nav-group-content" id="group-custom-manage" style="display: none;">
        <a href="#" onclick="openAddCustomGate()" class="nav-subitem">
            <i class="fas fa-plus-circle"></i>
            <span>➕ Add Custom Gate</span>
        </a>
        <a href="adminaccess_panel.php?tab=gates" class="nav-subitem">
            <i class="fas fa-crown"></i>
            <span>👑 Admin Gate Manager</span>
        </a>
    </div>
</div>

<?php foreach ($groupedGates as $category => $categoryGates): ?>
<?php $catInfo = getCategoryInfo($category); ?>
<div class="nav-group">
    <div class="nav-group-header" onclick="toggleGroup('<?php echo $category; ?>')">
        <div class="nav-group-title">
            <i class="fas <?php echo $catInfo['icon']; ?>"></i>
            <span><?php echo htmlspecialchars($catInfo['label']); ?></span>
        </div>
        <i class="fas fa-chevron-down nav-group-arrow" id="arrow-<?php echo $category; ?>"></i>
    </div>
    <div class="nav-group-content" id="group-<?php echo $category; ?>">
        <?php foreach ($categoryGates as $gate): ?>
            <?php 
            $gateKey = $gate['key'];
            $gateLabel = $gate['label'];
            $gateCost = $gate['credit_cost'] ?? 0;
            $isCustom = $gate['source'] === 'custom';
            $isAdminGate = $gate['source'] === 'admin';
            
            // Determine icon
            $icon = 'fa-credit-card';
            if (strpos($gateKey, 'shopify') !== false) $icon = 'fab fa-shopify';
            elseif (strpos($gateKey, 'stripe') !== false) $icon = 'fab fa-stripe';
            elseif (strpos($gateKey, 'razorpay') !== false) $icon = 'fas fa-rupee-sign';
            elseif (strpos($gateKey, 'paypal') !== false) $icon = 'fab fa-paypal';
            elseif (strpos($gateKey, 'auth') !== false) $icon = 'fas fa-shield-alt';
            elseif (strpos($gateKey, 'charge') !== false) $icon = 'fas fa-bolt';
            elseif ($isCustom) $icon = 'fa-star';
            elseif ($isAdminGate) $icon = 'fa-crown';
            ?>
            <a href="index.php?page=universal&gate=<?php echo urlencode($gateKey); ?>" class="nav-subitem">
                <i class="fas <?php echo $icon; ?>"></i>
                <span><?php echo htmlspecialchars($gateLabel); ?></span>
                <?php if ($gateCost > 0): ?>
                    <span style="margin-left: auto; font-size: 0.55rem; color: var(--primary);"><?php echo $gateCost; ?>c</span>
                <?php endif; ?>
                <?php if ($isCustom): ?>
                    <button class="delete-custom-gate" data-id="<?php echo $gate['custom_id']; ?>" onclick="event.preventDefault(); deleteCustomGate('<?php echo $gate['custom_id']; ?>')" style="background:none; border:none; color:var(--danger); cursor:pointer; margin-left:0.3rem;">
                        <i class="fas fa-trash-alt" style="font-size:0.55rem;"></i>
                    </button>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<script>
function openAddCustomGate() {
    Swal.fire({
        title: 'Add Custom Gateway',
        html: `
            <input type="text" id="customGateName" class="swal2-input" placeholder="Gate Name (e.g., My API)">
            <input type="url" id="customGateEndpoint" class="swal2-input" placeholder="API Endpoint (use {cc} for card)">
            <input type="number" id="customGateCost" class="swal2-input" placeholder="Credit Cost" value="5">
            <select id="customGateCategory" class="swal2-select">
                <option value="auto_checkers">Auto Checkers</option>
                <option value="checkers">Checkers</option>
                <option value="hitters">Hitters</option>
                <option value="tools">Tools</option>
                <option value="flex_tools">Flex Tools</option>
            </select>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Add Gateway',
        background: 'var(--card)',
        color: 'var(--text)',
        preConfirm: () => {
            const name = document.getElementById('customGateName').value;
            const endpoint = document.getElementById('customGateEndpoint').value;
            const cost = document.getElementById('customGateCost').value;
            const category = document.getElementById('customGateCategory').value;
            if (!name || !endpoint) {
                Swal.showValidationMessage('Please fill all fields');
                return false;
            }
            return { name, endpoint, cost, category };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('/api/add_custom_gate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: result.value.name,
                    endpoint: result.value.endpoint,
                    cost: parseInt(result.value.cost),
                    category: result.value.category
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success!', 'Custom gate added. Refresh page to see it.', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Swal.fire('Error', data.error || 'Failed to add gate', 'error');
                }
            })
            .catch(err => Swal.fire('Error', err.message, 'error'));
        }
    });
}

function deleteCustomGate(id) {
    Swal.fire({
        title: 'Delete Custom Gate?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete',
        background: 'var(--card)',
        color: 'var(--text)'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('/api/delete_custom_gate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Deleted!', 'Custom gate removed', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Swal.fire('Error', data.error, 'error');
                }
            });
        }
    });
}
</script>
