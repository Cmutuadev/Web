<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../checker_extensions.php';

$onlineUsers = 0;
$recentActivity = [];
$topPerformers = [];

// 🔥 REAL DATA FROM users.json
$users = json_decode(file_get_contents(__DIR__ . '/../users.json'), true) ?: [];
$currentTime = time();

foreach ($users as $user) {
    if (isset($user['last_activity']) && ($currentTime - $user['last_activity']) < 300) {
        $onlineUsers++;
    }
}

// 🔥 RECENT ACTIVITY from activity_log.json
if (file_exists(__DIR__ . '/../activity_log.json')) {
    $logLines = file(__DIR__ . '/../activity_log.json');
    $recentLines = array_slice($logLines, -10);
    foreach ($recentLines as $line) {
        $activity = json_decode(trim($line), true);
        if ($activity) {
            $recentActivity[] = [
                'card' => $activity['card'] ?? '****',
                'status' => $activity['status'] ?? 'UNKNOWN',
                'time' => date('H:i A', $activity['timestamp'] ?? time()),
                'user' => $activity['user_id'] ?? 'User'
            ];
        }
    }
}

// 🔥 TOP PERFORMERS
usort($users, function($a, $b) {
    return ($b['total_checks'] ?? 0) <=> ($a['total_checks'] ?? 0);
});
$topUsers = array_slice($users, 0, 5);
foreach ($topUsers as $i => $user) {
    $topPerformers[] = [
        'name' => $user['username'] ?? 'User' . ($i+1),
        'hits' => ($user['approved'] ?? 0) + ($user['charged'] ?? 0),
        'live' => isset($user['last_activity']) && ($currentTime - $user['last_activity']) < 300 ? 1 : 0
    ];
}

echo json_encode([
    'online_count' => $onlineUsers,
    'recent_activity' => array_reverse($recentActivity),
    'top_performers' => $topPerformers,
    'timestamp' => $currentTime
]);
?>