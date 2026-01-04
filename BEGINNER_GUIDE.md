# Your First Day with cPanel Hosting
## Skip the Traps, Deploy Like a Pro with Demetrly

**You just bought web hosting. Now what?**

Most tutorials tell you to click "Softaculous" and install WordPress. **Don't.** That's a trap that will limit you for years.

Instead, let me show you the professional way to use your hosting - and it's actually easier.

---

## 🚫 Why NOT to Use cPanel's Auto-Installers

When you log into cPanel for the first time, you'll see tempting icons like:

- **Softaculous** - "Install 450+ apps in one click!"
- **WordPress Installer** - "Get WordPress now!"
- **Site Builder** - "Build a site without code!"

### The Problem

These tools seem easy but they:
1. **Lock you in** - Hard to move your site later
2. **Create bad habits** - You never learn how things actually work
3. **Limit flexibility** - Can't use modern tools like GitHub
4. **Cause conflicts** - Multiple auto-installers fighting over files
5. **Hide errors** - When things break, you can't fix them

**Real talk:** Professional developers never use these. And you don't need to either.

---

## ✅ The Professional (Easier) Way: Demetrly

Instead of clicking random installers, you'll use **one tool** that:
- Creates subdomains automatically
- Handles all the technical setup
- Gives you an AI assistant for deployments
- Works with GitHub, WordPress, or anything else
- Teaches you the right way as you go

**Once you set up Demetrly, you'll never need those auto-installers again.**

---

## 📋 Complete Setup Guide (Even if You've Never Done This Before)

### Step 1: Log Into Your New Hosting

You got an email from your hosting provider (Bluehost, HostGator, SiteGround, etc.) with:
- **cPanel URL** - Usually: `https://yourdomain.com:2083` or `https://server123.yourhost.com:2083`
- **Username** - Something like `myuser123`
- **Password** - A random string they generated

**Go there and log in.**

---

### Step 2: Don't Click Anything Yet!

You'll see a dashboard full of icons. **Ignore them for now.**

We need to get some API keys first so Demetrly can do its magic.

---

### Step 3: Get Your cPanel API Token

**Why?** This lets Demetrly create subdomains for you automatically.

