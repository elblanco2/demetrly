<?php
/**
 * EduDomain Forge - Sample Configuration File
 *
 * IMPORTANT:
 * 1. Copy this file to a secure location OUTSIDE your web root
 * 2. Rename to creator_config.php
 * 3. Fill in your actual credentials
 * 4. Set permissions: chmod 600 creator_config.php
 *
 * Recommended location: /home/yourusername/config/creator_config.php
 * Then update index.php line 20 to point to your config path
 */

return [
    // =================================================================
    // cPanel API Configuration
    // =================================================================
    // Get API token from: cPanel → Security → Manage API Tokens
    'cpanel_host' => 'your-server.serversignin.com',
    'cpanel_user' => 'your_cpanel_username',
    'cpanel_api_token' => 'YOUR_CPANEL_API_TOKEN_HERE',

    // =================================================================
    // Cloudflare API Configuration (Optional but Recommended)
    // =================================================================
    // Get API token from: Cloudflare Dashboard → My Profile → API Tokens
    // Required permissions: Zone:DNS:Edit
    'cloudflare_api_token' => 'YOUR_CLOUDFLARE_API_TOKEN',
    'cloudflare_zone_id' => 'YOUR_CLOUDFLARE_ZONE_ID',

    // =================================================================
    // AI API Configuration (REQUIRED for AI Deployment Assistant)
    // =================================================================
    // The AI assistant helps users deploy apps, fix errors, and configure sites
    // Each subdomain gets its own AI chat interface powered by Claude

    // Anthropic Claude API (RECOMMENDED)
    // Get key from: https://console.anthropic.com/
    // Cost: ~$0.01-0.05 per deployment conversation
    'anthropic_api_key' => 'sk-ant-YOUR_ANTHROPIC_API_KEY',

    // Choose AI provider: 'anthropic' (recommended) or 'gemini'
    'ai_provider' => 'anthropic',

    // Google Gemini API (Alternative)
    // Get key from: https://makersuite.google.com/app/apikey
    'gemini_api_key' => 'YOUR_GEMINI_API_KEY',

    // =================================================================
    // Admin Authentication
    // =================================================================
    // Generate hash with: php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
    // Use a STRONG password: 12+ characters, mixed case, numbers, symbols
    'admin_key_hash' => '$2y$10$EXAMPLE_HASH_REPLACE_WITH_YOUR_HASH',

    // =================================================================
    // System Paths
    // =================================================================
    // Absolute path to your web root
    'web_root' => '/home/your_username/public_html',

    // Log file location (must be writable)
    'log_path' => '/home/your_username/logs/subdomain_creation.log',

    // Template directory for new subdomains
    // Default: 'ai-assistant' - Deploys AI chat interface that helps with deployment
    // Alternative: 'modern' - Beautiful static template
    // Custom: Point to your own template directory
    'template_path' => __DIR__ . '/templates/ai-assistant',

    // If you prefer the static modern template instead:
    // 'template_path' => __DIR__ . '/templates/modern',

    // =================================================================
    // Optional: Advanced Settings
    // =================================================================

    // Session timeout in seconds (default: 1800 = 30 minutes)
    'session_timeout' => 1800,

    // Max subdomains per hour (rate limiting)
    'max_subdomains_per_hour' => 5,

    // Max deletions per hour (rate limiting)
    'max_deletions_per_hour' => 3,

    // Enable debug logging (set to false in production)
    'debug_mode' => false,

    // Default LMS if none selected
    'default_lms' => 'none',

    // AI generation timeout (seconds)
    'ai_timeout' => 60,
];
