<?php
/**
 * Subdomain Creation Agent for apiprofe.com
 *
 * Automates the creation of new subdomains through a web interface
 * Integrates with cPanel, Cloudflare, and AI APIs (Anthropic/Gemini)
 *
 * Based on the email webform app pattern with enhanced functionality
 *
 * @version 1.0
 * @author apiprofe.com Development Team
 */

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Load configuration
// Check for config in these locations (in order)
$configPaths = [
    __DIR__ . '/creator_config.php',  // Local directory (recommended)
    dirname(__DIR__) . '/config/creator_config.php',  // Parent config directory
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
    die('Configuration file not found. Please create creator_config.php in the application directory or see creator_config.sample.php');
}

$config = require $configPath;

// Constants
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('MAX_SUBDOMAINS_PER_HOUR', 5);
define('LOG_FILE', $config['log_path'] ?? __DIR__ . '/logs/subdomain_creation.log');

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

/**
 * Log a message to the subdomain creation log
 */
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $logEntry = json_encode([
        'timestamp' => $timestamp,
        'level' => $level,
        'message' => $message,
        'ip' => $ip,
    ]) . "\n";

    error_log($logEntry, 3, LOG_FILE);
}

/**
 * Generate a CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
        return false;
    }

    // Check session timeout
    if (isset($_SESSION['login_time'])) {
        if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
            session_destroy();
            return false;
        }
    }

    return true;
}

/**
 * Validate subdomain name
 */
function validateSubdomainName($name) {
    // Alphanumeric with hyphens only, 3-50 characters
    if (!preg_match('/^[a-z0-9-]{3,50}$/i', $name)) {
        return false;
    }

    // Cannot start or end with hyphen
    if (substr($name, 0, 1) === '-' || substr($name, -1) === '-') {
        return false;
    }

    // Reserved names
    $reserved = ['www', 'mail', 'ftp', 'admin', 'api', 'cpanel', 'webmail'];
    if (in_array(strtolower($name), $reserved)) {
        return false;
    }

    return true;
}

/**
 * Check rate limiting
 */
function checkRateLimit() {
    if (!isset($_SESSION['subdomain_creations'])) {
        $_SESSION['subdomain_creations'] = [];
    }

    // Remove entries older than 1 hour
    $oneHourAgo = time() - 3600;
    $_SESSION['subdomain_creations'] = array_filter(
        $_SESSION['subdomain_creations'],
        function($timestamp) use ($oneHourAgo) {
            return $timestamp > $oneHourAgo;
        }
    );

    // Check if limit exceeded
    return count($_SESSION['subdomain_creations']) < MAX_SUBDOMAINS_PER_HOUR;
}

/**
 * Record subdomain creation for rate limiting
 */
function recordSubdomainCreation() {
    if (!isset($_SESSION['subdomain_creations'])) {
        $_SESSION['subdomain_creations'] = [];
    }
    $_SESSION['subdomain_creations'][] = time();
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// =============================================================================
// API INTEGRATION FUNCTIONS
// =============================================================================

/**
 * Create cPanel subdomain
 */
function createCPanelSubdomain($subdomainName, $config) {
    $url = "https://{$config['cpanel_host']}:2083/execute/SubDomain/addsubdomain";

    $params = http_build_query([
        'domain' => $subdomainName,
        'rootdomain' => $config['domain'],
        'dir' => "public_html/{$subdomainName}.{$config['domain']}"
    ]);

    $fullUrl = $url . '?' . $params;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: cpanel {$config['cpanel_user']}:{$config['cpanel_token']}"
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        logMessage("cPanel API cURL error: {$curlError}", 'ERROR');
        return ['success' => false, 'error' => "Connection error: {$curlError}"];
    }

    $data = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($data['status']) && $data['status'] == 1) {
        logMessage("cPanel subdomain created: {$subdomainName}.{$config['domain']}", 'SUCCESS');
        return ['success' => true, 'data' => $data];
    } else {
        $error = $data['errors'][0] ?? 'Unknown error';
        logMessage("cPanel subdomain creation failed: {$error}", 'ERROR');
        return ['success' => false, 'error' => $error];
    }
}

/**
 * Create Cloudflare DNS record
 */
function createCloudflareRecord($subdomainName, $config) {
    $url = "https://api.cloudflare.com/client/v4/zones/{$config['cloudflare_zone_id']}/dns_records";

    $data = json_encode([
        'type' => 'CNAME',
        'name' => "{$subdomainName}.{$config['domain']}",
        'content' => $config['domain'],
        'ttl' => 1,
        'proxied' => true
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$config['cloudflare_api_token']}",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        logMessage("Cloudflare API cURL error: {$curlError}", 'ERROR');
        return ['success' => false, 'error' => "Connection error: {$curlError}"];
    }

    $result = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($result['success']) && $result['success']) {
        logMessage("Cloudflare DNS record created: {$subdomainName}.{$config['domain']}", 'SUCCESS');
        return ['success' => true, 'data' => $result['result']];
    } else {
        $error = $result['errors'][0]['message'] ?? 'Unknown error';
        logMessage("Cloudflare DNS creation failed: {$error}", 'ERROR');
        return ['success' => false, 'error' => $error];
    }
}

/**
 * Create subdomain database
 */
function createSubdomainDatabase($subdomainName, $config) {
    // Use cPanel username prefix as required by cPanel
    $dbName = $config['cpanel_user'] . "_" . preg_replace('/[^a-z0-9_]/i', '', $subdomainName);
    $dbName = substr($dbName, 0, 64); // MySQL database name limit

    $url = "https://{$config['cpanel_host']}:2083/execute/Mysql/create_database";

    $params = http_build_query([
        'name' => $dbName
    ]);

    $fullUrl = $url . '?' . $params;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: cpanel {$config['cpanel_user']}:{$config['cpanel_token']}"
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($data['status']) && $data['status'] == 1) {
        logMessage("Database created: {$dbName}", 'SUCCESS');
        return ['success' => true, 'database' => $dbName];
    } else {
        $error = $data['errors'][0] ?? 'Unknown error';
        logMessage("Database creation failed: {$error}", 'ERROR');
        return ['success' => false, 'error' => $error];
    }
}

