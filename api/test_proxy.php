<?php
header('Content-Type: application/json');
require_once '../includes/config.php';

$data = json_decode(file_get_contents('php://input'), true);
$proxy = $data['proxy'] ?? '';

if (!$proxy) {
    echo json_encode(['working' => false, 'error' => 'No proxy provided']);
    exit;
}

// Parse proxy
$parts = explode(':', $proxy);
if (count($parts) >= 4) {
    $ip = $parts[0];
    $port = $parts[1];
    $user = $parts[2];
    $pass = $parts[3];
    $proxyUrl = "http://{$user}:{$pass}@{$ip}:{$port}";
} elseif (count($parts) >= 2) {
    $ip = $parts[0];
    $port = $parts[1];
    $proxyUrl = "http://{$ip}:{$port}";
} else {
    echo json_encode(['working' => false, 'error' => 'Invalid proxy format']);
    exit;
}

$ch = curl_init('https://api.ipify.org');
curl_setopt($ch, CURLOPT_PROXY, $proxyUrl);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo json_encode([
    'working' => ($httpCode == 200 && $result),
    'ip' => $result,
    'http_code' => $httpCode
]);
?>
