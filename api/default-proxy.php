<?php
// Default proxy configuration
// You can add your default proxies here
$defaultProxies = [
    'http://proxy1.example.com:8080',
    'http://proxy2.example.com:8080',
    'socks5://proxy3.example.com:1080'
];

// Return a random default proxy
if (isset($_GET['get'])) {
    header('Content-Type: application/json');
    if (empty($defaultProxies)) {
        echo json_encode(['proxy' => null]);
    } else {
        $randomIndex = array_rand($defaultProxies);
        echo json_encode(['proxy' => $defaultProxies[$randomIndex]]);
    }
    exit;
}
?>