/**
 * Generate AI content using Anthropic Claude
 */
function generateWithClaude($subdomainData, $config) {
    if (empty($config['anthropic_api_key']) || $config['anthropic_api_key'] === 'REPLACE_WITH_ANTHROPIC_KEY') {
        return ['success' => false, 'error' => 'Anthropic API key not configured'];
    }

    $prompt = "Generate content for a new educational subdomain:\n\n";
    $prompt .= "- Subdomain: {$subdomainData['name']}.{$config['domain']}\n";
    $prompt .= "- Educational Focus: {$subdomainData['focus']}\n";
    $prompt .= "- Primary LMS: {$subdomainData['lms']}\n";
    $prompt .= "- Description: {$subdomainData['description']}\n\n";
    $prompt .= "Please generate the following in JSON format:\n";
    $prompt .= "1. welcome_title: A welcoming title for the homepage\n";
    $prompt .= "2. welcome_content: 2-3 paragraphs of welcome text (HTML)\n";
    $prompt .= "3. tool_suggestions: Array of 3-5 tool ideas specific to this subject\n";
    $prompt .= "4. navigation_menu: Array of menu items (name and description)\n";
    $prompt .= "5. theme_colors: Object with primary_color and secondary_color (hex codes)\n";
    $prompt .= "6. hero_tagline: A catchy tagline for the subdomain\n\n";
    $prompt .= "Return ONLY valid JSON, no markdown formatting.";

    $requestData = [
        'model' => 'claude-sonnet-4-5-20250929',
        'max_tokens' => 4096,
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.anthropic.com/v1/messages');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-api-key: ' . $config['anthropic_api_key'],
        'anthropic-version: 2023-06-01',
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        logMessage("Claude API cURL error: {$curlError}", 'ERROR');
        return ['success' => false, 'error' => "Connection error: {$curlError}"];
    }

    $result = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($result['content'][0]['text'])) {
        $aiContent = $result['content'][0]['text'];

        // Try to parse JSON from AI response
        $contentData = json_decode($aiContent, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            logMessage("AI content generated successfully via Claude", 'SUCCESS');
            return ['success' => true, 'content' => $contentData];
        } else {
            // If not valid JSON, return raw content
            logMessage("AI content generated but not valid JSON", 'WARNING');
            return ['success' => true, 'content' => ['raw' => $aiContent]];
        }
    } else {
        $error = $result['error']['message'] ?? 'Unknown error';
        logMessage("Claude API error: {$error}", 'ERROR');
        return ['success' => false, 'error' => $error];
    }
}

/**
 * Generate AI content using Google Gemini
 */
function generateWithGemini($subdomainData, $config) {
    if (empty($config['gemini_api_key']) || $config['gemini_api_key'] === 'REPLACE_WITH_GEMINI_KEY') {
        return ['success' => false, 'error' => 'Gemini API key not configured'];
    }

    $prompt = "Generate content for a new educational subdomain:\n\n";
    $prompt .= "- Subdomain: {$subdomainData['name']}.{$config['domain']}\n";
    $prompt .= "- Educational Focus: {$subdomainData['focus']}\n";
    $prompt .= "- Primary LMS: {$subdomainData['lms']}\n";
    $prompt .= "- Description: {$subdomainData['description']}\n\n";
    $prompt .= "Please generate the following in JSON format:\n";
    $prompt .= "1. welcome_title: A welcoming title\n";
    $prompt .= "2. welcome_content: 2-3 paragraphs of welcome text (HTML)\n";
    $prompt .= "3. tool_suggestions: Array of 3-5 tool ideas\n";
    $prompt .= "4. navigation_menu: Array of menu items\n";
    $prompt .= "5. theme_colors: Object with primary_color and secondary_color\n";
    $prompt .= "6. hero_tagline: A catchy tagline\n\n";
    $prompt .= "Return ONLY valid JSON.";

    $requestData = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]
    ];

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={$config['gemini_api_key']}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        logMessage("Gemini API cURL error: {$curlError}", 'ERROR');
        return ['success' => false, 'error' => "Connection error: {$curlError}"];
    }

    $result = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $aiContent = $result['candidates'][0]['content']['parts'][0]['text'];

        // Try to parse JSON
        $contentData = json_decode($aiContent, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            logMessage("AI content generated successfully via Gemini", 'SUCCESS');
            return ['success' => true, 'content' => $contentData];
        } else {
            logMessage("AI content generated but not valid JSON", 'WARNING');
            return ['success' => true, 'content' => ['raw' => $aiContent]];
        }
    } else {
        $error = $result['error']['message'] ?? 'Unknown error';
        logMessage("Gemini API error: {$error}", 'ERROR');
        return ['success' => false, 'error' => $error];
    }
}

/**
 * Generate AI content (router function)
 */
function generateAIContent($subdomainData, $config) {
    $provider = $config['ai_provider'] ?? 'anthropic';

    if ($provider === 'gemini') {
        return generateWithGemini($subdomainData, $config);
    } else {
        return generateWithClaude($subdomainData, $config);
    }
}

// =============================================================================
// FILE SYSTEM OPERATIONS
// =============================================================================

/**
 * Create directory structure for subdomain
 */
