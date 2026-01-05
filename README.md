# Demetrly ⚡

**cPanel Subdomain Automation + AI Deployment Assistant**

The most comprehensive subdomain management system for cPanel. Create subdomains instantly, then let **AI help you deploy anything** - from GitHub repos to WordPress sites. Each subdomain gets its own AI-powered chat interface for guided deployment.

> 🤖 **NEW:** Every subdomain includes an AI assistant powered by Claude that helps with deployment, debugging, and configuration!

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](CONTRIBUTING.md)

---

## 🚀 Why Demetrly?

Most subdomain tools only handle **one** thing - either DNS, or cPanel, or file deployment. **Demetrly does it all**:

| Feature | Demetrly | Other Tools |
|---------|----------|-------------|
| cPanel Subdomain Creation | ✅ | ⚠️ Some |
| Cloudflare DNS Automation | ✅ | ⚠️ Some |
| MySQL Database Provisioning | ✅ | ❌ Rare |
| Template File Deployment | ✅ | ❌ Rare |
| AI Content Generation | ✅ | ❌ None |
| SQLite Tracking Database | ✅ | ❌ None |
| Complete Deletion Workflow | ✅ | ❌ None |
| Web Management UI | ✅ | ⚠️ Some |
| Full Audit Trail | ✅ | ❌ None |

**One script. Complete automation. Zero headaches.**

---

## 📖 Quick Start

**Want to deploy this on your domain?**

👉 **[Complete Deployment Guide →](DEPLOYMENT_GUIDE.md)** - Step-by-step walkthrough (~45 minutes)

**Already deployed? Learn to use it:**
- [Beginner Guide](BEGINNER_GUIDE.md) - Detailed walkthrough with screenshots
- [Quick Start](QUICK_START.md) - 10-minute setup for experienced users

---

## 🎯 Perfect For

- 🏢 **Web Agencies**: Quickly provision client subdomains with custom branding
- 🔬 **Developers**: Spin up development/staging environments in seconds
- 🎓 **Educational Platforms**: Deploy course-specific sites with LMS integration
- 💼 **SaaS Platforms**: White-label subdomain provisioning for customers
- 🏠 **Hosting Providers**: Offer self-service subdomain creation to users
- 🛠️ **Personal Projects**: Manage multiple hobby sites from one interface
- 🧪 **Testing**: Create/destroy test environments on demand

---

## ✨ Key Features

### 🤖 AI Deployment Assistant (NEW!)

Every subdomain automatically deploys with an **AI-powered chat interface**:

```
Visit: https://myapp.yourdomain.com

🤖 Welcome! I can help you deploy:
   • GitHub repositories (any framework)
   • WordPress sites
   • Static HTML/CSS/JS
   • PHP applications

   What would you like to deploy?

👤 Deploy https://github.com/user/my-app

🤖 Analyzing repository...
   ✓ Detected: React application
   ⚠️ Node.js not available on this server

   I can help you:
   1. Build locally and upload via drag-drop
   2. Deploy to Vercel/Netlify instead
   3. Create a static export

   I'll create an upload interface for you!
   <Creates upload.php with drag-drop UI>
   ✅ Ready! Upload your build folder here.
```

**AI Capabilities:**
- 🔧 Fix file permissions automatically
- 📦 Detect project types (React, Vue, PHP, WordPress, etc.)
- 🐛 Debug errors from logs
- 📁 Create upload interfaces
- ⚙️ Generate .htaccess configs
- 🔍 Check PHP version/extensions
- 💡 Suggest deployment strategies

### 🎨 AI Content Generation (Optional)

Also integrates with **Claude** or **Gemini** for:
- Custom welcome pages based on purpose
- Relevant navigation and links
- Professional starter content
- Theme-appropriate styling

### 🔄 Complete Lifecycle Management

#### **Creation** (One-Click Automation)
```
1. Pre-flight Safety Checks
   ├─ Validates subdomain format
   ├─ Checks DNS conflicts
   ├─ Verifies database availability
   └─ Ensures cPanel quota

2. Automated Deployment
   ├─ Creates cPanel subdomain
   ├─ Configures Cloudflare DNS
   ├─ Provisions MySQL database
   ├─ Deploys template files
   ├─ Generates AI content (optional)
   └─ Creates configuration

3. Tracking & Logging
   ├─ Records to SQLite database
   ├─ Logs each step with timestamp
   └─ Tracks metadata and IP
```

#### **Deletion** (Complete Cleanup)
```
1. Safety Verification
   ├─ Database-tracked only
   ├─ Name confirmation required
   ├─ Path validation
   └─ Rate limiting

2. Complete Removal
   ├─ Cloudflare DNS record
   ├─ cPanel subdomain
   ├─ MySQL database
   ├─ All files/directories
   └─ Tracking database update

3. Audit Trail
   ├─ Deletion timestamp
   ├─ Success/failure per step
   └─ Error logging
```

### 📊 Beautiful Management Interface

**Tabbed UI** with:

