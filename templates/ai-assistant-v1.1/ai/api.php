<?php
/**
 * AI Deployment Assistant - Backend API
 * Proxies requests to Anthropic Claude API
 */

header('Content-Type: application/json');

// Load configuration
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    die(json_encode(['success' => false, 'error' => 'Configuration not found']));
}

$config = require $configPath;

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    die(json_encode(['success' => false, 'error' => 'Invalid request']));
}

// Handle action requests
if (isset($input['action'])) {
    handleAction($input['action'], $input['params'] ?? []);
    exit;
}

// Handle chat messages
if (!isset($input['message'])) {
    die(json_encode(['success' => false, 'error' => 'Message required']));
}

$userMessage = $input['message'];
$history = $input['history'] ?? [];
$userMode = $input['userMode'] ?? 'beginner';

// Build system prompt
$systemPrompt = buildSystemPrompt($config, $userMode);

// Build conversation for Claude
$messages = [];

// Add history (excluding system messages)
foreach ($history as $msg) {
    if ($msg['role'] !== 'system') {
        $messages[] = [
            'role' => $msg['role'],
            'content' => $msg['content']
        ];
    }
}

// Add current user message
$messages[] = [
    'role' => 'user',
    'content' => $userMessage
];

// Call Claude API
$response = callClaudeAPI($config['ANTHROPIC_API_KEY'], $systemPrompt, $messages);

if ($response['success']) {
    // Parse response for actions
    $actions = parseActions($response['content']);

    echo json_encode([
        'success' => true,
        'response' => $response['content'],
        'actions' => $actions
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => $response['error']
    ]);
}

/**
 * Build system prompt with context
 */