function createDirectoryStructure($subdomainName, $config) {
    $basePath = $config['web_root'] . "/{$subdomainName}.{$config['domain']}";

    $directories = [
        $basePath,
        "{$basePath}/tools",
        "{$basePath}/lms",
        "{$basePath}/assets",
        "{$basePath}/assets/css",
        "{$basePath}/assets/js",
        "{$basePath}/assets/images",
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                logMessage("Failed to create directory: {$dir}", 'ERROR');
                return ['success' => false, 'error' => "Failed to create directory: {$dir}"];
            }
        }
    }

    logMessage("Directory structure created for {$subdomainName}.{$config['domain']}", 'SUCCESS');
    return ['success' => true, 'path' => $basePath];
}

/**
 * Recursive copy function
 */
function recursiveCopy($src, $dst) {
    if (!file_exists($src)) {
        return false;
    }

    $dir = opendir($src);
    @mkdir($dst, 0755, true);

    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                recursiveCopy($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }

    closedir($dir);
    return true;
}

/**
 * Copy template files to new subdomain
 */
function copyTemplateFiles($subdomainName, $config, $aiContent = null, $formData = null) {
    $templatePath = $config['template_path'];
    $targetPath = $config['web_root'] . "/{$subdomainName}.{$config['domain']}";
    $fullDomain = "{$subdomainName}.{$config['domain']}";

    if (!is_dir($templatePath)) {
        logMessage("Template directory not found: {$templatePath}", 'ERROR');
        return ['success' => false, 'error' => 'Template directory not found'];
    }

    // Check if this is v1.1 structure (has root/, ai/, uploads/ subdirectories)
    $isV11 = is_dir($templatePath . '/root') && is_dir($templatePath . '/ai') && is_dir($templatePath . '/uploads');

    if ($isV11) {
        // V1.1 Template Structure
        logMessage("Deploying AI Assistant v1.1 structure", 'INFO');

        // 1. Copy root/ files to subdomain root
        if (!recursiveCopy($templatePath . '/root', $targetPath)) {
            logMessage("Failed to copy root files to {$targetPath}", 'ERROR');
            return ['success' => false, 'error' => 'Failed to copy root files'];
        }

        // 2. Copy ai/ directory to /ai/ subdirectory
        $aiTargetPath = $targetPath . '/ai';
        if (!recursiveCopy($templatePath . '/ai', $aiTargetPath)) {
            logMessage("Failed to copy AI files to {$aiTargetPath}", 'ERROR');
            return ['success' => false, 'error' => 'Failed to copy AI files'];
        }

        // 3. Create uploads/ directory and copy .htaccess
        $uploadsPath = $targetPath . '/uploads';
        if (!is_dir($uploadsPath)) {
            mkdir($uploadsPath, 0755, true);
        }

        if (file_exists($templatePath . '/uploads/.htaccess')) {
            copy($templatePath . '/uploads/.htaccess', $uploadsPath . '/.htaccess');
        }

        // 4. Customize root/index.html
        customizeRootIndex($targetPath . '/index.html', $subdomainName, $fullDomain, $formData);

        // 5. Customize ai/index.html
        customizeAIIndex($aiTargetPath . '/index.html', $subdomainName, $fullDomain);

        // 6. Customize ai/config.php
        customizeAIConfig($aiTargetPath . '/config.php', $subdomainName, $fullDomain, $config, $formData);

        logMessage("AI Assistant v1.1 deployed to {$fullDomain}", 'SUCCESS');
    } else {
        // V1.0 Template Structure (backward compatibility)
        logMessage("Deploying v1.0 template structure", 'INFO');

        // Copy template files
        if (!recursiveCopy($templatePath, $targetPath)) {
            logMessage("Failed to copy template files to {$targetPath}", 'ERROR');
            return ['success' => false, 'error' => 'Failed to copy template files'];
        }

        // Customize index.php with AI content or defaults
        $aiContentData = ($aiContent && isset($aiContent['content'])) ? $aiContent['content'] : null;
        customizeIndexWithAI($targetPath . '/index.php', $aiContentData, [
            'name' => $subdomainName,
            'focus' => $formData['focus'] ?? ucfirst($subdomainName),
            'description' => $formData['description'] ?? 'Professional tools and resources for educators'
        ]);

        logMessage("Template files copied to {$fullDomain}", 'SUCCESS');
    }

    return ['success' => true];
}

/**
 * Customize index.php with AI-generated content or defaults
 */
function customizeIndexWithAI($indexPath, $aiContent, $subdomainData = null) {
    if (!file_exists($indexPath)) {
        return;
    }

    $content = file_get_contents($indexPath);

    // Determine content to use (AI or fallback defaults)
    // Use empty() to check for both null and empty strings
    $welcomeTitle = $aiContent['welcome_title'] ??
                    (!empty($subdomainData['focus']) ? $subdomainData['focus'] : ucfirst($subdomainData['name']));

    $heroTagline = $aiContent['hero_tagline'] ??
                   (!empty($subdomainData['description']) ? $subdomainData['description'] : 'Professional tools and resources for educators');

    $welcomeContent = $aiContent['welcome_content'] ??
                      '<p>This subdomain provides specialized tools and resources. Content coming soon!</p>';

    // Replace placeholders
    $content = str_replace('{{WELCOME_TITLE}}', sanitizeInput($welcomeTitle), $content);
    $content = str_replace('{{HERO_TAGLINE}}', sanitizeInput($heroTagline), $content);
    $content = str_replace('{{WELCOME_CONTENT}}', $welcomeContent, $content);

    file_put_contents($indexPath, $content);
}

/**
 * Customize root/index.html for v1.1 template
 */
