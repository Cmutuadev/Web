<?php
// BIN Lookup API Proxy - Bypasses CORS and rate limits
header('Content-Type: application/json');

$bin = $_GET['bin'] ?? '';
if (empty($bin) || strlen($bin) < 6) {
    echo json_encode(['error' => 'Invalid BIN']);
    exit;
}

$bin = substr($bin, 0, 6);

// Try multiple APIs
$apis = [
    "https://lookup.binlist.net/{$bin}",
    "https://binlist.io/lookup/{$bin}/",
    "https://bin-ip-checker.p.rapidapi.com/?bin={$bin}",
    "https://data.handyapi.com/bin/{$bin}"
];

$result = null;
foreach ($apis as $api) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && !empty($response)) {
        $data = json_decode($response, true);
        if ($data && (isset($data['scheme']) || isset($data['brand']))) {
            $result = $data;
            break;
        }
    }
}

if ($result) {
    echo json_encode([
        'success' => true,
        'bin' => $bin,
        'scheme' => strtoupper($result['scheme'] ?? $result['brand'] ?? 'Unknown'),
        'type' => strtoupper($result['type'] ?? 'Unknown'),
        'bank' => $result['bank']['name'] ?? $result['bank'] ?? 'Unknown',
        'country' => $result['country']['alpha2'] ?? $result['country']['code'] ?? $result['country'] ?? 'N/A',
        'country_name' => $result['country']['name'] ?? $result['country'] ?? 'Unknown',
        'emoji' => $result['country']['emoji'] ?? '',
        'currency' => $result['country']['currency'] ?? 'USD'
    ]);
} else {
    // Return local fallback data for common BINs
    $fallback = [
        '550989' => ['scheme' => 'MASTERCARD', 'type' => 'DEBIT', 'bank' => 'Public Bank Berhad', 'country' => 'MY', 'country_name' => 'Malaysia', 'emoji' => '🇲🇾', 'currency' => 'MYR'],
        '411111' => ['scheme' => 'VISA', 'type' => 'CREDIT', 'bank' => 'Test Bank', 'country' => 'US', 'country_name' => 'United States', 'emoji' => '🇺🇸', 'currency' => 'USD'],
        '424242' => ['scheme' => 'VISA', 'type' => 'CREDIT', 'bank' => 'Test Bank', 'country' => 'US', 'country_name' => 'United States', 'emoji' => '🇺🇸', 'currency' => 'USD'],
        '378282' => ['scheme' => 'AMERICAN EXPRESS', 'type' => 'CREDIT', 'bank' => 'American Express', 'country' => 'US', 'country_name' => 'United States', 'emoji' => '🇺🇸', 'currency' => 'USD'],
        '371449' => ['scheme' => 'AMERICAN EXPRESS', 'type' => 'CREDIT', 'bank' => 'American Express', 'country' => 'US', 'country_name' => 'United States', 'emoji' => '🇺🇸', 'currency' => 'USD'],
        '601111' => ['scheme' => 'DISCOVER', 'type' => 'CREDIT', 'bank' => 'Discover Bank', 'country' => 'US', 'country_name' => 'United States', 'emoji' => '🇺🇸', 'currency' => 'USD'],
        '353011' => ['scheme' => 'JCB', 'type' => 'CREDIT', 'bank' => 'JCB', 'country' => 'JP', 'country_name' => 'Japan', 'emoji' => '🇯🇵', 'currency' => 'JPY'],
        '400000' => ['scheme' => 'VISA', 'type' => 'DEBIT', 'bank' => 'Test Bank', 'country' => 'US', 'country_name' => 'United States', 'emoji' => '🇺🇸', 'currency' => 'USD']
    ];
    
    if (isset($fallback[$bin])) {
        echo json_encode(array_merge(['success' => true], $fallback[$bin]));
    } else {
        echo json_encode(['success' => false, 'error' => 'BIN not found']);
    }
}
?>
