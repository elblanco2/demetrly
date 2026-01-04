<?php
/**
 * List Subdomains AJAX Endpoint
 * Returns paginated list of subdomains
 */

session_start();

header('Content-Type: application/json');

// Load configuration and database
require_once __DIR__ . '/../includes/db.php';

// Check authentication
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check session timeout
if (isset($_SESSION['login_time'])) {
    if (time() - $_SESSION['login_time'] > 1800) { // 30 minutes
        session_destroy();
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Session expired']);
        exit;
    }
}

// Get parameters
$status = $_GET['status'] ?? 'active';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// Validate status
if (!in_array($status, ['active', 'deleted', 'partially_deleted', 'all'])) {
    $status = 'active';
}

// Validate pagination
$limit = max(1, min($limit, 100)); // Between 1 and 100
$offset = max(0, $offset);

// Get subdomains
$subdomains = getAllSubdomains($status, $limit, $offset);
$totalCount = getSubdomainCount($status);

// Format dates for frontend
foreach ($subdomains as &$subdomain) {
    $subdomain['created_at_formatted'] = date('M j, Y g:i A', strtotime($subdomain['created_at']));
    $subdomain['ai_generated_text'] = $subdomain['ai_generated'] ? 'Yes' : 'No';
}

echo json_encode([
    'success' => true,
    'data' => $subdomains,
    'total' => $totalCount,
    'limit' => $limit,
    'offset' => $offset,
    'hasMore' => ($offset + $limit) < $totalCount
]);