1. In cPanel, scroll down to **"Security"** section
2. Click **"Manage API Tokens"**
3. Click **"Create"**
4. Name it: `demetrly`
5. Click **"Create"**
6. **IMPORTANT:** Copy the token somewhere safe (you won't see it again!)

**Save this in a text file on your computer for now.**

---

### Step 4: Set Up Cloudflare (Optional but Recommended)

**Why?** Cloudflare makes your site faster and handles DNS automatically.

#### 4a. Create Free Cloudflare Account

1. Go to https://dash.cloudflare.com/sign-up
2. Sign up (it's free)
3. Add your domain
4. Cloudflare will scan your domain
5. Click **"Continue"**

#### 4b. Change Your Nameservers

Cloudflare will give you two nameservers like:
```
ns1.cloudflare.com
ns2.cloudflare.com
```

**Where to change them:**
- **If you bought domain from GoDaddy:** GoDaddy dashboard → Domain Settings → Nameservers
- **If you bought from Namecheap:** Namecheap dashboard → Domain List → Manage → Nameservers
- **If domain came with hosting:** Your hosting control panel → Domain Management

Change to Cloudflare's nameservers. **This takes 5 minutes to 24 hours to work.**

#### 4c. Get Cloudflare API Token

1. In Cloudflare, click your profile icon (top right)
2. Click **"My Profile"**
3. Click **"API Tokens"** (left sidebar)
4. Click **"Create Token"**
5. Find **"Edit zone DNS"** template → Click **"Use template"**
6. Under "Zone Resources" → Select your domain
7. Click **"Continue to summary"**
8. Click **"Create Token"**
9. **Copy the token** - save it in your text file

#### 4d. Get Your Zone ID

1. Back in Cloudflare dashboard
2. Click on your domain
3. Scroll down the right sidebar
4. You'll see **"Zone ID"** - copy it
5. Save it in your text file

**You now have:**
- ✅ cPanel API token
- ✅ Cloudflare API token
- ✅ Cloudflare Zone ID

---

### Step 5: Get an Anthropic API Key (For AI Assistant)

**Why?** This powers the AI chat that helps you deploy apps.

1. Go to https://console.anthropic.com/
2. Sign up (they give you free credits to start!)
3. Click **"API Keys"** in the dashboard
4. Click **"Create Key"**
5. Name it: `demetrly`
6. Copy the key (starts with `sk-ant-`)
7. Save it in your text file

**Cost:** About $0.01-0.05 per deployment conversation. Your free credits will last a while!

---

### Step 6: Upload Demetrly to Your Server

#### Option A: Using File Manager (Easiest)

1. Download Demetrly from GitHub:
   - Go to https://github.com/elblanco2/demetrly
   - Click green **"Code"** button
   - Click **"Download ZIP"**

2. In cPanel, find **"File Manager"**
3. Navigate to `public_html`
4. Click **"Upload"** (top right)
5. Upload the Demetrly zip file
6. Right-click the zip → **"Extract"**
7. Rename the extracted folder to `demetrly`

**Your URL will be:** `https://yourdomain.com/demetrly`

#### Option B: Using Git (If You're Comfortable)

```bash
cd public_html
git clone https://github.com/elblanco2/demetrly.git
```

---

### Step 7: Configure Demetrly

1. In File Manager, navigate to `/public_html/demetrly`
2. Find `creator_config.sample.php`
3. Right-click → **"Copy"**
4. Rename the copy to `creator_config.php`
5. Right-click `creator_config.php` → **"Edit"**

6. Fill in your saved information:

```php
return [
    // cPanel API Configuration
    'cpanel_host' => 'your-server.yourhost.com',  // From your cPanel URL
    'cpanel_user' => 'your_username',              // Your cPanel username
    'cpanel_api_token' => 'YOUR_CPANEL_TOKEN',     // From Step 3

    // Cloudflare Configuration
    'cloudflare_api_token' => 'YOUR_CLOUDFLARE_TOKEN',  // From Step 4c
    'cloudflare_zone_id' => 'YOUR_ZONE_ID',              // From Step 4d

    // Anthropic API
    'anthropic_api_key' => 'sk-ant-YOUR_KEY',      // From Step 5

    // Domain
    'domain' => 'yourdomain.com',                  // Your actual domain

    // Admin Password
    'admin_key_hash' => '$2y$10$...',              // See below

    // Paths (update YOUR_USERNAME)
    'web_root' => '/home/YOUR_USERNAME/public_html',
    'log_path' => '/home/YOUR_USERNAME/logs/subdomain_creation.log',
];
```

#### Generate Password Hash

In cPanel, go to **"Terminal"** (under Advanced):

```bash
php -r "echo password_hash('your-secure-password', PASSWORD_DEFAULT);"
```

Copy the output and paste it as `admin_key_hash`.

7. Click **"Save Changes"**

8. Set permissions:
```bash
chmod 600 creator_config.php
```

---

### Step 8: Create Required Directories

In cPanel Terminal:

```bash
mkdir -p ~/logs
mkdir -p ~/data
chmod 755 ~/logs
chmod 755 ~/data
```

---

### Step 9: Test It!

1. Visit: `https://yourdomain.com/demetrly`
2. Enter your password (the one you used to generate the hash)
3. You should see the Demetrly dashboard!

---

## 🎉 Your First Subdomain with AI

Now let's create your first subdomain the modern way:

1. In Demetrly, fill in the form:
   - **Subdomain name:** `demo`
   - **Description:** `My first AI-assisted site`

2. Click **"Create Subdomain"**

3. Watch as Demetrly:
   - ✓ Creates cPanel subdomain
   - ✓ Configures Cloudflare DNS
   - ✓ Creates MySQL database
   - ✓ Deploys AI chat interface

4. Visit `https://demo.yourdomain.com`

**You'll see an AI chat interface!**

---

## 💬 Deploy Your First App with AI

Let's deploy a simple portfolio site:

### Chat with the AI:

**You type:**
> "I want to deploy a portfolio website"

**AI responds:**
> "Great! I can help you with that. Do you have:
> 1. An existing GitHub repository?
> 2. Files on your computer to upload?
> 3. Want me to create a starter template?"

**You type:**
> "Create a starter template"

**AI responds:**
> "I'll create a beautiful portfolio template for you!
> <Creates files>
> ✅ Portfolio site deployed!
> Your site is live at https://demo.yourdomain.com"

**That's it.** No FTP, no SSH, no confusion.

---

## 🚀 What You Can Deploy

### Static Sites (HTML/CSS/JS)
```
You: "I need to upload my HTML site"
AI: <Creates upload interface>
    "Drag and drop your files here!"
```

### WordPress
```
You: "Install WordPress"
AI: <Downloads and configures WordPress>
    "Visit /wp-admin to complete setup!"
```

### GitHub Repos
```
You: "Deploy https://github.com/user/my-app"
AI: <Analyzes repo>
    "This is a React app. I'll guide you through
     building it locally and uploading..."
```

### PHP Applications
```
You: "I have a PHP app"
AI: "Upload your files, and I'll:
     - Configure .htaccess
     - Set up database
     - Fix permissions
     - Test it works"
```

---

## 🎯 Why This is Better Than Auto-Installers

### Traditional Way (Softaculous):
1. Click "Install WordPress"
2. Fill out a form
3. Wait
4. Hope it works
5. **When it breaks:** You're stuck (can't see logs, can't debug)
6. **Want to move it?** Nearly impossible
7. **Want to use Git?** Can't
8. **Want custom setup?** Too bad

### Demetrly + AI Way:
1. Tell AI what you want
2. AI handles everything
3. You learn as you go
4. **When it breaks:** AI reads logs and fixes it
5. **Want to move it?** AI helps you export
6. **Want to use Git?** AI detects and deploys
7. **Want custom setup?** Just ask the AI

---

## 🧠 You're Learning the Pro Way

Here's what's actually happening behind the scenes:

### When you create a subdomain:
```bash
# Demetrly executes:
cPanel API → Create subdomain
Cloudflare API → Add DNS record
MySQL → Create database
File System → Deploy template files
SQLite → Track everything
```

**But you didn't need to learn any of that yet.** The AI did it for you.

### When AI helps you deploy:
```bash
# AI is actually running:
chmod 755 directories/
chmod 644 files/
grep -r "error" logs/
curl -X POST cloudflare.com/api...
```

**Again, you don't need to memorize these commands.** Just ask in plain English.

### Over time, you'll naturally learn:
- How DNS works
- How file permissions work
- How databases connect to apps
- How to read error logs
- How to deploy from GitHub

**But you'll learn by doing real projects, not by reading docs.**

---

## 🔥 Common Beginner Questions

### "What if I already installed WordPress via Softaculous?"

No problem! Create a new subdomain with Demetrly and migrate:
1. Export your Softaculous WordPress
2. Tell AI: "Help me migrate my WordPress site"
3. AI will guide you through it

### "Is this more expensive than auto-installers?"

**No!** Auto-installers are included with hosting (you already paid for them).

Demetrly costs:
- Hosting: $0 (you already have it)
- Cloudflare: $0 (free tier)
- Anthropic AI: ~$5/month for casual use
- **Total new cost:** ~$5/month for unlimited AI help

Compare that to hiring someone to help you debug for $50/hour!

### "What if I don't know any coding?"

**Perfect!** That's the point. You talk to AI in normal language:
- "My contact form doesn't work"
- "How do I add a blog?"
- "Can you make the text bigger?"

The AI translates that into code and does it for you.

### "Can I still learn to code later?"

**Absolutely!** In fact, this is a better way to learn:

1. **Build real projects first** (with AI help)
2. **See how things work** (AI explains as it goes)
3. **Gradually take over** (do more yourself as you learn)

Vs. the traditional way:
1. Read tutorials for months
2. Build nothing useful
3. Give up because it's boring

### "What if the AI makes a mistake?"

The AI includes an undo system. Plus, Demetrly tracks everything in a database, so you can:
- See what changed
- Restore previous versions
- Delete and start fresh

**You can't break anything permanently.**

---

## 📚 Next Steps

### Week 1: Get Comfortable
- Create 3-4 test subdomains
- Try different deployment types
- Ask the AI random questions
- **Goal:** Feel confident talking to AI

### Week 2: Build Something Real
- Deploy your first real project
- Maybe a portfolio, blog, or business site
- Let AI handle the tricky parts
- **Goal:** Have a live website

### Week 3: Learn How It Works
- Ask AI: "Explain how DNS works"
- Ask AI: "Show me the code you just wrote"
- Try doing small tasks manually
- **Goal:** Understand the fundamentals

### Month 2+: Go Deeper
- Deploy more complex apps
- Learn Git and version control
- Start modifying code yourself
- Use AI less, code more
- **Goal:** Transition from beginner to intermediate

---

## 🎓 Resources for Learning More

### After You're Comfortable with Demetrly:

**Free Courses:**
- FreeCodeCamp - HTML/CSS/JavaScript
- The Odin Project - Full stack web development
- CS50 - Computer Science fundamentals

**Documentation:**
- MDN Web Docs - HTML/CSS/JavaScript reference
- PHP.net - PHP documentation
- GitHub Guides - Learn Git

**Communities:**
- r/webdev on Reddit
- Dev.to community
- Discord web dev servers

**But remember:** You don't need to read all this first. Build with Demetrly, learn as you go, dive deeper when curious.

---

## ⚠️ Things to Avoid (Common Traps)

### ❌ Don't Install Multiple "One-Click" Apps
- Softaculous WordPress + cPanel WordPress = conflicts
- Use Demetrly for everything instead

### ❌ Don't Panic About Error Messages
- Copy error into AI chat
- AI will explain and fix it
- You'll learn from each error

### ❌ Don't Edit Live Sites Directly
- Use Demetrly to create a `dev.yourdomain.com` subdomain
- Test changes there first
- Then deploy to main site

### ❌ Don't Share Your API Keys
- Keep them in config files only
- Don't commit them to Git
- Don't paste them in forums

### ❌ Don't Get Overwhelmed
- You don't need to learn everything at once
- Focus on one project at a time
- The AI is there to help when stuck

---

## 🌟 Success Stories (What You'll Build)

### Month 1: Portfolio Site
- Beautiful design
- Contact form
- Projects showcase
- Blog (optional)

### Month 3: Business Website
- Professional branding
- Service pages
- Client testimonials
- Appointment booking

### Month 6: Custom Web App
- User accounts
- Database integration
- Payment processing
- Admin dashboard

### Year 1: You're a Developer
- Comfortable with code
- Can build anything
- Help others get started
- Maybe even contribute to Demetrly!

---

## 💬 Final Thoughts

**Remember:** Every professional developer started as a beginner who didn't know what cPanel was.

The difference now is you have AI to guide you through every step.

**You're not learning alone.**

Demetrly + AI is like having a patient mentor who:
- Never judges your questions
- Explains things at your level
- Fixes mistakes instantly
- Is available 24/7

**Start today. Build something real. Learn as you go.**

Welcome to web development! 🚀

---

## 🆘 Need Help?

- **GitHub Issues:** https://github.com/elblanco2/demetrly/issues
- **Community Discord:** [Coming soon]
- **Video Tutorials:** [YouTube channel]

**Don't forget:** Your AI assistant is always there in each subdomain. When stuck, just ask it!
