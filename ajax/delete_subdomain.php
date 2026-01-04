<?php
/**
 * Delete Subdomain AJAX Endpoint
 * Handles subdomain deletion requests
 */

session_start();

header('Content-Type: application/json');

// Load configuration, database, and deletion handler
// Check for config in these locations (in order)
$configPaths = [
    __DIR__ . '/../creator_config.php',  // Local directory (recommended)
    dirname(dirname(__DIR__)) . '/config/creator_config.php',  // Parent config directory
    $_SERVER['HOME'] . '/config/creator_config.php',  // User home config
];

$configPath = null;
foreach ($configPaths as $path) {
    if (file_exists($path)) {
        $configPath = $path;
        break;
    }
}

if (!$configPath) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration file not found']);
    exit;
}

$config = require $configPath;

require_once __DIR__ . '/../includes/db.php';

// Load deletion functions from index.php by requiring the functions we need
require_once __DIR__ . '/../includes/deletion_handler.php';

// Need to define these functions from index.php
function logMessage($message, $level = 'INFO') {
    global $config;
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $logEntry = json_encode([
        'timestamp' => $timestamp,
        'level' => $level,
        'message' => $message,
        'ip' => $ip,
    ]) . "\n";

    $logFile = $config['log_path'] ?? __DIR__ . '/../logs/subdomain_creation.log';
    error_log($logEntry, 3, $logFile);
}

function deleteCloudflareRecord($subdomainName, $config) {
    $fullDomain = "{$subdomainName}.{$config['domain']}";
    $listUrl = "https://api.cloudflare.com/client/v4/zones/{$config['cloudflare_zone_id']}/dns_records?name={$fullDomain}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $listUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$config['cloudflare_api_token']}",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);

    if (!$result['success'] || empty($result['result'])) {
        logMessage("Cloudflare DNS record not found for {$fullDomain}", 'WARNING');
        return ['success' => false, 'error' => 'DNS record not found'];
    }

    $recordId = $result['result'][0]['id'];
    $deleteUrl = "https://api.cloudflare.com/client/v4/zones/{$config['cloudflare_zone_id']}/dns_records/{$recordId}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $deleteUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$config['cloudflare_api_token']}",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);

    if ($result['success']) {
        logMessage("Cloudflare DNS record deleted for {$fullDomain}", 'SUCCESS');
        return ['success' => true];
    } else {
        $error = $result['errors'][0]['message'] ?? 'Unknown error';
        logMessage("Cloudflare DNS deletion failed: {$error}", 'ERROR');
        return ['success' => false, 'error' => $error];
    }
}

function deleteCPanelSubdomain($subdomainName, $config) {
    $url = "https://{$config['cpanel_host']}:2083/execute/SubDomain/delsubdomain";
    $params = http_build_query(['domain' => $subdomainName, 'rootdomain' => $config['domain']]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$url}?{$params}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: cpanel {$config['cpanel_user']}:{$config['cpanel_api_token']}"
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);

    if (isset($result['status']) && $result['status'] == 1) {
        logMessage("cPanel subdomain deleted: {$subdomainName}.{$config['domain']}", 'SUCCESS');
        return ['success' => true];
    } else {
        $error = $result['errors'][0] ?? 'Unknown error';
        logMessage("cPanel subdomain deletion failed: {$error}", 'ERROR');
        return ['success' => false, 'error' => $error];
    }
}

function deleteSubdomainDatabase($databaseName, $config) {
    if (empty($databaseName)) return ['success' => false, 'error' => 'Database name is empty'];

    $url = "https://{$config['cpanel_host']}:2083/execute/Mysql/delete_database";
    $params = http_build_query(['name' => $databaseName]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$url}?{$params}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: cpanel {$config['cpanel_user']}:{$config['cpanel_api_token']}"
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);

    if (isset($result['status']) && $result['status'] == 1) {
        logMessage("Database deleted: {$databaseName}", 'SUCCESS');
        return ['success' => true];
    } else {
        $error = $result['errors'][0] ?? 'Unknown error';
        logMessage("Database deletion failed: {$error}", 'ERROR');
        return ['success' => false, 'error' => $error];
    }
}

function recursiveDelete($dir, $config) {
    $safePath = realpath($dir);
    if ($safePath === false) {
        logMessage("Directory does not exist: {$dir}", 'WARNING');
        return ['success' => false, 'error' => 'Directory does not exist'];
    }

    // Get web root from config
    $webRoot = realpath($config['web_root']);
    $domain = $config['domain'];

    if (strpos($safePath, $webRoot . '/') !== 0) {
        logMessage("SECURITY: Attempted to delete path outside web root: {$safePath}", 'SECURITY');
        return ['success' => false, 'error' => 'Invalid path - must be under web root'];
    }

    if (strpos($safePath, '.' . $domain) === false) {
        logMessage("SECURITY: Attempted to delete path without .{$domain}: {$safePath}", 'SECURITY');
        return ['success' => false, 'error' => "Safety check failed - path must contain .{$domain}"];
    }

    if ($safePath === $webRoot) {
        logMessage("SECURITY: Attempted to delete web root", 'SECURITY');
        return ['success' => false, 'error' => 'Cannot delete web root'];
    }

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($safePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($safePath);
        logMessage("Directory deleted: {$safePath}", 'SUCCESS');
        return ['success' => true, 'deleted_path' => $safePath];
    } catch (Exception $e) {
        logMessage("Directory deletion failed: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Check authentication
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check session timeout
if (isset($_SESSION['login_time'])) {
    if (time() - $_SESSION['login_time'] > 1800) {
        session_destroy();
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Session expired']);
        exit;
    }
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['subdomain_id']) || !isset($input['confirm_name']) || !isset($input['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Verify CSRF token
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Check rate limiting
if (!checkDeletionRateLimit()) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Rate limit exceeded. Maximum 3 deletions per hour.']);
    exit;
}

$subdomain_id = (int)$input['subdomain_id'];
$confirm_name = trim($input['confirm_name']);

// Validate deletion request
$validation = validateDeletionRequest($subdomain_id, $confirm_name);

if (!$validation['valid']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $validation['error']]);
    exit;
}

// Execute deletion
$result = deleteSubdomain($subdomain_id, $config);

// Return result
echo json_encode($result);
