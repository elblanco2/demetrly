<?php
/**
 * Subdomain Management - Database Module
 *
 * SQLite database for tracking created subdomains and deletion history
 *
 * Location: Configurable via creator_config.php or defaults to ./data/
 */

// Database configuration
// Default to data directory in application root
define('DB_PATH', __DIR__ . '/../data/subdomain_tracker.db');

/**
 * Get database connection
 * Creates database and tables if they don't exist
 */
function getDBConnection() {
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Set busy timeout to 5 seconds
        $db->setAttribute(PDO::ATTR_TIMEOUT, 5);

        // Enable WAL mode for better concurrency
        $db->exec('PRAGMA journal_mode = WAL');

        // Initialize schema if needed
        initializeSchema($db);

        return $db;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Initialize database schema
 */
function initializeSchema($db) {
    // Check if tables exist
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='subdomains'");

    if ($result->fetchColumn() === false) {
        // Create tables
        $db->exec("
            CREATE TABLE subdomains (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                subdomain_name TEXT NOT NULL UNIQUE,
                full_domain TEXT NOT NULL,
                educational_focus TEXT,
                primary_lms TEXT,
                description TEXT,
                ai_generated INTEGER DEFAULT 0,
                database_name TEXT,
                cloudflare_record_id TEXT,
                directory_path TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_by_ip TEXT,
                status TEXT DEFAULT 'active'
            );

            CREATE TABLE deletion_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                subdomain_id INTEGER NOT NULL,
                subdomain_name TEXT NOT NULL,
                deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                deleted_by_ip TEXT,
                cpanel_deleted INTEGER DEFAULT 0,
                cloudflare_deleted INTEGER DEFAULT 0,
                database_deleted INTEGER DEFAULT 0,
                files_deleted INTEGER DEFAULT 0,
                errors TEXT,
                FOREIGN KEY(subdomain_id) REFERENCES subdomains(id)
            );

            CREATE TABLE creation_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                subdomain_id INTEGER,
                subdomain_name TEXT NOT NULL,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                step_name TEXT,
                status TEXT,
                message TEXT,
                ip_address TEXT,
                FOREIGN KEY(subdomain_id) REFERENCES subdomains(id)
            );

            CREATE INDEX idx_subdomains_status ON subdomains(status);
            CREATE INDEX idx_subdomains_created ON subdomains(created_at);
            CREATE INDEX idx_deletion_log_subdomain ON deletion_log(subdomain_id);
            CREATE INDEX idx_creation_log_subdomain ON creation_log(subdomain_id);
        ");
    }
}

/**
 * Track a newly created subdomain
 *
 * @param array $data Subdomain data
 * @return int|false Subdomain ID or false on failure
 */
function trackSubdomainCreation($data) {
    $db = getDBConnection();
    if (!$db) return false;

    try {
        $stmt = $db->prepare("
            INSERT INTO subdomains (
                subdomain_name, full_domain, educational_focus, primary_lms, description,
                ai_generated, database_name, cloudflare_record_id, directory_path, created_by_ip
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['subdomain_name'],
            $data['full_domain'],
            $data['educational_focus'] ?? null,
            $data['primary_lms'] ?? null,
            $data['description'] ?? null,
            $data['ai_generated'] ?? 0,
            $data['database_name'],
            $data['cloudflare_record_id'] ?? null,
            $data['directory_path'],
            $data['created_by_ip'] ?? null
        ]);

        return $db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Failed to track subdomain: " . $e->getMessage());
        return false;
    }
}

/**
 * Log a creation step
 *
 * @param int $subdomain_id Subdomain ID
 * @param string $step Step name
 * @param string $status Status (SUCCESS, WARNING, ERROR)
 * @param string $message Message
 */
