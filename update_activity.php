<?php

require_once __DIR__ . '/gate/validkey.php';
validateApiKey();

session_start();

// Check if user is authenticated
if (!isset($_SESSION['user']) || $_SESSION['user']['auth_provider'] !== 'telegram') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Hardcoded database connection information
 $databaseUrl = 'postgresql://card_chk_db_user:Zm2zF0tYtCDNBfaxh46MPPhC0wrB5j4R@dpg-d3l08pmr433s738hj84g-a.oregon-postgres.render.com/card_chk_db';

// Database connection
try {
    // Parse the database URL
    $dbUrl = parse_url($databaseUrl);
    
    // Extract components with defaults
    $host = $dbUrl['host'] ?? null;
    $port = $dbUrl['port'] ?? 5432; // Default PostgreSQL port
    $user = $dbUrl['user'] ?? null;
    $pass = $dbUrl['pass'] ?? null;
    $path = $dbUrl['path'] ?? null;
    
    // Validate required components
    if (!$host || !$user || !$pass || !$path) {
        throw new Exception("Missing required database connection parameters");
    }
    
    // Remove leading slash from path to get database name
    $dbName = ltrim($path, '/');
    
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbName",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Check if table exists
    $tableExists = false;
    try {
        $stmt = $pdo->query("SELECT to_regclass('public.online_users')");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && $result['to_regclass']) {
            $tableExists = true;
        }
    } catch (Exception $e) {
        // Table doesn't exist
    }
    
    // Create table if it doesn't exist
    if (!$tableExists) {
        $pdo->exec("
            CREATE TABLE online_users (
                id SERIAL PRIMARY KEY,
                session_id VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                photo_url VARCHAR(255),
                telegram_id BIGINT,
                username VARCHAR(255),
                last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(session_id)
            );
        ");
    } else {
        // Check if columns exist and add them if they don't
        $columns = $pdo->query("SELECT column_name FROM information_schema.columns 
                               WHERE table_name = 'online_users'")->fetchAll(PDO::FETCH_COLUMN);
        
        $columnNames = array_flip($columns);
        
        // Add telegram_id column if it doesn't exist
        if (!isset($columnNames['telegram_id'])) {
            $pdo->exec("ALTER TABLE online_users ADD COLUMN telegram_id BIGINT");
        }
        
        // Add username column if it doesn't exist
        if (!isset($columnNames['username'])) {
            $pdo->exec("ALTER TABLE online_users ADD COLUMN username VARCHAR(255)");
        }
    }
    
    // Get current user information
    $sessionId = session_id(); // Use session ID as unique identifier
    $name = $_SESSION['user']['name'] ?? 'Unknown User';
    $photoUrl = $_SESSION['user']['photo_url'] ?? null;
    $telegramId = $_SESSION['user']['id'] ?? null; // Get Telegram ID from session
    $username = $_SESSION['user']['username'] ?? null; // Get Telegram username from session
    
    // Validate required fields
    if (empty($name)) {
        throw new Exception("User name cannot be empty");
    }
    
    // Update current user's last_activity timestamp using session_id
    $updateStmt = $pdo->prepare("
        INSERT INTO online_users (session_id, name, photo_url, telegram_id, username, last_activity)
        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT (session_id) DO UPDATE SET
            name = EXCLUDED.name,
            photo_url = EXCLUDED.photo_url,
            telegram_id = EXCLUDED.telegram_id,
            username = EXCLUDED.username,
            last_activity = CURRENT_TIMESTAMP
    ");
    $updateStmt->execute([$sessionId, $name, $photoUrl, $telegramId, $username]);
    
    // Clean up users not active in the last 10 seconds
    $cleanupStmt = $pdo->prepare("
        DELETE FROM online_users
        WHERE last_activity < NOW() - INTERVAL '10 seconds'
    ");
    $cleanupStmt->execute();
    
    // Get all online users (active in the last 10 seconds)
    $usersStmt = $pdo->prepare("
        SELECT session_id, name, photo_url, telegram_id, username, last_activity
        FROM online_users
        WHERE last_activity >= NOW() - INTERVAL '10 seconds'
        ORDER BY last_activity DESC
    ");
    $usersStmt->execute();
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format user data for response
    $formattedUsers = [];
    foreach ($users as $user) {
        // Generate avatar URL if not available
        $avatarUrl = $user['photo_url'];
        if (empty($avatarUrl)) {
            // Generate initials from name
            $initials = '';
            $words = explode(' ', trim($user['name']));
            foreach ($words as $word) {
                if (!empty($word)) {
                    $initials .= strtoupper(substr($word, 0, 1));
                    if (strlen($initials) >= 2) break;
                }
            }
            if (empty($initials)) $initials = 'U';
            $avatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode($initials) . '&background=3b82f6&color=fff&size=64';
        }
        
        // Format username with @ symbol
        $formattedUsername = $user['username'] ? '@' . $user['username'] : null;
        
        // Create user data array with is_online field instead of is_current_user
        $userData = [
            'name' => $user['name'],
            'username' => $formattedUsername,
            'photo_url' => $avatarUrl,
            'is_online' => true  // All users in this list are online
        ];
        
        $formattedUsers[] = $userData;
    }
    
    // Get total count of online users (including current user)
    $totalCount = count($formattedUsers);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'count' => $totalCount,
        'users' => $formattedUsers
    ]);
    
} catch (Exception $e) {
    error_log("Database error in update_activity.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
