# AI Deployment Assistant Template

This template provides an **AI-powered deployment assistant** for your subdomain. It's like having Claude Code built into your website!

## What It Does

The AI assistant helps you:
- 🤖 **Deploy GitHub repositories** - Just paste a URL
- 🔧 **Fix permissions** - Automatically correct file/folder permissions
- 📁 **Create upload interfaces** - Drag-and-drop file uploads
- 🐛 **Debug errors** - Read logs and suggest fixes
- 📦 **Install WordPress** - Guided WordPress installation
- ⚙️ **Configure environment** - Set up databases, .htaccess, etc.

## How It Works

### 1. **Conversational Interface**
Chat naturally with the AI:
```
You: Deploy https://github.com/user/my-react-app
AI: I see this is a React app. Since this server doesn't have Node.js,
    I can help you build it locally and upload the files.

    Would you like me to:
    1. Create an upload interface
    2. Guide you through the build process
    3. Suggest alternative hosting
```

### 2. **Auto-Actions**
AI can execute server commands:
- Fix permissions (`chmod`)
- Create files (upload.php, .htaccess)
- Read error logs
- Check PHP version/extensions
- Create database configs

### 3. **Smart Detection**
Paste a GitHub URL and AI detects:
- React/Vue/Angular → Requires build step
- PHP/WordPress → Can deploy directly
- Static HTML → Ready to upload
- Node.js apps → Suggests alternatives

## Files

```
ai-assistant/
├── index.html      # Chat interface (beautiful UI)
├── chat.js         # Frontend logic
├── api.php         # Backend (proxies to Claude)
├── config.php      # Configuration (auto-generated)
└── README.md       # This file
```

## API Integration

The assistant uses **Claude 3.5 Sonnet** via Anthropic API.

**Key configured in:** `config.php` → `ANTHROPIC_API_KEY`

**System prompt includes:**
- Server environment details
- Available capabilities
- Limitations (no Node.js, etc.)
- Action commands AI can execute

## Example Conversations

### Deploy Static Site
```
User: I want to upload my HTML site
AI: I'll create a file upload interface for you!
    <Executes: create_upload action>
    ✅ Upload interface ready at upload.php
    You can now drag-and-drop your files!
```

### Fix Errors
```
User: Getting a 500 error
AI: Let me check the error logs...
    <Executes: check_logs action>

    Found the issue: Missing .htaccess file
    I'll create one for you with proper PHP settings.
    <Executes: create_htaccess action>
    ✅ Done! Try refreshing your site.
```

### GitHub Deployment
```
User: Deploy https://github.com/wordpress/wordpress
AI: I can help you install WordPress!
    <Executes: install_wordpress action>

    ✓ WordPress downloaded
    ✓ Database configured
    ✓ wp-config.php created

    Visit https://yoursite.com/wp-admin to complete setup!
```

## Customization

### Add New Actions

Edit `api.php` and add to `handleAction()`:

```php
case 'my_custom_action':
    // Your PHP code here
    exec("your-command");

    echo json_encode([
        'success' => true,
        'message' => 'Action completed!'
    ]);
    break;
```

Then AI can use it:
```xml
<action type="my_custom_action" label="Do Something Cool" autoExecute="true" />
```

### Modify System Prompt

Edit `buildSystemPrompt()` in `api.php` to change AI behavior.

### Change AI Model

In `api.php`, change:
```php
'model' => 'claude-3-5-haiku-20241022',  // Faster, cheaper
// or
'model' => 'claude-3-opus-20240229',     // Most capable
```

## Security

✅ **Safe:**
- AI can't execute arbitrary shell commands
- Only pre-defined actions allowed
- All file paths validated
- No database credentials exposed to AI

⚠️ **Keep Private:**
- `config.php` (contains API key)
- Conversation history (stored in browser localStorage)

## Conversation History

Chats are saved in browser localStorage. To clear:
```javascript
localStorage.removeItem('ai_chat_history');
location.reload();
```

Or use the "Clear Chat" button (add to UI if desired).

## Cost

Uses Anthropic Claude API:
- **Sonnet 3.5:** ~$3 per million input tokens, ~$15 per million output tokens
- Typical conversation: ~$0.01-0.05
- Budget-friendly for personal use

## Troubleshooting

**AI not responding?**
- Check API key in `config.php`
- Check browser console for errors
- Verify `api.php` has correct permissions (644)

**Actions not working?**
- Check PHP error logs
- Ensure directory is writable
- Try manually: `chmod 755 /path/to/subdomain`

**Want to disable AI?**
- Replace with regular template
- Or remove API key from config (disables AI)

---

**Built with Demetrly** 🚀
Powered by Claude from Anthropic
