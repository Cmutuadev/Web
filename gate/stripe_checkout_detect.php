<?php
// Stripe Checkout Detection Gate
// Detects checkout info from URL using both APIs

header('Content-Type: application/json');

$checkoutUrl = $_POST['checkout_url'] ?? $_GET['checkout_url'] ?? '';
$gate = $_POST['gate'] ?? $_GET['gate'] ?? 'rylax';
$proxy = $_POST['proxy'] ?? $_GET['proxy'] ?? '';

if (empty($checkoutUrl)) {
    echo json_encode(['success' => false, 'error' => 'Missing checkout URL']);
    exit;
}

// Function to extract domain
function getDomain($url) {
    $parsed = parse_url($url);
    return $parsed['host'] ?? 'UNKNOWN';
}

if ($gate === 'rylax') {
    // Use Rylax API for detection
    $encodedUrl = urlencode($checkoutUrl);
    $apiUrl = "https://rylax.pro/bot.js/process?url={$encodedUrl}&cc=dummy";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if (!empty($proxy)) {
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo json_encode(['success' => false, 'error' => 'Failed to connect to Rylax API']);
        exit;
    }
    
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['success']) || $data['success'] !== true) {
        $errorMsg = $data['error'] ?? 'Checkout detection failed';
        $translations = [
            'No se pudo capturar la información del checkout' => 'Checkout expired or invalid',
            'URL inválida' => 'Invalid checkout URL',
            'Checkout expirado' => 'Checkout expired',
            'Claves no encontradas' => 'Stripe keys not found'
        ];
        foreach ($translations as $spanish => $english) {
            if (stripos($errorMsg, $spanish) !== false) {
                $errorMsg = $english;
                break;
            }
        }
        echo json_encode(['success' => false, 'error' => $errorMsg]);
        exit;
    }
    
    $checkoutData = $data['checkout_data'];
    $amount = $checkoutData['amount'] ?? 0;
    $currency = strtoupper($checkoutData['currency'] ?? 'USD');
    $amountDisplay = $amount > 0 ? '$' . number_format($amount / 100, 2) . ' ' . $currency : '$0.00 USD';
    
    $siteDomain = 'UNKNOWN';
    if (!empty($checkoutData['success_url'])) {
        $parsed = parse_url($checkoutData['success_url']);
        $siteDomain = $parsed['host'] ?? 'UNKNOWN';
    }
    
    echo json_encode([
        'success' => true,
        'pk_live' => $checkoutData['pk_live'] ?? 'N/A',
        'cs_live' => $checkoutData['cs_live'] ?? 'N/A',
        'site' => $siteDomain,
        'amount' => $amountDisplay,
        'product' => $checkoutData['product_name'] ?? 'Auto Checkout',
        'email' => $checkoutData['customer_email'] ?? 'N/A',
        'checkout_type' => strtoupper($checkoutData['mode'] ?? 'PAYMENT')
    ]);
    exit;
    
} elseif ($gate === 'stormx') {
    // Use StormX API for detection
    $API_KEY = "darkkboy";
    $stormxUrl = "https://hitter.stormx.pw/stripe-hitter";
    $encodedSite = urlencode($checkoutUrl);
    $testCard = "4242424242424242|12|2025|123";
    $fullUrl = "{$stormxUrl}?key={$API_KEY}&site={$encodedSite}&cc={$testCard}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X)");
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if (!empty($proxy)) {
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
    }
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($data && isset($data['success'])) {
        $siteDomain = getDomain($checkoutUrl);
        
        echo json_encode([
            'success' => true,
            'pk_live' => $data['pk_live'] ?? 'N/A',
            'cs_live' => $data['cs_live'] ?? 'N/A',
            'site' => $siteDomain,
            'amount' => 'Detected on process',
            'product' => 'Stripe Checkout',
            'email' => 'N/A',
            'checkout_type' => 'PAYMENT'
        ]);
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Could not detect checkout with StormX']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid gate selected']);
?>
