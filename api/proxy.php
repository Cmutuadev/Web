<?php
header('Content-Type: application/json');
session_start();

// Simple cache to avoid repeated calls
$cacheFile = __DIR__ . '/../data/proxy_cache.json';
$cacheTime = 300; // 5 minutes cache

$url = $_GET['url'] ?? '';
if (!$url) {
    echo json_encode(['error' => 'No URL provided']);
    exit;
}

// Check cache
if (file_exists($cacheFile)) {
    $cache = json_decode(file_get_contents($cacheFile), true);
    $cacheKey = md5($url);
    
    if (isset($cache[$cacheKey]) && (time() - $cache[$cacheKey]['time']) < $cacheTime) {
        echo $cache[$cacheKey]['data'];
        exit;
    }
} else {
    $cache = [];
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; ApprovedChecker/1.0)');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['error' => 'Request failed', 'http_code' => $httpCode]);
    exit;
}

// Cache the response
$cacheKey = md5($url);
$cache[$cacheKey] = [
    'time' => time(),
    'data' => $response
];

// Limit cache size to 100 entries
if (count($cache) > 100) {
    array_shift($cache);
}

file_put_contents($cacheFile, json_encode($cache));

echo $response;
?>
