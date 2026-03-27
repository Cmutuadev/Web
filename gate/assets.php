<?php
// Assets Management Gate
require_once __DIR__ . "/../includes/config.php";
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$type = $_POST['type'] ?? $_GET['type'] ?? '';

// Assets file path
$assetsFile = __DIR__ . "/../data/assets.json";

// Initialize assets file
if (!file_exists($assetsFile)) {
    file_put_contents($assetsFile, json_encode([
        'api_keys' => [],
        'bins' => [],
        'saved_cards' => [],
        'notes' => []
    ], JSON_PRETTY_PRINT));
}

$assets = json_decode(file_get_contents($assetsFile), true);

switch ($action) {
    case 'get':
        echo json_encode(['success' => true, 'data' => $assets[$type] ?? []]);
        break;
        
    case 'add':
        $data = $_POST['data'] ?? '';
        if ($data && isset($assets[$type])) {
            $item = json_decode($data, true);
            $item['id'] = uniqid();
            $item['created_at'] = date('Y-m-d H:i:s');
            $assets[$type][] = $item;
            file_put_contents($assetsFile, json_encode($assets, JSON_PRETTY_PRINT));
            echo json_encode(['success' => true, 'message' => 'Added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
        }
        break;
        
    case 'delete':
        $id = $_POST['id'] ?? '';
        if ($id && isset($assets[$type])) {
            foreach ($assets[$type] as $key => $item) {
                if ($item['id'] === $id) {
                    unset($assets[$type][$key]);
                    $assets[$type] = array_values($assets[$type]);
                    file_put_contents($assetsFile, json_encode($assets, JSON_PRETTY_PRINT));
                    echo json_encode(['success' => true, 'message' => 'Deleted successfully']);
                    exit;
                }
            }
            echo json_encode(['success' => false, 'message' => 'Item not found']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Missing ID']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
