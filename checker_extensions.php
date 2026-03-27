<?php
// checker_extensions.php
// Shared helpers for credits, user access, card parsing + STATS TRACKING 🔥

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Load users.json and refresh current user's session info.
 * Call once near the top of index.php after auth checks.
 */
function ce_refresh_user_from_json(bool $saveChanges = false): void
{
    $usersFile = __DIR__ . '/users.json';

    if (!isset($_SESSION['user']['username'])) {
        return;
    }

    if (!file_exists($usersFile)) {
        return;
    }

    $raw = file_get_contents($usersFile);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return;
    }

    $uname = $_SESSION['user']['username'];
    if (!isset($data[$uname]) || !is_array($data[$uname])) {
        return;
    }

    $u = $data[$uname];

    // 🔥 FULL STATS SYNC
    $_SESSION['user']['credits'] = $u['credits'] ?? 0;
    $_SESSION['user']['banned']  = !empty($u['banned']);
    $_SESSION['user']['approved'] = $u['approved'] ?? 0;
    $_SESSION['user']['charged'] = $u['charged'] ?? 0;
    $_SESSION['user']['total_checks'] = $u['total_checks'] ?? 0;
    $_SESSION['user']['last_activity'] = $u['last_activity'] ?? 0;

    // 🔥 SAVE CHANGES BACK TO JSON if requested
    if ($saveChanges && isset($data[$uname])) {
        $data[$uname] = array_merge($data[$uname], [
            'credits' => $_SESSION['user']['credits'] ?? 0,
            'banned' => $_SESSION['user']['banned'] ?? false,
            'approved' => $_SESSION['user']['approved'] ?? 0,
            'charged' => $_SESSION['user']['charged'] ?? 0,
            'total_checks' => $_SESSION['user']['total_checks'] ?? 0,
            'last_activity' => $_SESSION['user']['last_activity'] ?? time(),
            'username' => $uname
        ]);
        
        file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT));
    }
}

/**
 * Update user stats after card check
 */
function ce_update_user_stats(string $status): void
{
    if (!isset($_SESSION['user']['username'])) return;
    
    // Increment counters
    $_SESSION['user']['total_checks'] = ($_SESSION['user']['total_checks'] ?? 0) + 1;
    $_SESSION['user']['last_activity'] = time();
    
    if ($status === 'APPROVED') {
        $_SESSION['user']['approved'] = ($_SESSION['user']['approved'] ?? 0) + 1;
    }
    if ($status === 'CHARGED') {
        $_SESSION['user']['charged'] = ($_SESSION['user']['charged'] ?? 0) + 1;
    }
    
    // Save to JSON
    ce_refresh_user_from_json(true);
}

/**
 * Check if current user is banned or has no credits (non‑admin).
 * Returns array with flags to use in UI + backend.
 */
function ce_get_user_access_state(bool $isAdmin): array
{
    $credits = (float)($_SESSION['user']['credits'] ?? 0);
    $banned  = !empty($_SESSION['user']['banned']);

    $noCredit = !$isAdmin && ($credits <= 0);
    $blocked  = !$isAdmin && $banned;

    return [
        'credits'  => $credits,
        'approved' => $_SESSION['user']['approved'] ?? 0,
        'charged'  => $_SESSION['user']['charged'] ?? 0,
        'total_checks' => $_SESSION['user']['total_checks'] ?? 0,
        'noCredit' => $noCredit,
        'banned'   => $blocked,
    ];
}

/**
 * Normalize a single card line into a pipe‑separated string.
 * Accepts formats like:
 *  - 5510...|07|27|251
 *  - 4661... 10/27 718 | VISA ... US
 */
function ce_normalize_card_line(string $line): ?string
{
    $line = trim($line);
    if ($line === '') {
        return null;
    }

    // collapse whitespace
    $line = preg_replace('/s+/', ' ', $line);

    // normalize common separators to pipes
    $line = str_replace([' / ', '/', ' ', '‖'], '|', $line);

    // allow comma or semicolon separators if present
    $line = str_replace([',', ';'], '|', $line);

    // remove duplicate pipes
    $line = preg_replace('/|{2,}/', '|', $line);

    return $line;
}

/**
 * Apply normalization to all submitted cards (bulk textarea).
 */
function ce_normalize_card_input(string $bulk): array
{
    $lines = preg_split('/
|
|
/', $bulk);
    $out   = [];

    foreach ($lines as $l) {
        $norm = ce_normalize_card_line($l);
        if ($norm !== null) {
            $out[] = $norm;
        }
    }

    return $out;
}