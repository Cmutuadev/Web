<?php
session_start();
$input = json_decode(file_get_contents('php://input'), true);

$activity = [
    'user_id' => $_SESSION['user']['username'] ?? $input['user_id'] ?? 'guest',
    'card' => $input['card'] ?? '****',
    'status' => $input['status'] ?? 'UNKNOWN',
    'timestamp' => time(),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
];

file_put_contents(__DIR__ . '/../activity_log.json', json_encode($activity) . "
", FILE_APPEND | LOCK_EX);
http_response_code(200);
?>