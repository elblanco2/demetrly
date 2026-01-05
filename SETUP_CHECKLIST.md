# Setup Checklist
## Quick Reference for Deploying SubForge

Print this checklist and mark off each step as you complete it.

---

## ☑️ Pre-Installation (5 minutes)

### Gather Credentials

- [ ] **cPanel API Token**
  - Location: cPanel → Security → Manage API Tokens → Create
  - Save to: `_____________________`

- [ ] **Anthropic API Key** (for Claude AI)
  - Location: console.anthropic.com → API Keys
  - Starts with: `sk-ant-`
  - Save to: `_____________________`

- [ ] **Cloudflare Zone ID** (optional, if using Cloudflare)
  - Location: Cloudflare Dashboard → Right sidebar
  - Save to: `_____________________`

- [ ] **Cloudflare API Token** (optional, if using Cloudflare)
  - Location: Profile → API Tokens → Create Token
  - Template: "Edit zone DNS"
  - Save to: `_____________________`

### Server Information

- [ ] cPanel Host: `_____________________`
- [ ] cPanel Username: `_____________________`
- [ ] SSH Port: `_____________________` (usually 22 or 8228)
- [ ] Web Root: `/home/_________/public_html`

### Choose Admin Password

- [ ] Password: `_____________________` (keep secure!)

---

## ☑️ Installation (20 minutes)

### Upload Files

- [ ] Connect to server via SSH
- [ ] Create directory: `~/public_html/yourdomain.com/subdomaincreator`
- [ ] Create directory: `~/config`
- [ ] Create directory: `~/logs`
- [ ] Upload all files from `subdomaincreator/` folder
- [ ] Verify upload: Check `index.php` exists

### Configure

- [ ] Copy `creator_config.sample.php` to `~/config/creator_config_yourdomain.php`
- [ ] Edit config file and update:
  - [ ] `domain` → Your domain name
  - [ ] `cpanel_host` → Your cPanel host
  - [ ] `cpanel_user` → Your cPanel username
  - [ ] `cpanel_token` → Paste from credentials above
  - [ ] `cloudflare_enabled` → true/false (based on your setup)
  - [ ] `cloudflare_api_token` → Paste if using Cloudflare
  - [ ] `cloudflare_zone_id` → Paste if using Cloudflare
  - [ ] `anthropic_api_key` → Paste from credentials above
  - [ ] `web_root` → Update with your username
  - [ ] `template_path` → Update with your domain and username
  - [ ] `log_path` → Update with your username

- [ ] Generate password hash:
  ```bash
  php -r "echo password_hash('YourPassword', PASSWORD_DEFAULT);"
  ```

- [ ] Paste hash into config: `admin_key_hash` → `_____________________`

- [ ] Edit `index.php` line ~20:
  - [ ] Update `$configPath` to your config file location

### Set Permissions

- [ ] `chmod 600 ~/config/creator_config_yourdomain.php`
- [ ] `chmod 755 ~/public_html/yourdomain.com/subdomaincreator`
- [ ] `chmod 755 ~/public_html/yourdomain.com/subdomaincreator/data`
- [ ] `chmod -R 755 ~/public_html/yourdomain.com/subdomaincreator/templates/`

---

## ☑️ Testing (5 minutes)

### Verify Installation

- [ ] Check Cloudflare status: `dig yourdomain.com NS +short`
  - If shows Cloudflare nameservers → Keep `cloudflare_enabled => true`
  - If NOT Cloudflare → Set `cloudflare_enabled => false` in config

- [ ] Visit: `https://yourdomain.com/subdomaincreator/`
  - [ ] Login page appears
  - [ ] No PHP errors shown

- [ ] Login with your password
  - [ ] Redirects to tabbed interface
  - [ ] "Create Subdomain" tab visible
  - [ ] "Manage Subdomains" tab visible

---

## ☑️ Create First Subdomain (5 minutes)

### Fill Out Form

- [ ] Subdomain Name: `test01`
- [ ] Description: `First test subdomain`
- [ ] Educational Focus: (leave blank or "Testing")
- [ ] Primary LMS: `None`
- [ ] AI Content Generation: ✅ Checked

### Create