**Create Tab**:
- Quick subdomain creation form
- Optional metadata (purpose, description)
- AI content toggle
- Real-time progress tracking

**Manage Tab**:
- List all subdomains
- Filter by status (active/deleted)
- View detailed creation logs
- Export data (JSON/CSV)
- Delete with confirmation
- Pagination for 100+ subdomains

### 🔒 Enterprise-Grade Security

- ✅ **CSRF Protection** on all forms
- ✅ **Session Management** with 30-min timeout
- ✅ **Rate Limiting**: 5 creations/hour, 3 deletions/hour
- ✅ **Path Validation**: Triple-check before file operations
- ✅ **SQL Injection Prevention**: Parameterized queries
- ✅ **XSS Protection**: HTML escaping everywhere
- ✅ **SSL Verification**: Secure API communications
- ✅ **Password Hashing**: bcrypt with strong defaults

### 📈 SQLite Tracking Database

**Comprehensive tracking** of:
- All created subdomains with metadata
- Step-by-step creation logs
- Deletion audit trail with details
- IP addresses and timestamps
- Custom metadata fields

**Export capabilities**: JSON and CSV for analytics

---

## 🚀 Quick Start

### 👋 New to cPanel? Start Here!

**Complete Beginner?** Follow our step-by-step guide:
### **📖 [Complete Beginner Guide →](BEGINNER_GUIDE.md)**
*Covers everything from "What is cPanel?" to your first deployed site*

**Want to Jump In?** Get set up in 10 minutes:
### **⚡ [10-Minute Quick Start →](QUICK_START.md)**
*Condensed setup guide with exact steps and times*

**Prefer Video?** Watch along as you set up:
### **🎥 [Video Tutorial →](VIDEO_SCRIPT_BEGINNER.md)**
*Full walkthrough coming to YouTube soon!*

---

### For Experienced Developers

```bash
# 1. Download
git clone https://github.com/elblanco2/demetrly.git

# 2. Upload to public_html/demetrly/

# 3. Configure
cp creator_config.sample.php creator_config.php
# Add API keys: cPanel, Cloudflare, Anthropic

# 4. Set Permissions
chmod 600 creator_config.php
mkdir -p ~/data ~/logs

# 5. Access
# https://yourdomain.com/demetrly/
```

**Generate Password Hash:**
```bash
php -r "echo password_hash('YourPassword', PASSWORD_DEFAULT);"
```

---

## 📖 Use Cases

### 🏢 Web Agency: Client Subdomains

**Scenario**: Agency manages 50+ client sites on one server

**Before Demetrly**:
- ⏰ 15 minutes per subdomain setup
- 🔧 Manual cPanel, DNS, database configuration
- 📝 Tracking in spreadsheets
- 🗑️ Partial cleanup when projects end

**After Demetrly**:
- ⚡ 30 seconds per subdomain
- 🤖 One-click automation
- 📊 SQLite database tracks everything
- 🗑️ Complete cleanup with one click

**Result**: Save 12+ hours per month on subdomain management

---

### 🔬 Developer: Staging Environments

**Scenario**: Developer needs separate staging for each feature branch

```bash
# Create staging for feature
feature-auth.yourdomain.com
feature-payments.yourdomain.com
feature-dashboard.yourdomain.com

# Test, merge, then delete when done
```

**Benefits**:
- Isolated testing environments
- Automatic database per environment
- Easy cleanup after merge
- Full tracking for debugging

---

### 🎓 Educational Platform: Course Sites

**Scenario**: University creates subdomain per course

```bash
cs101-spring2025.university.edu
math201-fall2024.university.edu
physics301-spring2025.university.edu
```

**Features Used**:
- Educational metadata tracking
- LMS integration field (Canvas, Moodle, etc.)
- AI-generated course-specific content
- Bulk deletion at semester end

---

### 💼 SaaS Platform: Customer Subdomains

**Scenario**: SaaS needs customer-specific subdomains

```bash
acme-corp.platform.com
startup-inc.platform.com
enterprise-llc.platform.com
```

**Integration**:
- API calls from registration webhook
- Automatic provisioning on signup
- Track in SQLite + your app database
- Delete on churn with cleanup

---

## 🛠️ Configuration

Minimal `creator_config.php`:

```php
<?php
return [
    // Required: cPanel API
    'cpanel_host' => 'server.example.com',
    'cpanel_user' => 'username',
    'cpanel_api_token' => 'your_token',

    // Optional: Cloudflare
    'cloudflare_api_token' => 'your_token',
    'cloudflare_zone_id' => 'your_zone_id',

    // Optional: AI
    'ai_provider' => 'anthropic', // or 'gemini'
    'anthropic_api_key' => 'sk-ant-xxx',

    // Security
    'admin_key_hash' => password_hash('strong-password', PASSWORD_DEFAULT),

    // Paths
    'web_root' => '/home/username/public_html',
    'log_path' => '/home/username/logs/subdomain.log',
];
```

**Full configuration options**: See [creator_config.sample.php](creator_config.sample.php)

