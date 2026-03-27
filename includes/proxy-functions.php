<?php
// Proxy Management Functions
// Database file location
define('PROXY_DB', __DIR__ . '/../data/proxies.json');

// Initialize proxy database
function initProxyDB() {
    if (!file_exists(PROXY_DB)) {
        $data = ['proxies' => [], 'last_updated' => date('Y-m-d H:i:s')];
        file_put_contents(PROXY_DB, json_encode($data, JSON_PRETTY_PRINT));
    }
    return json_decode(file_get_contents(PROXY_DB), true);
}

// Save proxy database
function saveProxyDB($data) {
    $data['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents(PROXY_DB, json_encode($data, JSON_PRETTY_PRINT));
    return true;
}

// Add proxy
function addProxy($proxy) {
    $data = initProxyDB();
    
    // Parse proxy format
    $parts = explode(':', $proxy);
    if (count($parts) < 2) {
        return ['success' => false, 'message' => 'Invalid proxy format. Use: ip:port or ip:port:user:pass'];
    }
    
    $proxyData = [
        'address' => $proxy,
        'ip' => $parts[0],
        'port' => $parts[1],
        'username' => $parts[2] ?? '',
        'password' => $parts[3] ?? '',
        'added' => date('Y-m-d H:i:s'),
        'last_tested' => null,
        'status' => 'pending',
        'response_time' => null,
        'success_count' => 0,
        'fail_count' => 0
    ];
    
    // Check if already exists
    foreach ($data['proxies'] as $existing) {
        if ($existing['address'] === $proxy) {
            return ['success' => false, 'message' => 'Proxy already exists'];
        }
    }
    
    $data['proxies'][] = $proxyData;
    saveProxyDB($data);
    
    return ['success' => true, 'message' => 'Proxy added successfully'];
}

// Add multiple proxies
function addProxies($proxiesList) {
    $proxies = explode("\n", $proxiesList);
    $added = 0;
    $failed = 0;
    $errors = [];
    
    foreach ($proxies as $proxy) {
        $proxy = trim($proxy);
        if (empty($proxy)) continue;
        
        $result = addProxy($proxy);
        if ($result['success']) {
            $added++;
        } else {
            $failed++;
            $errors[] = $proxy . ': ' . $result['message'];
        }
    }
    
    return [
        'success' => true,
        'added' => $added,
        'failed' => $failed,
        'errors' => $errors
    ];
}

// Get all proxies
function getProxies() {
    $data = initProxyDB();
    return $data['proxies'];
}

// Delete proxy
function deleteProxy($address) {
    $data = initProxyDB();
    
    foreach ($data['proxies'] as $key => $proxy) {
        if ($proxy['address'] === $address) {
            unset($data['proxies'][$key]);
            $data['proxies'] = array_values($data['proxies']);
            saveProxyDB($data);
            return ['success' => true, 'message' => 'Proxy deleted'];
        }
    }
    
    return ['success' => false, 'message' => 'Proxy not found'];
}

// Test proxy
function testProxy($address, $timeout = 5) {
    $parts = explode(':', $address);
    if (count($parts) < 2) {
        return ['success' => false, 'message' => 'Invalid proxy format'];
    }
    
    $ip = $parts[0];
    $port = $parts[1];
    $username = $parts[2] ?? '';
    $password = $parts[3] ?? '';
    
    $startTime = microtime(true);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://httpbin.org/ip");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_PROXY, "$ip:$port");
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    
    if ($username && $password) {
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$username:$password");
    }
    
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $responseTime = round((microtime(true) - $startTime) * 1000, 2);
    
    if ($response && $httpCode === 200) {
        $data = json_decode($response, true);
        return [
            'success' => true,
            'message' => 'Proxy works',
            'response_time' => $responseTime,
            'ip' => $data['origin'] ?? $ip
        ];
    } else {
        return [
            'success' => false,
            'message' => $error ?: "Failed to connect (HTTP $httpCode)",
            'response_time' => $responseTime
        ];
    }
}

// Test and update proxy status
function testAndUpdateProxy($address) {
    $testResult = testProxy($address);
    
    $data = initProxyDB();
    foreach ($data['proxies'] as &$proxy) {
        if ($proxy['address'] === $address) {
            $proxy['last_tested'] = date('Y-m-d H:i:s');
            $proxy['status'] = $testResult['success'] ? 'working' : 'failed';
            $proxy['response_time'] = $testResult['response_time'] ?? null;
            
            if ($testResult['success']) {
                $proxy['success_count']++;
            } else {
                $proxy['fail_count']++;
            }
            
            saveProxyDB($data);
            break;
        }
    }
    
    return $testResult;
}

// Get random working proxy
function getRandomProxy() {
    $proxies = getProxies();
    $working = array_filter($proxies, function($p) {
        return $p['status'] === 'working';
    });
    
    if (empty($working)) {
        return null;
    }
    
    $working = array_values($working);
    return $working[array_rand($working)]['address'];
}

// Get proxy for rotation (round robin)
function getRotatingProxy() {
    static $index = 0;
    $proxies = getProxies();
    $working = array_filter($proxies, function($p) {
        return $p['status'] === 'working';
    });
    
    if (empty($working)) {
        return null;
    }
    
    $working = array_values($working);
    $proxy = $working[$index % count($working)];
    $index++;
    
    return $proxy['address'];
}

// Export proxies
function exportProxies($format = 'txt') {
    $proxies = getProxies();
    $output = '';
    
    if ($format === 'txt') {
        foreach ($proxies as $proxy) {
            $output .= $proxy['address'] . "\n";
        }
    } elseif ($format === 'json') {
        $output = json_encode($proxies, JSON_PRETTY_PRINT);
    }
    
    return $output;
}

// Get proxy statistics
function getProxyStats() {
    $proxies = getProxies();
    $total = count($proxies);
    $working = count(array_filter($proxies, function($p) {
        return $p['status'] === 'working';
    }));
    $failed = count(array_filter($proxies, function($p) {
        return $p['status'] === 'failed';
    }));
    $pending = $total - $working - $failed;
    
    $avgResponse = 0;
    $responseTimes = array_filter(array_column($proxies, 'response_time'));
    if (!empty($responseTimes)) {
        $avgResponse = array_sum($responseTimes) / count($responseTimes);
    }
    
    return [
        'total' => $total,
        'working' => $working,
        'failed' => $failed,
        'pending' => $pending,
        'avg_response' => round($avgResponse, 2),
        'last_updated' => initProxyDB()['last_updated']
    ];
}
?>
