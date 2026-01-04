#!/usr/bin/env php
<?php
/**
 * Demetrly AI Assistant v1.0 → v1.1 Migration Script
 *
 * Migrates existing subdomains to the new v1.1 structure where:
 * - AI chat moves from root to /ai/ subdirectory
 * - New landing page at root
 * - /uploads/ directory created
 * - AI is protected from being overwritten by deployments
 *
 * Usage: php migrate-to-v1.1.php <subdomain_name>
 * Example: php migrate-to-v1.1.php demogame
 */

// Ensure script is run from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Check arguments
if ($argc < 2) {
    echo "Usage: php migrate-to-v1.1.php <subdomain_name>\n";
    echo "Example: php migrate-to-v1.1.php demogame\n";
    exit(1);
}

$subdomainName = $argv[1];

// Load configuration
$configPath = __DIR__ . '/creator_config.php';
if (!file_exists($configPath)) {
    die("Error: Configuration file not found at {$configPath}\n");
}

$config = require $configPath;

// Determine paths
$webRoot = $config['web_root'];
$domain = $config['domain'];
$fullDomain = "{$subdomainName}.{$domain}";
$subdomainPath = "{$webRoot}/{$fullDomain}";

echo "=== Demetrly v1.0 → v1.1 Migration ===\n\n";
echo "Subdomain: {$fullDomain}\n";
echo "Path: {$subdomainPath}\n\n";

// Verify subdomain exists
if (!is_dir($subdomainPath)) {
    die("Error: Subdomain directory not found: {$subdomainPath}\n");
}

// Check if already migrated
if (is_dir("{$subdomainPath}/ai") && file_exists("{$subdomainPath}/ai/.protected")) {
    echo "⚠️  This subdomain appears to already be on v1.1 (has /ai/ with .protected marker)\n";
    echo "Do you want to re-run the migration anyway? (yes/no): ";
    $response = trim(fgets(STDIN));
    if (strtolower($response) !== 'yes') {
        die("Migration cancelled.\n");
    }
}

echo "Step 1: Creating backup...\n";
$backupPath = "{$subdomainPath}_v1.0_backup_" . date('Y-m-d_H-i-s');
if (!recursiveCopy($subdomainPath, $backupPath)) {
    die("Error: Failed to create backup at {$backupPath}\n");
}
echo "✓ Backup created: {$backupPath}\n\n";

echo "Step 2: Creating /ai/ directory...\n";
$aiPath = "{$subdomainPath}/ai";
if (!is_dir($aiPath)) {
    mkdir($aiPath, 0755, true);
    echo "✓ Created /ai/ directory\n";
} else {
    echo "✓ /ai/ directory already exists\n";
}

echo "\nStep 3: Moving AI files to /ai/...\n";
$aiFiles = ['index.html', 'chat.js', 'api.php', 'config.php', 'upload.php'];
$movedFiles = 0;

foreach ($aiFiles as $file) {
    $sourcePath = "{$subdomainPath}/{$file}";
    $targetPath = "{$aiPath}/{$file}";

    if (file_exists($sourcePath)) {
        if (!file_exists($targetPath)) {
            if (copy($sourcePath, $targetPath)) {
                unlink($sourcePath); // Remove from root
                echo "  ✓ Moved {$file} → /ai/{$file}\n";
                $movedFiles++;
            } else {
                echo "  ⚠️  Failed to move {$file}\n";
            }
        } else {
            echo "  → {$file} already exists in /ai/, skipping\n";
        }
    }
}

echo "✓ Moved {$movedFiles} AI files\n\n";

echo "Step 4: Creating new root landing page...\n";
$templatePath = $config['template_path'];

// Check if v1.1 template exists
if (is_dir("{$templatePath}-v1.1/root")) {
    $rootTemplatePath = "{$templatePath}-v1.1/root/index.html";
} else {
    // Fallback: create basic landing page
    $rootTemplatePath = null;
}