function buildSystemPrompt($config, $userMode = 'beginner') {
    $domain = $config['FULL_DOMAIN'];
    $subdomainName = $config['SITE_NAME'];

    // Mode-specific instructions
    $modeInstructions = ($userMode === 'beginner') ?
        "USER MODE: BEGINNER
- Explain each step before executing actions
- Define technical terms when first mentioned
- Use analogies and simple language
- Offer learning opportunities
- Show 'Why we're doing this' context
- Ask 'Does this make sense?' after complex explanations
- Be patient and educational" :
        "USER MODE: EXPERT
- Concise, technical responses
- Assume user knows terminology
- Show technical details (paths, permissions, commands)
- Fewer confirmation prompts
- Direct action-oriented language
- Focus on efficiency";

    return "You are an AI deployment assistant for the subdomain: {$domain}

Your role is to help users deploy applications, fix errors, and configure their site on a cPanel shared hosting environment.

{$modeInstructions}

CRITICAL LOCATION INFO:
- You are located in the /ai/ subdirectory
- Your files: /ai/index.html, /ai/chat.js, /ai/api.php (this file)
- User's web root: / (parent directory)
- Uploads staging area: /uploads/
- NEVER modify or delete files in /ai/ directory - this would destroy you!
- When deploying apps, deploy to / or /app/, NEVER to /ai/

IMPORTANT CAPABILITIES:
You can autonomously deploy applications via action tags:
- Extract ZIP files from /uploads/
- Analyze project types (React, Vue, PHP, WordPress, static HTML)
- Deploy files to web root (protecting /ai/ and /uploads/)
- Fix file permissions
- Check if Node.js is available
- Create upload interfaces
- Clean up after deployments

ENVIRONMENT INFO:
- Platform: cPanel shared hosting
- PHP Version: " . PHP_VERSION . "
- AI Location: " . dirname(__FILE__) . " (/ai/ subdirectory)
- Web Root: " . dirname(dirname(__FILE__)) . " (parent directory)
- Subdomain: {$domain}
- Database: " . ($config['DB_NAME'] ?? 'Not configured') . "
- ZipArchive: " . (class_exists('ZipArchive') ? 'Available' : 'Not available') . "

DEPLOYMENT WORKFLOW:
1. User uploads ZIP to /uploads/ (via upload.php)
2. You detect the upload (chat.js polls every 10 seconds)
3. You proactively ask: \"I see filename.zip! Should I deploy it?\"
4. User confirms
5. You extract → analyze → deploy → set permissions → cleanup
6. Total time: ~30 seconds, fully autonomous

ACTION TAGS - CRITICAL INSTRUCTIONS:

⚠️ IMPORTANT: You MUST output action tags to execute server commands. DO NOT just describe what you're going to do - OUTPUT THE ACTION TAG!

CORRECT ✅:
User: \"Can you extract the ZIP?\"
You: \"I'll extract the ZIP file now.

<action type=\"extract_zip\" filename=\"app.zip\" label=\"Extract ZIP\" autoExecute=\"true\" />

This will unpack all the files so we can deploy them.\"

WRONG ❌:
User: \"Can you extract the ZIP?\"
You: \"I'm extracting the ZIP file...\" [NO ACTION TAG = NOTHING HAPPENS]

ACTION TAG FORMAT:
<action type=\"ACTION_NAME\" param1=\"value\" param2=\"value\" label=\"Button Label\" autoExecute=\"true|false\" />

- autoExecute=\"true\" = runs automatically without user clicking
- autoExecute=\"false\" = user must click button to execute
- Use autoExecute=\"true\" for safe operations (list files, check status)
- Use autoExecute=\"false\" for destructive operations (delete, overwrite)

AVAILABLE ACTIONS:

1. list_uploads
   Format: <action type=\"list_uploads\" label=\"Check Uploads\" autoExecute=\"true\" />
   When: User asks what files are uploaded, or you need to check before deployment

2. extract_zip
   Format: <action type=\"extract_zip\" filename=\"file.zip\" label=\"Extract ZIP\" autoExecute=\"true\" />
   When: User confirms deployment, or says \"extract\" or \"unzip\"
   Params: filename (must match exact filename in /uploads/)

3. detect_project_type
   Format: <action type=\"detect_project_type\" path=\"extracted_folder\" label=\"Analyze Project\" autoExecute=\"true\" />
   When: After extraction, to determine React/Vue/PHP/static
   Params: path (folder name in /uploads/, usually \"extracted_FILENAME\")

4. deploy_app
   Format: <action type=\"deploy_app\" source_path=\"extracted_folder\" target=\"app\" label=\"Deploy to /app/\" autoExecute=\"false\" />
   When: User confirms deployment after analysis
   Params: source_path (extracted folder), target (\"root\" or \"app\")
   AutoExecute: false (let user confirm)

5. cleanup_uploads
   Format: <action type=\"cleanup_uploads\" filename=\"file.zip\" extracted_dir=\"extracted_folder\" label=\"Clean Up\" autoExecute=\"true\" />
   When: After successful deployment
   Params: filename (ZIP file), extracted_dir (extracted folder name)

6. fix_permissions
   Format: <action type=\"fix_permissions\" label=\"Fix Permissions\" autoExecute=\"true\" />
   When: After deployment or if user reports permission errors

7. create_upload
   Format: <action type=\"create_upload\" label=\"Create Upload Interface\" autoExecute=\"true\" />
   When: User wants to upload files or you need upload capability

8. check_node_available
   Format: <action type=\"check_node_available\" label=\"Check Node.js\" autoExecute=\"true\" />
   When: Detected Node.js project, need to know if npm/node available

9. check_logs
   Format: <action type=\"check_logs\" label=\"Check Error Logs\" autoExecute=\"true\" />
   When: User reports errors or 500/404 issues

10. check_requirements
    Format: <action type=\"check_requirements\" label=\"Check PHP Info\" autoExecute=\"true\" />
    When: User asks about server capabilities or you need version info

11. create_redirect
    Format: <action type=\"create_redirect\" type=\"302\" label=\"Create Redirect\" autoExecute=\"false\" />
    When: After deploying an app to validate the location and create a redirect
    Params: type (\"302\" temporary [default] or \"301\" permanent)
    AutoExecute: false (always ask user for confirmation before creating redirects)

    What it does:
    - Searches /app/ directory for index.html
    - Creates .htaccess redirect from root (/) to the app location
    - Backs up existing .htaccess before modifying

    Example response after deployment:
    \"✅ Deployment complete! I found your app at /app/mini-snake-main/index.html

    Would you like me to create a redirect so visitors to your root domain
    automatically go to your app?

    <action type=\"create_redirect\" type=\"302\" label=\"Create Redirect\" autoExecute=\"false\" />

    This will add a temporary redirect to your .htaccess file.\"

WORKFLOW EXAMPLE - USER UPLOADS ZIP:

User: \"I uploaded myapp.zip, can you deploy it?\"

You: \"Great! Let me check what's in the uploads folder.

<action type=\"list_uploads\" label=\"Check Uploads\" autoExecute=\"true\" />

Once I see the file, I'll extract and deploy it for you.\"

[After list_uploads returns showing myapp.zip]

You: \"I found myapp.zip! Let me extract it now.

<action type=\"extract_zip\" filename=\"myapp.zip\" label=\"Extract ZIP\" autoExecute=\"true\" />

After extraction, I'll analyze the project type.\"

[After extraction]

You: \"Extraction complete! Now analyzing the project.

<action type=\"detect_project_type\" path=\"extracted_myapp\" label=\"Analyze Project\" autoExecute=\"true\" />

This will tell me if it's React, PHP, static HTML, etc.\"

[After analysis shows it's static HTML]

You: \"Perfect! This is a static HTML site - ready to deploy.

<action type=\"deploy_app\" source_path=\"extracted_myapp\" target=\"app\" label=\"Deploy to /app/\" autoExecute=\"false\" />

Click the button above to deploy, or say 'deploy it' and I'll proceed!\"

[After deployment]

You: \"Deployment successful! Cleaning up temporary files.

<action type=\"cleanup_uploads\" filename=\"myapp.zip\" extracted_dir=\"extracted_myapp\" label=\"Clean Up\" autoExecute=\"true\" />

Your app should now be live at https://{$domain}/app/\"

REMEMBER:
- Always OUTPUT the action tag in your response
- Don't just say \"I'm doing X\" - actually output <action type=\"X\" ... />
- Multiple actions in one response is fine
- Explain what you're doing, THEN output the action tag

UPDATED DEPLOYMENT WORKFLOW (with redirect):

After deploying an app with deploy_app action:

1. Confirm deployment success
2. Offer to create redirect:

   \"✅ Deployment complete! Your app is at /app/[actual-path]/

   Would you like me to create a redirect from your root domain?
   This will make it so visitors to https://yourdomain.com automatically
   go to https://yourdomain.com/app/[actual-path]/

   <action type=\"create_redirect\" type=\"302\" label=\"Create Redirect\" autoExecute=\"false\" />

   Note: This will replace your landing page with the app.\"

3. If user confirms, the create_redirect action will:
   - Find where index.html actually landed
   - Create a 302 temporary redirect in .htaccess
   - Backup existing .htaccess if present

4. Confirm redirect created and provide final URL

NODE.JS PROJECT GUIDANCE - CRITICAL:

When you detect a Node.js project (package.json) without build output:

STEP 1: Check Node.js availability
<action type=\"check_node_available\" label=\"Check Node.js\" autoExecute=\"true\" />

STEP 2A: IF NODE.JS IS AVAILABLE on server (result shows node/npm found):
**Guide user through cPanel Terminal deployment:**

\"Great news! This server has Node.js installed. Here's how to deploy:

1. **Open cPanel Terminal** (I'll guide you)
   - Log into your cPanel
   - Find 'Terminal' in the Advanced section
   - Click to open a new terminal window

2. **Navigate to this subdomain:**
   ```
   cd ~/public_html/{$domain}
   ```

3. **Install dependencies:**
   ```
   npm install
   ```
   (This might take 2-3 minutes - don't close the window)

4. **Build the project:**
   ```
   npm run build
   ```
   (This creates the production-ready files)

5. **Tell me when it's done** and I'll:
   - Find the build output (usually /dist or /build)
   - Deploy it to your web root
   - Set proper permissions

Would you like to try this? I can wait while you run these commands in cPanel Terminal.\"

STEP 2B: IF NODE.JS IS NOT AVAILABLE (result shows not found):
**Offer THREE clear deployment paths:**

\"I detected this is a Node.js project, but this shared hosting doesn't have Node.js installed.

**Don't worry - you have 3 good options:**

📦 **Option 1: Build Locally (Recommended for learning)**
1. On your computer, open Terminal/Command Prompt
2. Navigate to the project folder
3. Run: `npm install` (installs dependencies)
4. Run: `npm run build` (creates /build or /dist folder)
5. ZIP up ONLY the build folder
6. Upload it here and I'll deploy it!

Want me to create an upload interface for your build folder?

🚀 **Option 2: Use Vercel (Easiest - Free!)**
1. Go to https://vercel.com (free for personal projects)
2. Click 'Import Project'
3. Connect your GitHub repo
4. Click Deploy (Vercel builds it automatically!)
5. Get a live URL instantly

This is the easiest option for Node.js apps!

☁️ **Option 3: Use Netlify (Also Free!)**
1. Go to https://netlify.com
2. Drag and drop your project folder (or connect GitHub)
3. Netlify builds and deploys automatically
4. Get a custom URL

**Which option sounds best to you?**

If you choose Option 1, I can:
- Create an upload interface
- Guide you through the build process
- Deploy the built files here

If you choose Options 2 or 3, I can:
- Explain the exact steps
- Help you understand what's happening
- Be here if you need help!\"

REMEMBER:
- Always check_node_available FIRST
- Don't just say \"this won't work\" - offer solutions!
- Be encouraging - Node.js apps are very deployable, just need different approach
- Guide beginners through each step
- Experts can skip to whichever option they prefer

SECURITY:
- All paths validated with realpath()
- ZIP extraction checks for path traversal (rejects ../)
- deploy_app enforces protected directories
- /uploads/.htaccess prevents script execution
- File permissions: 644 (files), 755 (dirs)

Be proactive but friendly. Make deployment feel easy!";
}

/**
 * Call Claude API
 */
function callClaudeAPI($apiKey, $systemPrompt, $messages) {
    $url = 'https://api.anthropic.com/v1/messages';

    $data = [
        'model' => 'claude-sonnet-4-5-20250929',
        'max_tokens' => 4096,
        'system' => $systemPrompt,
        'messages' => $messages
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => 'API request failed: ' . $httpCode
        ];
    }

    $result = json_decode($response, true);

    if (isset($result['content'][0]['text'])) {
        return [
            'success' => true,
            'content' => $result['content'][0]['text']
        ];
    }

    return [
        'success' => false,
        'error' => 'Invalid API response'
    ];
}

/**
 * Parse action tags from response
 */
function parseActions($content) {
    $actions = [];

    // Match <action ... /> tags (any attributes in any order)
    preg_match_all('/<action\s+([^>]+?)\s*\/>/', $content, $tagMatches, PREG_SET_ORDER);

    foreach ($tagMatches as $tagMatch) {
        $attributesString = $tagMatch[1];

        // Extract all attributes (name="value" pairs)
        preg_match_all('/(\w+)="([^"]*)"/', $attributesString, $attrMatches, PREG_SET_ORDER);

        $attributes = [];
        foreach ($attrMatches as $attrMatch) {
            $attributes[$attrMatch[1]] = $attrMatch[2];
        }

        // Must have at least 'type' attribute
        if (!isset($attributes['type'])) {
            continue;
        }

        // Extract known attributes
        $type = $attributes['type'];
        $label = $attributes['label'] ?? ucfirst(str_replace('_', ' ', $type));
        $autoExecute = isset($attributes['autoExecute']) && $attributes['autoExecute'] === 'true';

        // All other attributes become params
        $params = [];
        foreach ($attributes as $key => $value) {
            if (!in_array($key, ['type', 'label', 'autoExecute'])) {
                $params[$key] = $value;
            }
        }

        $actions[] = [
            'type' => $type,
            'label' => $label,
            'autoExecute' => $autoExecute,
            'params' => $params
        ];
    }

    return $actions;
}

/**
 * Handle server actions
 */
function handleAction($action, $params) {
    $currentDir = __DIR__;

    switch ($action) {
        case 'fix_permissions':
            // Fix file permissions
            exec("find {$currentDir} -type f -exec chmod 644 {} \;");
            exec("find {$currentDir} -type d -exec chmod 755 {} \;");

            echo json_encode([
                'success' => true,
                'message' => 'Permissions fixed! Files: 644, Directories: 755'
            ]);
            break;

        case 'create_upload':
            $uploadCode = getUploadTemplate();
            $rootDir = dirname($currentDir);
            file_put_contents($rootDir . '/upload.php', $uploadCode);

            $uploadUrl = $config['WEBSITE_URL'] . '/upload.php';
            echo json_encode([
                'success' => true,
                'message' => "Upload interface created at /upload.php",
                'upload_url' => $uploadUrl
            ]);
            break;

        case 'check_logs':
            $errorLog = ini_get('error_log');
            if (file_exists($errorLog)) {
                $logs = file_get_contents($errorLog);
                $recentLogs = implode("\n", array_slice(explode("\n", $logs), -20));

                echo json_encode([
                    'success' => true,
                    'message' => "Recent error logs:\n\n{$recentLogs}"
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'No error logs found (that\'s good!)'
                ]);
            }
            break;

        case 'check_requirements':
            $extensions = get_loaded_extensions();
            $info = [
                'PHP Version' => PHP_VERSION,
                'Extensions' => implode(', ', $extensions),
                'Memory Limit' => ini_get('memory_limit'),
                'Max Upload' => ini_get('upload_max_filesize'),
                'Writable' => is_writable($currentDir) ? 'Yes' : 'No'
            ];

            $message = "System Information:\n\n";
            foreach ($info as $key => $value) {
                $message .= "• {$key}: {$value}\n";
            }

            echo json_encode([
                'success' => true,
                'message' => $message
            ]);
            break;

        case 'list_uploads':
            $uploadsDir = dirname(__DIR__) . '/uploads/';

            if (!is_dir($uploadsDir)) {
                echo json_encode(['success' => false, 'error' => 'Uploads directory not found']);
                break;
            }

            $files = array_diff(scandir($uploadsDir), ['.', '..', '.htaccess']);
            $fileList = [];

            foreach ($files as $file) {
                $filePath = $uploadsDir . $file;
                if (is_file($filePath)) {
                    $fileList[] = [
                        'name' => $file,
                        'size' => filesize($filePath),
                        'size_human' => formatBytes(filesize($filePath)),
                        'uploaded' => date('Y-m-d H:i:s', filemtime($filePath)),
                        'extension' => pathinfo($file, PATHINFO_EXTENSION)
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'files' => $fileList,
                'count' => count($fileList)
            ]);
            break;

        case 'extract_zip':
            $filename = $params['filename'] ?? '';
            $uploadsDir = dirname(__DIR__) . '/uploads/';
            $zipPath = $uploadsDir . basename($filename);

            if (empty($filename)) {
                echo json_encode(['success' => false, 'error' => 'Filename required']);
                break;
            }

            if (!file_exists($zipPath)) {
                echo json_encode(['success' => false, 'error' => 'ZIP file not found: ' . basename($filename)]);
                break;
            }

            if (!class_exists('ZipArchive')) {
                echo json_encode(['success' => false, 'error' => 'ZipArchive not available on this server']);
                break;
            }

            $extractDir = $uploadsDir . 'extracted_' . pathinfo($filename, PATHINFO_FILENAME);
            if (is_dir($extractDir)) {
                recursiveDelete($extractDir);
            }
            mkdir($extractDir, 0755, true);

            $zip = new ZipArchive;
            $result = $zip->open($zipPath);

            if ($result !== TRUE) {
                echo json_encode(['success' => false, 'error' => 'Failed to open ZIP (error code: ' . $result . ')']);
                break;
            }

            // Security: Check for path traversal
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (strpos($name, '..') !== false) {
                    $zip->close();
                    echo json_encode(['success' => false, 'error' => 'ZIP contains path traversal - rejected for security']);
                    break 2;
                }
            }

            $zip->extractTo($extractDir);
            $numFiles = $zip->numFiles;
            $zip->close();

            echo json_encode([
                'success' => true,
                'message' => 'ZIP extracted successfully',
                'extract_path' => basename($extractDir),
                'files_count' => $numFiles
            ]);
            break;

        case 'detect_project_type':
            $projectPath = $params['path'] ?? '';
            $uploadsDir = dirname(__DIR__) . '/uploads/';

            // Security: Prevent directory traversal
            if (strpos($projectPath, '..') !== false) {
                echo json_encode(['success' => false, 'error' => 'Invalid path']);
                break;
            }

            $fullPath = realpath($uploadsDir . $projectPath);

            if (!$fullPath || !is_dir($fullPath)) {
                echo json_encode(['success' => false, 'error' => 'Project directory not found']);
                break;
            }

            $detection = [
                'type' => 'unknown',
                'framework' => null,
                'requires_build' => false,
                'has_build_output' => false,
                'deployment_ready' => false,
                'files_found' => [],
                'build_command' => null,
                'build_output_dir' => null
            ];

            $checks = [
                'package.json' => 'nodejs',
                'composer.json' => 'php',
                'index.html' => 'static',
                'index.php' => 'php',
                'wp-config.php' => 'wordpress',
                'build' => 'has_build',
                'dist' => 'has_dist',
                'public' => 'has_public'
            ];

            foreach ($checks as $file => $indicator) {
                if (file_exists($fullPath . '/' . $file)) {
                    $detection['files_found'][] = $file;

                    if ($indicator === 'nodejs') {
                        $detection['type'] = 'nodejs';
                        $detection['requires_build'] = true;

                        $pkg = json_decode(file_get_contents($fullPath . '/package.json'), true);
                        if (isset($pkg['dependencies']['react'])) $detection['framework'] = 'react';
                        if (isset($pkg['dependencies']['vue'])) $detection['framework'] = 'vue';
                        if (isset($pkg['dependencies']['next'])) $detection['framework'] = 'nextjs';
                        if (isset($pkg['scripts']['build'])) $detection['build_command'] = 'npm run build';
                    } elseif ($indicator === 'has_build' || $indicator === 'has_dist' || $indicator === 'has_public') {
                        $detection['has_build_output'] = true;
                        $detection['build_output_dir'] = $file;
                    } elseif ($indicator === 'static' && $detection['type'] === 'unknown') {
                        $detection['type'] = 'static';
                        $detection['deployment_ready'] = true;
                    } elseif ($indicator === 'php' || $indicator === 'wordpress') {
                        $detection['type'] = $indicator;
                        $detection['deployment_ready'] = true;
                    }
                }
            }

            if ($detection['type'] === 'nodejs' && $detection['has_build_output']) {
                $detection['deployment_ready'] = true;
            }

            echo json_encode(['success' => true, 'detection' => $detection]);
            break;

        case 'check_node_available':
            $nodeCheck = exec("which node 2>&1", $nodeOutput, $nodeReturn);
            $npmCheck = exec("which npm 2>&1", $npmOutput, $npmReturn);

            $nodeAvailable = ($nodeReturn === 0 && !empty($nodeCheck));
            $npmAvailable = ($npmReturn === 0 && !empty($npmCheck));

            $versions = [];
            if ($nodeAvailable) {
                exec("node --version 2>&1", $vOut);
                $versions['node'] = $vOut[0] ?? 'unknown';
            }
            if ($npmAvailable) {
                exec("npm --version 2>&1", $vOut);
                $versions['npm'] = $vOut[0] ?? 'unknown';
            }

            echo json_encode([
                'success' => true,
                'node_available' => $nodeAvailable,
                'npm_available' => $npmAvailable,
                'versions' => $versions,
                'paths' => ['node' => $nodeCheck ?: null, 'npm' => $npmCheck ?: null]
            ]);
            break;

        case 'deploy_app':
            $sourcePath = $params['source_path'] ?? '';
            $targetLocation = $params['target'] ?? 'root';

            $uploadsDir = dirname(__DIR__) . '/uploads/';
            $webRoot = dirname(__DIR__);

            // Security: Prevent directory traversal
            if (strpos($sourcePath, '..') !== false) {
                echo json_encode(['success' => false, 'error' => 'Invalid source path']);
                break;
            }

            $fullSourcePath = realpath($uploadsDir . $sourcePath);
            if (!$fullSourcePath || !is_dir($fullSourcePath)) {
                echo json_encode(['success' => false, 'error' => 'Source directory not found']);
                break;
            }

            $targetPath = ($targetLocation === 'root') ? $webRoot : $webRoot . '/app';
            if ($targetLocation === 'app' && !is_dir($targetPath)) {
                mkdir($targetPath, 0755, true);
            }

            $protectedDirs = ['ai', 'uploads'];

            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($fullSourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );

                $deployedFiles = 0;
                foreach ($iterator as $item) {
                    $relativePath = str_replace($fullSourcePath . '/', '', $item->getPathname());
                    $destinationPath = $targetPath . '/' . $relativePath;

                    // SAFETY: Skip protected directories
                    $skip = false;
                    foreach ($protectedDirs as $protected) {
                        if (strpos($relativePath, $protected . '/') === 0 || $relativePath === $protected) {
                            $skip = true;
                            break;
                        }
                    }
                    if ($skip) continue;

                    if ($item->isDir()) {
                        if (!is_dir($destinationPath)) {
                            mkdir($destinationPath, 0755, true);
                        }
                    } else {
                        copy($item->getPathname(), $destinationPath);
                        chmod($destinationPath, 0644);
                        $deployedFiles++;
                    }
                }

                $config = require __DIR__ . '/config.php';
                $deployUrl = ($targetLocation === 'root') ?
                    $config['WEBSITE_URL'] :
                    $config['WEBSITE_URL'] . '/app/';

                echo json_encode([
                    'success' => true,
                    'message' => "Application deployed successfully ({$deployedFiles} files)",
                    'deployed_to' => $targetLocation === 'root' ? '/' : '/app/',
                    'url' => $deployUrl,
                    'files_deployed' => $deployedFiles
                ]);

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Deployment failed: ' . $e->getMessage()]);
            }
            break;

        case 'cleanup_uploads':
            $filename = $params['filename'] ?? '';
            $extractedDir = $params['extracted_dir'] ?? '';
            $uploadsDir = dirname(__DIR__) . '/uploads/';

            $removed = [];

            if ($filename) {
                $zipPath = $uploadsDir . basename($filename);
                if (file_exists($zipPath)) {
                    unlink($zipPath);
                    $removed[] = $filename;
                }
            }

            if ($extractedDir) {
                $extractPath = $uploadsDir . basename($extractedDir);
                if (is_dir($extractPath)) {
                    recursiveDelete($extractPath);
                    $removed[] = $extractedDir;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Cleanup completed',
                'removed' => $removed
            ]);
            break;

        case 'create_redirect':
            // Parameters
            $redirectType = $params['type'] ?? '302';
            $webRoot = dirname(__DIR__);
            $appDir = $webRoot . '/app';

            // Validate redirect type
            if (!in_array($redirectType, ['301', '302'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid redirect type. Use 301 or 302'
                ]);
                break;
            }

            // Step 1: Find index.html in /app/
            if (!is_dir($appDir)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'App directory not found. Deploy an app first.'
                ]);
                break;
            }

            $indexFiles = [];

            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($appDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getFilename() === 'index.html') {
                        $relativePath = str_replace($webRoot, '', $file->getPathname());
                        $indexFiles[] = $relativePath;
                    }
                }
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Error searching for index.html: ' . $e->getMessage()
                ]);
                break;
            }

            if (empty($indexFiles)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'No index.html found in /app/. Cannot create redirect.',
                    'suggestion' => 'Deploy an application first'
                ]);
                break;
            }

            // Use first index.html found (most likely the main app)
            $targetPath = $indexFiles[0];

            // Step 2: Create .htaccess redirect
            $htaccessPath = $webRoot . '/.htaccess';
            $timestamp = date('Y-m-d H:i:s');

            $redirectRule = "\n# Auto-generated redirect to deployed app\n";
            $redirectRule .= "# Created: {$timestamp}\n";
            $redirectRule .= "RedirectMatch {$redirectType} ^/$ {$targetPath}\n";

            try {
                // Backup existing .htaccess
                if (file_exists($htaccessPath)) {
                    $existingContent = file_get_contents($htaccessPath);

                    // Check if redirect already exists
                    if (preg_match("/Redirect(Match)?\s+(301|302)\s+(\^\/\$|\/)/", $existingContent)) {
                        echo json_encode([
                            'success' => false,
                            'error' => 'A redirect from root (/) already exists',
                            'suggestion' => 'Remove existing redirect in .htaccess before creating a new one'
                        ]);
                        break;
                    }

                    // Create backup
                    $backupPath = $htaccessPath . '.backup.' . time();
                    copy($htaccessPath, $backupPath);

                    // Append new redirect
                    file_put_contents($htaccessPath, $existingContent . $redirectRule);
                } else {
                    // Create new .htaccess
                    file_put_contents($htaccessPath, $redirectRule);
                }

                chmod($htaccessPath, 0644);

                $config = require __DIR__ . '/config.php';
                $domain = $config['WEBSITE_URL'];

                echo json_encode([
                    'success' => true,
                    'message' => "Created {$redirectType} redirect from / to {$targetPath}",
                    'redirect_type' => $redirectType === '301' ? 'Permanent (301)' : 'Temporary (302)',
                    'source' => '/',
                    'target' => $targetPath,
                    'full_url' => $domain . $targetPath,
                    'htaccess_path' => '/.htaccess',
                    'all_index_files' => $indexFiles,
                    'note' => 'Root domain now redirects to your deployed app'
                ]);

            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to create redirect: ' . $e->getMessage()
                ]);
            }
            break;

        default:
            echo json_encode([
                'success' => false,
                'error' => 'Unknown action: ' . $action
            ]);
    }
}

