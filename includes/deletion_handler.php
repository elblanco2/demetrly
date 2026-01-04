<?php
/**
 * Subdomain Deletion Handler
 *
 * Orchestrates the complete deletion of a subdomain including:
 * - Cloudflare DNS record
 * - cPanel subdomain
 * - MySQL database
 * - Directory and files
 *
 * CRITICAL SAFETY: Only deletes subdomains tracked in database
 */

require_once __DIR__ . '/db.php';

/**
 * Check deletion rate limit
 * Max 3 deletions per hour per session
 */
function checkDeletionRateLimit() {
    if (!isset($_SESSION['subdomain_deletions'])) {
        $_SESSION['subdomain_deletions'] = [];
    }

    $oneHourAgo = time() - 3600;
    $_SESSION['subdomain_deletions'] = array_filter(
        $_SESSION['subdomain_deletions'],
        fn($t) => $t > $oneHourAgo
    );

    if (count($_SESSION['subdomain_deletions']) >= 3) {
        logMessage("Deletion rate limit exceeded", 'WARNING');
        return false;
    }

    return true;
}

/**
 * Record deletion attempt for rate limiting
 */
function recordDeletionAttempt() {
    if (!isset($_SESSION['subdomain_deletions'])) {
        $_SESSION['subdomain_deletions'] = [];
    }
    $_SESSION['subdomain_deletions'][] = time();
}

/**
 * Delete a subdomain completely
 *
 * @param int $subdomain_id Database ID of subdomain to delete
 * @param array $config Configuration array
 * @return array Results with success status and details
 */
function deleteSubdomain($subdomain_id, $config) {
    // CRITICAL: Verify subdomain is tracked in database
    $subdomain = getSubdomainById($subdomain_id);

    if (!$subdomain) {
        logMessage("Deletion blocked: Subdomain ID {$subdomain_id} not found in database", 'SECURITY');
        return [
            'success' => false,
            'error' => 'Subdomain not tracked in database - manual deletion required for safety'
        ];
    }

    // Check if already deleted
    if ($subdomain['status'] === 'deleted') {
        logMessage("Subdomain already marked as deleted: {$subdomain['subdomain_name']}", 'WARNING');
        return [
            'success' => false,
            'error' => 'Subdomain already deleted'
        ];
    }

    $subdomainName = $subdomain['subdomain_name'];
    logMessage("Starting deletion of subdomain: {$subdomainName} (ID: {$subdomain_id})", 'INFO');

    $results = [
        'success' => true,
        'subdomain_name' => $subdomainName,
        'subdomain_id' => $subdomain_id,
        'steps' => [],
        'errors' => []
    ];

    // Step 1: Delete Cloudflare DNS record
    logMessage("Step 1/4: Deleting Cloudflare DNS record...", 'INFO');
    $cfResult = deleteCloudflareRecord($subdomainName, $config);
    $results['cloudflare_deleted'] = $cfResult['success'];
    $results['steps'][] = 'Cloudflare DNS: ' . ($cfResult['success'] ? 'DELETED' : 'FAILED');

    if (!$cfResult['success']) {
        $results['errors'][] = 'Cloudflare: ' . $cfResult['error'];
        logMessage("Cloudflare deletion failed for {$subdomainName}: " . $cfResult['error'], 'ERROR');
    }

    // Step 2: Delete cPanel subdomain
    logMessage("Step 2/4: Deleting cPanel subdomain...", 'INFO');
    $cpanelResult = deleteCPanelSubdomain($subdomainName, $config);
    $results['cpanel_deleted'] = $cpanelResult['success'];
    $results['steps'][] = 'cPanel Subdomain: ' . ($cpanelResult['success'] ? 'DELETED' : 'FAILED');

    if (!$cpanelResult['success']) {
        $results['errors'][] = 'cPanel: ' . $cpanelResult['error'];
        logMessage("cPanel deletion failed for {$subdomainName}: " . $cpanelResult['error'], 'ERROR');
    }

    // Step 3: Delete database
    logMessage("Step 3/4: Deleting database...", 'INFO');
    if (!empty($subdomain['database_name'])) {
        $dbResult = deleteSubdomainDatabase($subdomain['database_name'], $config);
        $results['database_deleted'] = $dbResult['success'];
        $results['steps'][] = 'Database (' . $subdomain['database_name'] . '): ' . ($dbResult['success'] ? 'DELETED' : 'FAILED');

        if (!$dbResult['success']) {
            $results['errors'][] = 'Database: ' . $dbResult['error'];
            logMessage("Database deletion failed for {$subdomain['database_name']}: " . $dbResult['error'], 'ERROR');
        }
    } else {
        $results['database_deleted'] = true; // No database to delete
        $results['steps'][] = 'Database: NONE';
        logMessage("No database to delete for {$subdomainName}", 'INFO');
    }

    // Step 4: Delete directory and files
    logMessage("Step 4/4: Deleting directory and files...", 'INFO');
    if (!empty($subdomain['directory_path'])) {
        $dirResult = recursiveDelete($subdomain['directory_path'], $config);
        $results['files_deleted'] = $dirResult['success'];
        $results['steps'][] = 'Directory: ' . ($dirResult['success'] ? 'DELETED' : 'FAILED');

        if (!$dirResult['success']) {
            $results['errors'][] = 'Directory: ' . $dirResult['error'];
            logMessage("Directory deletion failed for {$subdomain['directory_path']}: " . $dirResult['error'], 'ERROR');
        }
    } else {
        $results['files_deleted'] = true; // No directory to delete
        $results['steps'][] = 'Directory: NONE';
        logMessage("No directory to delete for {$subdomainName}", 'INFO');
    }

    // Determine overall success
    $allDeleted = $results['cloudflare_deleted'] &&
                  $results['cpanel_deleted'] &&
                  $results['database_deleted'] &&
                  $results['files_deleted'];

    if (!$allDeleted) {
        $results['success'] = false;
        $failedSteps = [];
        if (!$results['cloudflare_deleted']) $failedSteps[] = 'Cloudflare';
        if (!$results['cpanel_deleted']) $failedSteps[] = 'cPanel';
        if (!$results['database_deleted']) $failedSteps[] = 'Database';
        if (!$results['files_deleted']) $failedSteps[] = 'Files';

        logMessage("Deletion partially failed for {$subdomainName}. Failed: " . implode(', ', $failedSteps), 'ERROR');
    } else {
        logMessage("Subdomain completely deleted: {$subdomainName}", 'SUCCESS');
    }

    // Step 5: Mark as deleted in tracking database
    $deletionDetails = [
        'cpanel_deleted' => $results['cpanel_deleted'],
        'cloudflare_deleted' => $results['cloudflare_deleted'],
        'database_deleted' => $results['database_deleted'],
        'files_deleted' => $results['files_deleted'],
        'errors' => $results['errors']
    ];

    $marked = markSubdomainDeleted($subdomain_id, $deletionDetails);

    if (!$marked) {
        logMessage("Warning: Failed to update database deletion status for {$subdomainName}", 'WARNING');
        $results['errors'][] = 'Failed to update tracking database';
    }

    // Record deletion attempt for rate limiting
    recordDeletionAttempt();

    return $results;
}