function customizeRootIndex($indexPath, $subdomainName, $fullDomain, $formData) {
    if (!file_exists($indexPath)) {
        logMessage("Root index.html not found: {$indexPath}", 'WARNING');
        return;
    }

    $content = file_get_contents($indexPath);

    // Prepare replacement values
    $siteName = ucfirst($subdomainName);
    $description = $formData['description'] ?? "{$siteName} deployment hub";

    // Replace placeholders
    $content = str_replace('{{SITE_NAME}}', sanitizeInput($siteName), $content);
    $content = str_replace('{{FULL_DOMAIN}}', sanitizeInput($fullDomain), $content);
    $content = str_replace('{{DESCRIPTION}}', sanitizeInput($description), $content);

    file_put_contents($indexPath, $content);
    logMessage("Customized root index.html for {$fullDomain}", 'INFO');
}

/**
 * Customize ai/index.html for v1.1 template
 */
function customizeAIIndex($indexPath, $subdomainName, $fullDomain) {
    if (!file_exists($indexPath)) {
        logMessage("AI index.html not found: {$indexPath}", 'WARNING');
        return;
    }

    $content = file_get_contents($indexPath);

    // Replace placeholders
    $siteName = ucfirst($subdomainName);
    $content = str_replace('{{SITE_NAME}}', sanitizeInput($siteName), $content);
    $content = str_replace('{{FULL_DOMAIN}}', sanitizeInput($fullDomain), $content);

    file_put_contents($indexPath, $content);
    logMessage("Customized AI index.html for {$fullDomain}", 'INFO');
}

/**
 * Customize ai/config.php for v1.1 template
 */
function customizeAIConfig($configPath, $subdomainName, $fullDomain, $config, $formData) {
    $templateConfigPath = str_replace('/config.php', '/config.template.php', $configPath);

    if (!file_exists($templateConfigPath)) {
        logMessage("AI config template not found: {$templateConfigPath}", 'WARNING');
        return;
    }

    $content = file_get_contents($templateConfigPath);

    // Prepare replacement values
    $description = $formData['description'] ?? ucfirst($subdomainName) . " AI deployment assistant";
    $dbName = $config['db_prefix'] . $subdomainName;
    $dbUser = $config['db_user'] ?? '';
    $dbPass = $config['db_password'] ?? '';
    $apiKey = $config['anthropic_api_key'] ?? '';

    // Replace placeholders (match config.template.php exactly)
    $content = str_replace('{{SUBDOMAIN_NAME}}', sanitizeInput(ucfirst($subdomainName)), $content);
    $content = str_replace('{{FULL_DOMAIN}}', sanitizeInput($fullDomain), $content);
    $content = str_replace('{{DESCRIPTION}}', sanitizeInput($description), $content);
    $content = str_replace('{{DB_NAME}}', sanitizeInput($dbName), $content);
    $content = str_replace('{{DB_USER}}', sanitizeInput($dbUser), $content);
    $content = str_replace('{{DB_PASS}}', $dbPass, $content); // Don't sanitize password
    $content = str_replace('{{ANTHROPIC_API_KEY}}', $apiKey, $content); // Don't sanitize API key

    file_put_contents($configPath, $content);
    chmod($configPath, 0600); // Secure config file
    logMessage("Customized AI config for {$fullDomain}", 'INFO');
}

/**
 * Generate configuration file for subdomain
 */
function generateConfigFile($subdomainData, $config, $aiContent = null) {
    $targetPath = $config['web_root'] . "/{$subdomainData['name']}.{$config['domain']}";

    $primaryColor = '#3498db';
    $secondaryColor = '#2ecc71';

    // Use AI-suggested colors if available
    if ($aiContent && isset($aiContent['content']['theme_colors'])) {
        $primaryColor = $aiContent['content']['theme_colors']['primary_color'] ?? $primaryColor;
        $secondaryColor = $aiContent['content']['theme_colors']['secondary_color'] ?? $secondaryColor;
    }

    // Use subdomain name as fallback if focus is empty
    $displayName = !empty($subdomainData['focus']) ? $subdomainData['focus'] : ucfirst($subdomainData['name']);
    $description = !empty($subdomainData['description']) ? $subdomainData['description'] : 'Professional tools and resources for educators';

    $configContent = "<?php\n";
    $configContent .= "return [\n";
    $configContent .= "    // Basic Information\n";
    $configContent .= "    'subdomain_name' => '" . sanitizeInput($subdomainData['name']) . "',\n";
    $configContent .= "    'full_domain' => '" . sanitizeInput($subdomainData['name']) . ".{$config['domain']}',\n";
    $configContent .= "    'display_name' => '" . sanitizeInput($displayName) . "',\n";
    $configContent .= "    'educational_focus' => '" . sanitizeInput($displayName) . "',\n";
    $configContent .= "    'description' => '" . sanitizeInput($description) . "',\n";
    $configContent .= "\n";
    $configContent .= "    // LMS Integration Settings\n";
    $configContent .= "    'supported_lms' => ['" . sanitizeInput($subdomainData['lms']) . "'],\n";
    $configContent .= "    'primary_lms' => '" . sanitizeInput($subdomainData['lms']) . "',\n";
    $configContent .= "\n";
    $configContent .= "    // Feature Flags\n";
    $configContent .= "    'features' => [\n";
    $configContent .= "        'gradebook_sync' => false,\n";
    $configContent .= "        'assignment_creator' => false,\n";
    $configContent .= "        'resource_library' => true,\n";
    $configContent .= "    ],\n";
    $configContent .= "\n";
    $configContent .= "    // Theme Customization\n";
    $configContent .= "    'theme' => [\n";
    $configContent .= "        'primary_color' => '{$primaryColor}',\n";
    $configContent .= "        'secondary_color' => '{$secondaryColor}',\n";
    $configContent .= "        'icon' => 'graduation-cap',\n";
    $configContent .= "    ],\n";
    $configContent .= "\n";
    $configContent .= "    // Database (if needed)\n";
    $configContent .= "    'database' => [\n";
    $configContent .= "        'name' => '" . $config['cpanel_user'] . "_" . preg_replace('/[^a-z0-9_]/i', '', $subdomainData['name']) . "',\n";
    $configContent .= "        'prefix' => '" . sanitizeInput($subdomainData['name']) . "_',\n";
    $configContent .= "    ],\n";
    $configContent .= "\n";
    $configContent .= "    // Creation metadata\n";
    $configContent .= "    'created_at' => '" . date('Y-m-d H:i:s') . "',\n";
    $configContent .= "    'ai_generated' => " . ($aiContent ? 'true' : 'false') . ",\n";
    $configContent .= "];\n";

    $configPath = $targetPath . '/config.php';

    if (file_put_contents($configPath, $configContent)) {
        logMessage("Configuration file created for {$subdomainData['name']}.{$config['domain']}", 'SUCCESS');
        return ['success' => true];
    } else {
        logMessage("Failed to create configuration file", 'ERROR');
        return ['success' => false, 'error' => 'Failed to create configuration file'];
    }
}

