<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$packageType = $input['package_type'] ?? 'bronze';
$planUpgrade = $input['plan_upgrade'] ?? 'basic';
$count = min(max(intval($input['count'] ?? 1), 1), 100);

// Package definitions
$packages = [
    'bronze' => ['credits' => 500, 'days' => 1, 'prefix' => 'BRONZE'],
    'silver' => ['credits' => 2000, 'days' => 7, 'prefix' => 'SILVER'],
    'gold' => ['credits' => 5000, 'days' => 15, 'prefix' => 'GOLD'],
    'diamond' => ['credits' => 15000, 'days' => 30, 'prefix' => 'DIAMOND']
];

if (!isset($packages[$packageType])) {
    echo json_encode(['success' => false, 'error' => 'Invalid package type']);
    exit;
}

$package = $packages[$packageType];
$generatedKeys = [];
$pdo = getDB();

if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

for ($i = 0; $i < $count; $i++) {
    // Generate random key: PREFIX-XXXX-XXXX-XXXX
    $random1 = strtoupper(substr(bin2hex(random_bytes(4)), 0, 4));
    $random2 = strtoupper(substr(bin2hex(random_bytes(4)), 0, 4));
    $random3 = strtoupper(substr(bin2hex(random_bytes(4)), 0, 4));
    $keyCode = $package['prefix'] . '-' . $random1 . '-' . $random2 . '-' . $random3;
    
    // Check if key already exists (very unlikely but just in case)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM redeem_keys WHERE key_code = ?");
    $stmt->execute([$keyCode]);
    if ($stmt->fetchColumn() > 0) {
        $i--; // Retry
        continue;
    }
    
    // Insert into database
    $stmt = $pdo->prepare("INSERT INTO redeem_keys (key_code, credits, plan, duration_days, package_type, created_by, status) 
                           VALUES (?, ?, ?, ?, ?, ?, 'unused')");
    $stmt->execute([
        $keyCode,
        $package['credits'],
        $planUpgrade,
        $package['days'],
        $packageType,
        $_SESSION['user']['name'] ?? 'Admin'
    ]);
    
    $generatedKeys[] = $keyCode;
}

echo json_encode([
    'success' => true,
    'keys' => $generatedKeys,
    'count' => count($generatedKeys),
    'package' => $packageType,
    'credits' => $package['credits'],
    'days' => $package['days']
]);
?>
