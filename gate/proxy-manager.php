<?php
require_once __DIR__ . "/../includes/proxy-functions.php";
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        $proxy = $_POST['proxy'] ?? $_GET['proxy'] ?? '';
        if (empty($proxy)) {
            echo json_encode(['success' => false, 'message' => 'Proxy address required']);
            exit;
        }
        $result = addProxy($proxy);
        echo json_encode($result);
        break;
        
    case 'add_bulk':
        $proxies = $_POST['proxies'] ?? $_GET['proxies'] ?? '';
        if (empty($proxies)) {
            echo json_encode(['success' => false, 'message' => 'Proxy list required']);
            exit;
        }
        $result = addProxies($proxies);
        echo json_encode($result);
        break;
        
    case 'delete':
        $proxy = $_POST['proxy'] ?? $_GET['proxy'] ?? '';
        if (empty($proxy)) {
            echo json_encode(['success' => false, 'message' => 'Proxy address required']);
            exit;
        }
        $result = deleteProxy($proxy);
        echo json_encode($result);
        break;
        
    case 'list':
        $proxies = getProxies();
        echo json_encode(['success' => true, 'proxies' => $proxies]);
        break;
        
    case 'stats':
        $stats = getProxyStats();
        echo json_encode(['success' => true, 'stats' => $stats]);
        break;
        
    case 'test':
        $proxy = $_POST['proxy'] ?? $_GET['proxy'] ?? '';
        if (empty($proxy)) {
            echo json_encode(['success' => false, 'message' => 'Proxy address required']);
            exit;
        }
        $result = testAndUpdateProxy($proxy);
        echo json_encode($result);
        break;
        
    case 'test_all':
        $proxies = getProxies();
        $results = [];
        foreach ($proxies as $proxy) {
            $test = testAndUpdateProxy($proxy['address']);
            $results[] = [
                'proxy' => $proxy['address'],
                'success' => $test['success'],
                'response_time' => $test['response_time'] ?? null,
                'message' => $test['message']
            ];
        }
        echo json_encode(['success' => true, 'results' => $results]);
        break;
        
    case 'export':
        $format = $_GET['format'] ?? 'txt';
        $content = exportProxies($format);
        header('Content-Disposition: attachment; filename="proxies.' . ($format === 'txt' ? 'txt' : 'json') . '"');
        header('Content-Type: text/plain');
        echo $content;
        break;
        
    case 'get_random':
        $proxy = getRandomProxy();
        if ($proxy) {
            echo json_encode(['success' => true, 'proxy' => $proxy]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No working proxies found']);
        }
        break;
        
    case 'get_rotating':
        $proxy = getRotatingProxy();
        if ($proxy) {
            echo json_encode(['success' => true, 'proxy' => $proxy]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No working proxies found']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
