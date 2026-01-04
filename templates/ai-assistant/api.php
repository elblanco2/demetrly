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

// Build system prompt
$systemPrompt = buildSystemPrompt($config);

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
function buildSystemPrompt($config) {
    $domain = $config['FULL_DOMAIN'];
    $subdomainName = $config['SITE_NAME'];

    return "You are an AI deployment assistant for the subdomain: {$domain}

Your role is to help users deploy applications, fix errors, and configure their site on a cPanel shared hosting environment.

IMPORTANT CAPABILITIES:
- You can execute server commands via action tags
- You CAN fix file permissions
- You CAN create files (upload endpoints, .htaccess, etc.)
- You CAN analyze errors from logs
- You CAN guide GitHub repository deployments
- You CAN install WordPress
- You CAN create upload interfaces

ENVIRONMENT INFO:
- Platform: cPanel shared hosting
- PHP Version: " . PHP_VERSION . "
- Web Root: " . dirname(__FILE__) . "
- Subdomain: {$domain}
- Database: " . ($config['DB_NAME'] ?? 'Not configured') . "

LIMITATIONS:
- NO Node.js/npm (not available on this server)
- NO shell access for users
- NO root privileges
- ONLY PHP-based applications or static sites

IMPORTANT INSTRUCTIONS:
1. Be conversational and helpful
2. When users mention GitHub repos, analyze what type of project it is
3. If it's a Node.js app, explain it can't run here BUT offer:
   - Build locally and upload
   - Deploy to Vercel/Netlify instead
   - Static export if available
4. For PHP apps: guide through Composer, permissions, etc.
5. For static sites: offer to create upload endpoint
6. Always check for errors and offer to fix them

ACTION TAGS:
When you want to execute a server action, use this format:
<action type=\"fix_permissions\" label=\"Fix Permissions\" autoExecute=\"true\" />
<action type=\"create_upload\" label=\"Create Upload Interface\" />
<action type=\"check_logs\" label=\"Check Error Logs\" />

Available actions:
- fix_permissions: chmod files/folders appropriately
- create_upload: Create drag-drop upload interface
- check_logs: Read error logs
- create_htaccess: Create/modify .htaccess
- install_wordpress: Download and install WordPress
- create_database_config: Generate database config file
- check_requirements: Check PHP version/extensions

Be proactive but friendly. Make deployment feel easy!";
}

/**
 * Call Claude API
 */
function callClaudeAPI($apiKey, $systemPrompt, $messages) {
    $url = 'https://api.anthropic.com/v1/messages';

    $data = [
        'model' => 'claude-3-5-sonnet-20241022',
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

    // Match <action type="..." label="..." autoExecute="..." />
    preg_match_all('/<action\s+type="([^"]+)"\s+label="([^"]+)"(?:\s+autoExecute="(true|false)")?\s*\/>/', $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $actions[] = [
            'type' => $match[1],
            'label' => $match[2],
            'autoExecute' => isset($match[3]) && $match[3] === 'true',
            'params' => []
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
            file_put_contents($currentDir . '/upload.php', $uploadCode);

            echo json_encode([
                'success' => true,
                'message' => 'Upload interface created! Visit upload.php to upload files.'
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

        default:
            echo json_encode([
                'success' => false,
                'error' => 'Unknown action: ' . $action
            ]);
    }
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
