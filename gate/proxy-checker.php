<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$gateway = $input['gateway'] ?? '';
$card = $input['card'] ?? '';
$cards = $input['cards'] ?? [];

$results = [];

if (!empty($cards)) {
    foreach ($cards as $c) {
        $results[] = processCard($gateway, $c);
    }
} elseif (!empty($card)) {
    $results[] = processCard($gateway, $card);
}

echo json_encode(['results' => $results]);

function processCard($gateway, $card) {
    $gatewayFile = __DIR__ . '/' . $gateway . '.php';
    
    if (!file_exists($gatewayFile)) {
        return ['card' => $card, 'status' => 'Error', 'message' => 'Gateway not found'];
    }
    
    ob_start();
    $_POST = ['cc' => $card];
    include $gatewayFile;
    $output = ob_get_clean();
    
    $status = 'DECLINED';
    $message = $output;
    
    if (stripos($output, 'APPROVED') !== false) {
        $status = 'APPROVED';
        $message = trim($output);
    } elseif (stripos($output, 'CHARGED') !== false) {
        $status = 'CHARGED';
        $message = trim($output);
    } elseif (stripos($output, '3DS') !== false) {
        $status = '3DS';
        $message = trim($output);
    }
    
    return [
        'card' => $card,
        'status' => $status,
        'message' => $message,
        'raw' => $output
    ];
}
?>