/**
 * Validate subdomain deletion request
 *
 * @param int $subdomain_id Subdomain ID to delete
 * @param string $confirmName User-entered subdomain name for confirmation
 * @return array Validation result
 */
function validateDeletionRequest($subdomain_id, $confirmName) {
    $subdomain = getSubdomainById($subdomain_id);

    if (!$subdomain) {
        return [
            'valid' => false,
            'error' => 'Subdomain not found in database'
        ];
    }

    if ($subdomain['status'] === 'deleted') {
        return [
            'valid' => false,
            'error' => 'Subdomain already deleted'
        ];
    }

    // Require exact name match for safety
    if ($confirmName !== $subdomain['subdomain_name']) {
        return [
            'valid' => false,
            'error' => 'Confirmation name does not match'
        ];
    }

    return [
        'valid' => true,
        'subdomain' => $subdomain
    ];
}

/**
 * Get deletion preview (what will be deleted)
 *
 * @param int $subdomain_id Subdomain ID
 * @return array|false Preview data or false
 */
function getDeletionPreview($subdomain_id) {
    $subdomain = getSubdomainById($subdomain_id);

    if (!$subdomain) {
        return false;
    }

    return [
        'subdomain_name' => $subdomain['subdomain_name'],
        'full_domain' => $subdomain['full_domain'],
        'database_name' => $subdomain['database_name'],
        'directory_path' => $subdomain['directory_path'],
        'created_at' => $subdomain['created_at'],
        'educational_focus' => $subdomain['educational_focus'],
        'status' => $subdomain['status']
    ];
}