if ($rootTemplatePath && file_exists($rootTemplatePath)) {
    $landingContent = file_get_contents($rootTemplatePath);

    // Replace placeholders
    $siteName = ucfirst($subdomainName);
    $description = "{$siteName} deployment hub";

    $landingContent = str_replace('{{SITE_NAME}}', $siteName, $landingContent);
    $landingContent = str_replace('{{FULL_DOMAIN}}', $fullDomain, $landingContent);
    $landingContent = str_replace('{{DESCRIPTION}}', $description, $landingContent);

    file_put_contents("{$subdomainPath}/index.html", $landingContent);
    echo "✓ Created landing page from v1.1 template\n";
} else {
    // Create basic landing page
    $landingContent = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$fullDomain}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            padding: 60px 40px;
            text-align: center;
        }
        h1 { color: #333; margin-bottom: 20px; }
        .card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            text-decoration: none;
            color: inherit;
            display: block;
            transition: transform 0.2s;
        }
        .card:hover { transform: translateY(-5px); }
        .card.ai { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 {$fullDomain}</h1>
        <a href="/ai/" class="card ai">
            <h2>🤖 AI Assistant</h2>
            <p>Deploy apps and manage your site</p>
        </a>
        <a href="/app/" class="card">
            <h2>📦 Your Application</h2>
            <p>Access your deployed app</p>
        </a>
    </div>
</body>
</html>
HTML;

    file_put_contents("{$subdomainPath}/index.html", $landingContent);
    echo "✓ Created basic landing page\n";
}

echo "\nStep 5: Creating /uploads/ directory...\n";
$uploadsPath = "{$subdomainPath}/uploads";
if (!is_dir($uploadsPath)) {
    mkdir($uploadsPath, 0755, true);
    echo "✓ Created /uploads/ directory\n";
} else {
    echo "✓ /uploads/ directory already exists\n";
}

// Create .htaccess for uploads security
$htaccessContent = <<<'HTACCESS'
# Security: Prevent execution of uploaded scripts
# Only allow file downloads, no script execution

# Deny access by default
Order Deny,Allow
Deny from all

# Allow PHP scripts (api.php needs to read uploads)
<FilesMatch "\.php$">
    Allow from all
</FilesMatch>

# Prevent script execution - remove handlers
RemoveHandler .php .phtml .php3 .php4 .php5 .phps
RemoveHandler .py .pl .cgi .asp .aspx .jsp .sh
RemoveType .php .phtml .php3 .php4 .php5 .phps
RemoveType .py .pl .cgi .asp .aspx .jsp .sh

# Allow downloads of archives and common file types
<FilesMatch "\.(zip|tar|gz|tgz|7z|rar|jpg|jpeg|png|gif|pdf|txt|md)$">
    Allow from all
    ForceType application/octet-stream
    Header set Content-Disposition attachment
</FilesMatch>

# Prevent directory listing
Options -Indexes

# Prevent access to hidden files (.htaccess, .git, etc.)
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>
HTACCESS;

file_put_contents("{$uploadsPath}/.htaccess", $htaccessContent);
echo "✓ Created uploads .htaccess\n\n";

echo "Step 6: Placing .protected marker in /ai/...\n";
$protectedContent = <<<'PROTECTED'
AI Assistant Directory - DO NOT DELETE

This directory contains the Demetrly AI Assistant v1.1
Deleting this directory will remove your AI deployment helper.

To redeploy the AI assistant, you would need to:
1. Re-run the subdomain creation process
2. Or manually copy the template from /templates/ai-assistant-v1.1/ai/

Created by: Demetrly v1.1
Protection marker: This file indicates a protected directory

PROTECTED;

file_put_contents("{$aiPath}/.protected", $protectedContent);
echo "✓ Created .protected marker\n\n";

echo "=== Migration Summary ===\n\n";
echo "✅ Backup created at: {$backupPath}\n";
echo "✅ AI files moved to: {$aiPath}/\n";
echo "✅ New landing page: {$subdomainPath}/index.html\n";
echo "✅ Uploads directory: {$uploadsPath}/\n";
echo "✅ Protected marker: {$aiPath}/.protected\n\n";

echo "🎉 Migration complete!\n\n";
echo "Next steps:\n";
echo "1. Visit https://{$fullDomain}/ → Should show landing page\n";
echo "2. Visit https://{$fullDomain}/ai/ → Should show AI chat\n";
echo "3. Test uploading a ZIP file\n";
echo "4. If everything works, you can delete the backup:\n";
echo "   rm -rf {$backupPath}\n\n";

/**
 * Recursively copy directory
 */
function recursiveCopy($src, $dst) {
    if (!is_dir($src)) {
        return false;
    }

    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }

    $dir = opendir($src);
    if (!$dir) {
        return false;
    }

    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;

        if (is_dir($srcPath)) {
            recursiveCopy($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }

    closedir($dir);
    return true;
}