- [ ] Click "Create Subdomain"
- [ ] Wait for success messages:
  - [ ] ✅ cPanel subdomain created
  - [ ] ✅ Cloudflare DNS created (if enabled)
  - [ ] ✅ Database created
  - [ ] ✅ Template deployed
  - [ ] ✅ Subdomain created successfully

### Verify

- [ ] Check "Manage Subdomains" tab
  - [ ] `test01.yourdomain.com` appears with status "Active"
- [ ] Visit: `https://test01.yourdomain.com/`
  - [ ] Landing page loads
  - [ ] "AI Deployment Assistant" card visible
- [ ] Click "AI Deployment Assistant"
  - [ ] Redirects to `/ai/` chat
  - [ ] Chat interface loads
  - [ ] Welcome message appears

---

## ☑️ Deploy App via AI Chat (15 minutes)

### Prepare

- [ ] Download: https://github.com/bradtraversy/2048-game → "Download ZIP"
- [ ] Save as: `2048-game.zip`

### AI Chat Conversation

Visit: `https://test01.yourdomain.com/ai/`

- [ ] **Prompt 1:** `Hi, I want to deploy a GitHub repository`
  - [ ] AI responds with greeting

- [ ] **Prompt 2:** `https://github.com/bradtraversy/2048-game`
  - [ ] AI acknowledges repo

- [ ] **Prompt 3:** `Create an upload interface for me`
  - [ ] AI creates upload.php
  - [ ] Link appears: `https://test01.yourdomain.com/uploads/upload.php`

- [ ] **Upload file:**
  - [ ] Visit upload link
  - [ ] Drag `2048-game.zip` into upload area
  - [ ] Wait for "Upload complete"

- [ ] **Prompt 4:** `I uploaded 2048-game.zip`
  - [ ] AI finds file
  - [ ] AI extracts ZIP
  - [ ] AI detects project type: "Static HTML"
  - [ ] AI offers deployment button

- [ ] **Prompt 5:** `Yes, deploy it`
  - [ ] AI deploys to `/app/2048-master/`
  - [ ] AI offers redirect creation

- [ ] **Prompt 6:** `Yes, create the redirect`
  - [ ] AI creates .htaccess redirect
  - [ ] Success message shows final URL

- [ ] **Prompt 7:** `Clean up the uploads`
  - [ ] AI removes ZIP and extracted files
  - [ ] Cleanup confirmation shown

### Verify Deployment

- [ ] Visit: `https://test01.yourdomain.com/`
  - [ ] Auto-redirects to 2048 game
  - [ ] Game loads and is playable

- [ ] Visit: `https://test01.yourdomain.com/ai/`
  - [ ] AI chat still accessible (no redirect interference)

- [ ] Refresh AI chat page
  - [ ] Previous conversation history still visible

---

## ✅ Success!

You've successfully deployed SubForge! Your system is ready for production use.

### Next Steps:

- [ ] Create more subdomains for real projects
- [ ] Customize landing page template
- [ ] Set up automated backups
- [ ] Bookmark management interface: `https://yourdomain.com/subdomaincreator/`

---

## 📝 Notes

Use this space to write down any important information:

**My subdomain creator URL:**
```
https://________________________________/subdomaincreator/
```

**Admin password reminder:**
```
Stored in: _________________________________
```

**API keys stored in:**
```
Config file: _________________________________
```

**Issues encountered:**
```
_________________________________________________________________

_________________________________________________________________

_________________________________________________________________
```

**Custom modifications made:**
```
_________________________________________________________________

_________________________________________________________________

_________________________________________________________________
```

---

## 🆘 Need Help?

If you get stuck:

1. ✅ Read: [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) - Full walkthrough
2. ✅ Check: [Troubleshooting section](DEPLOYMENT_GUIDE.md#troubleshooting)
3. ✅ Search: [GitHub Issues](https://github.com/yourusername/subforge/issues)
4. ✅ Create: New issue with error logs

**Common issues:**
- "Configuration file not found" → Check path in index.php
- "Unauthorized" → Verify cPanel API token
- "No files deployed" → Check template_path in config
- "AI not responding" → Verify Anthropic API key

---

**Checklist Version:** 1.0
**Last Updated:** January 2026
**Total Time:** ~45 minutes