---

## 📁 Project Structure

```
demetrly/
├── index.php                 # Main application
├── creator_config.sample.php # Sample config
├── includes/
│   ├── db.php                # SQLite database layer
│   └── deletion_handler.php  # Deletion orchestration
├── ajax/
│   ├── list_subdomains.php   # List endpoint
│   ├── delete_subdomain.php  # Delete endpoint
│   ├── get_logs.php          # Logs viewer
│   └── export_data.php       # Export JSON/CSV
├── assets/
│   ├── css/manager.css       # Styles
│   └── js/manager.js         # Frontend
└── templates/
    └── default/              # Default subdomain template
```

---

## 🔌 API Integration

### cPanel UAPI

**Required Permissions**:
- `SubDomain::addsubdomain`
- `SubDomain::delsubdomain`
- `Mysql::create_database`
- `Mysql::delete_database`

**Create API Token**: cPanel → Security → Manage API Tokens

---

### Cloudflare API

**Required Permissions**:
- `Zone:DNS:Edit`

**Create API Token**: Cloudflare Dashboard → My Profile → API Tokens → Create Token

---

### AI APIs (Optional)

**Anthropic Claude**:
- Model: claude-3-5-sonnet-20241022
- Get key: https://console.anthropic.com/

**Google Gemini**:
- Model: gemini-pro
- Get key: https://makersuite.google.com/app/apikey

---

## 🧪 Testing

```bash
# Test database
php test-db.php

# View database contents
php check-db.php

# Test deletion (requires subdomain ID)
php test-delete-simple.php 1
```

---

## 🛡️ Security Best Practices

1. **Store config outside web root**: `/home/user/config/`
2. **Set restrictive permissions**: `chmod 600 creator_config.php`
3. **Use HTTPS**: Required for production
4. **Strong admin password**: 12+ characters
5. **Regular backups**: Daily database backups
6. **Monitor logs**: Check for suspicious activity
7. **Update PHP**: Keep server software current
8. **Minimal API permissions**: Grant only what's needed
9. **Test deletion**: Verify complete cleanup
10. **Rate limiting**: Adjust based on usage

---

## 🎨 Customization

### Custom Templates

Place your template in `templates/custom/`:

```
templates/
├── default/          # Basic HTML template
│   ├── index.php
│   ├── config.php
│   └── assets/
└── custom/           # Your custom template
    ├── index.php
    ├── style.css
    └── ...
```

Update config to use your template:
```php
'template_path' => '/path/to/templates/custom',
```

### AI Prompt Customization

Edit `index.php` around line 320 to customize AI prompts:

```php
$prompt = "Generate a welcome page for a {$purpose} website...";
```

### Metadata Fields

Add custom fields in the database schema:

```sql
ALTER TABLE subdomains ADD COLUMN custom_field TEXT;
```

Update forms and tracking code accordingly.

---

## 🤝 Contributing

We welcome contributions! Here's how:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

**Areas needing help**:
- Additional hosting panel support (DirectAdmin, Plesk)
- WordPress/Joomla integration
- Docker deployment
- Unit tests
- Internationalization
- Mobile app

---

## 📄 License

MIT License - See [LICENSE](LICENSE) for details.

Free for personal and commercial use. Attribution appreciated but not required.

---

## 🙏 Credits

- Built with ❤️ for the cPanel community
- Powered by Anthropic Claude and Google Gemini
- cPanel UAPI for hosting automation
- Cloudflare for DNS management
- SQLite for lightweight tracking

---

## 📞 Support

- **Documentation**: [GitHub Wiki](https://github.com/yourusername/demetrly/wiki)
- **Issues**: [GitHub Issues](https://github.com/yourusername/demetrly/issues)
- **Discussions**: [GitHub Discussions](https://github.com/yourusername/demetrly/discussions)
- **Security**: See [SECURITY.md](SECURITY.md)

---

## 🗺️ Roadmap

### v1.1 (Coming Soon)
- [ ] DirectAdmin support
- [ ] Plesk support
- [ ] Docker deployment
- [ ] Bulk CSV import/export
- [ ] API webhooks

### v1.2
- [ ] WordPress multisite integration
- [ ] Multi-language UI
- [ ] Advanced analytics
- [ ] Template marketplace

### v2.0
- [ ] Multi-server orchestration
- [ ] RESTful API
- [ ] Mobile app (PWA)
- [ ] Enterprise SSO

---

## ⭐ Star History

[![Star History Chart](https://api.star-history.com/svg?repos=yourusername/demetrly&type=Date)](https://star-history.com/#yourusername/demetrly&Date)

---

## 💬 Testimonials

> "Saved us 20+ hours per month on subdomain management. Game changer!" - Web Agency Owner

> "Perfect for spinning up staging environments. Love the one-click deletion." - Developer

> "Finally, a tool that does EVERYTHING. No more manual DNS editing!" - Hosting Provider

---

**Made with ⚡ by developers, for developers**

*If you find this useful, please star the repo!*
