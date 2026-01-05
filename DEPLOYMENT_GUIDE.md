# Complete Deployment Guide
## Deploy Demetrly Subdomain Creator to Your Domain

This guide walks you through deploying the Demetrly subdomain creator system to your own domain in ~45 minutes.

---

## 📋 Table of Contents

1. [What You'll Build](#what-youll-build)
2. [Prerequisites](#prerequisites)
3. [Phase 1: Gather Credentials](#phase-1-gather-credentials)
4. [Phase 2: Upload Files](#phase-2-upload-files)
5. [Phase 3: Configure](#phase-3-configure)
6. [Phase 4: Test Installation](#phase-4-test-installation)
7. [Phase 5: Create First Subdomain](#phase-5-create-first-subdomain)
8. [Phase 6: Deploy App via AI Chat](#phase-6-deploy-app-via-ai-chat)
9. [Troubleshooting](#troubleshooting)
10. [Next Steps](#next-steps)

---

## 🎯 What You'll Build

By the end of this guide, you'll have:

- **Subdomain Creator Interface** at `https://yourdomain.com/subdomaincreator/`
- Ability to create unlimited subdomains instantly via web interface
- Each subdomain gets:
  - AI-powered deployment assistant (Claude)
  - File upload interface
  - MySQL database
  - Automatic DNS configuration
  - Landing page with AI chat access

---

## ✅ Prerequisites

### Required:
- ✅ **cPanel hosting account** with:
  - SSH access (for setup)
  - PHP 8.0+
  - MySQL database support
  - SQLite3 PHP extension
  - cURL enabled
- ✅ **Domain name** configured in cPanel
- ✅ **Anthropic API key** (for Claude AI) - [Get free credits](https://console.anthropic.com/)

### Optional (but recommended):
- Cloudflare account (for DNS management)
- FTP/SFTP client (alternative to SSH)
- Text editor for configuration files

### Time Required:
- **Setup:** 20 minutes
- **Testing:** 25 minutes
- **Total:** ~45 minutes

---

## 🔑 Phase 1: Gather Credentials

Before uploading files, gather these credentials. You'll need them for configuration.

### 1.1 cPanel API Token

**What it's for:** Creating subdomains and databases programmatically

**How to get it:**

1. Log into cPanel (`https://your-cpanel-host:2083`)
2. Navigate to **Security → Manage API Tokens**
3. Click **"Create"**
4. Name: `subdomain_creator`
5. Click **"Generate Token"**
6. **Copy the token immediately** (won't be shown again)
7. Save to a secure location

**Example token:** `ABCD1234567890EFGHIJKLMNOPQRSTUVWXYZ`

### 1.2 Anthropic API Key

**What it's for:** Powering the AI deployment assistant (Claude)

**How to get it:**

1. Go to [console.anthropic.com](https://console.anthropic.com/)
2. Sign up or log in
3. Navigate to **API Keys** section
4. Click **"Create Key"**
5. Name: `subdomain-creator`
6. Copy the key (starts with `sk-ant-`)
7. Save to a secure location

**Free tier:** $5 in credits (enough for ~50,000 messages)

### 1.3 Cloudflare API Token (Optional)

**What it's for:** Automatic DNS record creation for subdomains

**If you use Cloudflare for DNS:**

1. Go to [Cloudflare Dashboard](https://dash.cloudflare.com/)
2. Select your domain
3. **Copy Zone ID** from right sidebar (under "API" section)
4. Go to **Profile → API Tokens**
5. Click **"Create Token"**
6. Use template: **"Edit zone DNS"**
7. Select your domain zone
8. Click **"Continue to summary"** → **"Create Token"**
9. Copy both **Zone ID** and **API Token**

**If you don't use Cloudflare:**
- Skip this step
- Set `cloudflare_enabled => false` in config (covered later)

### 1.4 Admin Password

**Choose a strong password** for the subdomain creator admin panel.

We'll generate a secure hash in Phase 3.

**Example:** `MySecurePassword123!`

---

## 📤 Phase 2: Upload Files

### 2.1 Download/Clone Repository

**Option A: Git Clone (Recommended)**
```bash
cd ~/Downloads
git clone https://github.com/elblanco2/demetrly.git
cd demetrly/subdomaincreator
```

**Option B: Download ZIP**
1. Go to GitHub repository
2. Click **"Code" → "Download ZIP"**
3. Extract to your local machine

### 2.2 Connect to Server via SSH

```bash
ssh -p YOUR_SSH_PORT username@your-server.com
```

**Example:**
```bash
ssh -p YOUR_SSH_PORT username@your-server.com
```

### 2.3 Create Directory Structure

```bash
# Create subdomain creator directory
mkdir -p ~/public_html/yourdomain.com/subdomaincreator

# Create config directory (outside web root for security)
mkdir -p ~/config

# Create logs directory
mkdir -p ~/logs

# Create data directory (for SQLite database)
mkdir -p ~/public_html/yourdomain.com/subdomaincreator/data
```

**Replace `yourdomain.com` with your actual domain!**

### 2.4 Upload Files

**Option A: Using SCP (from your local machine)**

```bash
# Upload entire directory
scp -r -P YOUR_SSH_PORT subdomaincreator/* username@server:/home/username/public_html/yourdomain.com/subdomaincreator/
```

**Example:**
```bash
scp -r -P YOUR_SSH_PORT subdomaincreator/* username@your-server.com:/home/username/public_html/yourdomain.com/subdomaincreator/
```

**Option B: Using cPanel File Manager**

1. Log into cPanel
2. Open **File Manager**
3. Navigate to `public_html/yourdomain.com/`
4. Create folder: `subdomaincreator`
5. Upload all files from local `subdomaincreator/` folder
6. Extract if uploaded as ZIP

### 2.5 Verify Upload

```bash
ssh -p YOUR_SSH_PORT username@server
ls -la ~/public_html/yourdomain.com/subdomaincreator/
```

**Expected output:**
```
index.php
creator_config.sample.php
includes/
ajax/
assets/
templates/
data/
```

---

## ⚙️ Phase 3: Configure

### 3.1 Create Configuration File

**Connect to server:**
```bash
ssh -p YOUR_SSH_PORT username@server
```

**Copy sample config to secure location:**
```bash
cp ~/public_html/yourdomain.com/subdomaincreator/creator_config.sample.php \
   ~/config/creator_config_yourdomain.php
```

**Example:**
```bash
cp ~/public_html/lucasblanco.com/subdomaincreator/creator_config.sample.php \
   ~/config/creator_config_lucasblanco.php
```

### 3.2 Edit Configuration

**Open config file:**
```bash
nano ~/config/creator_config_yourdomain.php
```

**Update these values** (use credentials from Phase 1):

```php
<?php
return [
    // ========================================
    // DOMAIN CONFIGURATION
    // ========================================
    'domain' => 'yourdomain.com',  // ⚠️ CHANGE THIS!

    // ========================================
    // CPANEL INTEGRATION
    // ========================================
    'cpanel_host' => 'your-server.com',      // Your cPanel host
    'cpanel_user' => 'your_username',        // Your cPanel username
    'cpanel_token' => 'YOUR_CPANEL_TOKEN',   // Paste token from Phase 1.1

    // ========================================
    // CLOUDFLARE DNS (Optional)
    // ========================================
    'cloudflare_enabled' => true,  // Set false if not using Cloudflare
    'cloudflare_api_token' => 'YOUR_CLOUDFLARE_TOKEN',  // From Phase 1.3
    'cloudflare_zone_id' => 'YOUR_ZONE_ID',             // From Phase 1.3

    // ========================================
    // AI PROVIDER
    // ========================================
    'ai_provider' => 'anthropic',  // Keep as 'anthropic'
    'anthropic_api_key' => 'sk-ant-YOUR_KEY',  // Paste from Phase 1.2
    'model' => 'claude-sonnet-4-5-20250929',   // Keep default

    // ========================================
    // ADMIN AUTHENTICATION
    // ========================================
    'admin_key_hash' => 'GENERATE_THIS_NEXT',  // We'll generate below

    // ========================================
    // PATHS
    // ========================================
    'web_root' => '/home/username/public_html',  // ⚠️ CHANGE username
    'template_path' => '/home/username/public_html/yourdomain.com/subdomaincreator/templates/ai-assistant-v1.1',  // ⚠️ CHANGE
    'log_path' => '/home/username/logs/subdomain_creation.log',  // ⚠️ CHANGE

    // ========================================
    // SECURITY & RATE LIMITING
    // ========================================
    'session_timeout' => 1800,           // 30 minutes
    'max_subdomains_per_hour' => 5,
    'max_deletions_per_hour' => 3,
    'debug_mode' => false,               // Set true only for troubleshooting
    'auto_rollback' => false,            // Advanced feature

    // ========================================
    // WEBSITE CUSTOMIZATION
    // ========================================
    'site_name' => 'My Subdomain Creator',
    'site_description' => 'AI-powered subdomain deployment system',
];
```

### 3.3 Generate Password Hash

**Run this command on server:**
```bash
php -r "echo password_hash('YourChosenPassword', PASSWORD_DEFAULT) . \"\n\";"
```

**Example:**
```bash
php -r "echo password_hash('MySecurePassword123!', PASSWORD_DEFAULT) . \"\n\";"
```

**Output (example):**
```
$2y$10$abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJKLMNOP
```

**Copy the output and paste it into config:**
```php
'admin_key_hash' => '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJKLMNOP',
```

**Save and exit:**
- Press `Ctrl + O` (save)
- Press `Enter` (confirm)
- Press `Ctrl + X` (exit)

### 3.4 Update index.php Config Path

**Edit index.php:**
```bash
nano ~/public_html/yourdomain.com/subdomaincreator/index.php
```

**Find line ~20:**
```php
$configPath = '/home/username/config/creator_config.php';
```

**Change to YOUR path:**
```php
$configPath = '/home/username/config/creator_config_yourdomain.php';
```

**Save and exit** (`Ctrl+O`, `Enter`, `Ctrl+X`)

### 3.5 Set File Permissions

```bash
# Config file (strict permissions - only you can read)
chmod 600 ~/config/creator_config_yourdomain.php

# Application directories (readable by web server)
chmod 755 ~/public_html/yourdomain.com/subdomaincreator
chmod 755 ~/public_html/yourdomain.com/subdomaincreator/data
chmod 755 ~/logs

# Template directory
chmod -R 755 ~/public_html/yourdomain.com/subdomaincreator/templates/

# Make includes and ajax directories readable
chmod 755 ~/public_html/yourdomain.com/subdomaincreator/includes
chmod 755 ~/public_html/yourdomain.com/subdomaincreator/ajax
```

---

## 🧪 Phase 4: Test Installation

### 4.1 Check If Domain Uses Cloudflare

```bash
dig yourdomain.com NS +short
```

**Expected:**
- **If using Cloudflare:** Shows nameservers like `name1.cloudflare.com`
- **If NOT using Cloudflare:** Shows your host's nameservers

**Action:**
- If NOT using Cloudflare: Go back to config and set `cloudflare_enabled => false`

### 4.2 Access Web Interface

Open browser and visit:
```
https://yourdomain.com/subdomaincreator/
```

**Expected result:**
✅ Login page appears with password field

**If you see errors:**
- Check PHP error logs: `~/logs/error_log` or cPanel → Error Log
- Verify config path in index.php is correct
- Verify permissions: `ls -la ~/config/`
- Check all credentials in config file

### 4.3 Test Login

1. Enter the password you chose in Phase 3.3
2. Click **"Login"**

**Expected result:**
✅ Redirect to tabbed interface with "Create Subdomain" and "Manage Subdomains" tabs

**If login fails:**
- Verify password hash was generated correctly
- Check session support: `php -i | grep session`
- Clear browser cookies and try again

---

## 🚀 Phase 5: Create First Subdomain

### 5.1 Fill Out Form

**You're now in the subdomain creator interface!**

**Fill out the form:**

| Field | Value | Description |
|-------|-------|-------------|
| **Subdomain Name** | `test01` | Choose any name (lowercase, no spaces) |
| **Description** | `First test subdomain` | Brief description |
| **Educational Focus** | Leave blank or `Testing` | Optional metadata |
| **Primary LMS** | Select `None` | Select from dropdown |
| **AI Content Generation** | ✅ Check this box | Enables AI-generated welcome content |

### 5.2 Create Subdomain

Click **"Create Subdomain"** button

**Watch the progress messages:**
- ⏳ Validating input...
- ⏳ Creating cPanel subdomain...
- ✅ cPanel subdomain created successfully
- ⏳ Creating Cloudflare DNS record... (if enabled)
- ✅ DNS record created
- ⏳ Creating MySQL database...
- ✅ Database created: `username_test01`
- ⏳ Deploying template files...
- ✅ Template deployed successfully
- ⏳ Generating AI configuration...
- ✅ Subdomain created successfully!

**Expected time:** 10-30 seconds

### 5.3 Verify in Manage Tab

Click **"Manage Subdomains"** tab

**You should see:**

| Subdomain | Status | Database | Created |
|-----------|--------|----------|---------|
| test01.yourdomain.com | Active | username_test01 | Just now |

Click **"View Logs"** to see step-by-step creation process

### 5.4 Access Your New Subdomain

Open new browser tab and visit:
```
https://test01.yourdomain.com/
```

**Expected result:**
✅ Landing page appears with cards:
- **"AI Deployment Assistant"** card
- **"About This Site"** section
- Link to `/ai/` chat interface

Click **"AI Deployment Assistant"** → Should redirect to `/ai/` chat

---

## 🤖 Phase 6: Deploy App via AI Chat

**Now comes the fun part - let's deploy a real app using ONLY the AI chat!**

### 6.1 Access AI Chat

Visit:
```
https://test01.yourdomain.com/ai/
```

**Expected:**
✅ AI chat interface loads
✅ Welcome message appears
✅ Quick action buttons visible

**Select mode:**
- **Beginner Mode:** Detailed explanations, step-by-step
- **Expert Mode:** Concise, fast execution

### 6.2 AI Chat Conversation

**Follow these prompts exactly:**

---

#### **Prompt 1:** Initial Request
```
Hi, I want to deploy a GitHub repository
```

**Expected AI Response:**
- Greets you
- Asks for GitHub repository URL
- May offer quick action buttons

---

#### **Prompt 2:** Provide GitHub URL
```
https://github.com/bradtraversy/2048-game
```

**Expected AI Response:**
- Acknowledges the repo
- Explains it can't clone directly (no git on server)
- Offers to create upload interface
- Provides instructions to download ZIP from GitHub

---

#### **Prompt 3:** Request Upload Interface
```
Create an upload interface for me
```

**Expected AI Action:**
- Executes: `<action type="create_upload" label="Create Upload Interface" autoExecute="true" />`
- Creates `/uploads/upload.php` with drag-and-drop UI
- Returns success message with link

**Expected Response:**
```
✅ Upload interface created!

You can now upload files at:
https://test01.yourdomain.com/uploads/upload.php
```

---

#### **Prompt 4:** Download and Upload

**Manual step (you do this):**

1. Go to: https://github.com/bradtraversy/2048-game
2. Click **"Code" → "Download ZIP"**
3. Save as `2048-game.zip`
4. Visit: `https://test01.yourdomain.com/uploads/upload.php`
5. Drag and drop `2048-game.zip` into upload area
6. Wait for "Upload complete" message

---

#### **Prompt 5:** Notify AI of Upload
```
I uploaded 2048-game.zip
```

**Expected AI Workflow:**
1. Executes: `<action type="list_uploads">`
2. Sees: `2048-game.zip` in uploads
3. Responds: "I found 2048-game.zip!"
4. Executes: `<action type="extract_zip" filename="2048-game.zip">`
5. Confirms extraction to `extracted_2048-game/`
6. Executes: `<action type="detect_project_type" path="extracted_2048-game">`
7. Detects: Static HTML project
8. Offers deployment action button

**Expected Response:**
```
✅ Extraction complete! I found your files.

Project Analysis:
• Type: Static HTML
• Files found: index.html, style.css, script.js
• Deployment ready: Yes

Ready to deploy? Click the button below:
[Deploy to /app/] ← (button appears)
```

---

#### **Prompt 6:** Confirm Deployment
```
Yes, deploy it
```

**Expected AI Action:**
1. Executes: `<action type="deploy_app" source_path="extracted_2048-game/2048-master" target="app">`
2. Copies files to `/app/2048-master/`
3. Confirms deployment success
4. Offers redirect creation

**Expected Response:**
```
✅ Deployment complete! Your app is at /app/2048-master/

Would you like me to create a redirect from your root domain?
This will make it so visitors to https://test01.yourdomain.com
automatically go to the app.

[Create Redirect] ← (button appears)

Note: This will replace your landing page with the app.
```

---

#### **Prompt 7:** Create Redirect
```
Yes, create the redirect
```

**Expected AI Action:**
1. Executes: `<action type="create_redirect" type="302">`
2. Searches for `index.html` in `/app/`
3. Finds: `/app/2048-master/index.html`
4. Creates `.htaccess` with redirect rule
5. Returns success

**Expected Response:**
```
✅ Created 302 redirect from / to /app/2048-master/index.html

🔗 Redirect created!
Visit your root domain: https://test01.yourdomain.com/
It will automatically redirect to: https://test01.yourdomain.com/app/2048-master/index.html

Note: Found 1 index.html file.
Using: /app/2048-master/index.html
```

---

#### **Prompt 8:** Cleanup
```
Clean up the uploads
```

**Expected AI Action:**
1. Executes: `<action type="cleanup_uploads" filename="2048-game.zip" extracted_dir="extracted_2048-game">`
2. Deletes ZIP file
3. Deletes extracted directory
4. Confirms cleanup

**Expected Response:**
```
✅ Cleanup completed

Cleaned up:
• 2048-game.zip
• extracted_2048-game
```

---

### 6.3 Verify Deployment

**Test 1: Root Domain Redirect**
```
https://test01.yourdomain.com/
```
**Expected:** Auto-redirects to `/app/2048-master/index.html` → 2048 game loads and is playable ✅

**Test 2: AI Still Accessible**
```
https://test01.yourdomain.com/ai/
```
**Expected:** AI chat loads normally (no redirect interference) ✅

**Test 3: Conversation History Persisted**
- Refresh the `/ai/` page
- Scroll up in chat
- **Expected:** All previous messages still visible (stored in localStorage) ✅

---

## 🎉 Success!

**You've successfully:**
- ✅ Installed subdomain creator on your domain
- ✅ Created first subdomain via web interface
- ✅ Deployed a real app using ONLY AI chat
- ✅ Set up automatic redirect
- ✅ Verified all features work

**Your subdomain creator is now ready for production use!**

---

## 🔧 Troubleshooting

### Issue 1: "Configuration file not found"

**Symptom:** Error on login page

**Solution:**
```bash
# Check config file exists
ls -la ~/config/creator_config_*.php

# Check path in index.php
grep configPath ~/public_html/yourdomain.com/subdomaincreator/index.php

# Verify permissions
chmod 600 ~/config/creator_config_*.php
```

### Issue 2: "Unauthorized" during subdomain creation

**Symptom:** cPanel API call fails

**Solution:**
- Verify cPanel token in config hasn't expired
- Check token permissions: SubDomain, Mysql
- Generate new token in cPanel → Security → Manage API Tokens
- Update config with new token

### Issue 3: Subdomain created but no files

**Symptom:** Subdomain exists but shows blank page

**Solution:**
```bash
# Check template path in config
grep template_path ~/config/creator_config_*.php

# Verify template exists
ls -la ~/public_html/yourdomain.com/subdomaincreator/templates/ai-assistant-v1.1/

# Check permissions
chmod -R 755 ~/public_html/yourdomain.com/subdomaincreator/templates/
```

### Issue 4: AI not responding

**Symptom:** Chat loads but AI doesn't respond to messages

**Solution:**
1. Open browser console (F12)
2. Look for errors
3. Check Anthropic API key in subdomain's `/ai/config.php`
4. Verify API key is valid at console.anthropic.com
5. Check if you have API credits remaining

### Issue 5: Redirect creates infinite loop

**Symptom:** Subdomain keeps redirecting, can't access /ai/

**Solution:**
```bash
# Check .htaccess content
cat ~/public_html/test01.yourdomain.com/.htaccess

# Should use RedirectMatch ^/$ not Redirect /
# If wrong, fix it:
echo "RedirectMatch 302 ^/$ /app/2048-master/index.html" > ~/public_html/test01.yourdomain.com/.htaccess
```

### Issue 6: Upload fails with "File too large"

**Symptom:** File upload shows error

**Solution:**
```bash
# Check PHP upload limits
php -i | grep upload_max_filesize
php -i | grep post_max_size

# Increase in php.ini or .htaccess
echo "upload_max_filesize = 100M" >> ~/public_html/test01.yourdomain.com/.htaccess
echo "post_max_size = 100M" >> ~/public_html/test01.yourdomain.com/.htaccess
```

### Get Help

If you're stuck:

1. **Check logs:**
   - `~/logs/subdomain_creation.log`
   - cPanel → Errors → Last 300 Errors

2. **Debug mode:**
   - Edit config: `'debug_mode' => true`
   - Reproduce issue
   - Check detailed error messages
   - **Remember to set back to false!**

3. **GitHub Issues:**
   - Search existing issues: [github.com/elblanco2/demetrly/issues](https://github.com/elblanco2/demetrly/issues)
   - Create new issue with:
     - PHP version (`php -v`)
     - Error logs
     - Steps to reproduce

---

## 🚀 Next Steps

### Create More Subdomains

You can now create as many subdomains as you want:

1. Go to: `https://yourdomain.com/subdomaincreator/`
2. Fill out form with new subdomain name
3. Click "Create Subdomain"
4. Deploy apps via AI chat

**Ideas for subdomains:**
- `blog.yourdomain.com` - Personal blog
- `portfolio.yourdomain.com` - Portfolio site
- `docs.yourdomain.com` - Documentation
- `demo.yourdomain.com` - App demos
- `sandbox.yourdomain.com` - Testing environment

### Customize Templates

Modify the landing page template:

```bash
nano ~/public_html/yourdomain.com/subdomaincreator/templates/ai-assistant-v1.1/index.html
```

**All new subdomains will use your customized template!**

### Delete Subdomains

**Via web interface:**
1. Go to: `https://yourdomain.com/subdomaincreator/`
2. Click "Manage Subdomains" tab
3. Find subdomain to delete
4. Type subdomain name in confirmation
5. Click "Delete"

**What gets deleted:**
- ✅ cPanel subdomain
- ✅ Cloudflare DNS record
- ✅ MySQL database
- ✅ All files in subdomain directory

### Backup Your Data

**Backup SQLite database regularly:**
```bash
# Manual backup
cp ~/public_html/yourdomain.com/subdomaincreator/data/subdomain_tracker.db \
   ~/backups/subdomain_tracker_$(date +%Y%m%d).db

# Automated daily backup (add to crontab)
0 2 * * * cp ~/public_html/yourdomain.com/subdomaincreator/data/subdomain_tracker.db ~/backups/subdomain_tracker_$(date +%Y%m%d).db
```

### Monitor Usage

**View all subdomains:**
```bash
sqlite3 ~/public_html/yourdomain.com/subdomaincreator/data/subdomain_tracker.db \
  "SELECT subdomain_name, created_at, status FROM subdomains ORDER BY created_at DESC;"
```

**Export to CSV:**
1. Go to: `https://yourdomain.com/subdomaincreator/`
2. Click "Manage Subdomains" tab
3. Click "Export" button
4. Choose CSV or JSON format

### Security Best Practices

1. **Change admin password regularly:**
   ```bash
   php -r "echo password_hash('NewSecurePassword', PASSWORD_DEFAULT);"
   # Update hash in config file
   ```

2. **Monitor logs for suspicious activity:**
   ```bash
   tail -f ~/logs/subdomain_creation.log
   ```

3. **Keep config file secure:**
   ```bash
   chmod 600 ~/config/creator_config_*.php
   ls -la ~/config/  # Verify permissions
   ```

4. **Limit API token permissions:**
   - cPanel: Only SubDomain and Mysql
   - Cloudflare: Only Zone DNS Edit
   - Anthropic: Default permissions

5. **Regular backups:**
   - SQLite database (daily)
   - Configuration file (weekly)
   - Template files (after customization)

---

## 📚 Additional Resources

- **Main README:** [README.md](README.md) - Feature overview
- **Quick Start:** [QUICK_START.md](QUICK_START.md) - 10-minute setup for experienced users
- **Beginner Guide:** [BEGINNER_GUIDE.md](BEGINNER_GUIDE.md) - Detailed walkthrough with screenshots
- **Security:** [SECURITY.md](SECURITY.md) - Security best practices
- **Contributing:** [CONTRIBUTING.md](CONTRIBUTING.md) - Contribute to the project

---

## ❓ FAQ

**Q: Can I use this with shared hosting?**
A: Yes! As long as you have cPanel access and PHP 8.0+

**Q: Do I need a dedicated server?**
A: No, shared hosting works fine

**Q: How many subdomains can I create?**
A: Limited only by your hosting plan (most allow unlimited subdomains)

**Q: Can I use a different AI provider?**
A: Currently supports Anthropic Claude and Google Gemini

**Q: Is this free?**
A: The software is free (MIT license). You pay for:
- Hosting (varies by provider)
- Anthropic API usage (~$0.10-0.50 per subdomain deployment)

**Q: Can I customize the AI assistant?**
A: Yes! Edit templates/ai-assistant-v1.1/ai/api.php to modify behavior

**Q: What happens if I delete a subdomain?**
A: Full cleanup: cPanel subdomain, DNS, database, and all files deleted

**Q: Can I restore a deleted subdomain?**
A: No automatic restore. Always backup important data before deleting.

---

## 💬 Support

Need help? Here's how to get support:

1. **Documentation:** Read all guides in this repo
2. **Search Issues:** [github.com/elblanco2/demetrly/issues](https://github.com/elblanco2/demetrly/issues)
3. **Create Issue:** Include error logs, PHP version, steps to reproduce
4. **Community:** Join discussions in GitHub Discussions

---

## ✨ Success Stories

**Example deployments using this system:**

| Subdomain | Purpose | App Deployed | Time Taken |
|-----------|---------|--------------|------------|
| docarch.apiprofe.com | Document archive | Custom PHP app | 3 minutes |
| haha.apiprofe.com | Testing | Mini-snake game | 5 minutes |
| tetrisdemo.apiprofe.com | Demo | Tetris clone | 4 minutes |

**Share your success story!**
Create a pull request adding your deployment to this table.

---

**Ready to deploy? Start with [Phase 1: Gather Credentials](#phase-1-gather-credentials)**

Happy deploying! 🚀
