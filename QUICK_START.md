# Demetrly Quick Start
## From Zero to Deployed in 10 Minutes

**Just bought hosting? Start here.** ⚡

---

## ⚡ TL;DR (The Absolute Fastest Way)

```bash
1. Get 3 API keys (5 min)
2. Upload Demetrly (2 min)
3. Configure (2 min)
4. Create subdomain (30 sec)
5. Deploy with AI (30 sec)
```

**Total time:** ~10 minutes to your first live site

---

## 🔑 Step 1: Get API Keys (5 minutes)

### cPanel API Token
1. Log into cPanel
2. Security → "Manage API Tokens"
3. Create → Name: `demetrly` → Create
4. **Copy and save the token**

### Cloudflare (Optional but Recommended)
1. Sign up at cloudflare.com (free)
2. Add your domain
3. Get API Token: Profile → API Tokens → Create Token
   - Template: "Edit zone DNS"
   - Select your domain
4. **Copy Token + Zone ID**

### Anthropic API Key (For AI Assistant)
1. Sign up at console.anthropic.com
2. API Keys → Create Key
3. **Copy the key** (starts with `sk-ant-`)
4. Free credits included!

**💾 Save all 3 keys in a text file**

---

## 📦 Step 2: Install Demetrly (2 minutes)

### Download
Go to: https://github.com/elblanco2/demetrly
Click: Code → Download ZIP

### Upload to cPanel
1. cPanel → File Manager
2. Navigate to `public_html`
3. Upload → Select demetrly.zip
4. Right-click → Extract
5. Rename folder to `demetrly`

**Your URL:** `https://yourdomain.com/demetrly`

---

## ⚙️ Step 3: Configure (2 minutes)

### In File Manager:
1. Navigate to `/public_html/demetrly`
2. Right-click `creator_config.sample.php` → Copy
3. Rename copy to `creator_config.php`
4. Right-click `creator_config.php` → Edit

### Fill in your API keys:

```php
return [
    // cPanel
    'cpanel_host' => 'server123.yourhost.com',  // From your cPanel URL
    'cpanel_user' => 'your_username',            // Your cPanel username
    'cpanel_api_token' => 'PASTE_CPANEL_TOKEN',

    // Cloudflare
    'cloudflare_api_token' => 'PASTE_CLOUDFLARE_TOKEN',
    'cloudflare_zone_id' => 'PASTE_ZONE_ID',

    // AI
    'anthropic_api_key' => 'PASTE_ANTHROPIC_KEY',

    // Domain
    'domain' => 'yourdomain.com',  // Your actual domain

    // Password (generate hash below)
    'admin_key_hash' => '$2y$10$...',

    // Paths (replace YOUR_USERNAME)
    'web_root' => '/home/YOUR_USERNAME/public_html',
    'log_path' => '/home/YOUR_USERNAME/logs/subdomain_creation.log',
];
```

### Generate Password Hash:
In cPanel Terminal:
```bash
php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
```
Copy the output → Paste as `admin_key_hash`

### Set Permissions:
```bash
chmod 600 creator_config.php
```

### Create Directories:
```bash
mkdir -p ~/logs ~/data
chmod 755 ~/logs ~/data
```

**Save and close!**

---

## 🎉 Step 4: Create Your First Subdomain (30 seconds)

1. Visit: `https://yourdomain.com/demetrly`
2. Enter your password
3. Fill in the form:
   - Subdomain: `demo`
   - Description: `My first AI site`
4. Click **"Create Subdomain"**

**Watch it work:**
- ✓ cPanel subdomain created
- ✓ Cloudflare DNS configured
- ✓ MySQL database created
- ✓ AI assistant deployed

**Done!** Visit `https://demo.yourdomain.com`

---

## 🤖 Step 5: Deploy with AI (30 seconds)

You'll see a beautiful chat interface. Try this:

**You type:**
> "Create a portfolio website for me"

**AI responds:**
> "I'll create a beautiful portfolio template!
> <Creates files>
> ✅ Your portfolio is live!"

**Or try:**
> "Install WordPress"

> "I have HTML files to upload"

> "Deploy https://github.com/user/my-repo"

**The AI handles everything!**

---

## 🎯 What to Do Next

### Try These Commands:

**Install WordPress:**
```
"Install WordPress"
```

**Upload Files:**
```
"I need to upload my website files"
```
AI creates drag-drop interface