function logCreationStep($subdomain_id, $step, $status, $message) {
    $db = getDBConnection();
    if (!$db) return false;

    try {
        $stmt = $db->prepare("
            INSERT INTO creation_log (subdomain_id, subdomain_name, step_name, status, message, ip_address)
            SELECT ?, subdomain_name, ?, ?, ?, ?
            FROM subdomains WHERE id = ?
        ");

        $stmt->execute([
            $subdomain_id,
            $step,
            $status,
            $message,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $subdomain_id
        ]);

        return true;
    } catch (PDOException $e) {
        error_log("Failed to log creation step: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all subdomains with pagination
 *
 * @param string $status Status filter ('active', 'deleted', 'all')
 * @param int $limit Results per page
 * @param int $offset Offset for pagination
 * @return array Subdomains
 */
function getAllSubdomains($status = 'active', $limit = 20, $offset = 0) {
    $db = getDBConnection();
    if (!$db) return [];

    try {
        if ($status === 'all') {
            $stmt = $db->prepare("
                SELECT * FROM subdomains
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $offset]);
        } else {
            $stmt = $db->prepare("
                SELECT * FROM subdomains
                WHERE status = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$status, $limit, $offset]);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to get subdomains: " . $e->getMessage());
        return [];
    }
}

/**
 * Get total count of subdomains
 *
 * @param string $status Status filter
 * @return int Count
 */
function getSubdomainCount($status = 'active') {
    $db = getDBConnection();
    if (!$db) return 0;

    try {
        if ($status === 'all') {
            $stmt = $db->query("SELECT COUNT(*) FROM subdomains");
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM subdomains WHERE status = ?");
            $stmt->execute([$status]);
        }

        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Failed to count subdomains: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get single subdomain by ID
 *
 * @param int $id Subdomain ID
 * @return array|false Subdomain data or false
 */
function getSubdomainById($id) {
    $db = getDBConnection();
    if (!$db) return false;

    try {
        $stmt = $db->prepare("SELECT * FROM subdomains WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to get subdomain: " . $e->getMessage());
        return false;
    }
}

/**
 * Get subdomain by name
 *
 * @param string $name Subdomain name
 * @return array|false Subdomain data or false
 */
function getSubdomainByName($name) {
    $db = getDBConnection();
    if (!$db) return false;

    try {
        $stmt = $db->prepare("SELECT * FROM subdomains WHERE subdomain_name = ?");
        $stmt->execute([$name]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to get subdomain: " . $e->getMessage());
        return false;
    }
}

/**
 * Get creation logs for a subdomain
 *
 * @param int $subdomain_id Subdomain ID
 * @return array Logs
 */
function getCreationLogs($subdomain_id) {
    $db = getDBConnection();
    if (!$db) return [];

    try {
        $stmt = $db->prepare("
            SELECT * FROM creation_log
            WHERE subdomain_id = ?
            ORDER BY timestamp ASC
        ");
        $stmt->execute([$subdomain_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to get creation logs: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark subdomain as deleted
 *
 * @param int $subdomain_id Subdomain ID
 * @param array $details Deletion details
 * @return bool Success
 */
function markSubdomainDeleted($subdomain_id, $details) {
    $db = getDBConnection();
    if (!$db) return false;

    try {
        $db->beginTransaction();

        // Update subdomain status
        $status = 'deleted';
        if (!$details['cpanel_deleted'] || !$details['cloudflare_deleted'] ||
            !$details['database_deleted'] || !$details['files_deleted']) {
            $status = 'partially_deleted';
        }

        $stmt = $db->prepare("UPDATE subdomains SET status = ? WHERE id = ?");
        $stmt->execute([$status, $subdomain_id]);

        // Log deletion
        $stmt = $db->prepare("
            INSERT INTO deletion_log (
                subdomain_id, subdomain_name, deleted_by_ip,
                cpanel_deleted, cloudflare_deleted, database_deleted, files_deleted, errors
            )
            SELECT ?, subdomain_name, ?, ?, ?, ?, ?, ?
            FROM subdomains WHERE id = ?
        ");

        $stmt->execute([
            $subdomain_id,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $details['cpanel_deleted'] ? 1 : 0,
            $details['cloudflare_deleted'] ? 1 : 0,
            $details['database_deleted'] ? 1 : 0,
            $details['files_deleted'] ? 1 : 0,
            isset($details['errors']) ? json_encode($details['errors']) : null,
            $subdomain_id
        ]);

        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Failed to mark subdomain as deleted: " . $e->getMessage());
        return false;
    }
}

/**
 * Get deletion history for a subdomain
 *
 * @param int $subdomain_id Subdomain ID
 * @return array|false Deletion log or false
 */
function getDeletionLog($subdomain_id) {
    $db = getDBConnection();
    if (!$db) return false;

    try {
        $stmt = $db->prepare("SELECT * FROM deletion_log WHERE subdomain_id = ? ORDER BY deleted_at DESC LIMIT 1");
        $stmt->execute([$subdomain_id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to get deletion log: " . $e->getMessage());
        return false;
    }
}

/**
 * Export subdomains to array (for JSON/CSV)
 *
 * @param string $format Format ('json' or 'csv')
 * @return array Subdomains
 */
function exportSubdomains($format = 'json') {
    return getAllSubdomains('all', 999999, 0);
}

/**
 * Check if subdomain exists in tracking database
 *
 * @param string $name Subdomain name
 * @return bool Exists
 */
function subdomainExistsInDB($name) {
    $subdomain = getSubdomainByName($name);
    return $subdomain !== false;
}
