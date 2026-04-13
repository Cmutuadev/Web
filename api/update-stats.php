<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$gateway = $input['gateway'] ?? '';
$status = $input['status'] ?? '';
$card = $input['card'] ?? '';
$amount = $input['amount'] ?? 0;
$response = $input['response'] ?? '';
$username = $_SESSION['user']['name'] ?? 'system';

if (empty($gateway)) {
    echo json_encode(['success' => false, 'error' => 'No gateway provided']);
    exit;
}

try {
    // Use the correct function name from your config
    $db = getMongoDB();
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    $statsCollection = $db->stats;
    $gatewayStatsCollection = $db->gateway_stats;
    
    $stat = [
        'gateway' => $gateway,
        'status' => $status,
        'card' => $card,
        'card_bin' => substr($card, 0, 6),
        'card_last4' => substr($card, -4),
        'amount' => $amount,
        'response' => $response,
        'username' => $username,
        'user_id' => $_SESSION['user']['user_id'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'created_at' => date('Y-m-d H:i:s'),
        'timestamp' => new MongoDB\BSON\UTCDateTime(time() * 1000)
    ];
    
    $result = $statsCollection->insertOne($stat);
    
    $gatewayStatsCollection->updateOne(
        ['gateway' => $gateway],
        [
            '$inc' => [
                'total_checks' => 1,
                'approved_checks' => ($status === 'APPROVED' || $status === 'CHARGED') ? 1 : 0,
                'declined_checks' => ($status === 'DECLINED') ? 1 : 0
            ],
            '$set' => ['last_used' => new MongoDB\BSON\UTCDateTime(time() * 1000)],
            '$push' => [
                'recent_checks' => [
                    '$each' => [[
                        'status' => $status,
                        'card_bin' => substr($card, 0, 6),
                        'timestamp' => new MongoDB\BSON\UTCDateTime(time() * 1000)
                    ]],
                    '$slice' => -20
                ]
            ]
        ],
        ['upsert' => true]
    );
    
    echo json_encode([
        'success' => true,
        'id' => (string)$result->getInsertedId()
    ]);
    
} catch (Exception $e) {
    error_log("MongoDB error in update-stats.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
