<?php
/**
 * Get Creation Logs AJAX Endpoint
 * Returns creation logs for a specific subdomain
 */

session_start();

header('Content-Type: application/json');

// Load database
require_once __DIR__ . '/../includes/db.php';

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

// Get subdomain ID
if (!isset($_GET['subdomain_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing subdomain_id']);
    exit;
}

$subdomain_id = (int)$_GET['subdomain_id'];

// Get subdomain details
$subdomain = getSubdomainById($subdomain_id);

if (!$subdomain) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Subdomain not found']);
    exit;
}

// Get creation logs
$logs = getCreationLogs($subdomain_id);

// Format timestamps
foreach ($logs as &$log) {
    $log['timestamp_formatted'] = date('M j, Y g:i:s A', strtotime($log['timestamp']));
}

// Get deletion log if subdomain was deleted
$deletionLog = null;
if (in_array($subdomain['status'], ['deleted', 'partially_deleted'])) {
    $deletionLog = getDeletionLog($subdomain_id);
    if ($deletionLog) {
        $deletionLog['deleted_at_formatted'] = date('M j, Y g:i A', strtotime($deletionLog['deleted_at']));
        $deletionLog['errors_decoded'] = !empty($deletionLog['errors']) ? json_decode($deletionLog['errors'], true) : [];
    }
}

echo json_encode([
    'success' => true,
    'subdomain' => [
        'id' => $subdomain['id'],
        'name' => $subdomain['subdomain_name'],
        'full_domain' => $subdomain['full_domain'],
        'status' => $subdomain['status'],
        'created_at' => date('M j, Y g:i A', strtotime($subdomain['created_at']))
    ],
    'creation_logs' => $logs,
    'deletion_log' => $deletionLog
]);
