<?php
/**
 * Export Data AJAX Endpoint
 * Exports subdomain data as JSON or CSV
 */

session_start();

// Load database
require_once __DIR__ . '/../includes/db.php';

// Check authentication
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    http_response_code(401);
    die('Unauthorized');
}

// Check session timeout
if (isset($_SESSION['login_time'])) {
    if (time() - $_SESSION['login_time'] > 1800) {
        session_destroy();
        http_response_code(401);
        die('Session expired');
    }
}

// Get export format
$format = $_GET['format'] ?? 'json';

if (!in_array($format, ['json', 'csv'])) {
    $format = 'json';
}

// Get all subdomains
$subdomains = getAllSubdomains('all', 999999, 0);

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="subdomains-' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // CSV Header
    fputcsv($output, [
        'ID',
        'Subdomain',
        'Full Domain',
        'Educational Focus',
        'Primary LMS',
        'Description',
        'AI Generated',
        'Database Name',
        'Cloudflare Record ID',
        'Directory Path',
        'Status',
        'Created At',
        'Created By IP'
    ]);

    // CSV Data
    foreach ($subdomains as $sub) {
        fputcsv($output, [
            $sub['id'],
            $sub['subdomain_name'],
            $sub['full_domain'],
            $sub['educational_focus'],
            $sub['primary_lms'],
            $sub['description'],
            $sub['ai_generated'] ? 'Yes' : 'No',
            $sub['database_name'],
            $sub['cloudflare_record_id'],
            $sub['directory_path'],
            $sub['status'],
            $sub['created_at'],
            $sub['created_by_ip']
        ]);
    }

    fclose($output);
} else {
    // JSON export
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="subdomains-' . date('Y-m-d') . '.json"');

    $exportData = [
        'exported_at' => date('Y-m-d H:i:s'),
        'total_count' => count($subdomains),
        'counts_by_status' => [
            'active' => getSubdomainCount('active'),
            'deleted' => getSubdomainCount('deleted'),
            'partially_deleted' => getSubdomainCount('partially_deleted')
        ],
        'subdomains' => $subdomains
    ];

    echo json_encode($exportData, JSON_PRETTY_PRINT);
}