// =============================================================================
// PRE-FLIGHT SAFETY CHECKS
// =============================================================================

/**
 * Check if directory already exists
 */
function checkDirectoryExists($subdomainName, $config) {
    $path = $config['web_root'] . "/{$subdomainName}.{$config['domain']}";
    return is_dir($path);
}

/**
 * Check if database already exists via cPanel API
 */
function checkDatabaseExists($subdomainName, $config) {
    $dbName = $config['cpanel_user'] . "_" . preg_replace('/[^a-z0-9_]/i', '', $subdomainName);

    $url = "https://{$config['cpanel_host']}:2083/execute/Mysql/list_databases";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: cpanel {$config['cpanel_user']}:{$config['cpanel_token']}"
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (isset($data['data']) && is_array($data['data'])) {
        foreach ($data['data'] as $db) {
            if ($db === $dbName || (isset($db['database']) && $db['database'] === $dbName)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Check if subdomain already exists in cPanel
 */
function checkSubdomainExists($subdomainName, $config) {
    $url = "https://{$config['cpanel_host']}:2083/execute/SubDomain/listsubdomains";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: cpanel {$config['cpanel_user']}:{$config['cpanel_token']}"
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    $fullDomain = "{$subdomainName}.{$config['domain']}";

    if (isset($data['data']) && is_array($data['data'])) {
        foreach ($data['data'] as $sub) {
            if (isset($sub['domain']) && $sub['domain'] === $fullDomain) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Check if DNS record already exists in Cloudflare
 */
function checkCloudflareRecordExists($subdomainName, $config) {
    $url = "https://api.cloudflare.com/client/v4/zones/{$config['cloudflare_zone_id']}/dns_records?name={$subdomainName}.{$config['domain']}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$config['cloudflare_api_token']}",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (isset($data['result']) && is_array($data['result']) && count($data['result']) > 0) {
        return true;
    }

    return false;
}

/**
 * Run all pre-flight safety checks
 */
function runPreflightChecks($subdomainName, $config) {
    $conflicts = [];

    // Check directory
    if (checkDirectoryExists($subdomainName, $config)) {
        $conflicts[] = "Directory already exists: /home/{$config['cpanel_user']}/public_html/{$subdomainName}.{$config['domain']}";
    }

    // Check database
    if (checkDatabaseExists($subdomainName, $config)) {
        $dbName = $config['cpanel_user'] . "_" . preg_replace('/[^a-z0-9_]/i', '', $subdomainName);
        $conflicts[] = "Database already exists: {$dbName}";
    }

    // Check subdomain
    if (checkSubdomainExists($subdomainName, $config)) {
        $conflicts[] = "Subdomain already exists in cPanel: {$subdomainName}.{$config['domain']}";
    }

    // Check Cloudflare DNS
    if (checkCloudflareRecordExists($subdomainName, $config)) {
        $conflicts[] = "DNS record already exists in Cloudflare: {$subdomainName}.{$config['domain']}";
    }

    return $conflicts;
}

// =============================================================================
// MAIN WORKFLOW
// =============================================================================

/**
 * Main subdomain creation workflow
 */
function createSubdomain($formData, $config) {
    $results = [
        'success' => true,
        'steps' => [],
        'errors' => [],
        'subdomain_url' => '',
        'completed_steps' => []
    ];

    $subdomainName = $formData['name'];

    try {
        // PRE-FLIGHT SAFETY CHECKS
        $results['steps'][] = 'Running safety checks...';
        $conflicts = runPreflightChecks($subdomainName, $config);

        if (!empty($conflicts)) {
            throw new Exception("Cannot create subdomain - conflicts detected:\n" . implode("\n", $conflicts));
        }

        $results['steps'][] = 'All safety checks passed ✓';

        // Step 1: Create cPanel subdomain
        $results['steps'][] = 'Creating cPanel subdomain...';
        $cpanelResult = createCPanelSubdomain($subdomainName, $config);
        if (!$cpanelResult['success']) {
            throw new Exception("cPanel creation failed: " . $cpanelResult['error']);
        }
        $results['completed_steps'][] = 'cpanel';

        // Step 2: Create Cloudflare DNS record
        $results['steps'][] = 'Creating Cloudflare DNS record...';
        $cfResult = createCloudflareRecord($subdomainName, $config);
        if (!$cfResult['success']) {
            throw new Exception("Cloudflare DNS failed: " . $cfResult['error']);
        }
        $results['completed_steps'][] = 'cloudflare';

        // Step 3: Create database
        $results['steps'][] = 'Creating database...';
        $dbResult = createSubdomainDatabase($subdomainName, $config);
        if (!$dbResult['success']) {
            throw new Exception("Database creation failed: " . $dbResult['error']);
        }
        $results['completed_steps'][] = 'database';

        // Step 4: Create directory structure
        $results['steps'][] = 'Setting up directory structure...';
        $dirResult = createDirectoryStructure($subdomainName, $config);
        if (!$dirResult['success']) {
            throw new Exception("Directory creation failed: " . $dirResult['error']);
        }
        $results['completed_steps'][] = 'directories';

        // Step 5: Generate AI content (if not skipped)
        $aiContent = null;
        if (!$formData['skip_ai']) {
            $results['steps'][] = 'Generating AI content...';
            $aiResult = generateAIContent($formData, $config);
            if ($aiResult['success']) {
                $aiContent = $aiResult;
            } else {
                // Log warning but don't fail the whole process
                logMessage("AI content generation failed: " . $aiResult['error'], 'WARNING');
                $results['steps'][] = 'AI content generation failed (continuing without it)';
            }
        }

        // Step 6: Copy and customize template files
        $results['steps'][] = 'Copying template files...';
        $copyResult = copyTemplateFiles($subdomainName, $config, $aiContent, $formData);
        if (!$copyResult['success']) {
            throw new Exception("Template copy failed: " . $copyResult['error']);
        }
        $results['completed_steps'][] = 'files';

        // Step 7: Generate config file
        $results['steps'][] = 'Creating configuration...';
        $configResult = generateConfigFile($formData, $config, $aiContent);
        if (!$configResult['success']) {
            throw new Exception("Config creation failed: " . $configResult['error']);
        }
        $results['completed_steps'][] = 'config';

        // Success!
        $results['subdomain_url'] = "https://{$subdomainName}.{$config['domain']}";
        $results['steps'][] = 'Subdomain created successfully!';

        // Log successful creation
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => 'subdomain_created',
            'subdomain' => "{$subdomainName}.{$config['domain']}",
            'educational_focus' => $formData['focus'],
            'primary_lms' => $formData['lms'],
            'ai_generated' => !$formData['skip_ai'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'success' => true
        ];
        error_log(json_encode($logData) . "\n", 3, LOG_FILE);

        // Track in database
        require_once __DIR__ . '/includes/db.php';

        try {
            $trackingData = [
                'subdomain_name' => $subdomainName,
                'full_domain' => "{$subdomainName}.{$config['domain']}",
                'educational_focus' => $formData['focus'],
                'primary_lms' => $formData['lms'],
                'description' => $formData['description'],
                'ai_generated' => !$formData['skip_ai'] ? 1 : 0,
                'database_name' => $config['cpanel_user'] . "_" . preg_replace('/[^a-z0-9_]/i', '', $subdomainName),
                'cloudflare_record_id' => $cfResult['data']['id'] ?? null,
                'directory_path' => $config['web_root'] . "/{$subdomainName}.{$config['domain']}",
                'created_by_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ];

            $subdomainId = trackSubdomainCreation($trackingData);

            if ($subdomainId) {
                // Log each creation step to database
                foreach ($results['steps'] as $step) {
                    logCreationStep($subdomainId, $step, 'SUCCESS', $step);
                }
                logMessage("Subdomain tracked in database (ID: {$subdomainId})", 'SUCCESS');
            } else {
                logMessage("Warning: Subdomain created but not tracked in database", 'WARNING');
            }
        } catch (Exception $dbError) {
            // Log error but don't fail the creation
            logMessage("Database tracking failed: " . $dbError->getMessage(), 'WARNING');
        }

    } catch (Exception $e) {
        $results['success'] = false;
        $results['errors'][] = $e->getMessage();
        logMessage("Subdomain creation failed: " . $e->getMessage(), 'ERROR');

        // Rollback (implement rollback functions as needed)
        // rollbackSubdomain($subdomainName, $results['completed_steps'], $config);
    }

    return $results;
}

// =============================================================================
// DELETION FUNCTIONS
// =============================================================================

/**
 * Delete Cloudflare DNS record for subdomain
 */
function deleteCloudflareRecord($subdomainName, $config) {
    $fullDomain = "{$subdomainName}.{$config['domain']}";

    // Step 1: Find the DNS record ID
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        logMessage("Cloudflare list DNS cURL error: {$curlError}", 'ERROR');
        return ['success' => false, 'error' => "Connection error: {$curlError}"];
    }

    $result = json_decode($response, true);

    if (!$result['success'] || empty($result['result'])) {
        logMessage("Cloudflare DNS record not found for {$fullDomain}", 'WARNING');
        return ['success' => false, 'error' => 'DNS record not found'];
    }

    $recordId = $result['result'][0]['id'];

    // Step 2: Delete the DNS record
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        logMessage("Cloudflare delete DNS cURL error: {$curlError}", 'ERROR');
        return ['success' => false, 'error' => "Connection error: {$curlError}"];
    }

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

/**
 * Delete cPanel subdomain
 */
function deleteCPanelSubdomain($subdomainName, $config) {
    $url = "https://{$config['cpanel_host']}:2083/execute/SubDomain/delsubdomain";
    $params = http_build_query([
        'domain' => $subdomainName,
        'rootdomain' => $config['domain']
    ]);

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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        logMessage("cPanel subdomain delete cURL error: {$curlError}", 'ERROR');
        return ['success' => false, 'error' => "Connection error: {$curlError}"];
    }

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

/**
 * Delete subdomain database
 */
function deleteSubdomainDatabase($databaseName, $config) {
    if (empty($databaseName)) {
        return ['success' => false, 'error' => 'Database name is empty'];
    }

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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        logMessage("Database delete cURL error: {$curlError}", 'ERROR');
        return ['success' => false, 'error' => "Connection error: {$curlError}"];
    }

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

/**
 * Recursively delete directory and all contents
 *
 * SAFETY CHECKS:
 * - Must contain domain name
 * - Must be under configured web root
 * - Cannot delete web root
 */
function recursiveDelete($dir, $config) {
    // Get real path
    $safePath = realpath($dir);

    if ($safePath === false) {
        logMessage("Directory does not exist: {$dir}", 'WARNING');
        return ['success' => false, 'error' => 'Directory does not exist'];
    }

    // Get web root from config
    $webRoot = realpath($config['web_root']);
    $domain = $config['domain'];

    // SAFETY CHECK 1: Must be under web root
    if (strpos($safePath, $webRoot . '/') !== 0) {
        logMessage("SECURITY: Attempted to delete path outside web root: {$safePath}", 'SECURITY');
        return ['success' => false, 'error' => 'Invalid path - must be under web root'];
    }

    // SAFETY CHECK 2: Must contain domain name
    if (strpos($safePath, '.' . $domain) === false) {
        logMessage("SECURITY: Attempted to delete path without .{$domain}: {$safePath}", 'SECURITY');
        return ['success' => false, 'error' => "Safety check failed - path must contain .{$domain}"];
    }

    // SAFETY CHECK 3: Cannot delete web root itself
    if ($safePath === $webRoot) {
        logMessage("SECURITY: Attempted to delete web root", 'SECURITY');
        return ['success' => false, 'error' => 'Cannot delete web root'];
    }

    // Recursive deletion
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($safePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($safePath);

        logMessage("Directory deleted: {$safePath}", 'SUCCESS');
        return ['success' => true, 'deleted_path' => $safePath];
    } catch (Exception $e) {
        logMessage("Directory deletion failed: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// =============================================================================
// AUTHENTICATION HANDLING
// =============================================================================

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_key']) && !isAuthenticated()) {
    $adminKey = $_POST['admin_key'] ?? '';

    if (password_verify($adminKey, $config['admin_key_hash'])) {
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();
        logMessage("Admin logged in successfully", 'INFO');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $loginError = "Invalid admin password";
        logMessage("Failed login attempt", 'WARNING');
    }
}

// =============================================================================
// SUBDOMAIN CREATION HANDLING
// =============================================================================

$creationResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAuthenticated() && isset($_POST['create_subdomain'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $creationResult = [
            'success' => false,
            'errors' => ['Invalid CSRF token']
        ];
    } else {
        // Check rate limiting
        if (!checkRateLimit()) {
            $creationResult = [
                'success' => false,
                'errors' => ['Rate limit exceeded. Maximum ' . MAX_SUBDOMAINS_PER_HOUR . ' subdomains per hour.']
            ];
        } else {
            // Validate and sanitize input
            $subdomainName = strtolower(trim($_POST['subdomain_name'] ?? ''));

            if (!validateSubdomainName($subdomainName)) {
                $creationResult = [
                    'success' => false,
                    'errors' => ['Invalid subdomain name. Use only letters, numbers, and hyphens (3-50 characters).']
                ];
            } else {
                $formData = [
                    'name' => $subdomainName,
                    'focus' => sanitizeInput($_POST['educational_focus'] ?? $subdomainName),
                    'lms' => sanitizeInput($_POST['primary_lms'] ?? 'none'),
                    'description' => sanitizeInput($_POST['description'] ?? ''),
                    'skip_ai' => isset($_POST['skip_ai'])
                ];

                // Create the subdomain
                $creationResult = createSubdomain($formData, $config);

                if ($creationResult['success']) {
                    recordSubdomainCreation();

                    // Store result in session and redirect to prevent form resubmission (POST-Redirect-GET pattern)
                    $_SESSION['creation_result'] = $creationResult;
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?created=1');
                    exit;
                }
            }
        }
    }
}

// Check if we're displaying a result from redirect
if (isset($_GET['created']) && isset($_SESSION['creation_result'])) {
    $creationResult = $_SESSION['creation_result'];
    unset($_SESSION['creation_result']); // Clear it so it doesn't show again
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demetrly - Subdomain Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        input[type="text"],
        input[type="password"],
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        button:active {
            transform: translateY(0);
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border-left: 4px solid #3c3;
        }

        .progress-steps {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 6px;
            margin-top: 20px;
        }

        .progress-steps h3 {
            margin-bottom: 15px;
            color: #333;
            font-size: 16px;
        }

        .step {
            padding: 8px 0;
            color: #666;
            font-size: 14px;
        }

        .step.completed::before {
            content: "✓ ";
            color: #3c3;
            font-weight: bold;
        }

        .subdomain-url {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
            text-align: center;
        }

        .subdomain-url a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 18px;
        }

        .subdomain-url a:hover {
            text-decoration: underline;
        }

        .logout-link {
            text-align: center;
            margin-top: 20px;
        }

        .logout-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }

        .logout-link a:hover {
            text-decoration: underline;
        }

        .field-note {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .required {
            color: #c33;
        }
    </style>
    <link rel="stylesheet" href="assets/css/manager.css">
    <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
</head>
<body>
    <div class="container">
        <?php if (!isAuthenticated()): ?>
            <!-- Login Form -->
            <h1>Subdomain Creation Agent</h1>
            <p class="subtitle">Admin access required</p>

            <?php if (isset($loginError)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($loginError); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="admin_key">Admin Password</label>
                    <input type="password" id="admin_key" name="admin_key" required autofocus>
                </div>
                <button type="submit">Login</button>
            </form>

        <?php else: ?>
            <!-- Tabbed Interface -->
            <h1>Subdomain Manager</h1>
            <p class="subtitle">Create and manage subdomains for <?php echo htmlspecialchars($config['domain']); ?></p>

            <div class="tab-container">
                <div class="tabs">
                    <button class="tab-button active" data-tab="create">Create Subdomain</button>
                    <button class="tab-button" data-tab="manage">Manage Subdomains</button>
                </div>

                <!-- Create Tab -->
                <div class="tab-content active" id="create-tab">

            <?php if ($creationResult): ?>
                <?php if ($creationResult['success']): ?>
                    <div class="alert alert-success">
                        <strong>Success!</strong> Subdomain created successfully.

                        <?php if (!empty($creationResult['subdomain_url'])): ?>
                            <div class="subdomain-url">
                                <a href="<?php echo htmlspecialchars($creationResult['subdomain_url']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($creationResult['subdomain_url']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($creationResult['steps'])): ?>
                        <div class="progress-steps">
                            <h3>Creation Steps:</h3>
                            <?php foreach ($creationResult['steps'] as $step): ?>
                                <div class="step completed"><?php echo htmlspecialchars($step); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="alert alert-error">
                        <strong>Error!</strong> Subdomain creation failed.
                        <ul style="margin-top: 10px; margin-left: 20px;">
                            <?php foreach ($creationResult['errors'] as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <?php if (!empty($creationResult['steps'])): ?>
                        <div class="progress-steps">
                            <h3>Completed Steps Before Failure:</h3>
                            <?php foreach ($creationResult['steps'] as $step): ?>
                                <div class="step"><?php echo htmlspecialchars($step); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="form-group">
                    <label for="subdomain_name">Subdomain Name <span class="required">*</span></label>
                    <input type="text" id="subdomain_name" name="subdomain_name" required
                           placeholder="e.g., art, physics, moodle" pattern="[a-zA-Z0-9-]{3,50}">
                    <div class="field-note">Alphanumeric with hyphens only, 3-50 characters. Will become [name].{$config['domain']}</div>
                </div>

                <div class="form-group">
                    <label for="educational_focus">Educational Focus</label>
                    <input type="text" id="educational_focus" name="educational_focus"
                           placeholder="e.g., Art Education, Physics Teaching Tools">
                    <div class="field-note">Optional: Used by AI to generate relevant content</div>
                </div>

                <div class="form-group">
                    <label for="primary_lms">Primary LMS</label>
                    <select id="primary_lms" name="primary_lms">
                        <option value="none">None</option>
                        <option value="canvas">Canvas</option>
                        <option value="moodle">Moodle</option>
                        <option value="blackboard">Blackboard</option>
                        <option value="google_classroom">Google Classroom</option>
                    </select>
                    <div class="field-note">Optional: Which LMS integration to enable</div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"
                              placeholder="Brief description of this subdomain's purpose..."></textarea>
                    <div class="field-note">Optional: Used by AI for content generation</div>
                </div>

                <div class="form-group checkbox-group">
                    <input type="checkbox" id="skip_ai" name="skip_ai" value="1">
                    <label for="skip_ai" style="margin-bottom: 0;">Skip AI content generation</label>
                </div>
                <div class="field-note" style="margin-top: -10px; margin-bottom: 20px;">
                    Check to use default template content instead of AI-generated content
                </div>

                <button type="submit" name="create_subdomain">Create Subdomain</button>
            </form>
                </div>
                <!-- End Create Tab -->

                <!-- Manage Tab -->
                <div class="tab-content" id="manage-tab">
                    <div class="management-header">
                        <h2>All Subdomains</h2>
                        <div class="actions">
                            <button id="refresh-btn">Refresh</button>
                            <button id="export-json-btn">Export JSON</button>
                            <button id="export-csv-btn">Export CSV</button>
                        </div>
                    </div>

                    <div class="filter-section">
                        <label for="status-filter">Status:</label>
                        <select id="status-filter">
                            <option value="active">Active</option>
                            <option value="deleted">Deleted</option>
                            <option value="partially_deleted">Partially Deleted</option>
                            <option value="all">All</option>
                        </select>
                    </div>

                    <table class="subdomain-table">
                        <thead>
                            <tr>
                                <th>Subdomain</th>
                                <th>Focus</th>
                                <th>LMS</th>
                                <th>Created</th>
                                <th>AI</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="subdomain-list">
                            <tr>
                                <td colspan="7" class="loading">
                                    <div class="spinner"></div>
                                    Loading subdomains...
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div id="pagination"></div>
                </div>
                <!-- End Manage Tab -->
            </div>
            <!-- End Tab Container -->

            <!-- Delete Confirmation Modal -->
            <div id="delete-modal" class="modal">
                <div class="modal-content">
                    <h3>Delete Subdomain</h3>
                    <p>This will permanently delete the following resources:</p>
                    <ul>
                        <li><strong>cPanel Subdomain:</strong> <span id="modal-subdomain"></span></li>
                        <li><strong>Cloudflare DNS record</strong></li>
                        <li><strong>MySQL Database:</strong> <span id="modal-database"></span></li>
                        <li><strong>Directory:</strong> <span id="modal-directory"></span></li>
                    </ul>

                    <div class="warning">⚠️ This action cannot be undone!</div>

                    <div class="form-group">
                        <label>Type subdomain name to confirm:</label>
                        <input type="text" id="delete-confirm-input" placeholder="subdomain-name">
                    </div>

                    <div class="modal-actions">
                        <button id="cancel-delete" class="btn-secondary">Cancel</button>
                        <button id="confirm-delete" class="btn-danger" disabled>Delete</button>
                    </div>
                </div>
            </div>

            <!-- Logs Modal -->
            <div id="logs-modal" class="modal">
                <div class="modal-content">
                    <h3>Subdomain Logs</h3>
                    <div id="logs-content">
                        <div class="loading">
                            <div class="spinner"></div>
                            Loading logs...
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button id="close-logs" class="btn-secondary">Close</button>
                    </div>
                </div>
            </div>

            <div class="logout-link">
                <a href="?logout=1">Logout</a>
            </div>
        <?php endif; ?>
    </div>
    <script src="assets/js/manager.js"></script>
</body>
</html>