**Deploy GitHub Repo:**
```
"Deploy https://github.com/username/repo"
```
AI analyzes and guides you

**Fix Errors:**
```
"I'm getting a 500 error"
```
AI reads logs and fixes it

**Check System:**
```
"Check PHP version and extensions"
```

### Learning Path:

**Week 1:**
- Create 3-4 test subdomains
- Try different deployment types
- Get comfortable with AI

**Week 2:**
- Deploy your first real project
- Portfolio, blog, or business site
- Let AI handle complexity

**Month 1:**
- Start asking "how does this work?"
- Try doing tasks manually
- Understand fundamentals

**Month 2+:**
- Deploy complex applications
- Learn Git/version control
- Write code yourself

---

## 🆘 Troubleshooting

### "Can't access demetrly URL"
- Check File Manager: Is demetrly folder in `public_html`?
- Try: `https://yourdomain.com/demetrly/index.php`
- Check file permissions: `chmod 755 public_html/demetrly`

### "Invalid password"
- Regenerate hash: `php -r "echo password_hash('newpass', PASSWORD_DEFAULT);"`
- Update in `creator_config.php`
- Make sure no extra spaces/quotes

### "API error"
- Double-check API keys (no extra spaces)
- cPanel token: Is it active?
- Cloudflare: Selected correct zone?
- Anthropic: Key starts with `sk-ant-`?

### "Subdomain created but AI not working"
- Check Anthropic API key in config
- Visit subdomain directly: `https://subdomain.yourdomain.com`
- Check browser console for errors (F12)

### "Permission denied"
- cPanel Terminal: `chmod 755 ~/data ~/logs`
- Check web_root path matches your username

### Still stuck?
- GitHub Issues: https://github.com/elblanco2/demetrly/issues
- Include: Error message, what you tried, your hosting provider

---

## 💡 Pro Tips

### Subdomain Ideas:
- `portfolio` - Your professional site
- `blog` - Start writing
- `dev` - Development/testing
- `demo` - Show clients
- `app` - Your web application
- `playground` - Experiment freely

### Cost Optimization:
- Start with free Cloudflare
- Anthropic free credits (~$5 worth)
- Only pay for AI after free credits
- Typical usage: $5-10/month

### Security:
- Use strong password (12+ characters)
- Don't share API keys
- Keep config file secure (chmod 600)
- Different password for each subdomain

### Backups:
- Demetrly tracks everything in SQLite
- Export data: Management tab → Export
- Download weekly for safety

---

## 📚 Next Steps

### Read These:
1. [BEGINNER_GUIDE.md](BEGINNER_GUIDE.md) - Complete Day 1 guide
2. [VIDEO_SCRIPT_BEGINNER.md](VIDEO_SCRIPT_BEGINNER.md) - Video walkthrough
3. [README.md](README.md) - Full documentation

### Watch Video:
[Coming soon: YouTube tutorial]

### Join Community:
- Star on GitHub: https://github.com/elblanco2/demetrly
- Open issues for help
- Share what you build!

---

## 🚀 You're Ready!

**You now have:**
- ✅ Demetrly installed
- ✅ AI assistant on every subdomain
- ✅ Professional deployment workflow
- ✅ No need for auto-installers ever again

**Go build something!** 🎉

---

## 📊 Comparison Chart

### Traditional cPanel Way

| Task | Steps | Time |
|------|-------|------|
| Create subdomain | cPanel → Subdomains → Fill form → Submit | 2 min |
| Add DNS | Cloudflare → DNS → Add CNAME → Save | 3 min |
| Create database | cPanel → MySQL → Create DB → Create user → Assign | 5 min |
| Upload files | Download FileZilla → Connect → Upload | 10 min |
| Fix permissions | SSH → chmod commands | 5 min |
| **Total** | **6 different tools** | **25+ min** |

### Demetrly + AI Way

| Task | Steps | Time |
|------|-------|------|
| Create subdomain | Fill form → Click button | 30 sec |
| Add DNS | Automatic | 0 sec |
| Create database | Automatic | 0 sec |
| Upload files | Ask AI → Drag files | 30 sec |
| Fix permissions | Ask AI | 10 sec |
| **Total** | **1 tool + AI** | **~2 min** |

---

**Saved:** 23 minutes per subdomain
**Times 10 subdomains:** 3.8 hours saved
**Times 100 subdomains:** 38 hours saved

**Plus:** You learned the right way from Day 1! 🎓
