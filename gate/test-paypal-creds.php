<?php
// Simple test script for PayPal credentials
header('Content-Type: application/json');

$clientId = $_GET['client_id'] ?? '';
$clientSecret = $_GET['client_secret'] ?? '';

if (empty($clientId) || empty($clientSecret)) {
    echo json_encode(['error' => 'Missing credentials']);
    exit;
}

$auth = base64_encode($clientId . ':' . $clientSecret);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.sandbox.paypal.com/v1/oauth2/token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Basic ' . $auth,
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo json_encode([
    'http_code' => $httpCode,
    'response' => json_decode($response, true),
    'curl_error' => $curlError
], JSON_PRETTY_PRINT);
?>