/**
 * Helper: Format bytes to human-readable size
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Helper: Recursively delete directory and contents
 */
function recursiveDelete($dir) {
    if (!is_dir($dir)) {
        return false;
    }

    $items = array_diff(scandir($dir), ['.', '..']);

    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        is_dir($path) ? recursiveDelete($path) : unlink($path);
    }

    return rmdir($dir);
}

/**
 * Get upload interface template
 */
function getUploadTemplate() {
    return '<?php
/**
 * Simple File Upload Interface
 * Auto-generated by AI Assistant
 */

$uploadDir = __DIR__ . "/uploads/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["file"])) {
    $file = $_FILES["file"];
    $targetPath = $uploadDir . basename($file["name"]);

    if (move_uploaded_file($file["tmp_name"], $targetPath)) {
        echo json_encode(["success" => true, "message" => "File uploaded!"]);
    } else {
        echo json_encode(["success" => false, "error" => "Upload failed"]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>File Upload</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .drop-zone { border: 2px dashed #ccc; padding: 40px; text-align: center; border-radius: 8px; cursor: pointer; }
        .drop-zone.dragover { background: #f0f0f0; border-color: #666; }
    </style>
</head>
<body>
    <h1>📁 File Upload</h1>
    <div class="drop-zone" id="dropZone">
        <p>Drag & drop files here or click to browse</p>
        <input type="file" id="fileInput" multiple style="display:none">
    </div>
    <div id="status"></div>

    <script>
        const dropZone = document.getElementById("dropZone");
        const fileInput = document.getElementById("fileInput");
        const status = document.getElementById("status");

        dropZone.onclick = () => fileInput.click();

        dropZone.ondragover = (e) => {
            e.preventDefault();
            dropZone.classList.add("dragover");
        };

        dropZone.ondragleave = () => {
            dropZone.classList.remove("dragover");
        };

        dropZone.ondrop = (e) => {
            e.preventDefault();
            dropZone.classList.remove("dragover");
            uploadFiles(e.dataTransfer.files);
        };

        fileInput.onchange = () => {
            uploadFiles(fileInput.files);
        };

        async function uploadFiles(files) {
            for (const file of files) {
                const formData = new FormData();
                formData.append("file", file);

                status.innerHTML = `Uploading ${file.name}...`;

                const response = await fetch("upload.php", {
                    method: "POST",
                    body: formData
                });

                const result = await response.json();
                status.innerHTML += `<br>✓ ${file.name} uploaded!`;
            }
        }
    </script>
</body>
</html>';
}
