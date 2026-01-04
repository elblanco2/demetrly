# Demetrly Templates

This directory contains templates that are deployed to new subdomains.

## Available Templates

### Modern Template (Default)

A beautiful, professional template perfect for any use case:

**Features:**
- ✨ Modern gradient hero section
- 📱 Fully responsive design
- 🎨 Smooth animations
- 🚀 Fast and lightweight
- ⚙️ Easy to customize

**Perfect for:**
- Portfolio sites
- Project landing pages
- Business sites
- Personal blogs
- Product showcases
- Team pages

## Using Templates

### Setting Default Template

Edit `creator_config.php` and set:

```php
'template_path' => '/path/to/templates/modern',
```

### Creating Custom Templates

1. Copy the `modern` template:
```bash
cp -r templates/modern templates/my-custom
```

2. Customize the files:
- `index.php` - HTML structure
- `assets/css/style.css` - Styling
- `assets/js/main.js` - JavaScript
- `config.php` - Configuration values

3. Use your template:
```php
'template_path' => '/path/to/templates/my-custom',
```

## Template Variables

Templates support placeholder replacement:

| Variable | Description | Example |
|----------|-------------|---------|
| `{{SITE_NAME}}` | Subdomain name | "blog" |
| `{{FULL_DOMAIN}}` | Full subdomain URL | "blog.example.com" |
| `{{SUBDOMAIN_NAME}}` | Just the subdomain part | "blog" |
| `{{DB_NAME}}` | MySQL database name | "user_blog" |
| `{{DB_USER}}` | Database username | "user" |
| `{{DB_PASS}}` | Database password | Generated |
| `{{DESCRIPTION}}` | Site description | From form |
| `{{TAGLINE}}` | Site tagline | Customizable |
| `{{CONTACT_EMAIL}}` | Contact email | Generated or custom |
| `{{WEBSITE_URL}}` | Full HTTPS URL | "https://blog.example.com" |

### Custom Variables

Add your own in `config.php`:

```php
'MY_CUSTOM_VAR' => 'My Value',
```

Use in templates:

```html
<p>{{MY_CUSTOM_VAR}}</p>
```

## AI Content Generation

When AI generation is enabled, Demetrly can automatically populate:

- `{{WELCOME_CONTENT}}` - AI-generated welcome message
- `{{ABOUT_TEXT}}` - AI-generated about section
- `{{FEATURES}}` - Suggested features/tools
- `{{NAVIGATION}}` - Relevant navigation links

### Customizing AI Prompts

Edit `index.php` (main app) around line 320:

```php
$prompt = "Generate content for a {$purpose} website...";
```

## Template Structure

```
templates/modern/
├── index.php           # Main HTML page
├── config.php          # Configuration values
├── assets/
│   ├── css/
│   │   └── style.css   # Styles
│   ├── js/
│   │   └── main.js     # JavaScript
│   └── images/         # Images (optional)
└── README.md           # This file
```

## Customization Guide

### Changing Colors

Edit `assets/css/style.css` CSS variables:

```css
:root {
    --primary-color: #6366f1;      /* Main brand color */
    --secondary-color: #8b5cf6;    /* Secondary color */
    --text-dark: #1f2937;          /* Dark text */
    --text-light: #6b7280;         /* Light text */
}
```

### Changing Hero Background

Replace the gradient in `style.css`:

```css
.hero {
    background: linear-gradient(135deg, #your-color 0%, #your-color-2 100%);
}
```

Or use an image:

```css
.hero {
    background: url('../images/hero-bg.jpg') center/cover;
}
```

### Adding Sections

Copy an existing section in `index.php` and modify:

```html
<section id="your-section" class="section">
    <div class="container">
        <h2 class="section-title">Your Section</h2>
        <!-- Your content -->
    </div>
</section>
```

### Modifying Navigation

Edit the nav in `index.php`:

```html
<ul class="nav-links">
    <li><a href="#about">About</a></li>
    <li><a href="#features">Features</a></li>
    <li><a href="#your-section">Your Link</a></li>
</ul>
```

## Advanced Customization

### Adding a Contact Form Backend

Create `contact.php` in your template:

```php
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);

    // Send email
    $to = 'your@email.com';
    $subject = 'Contact from ' . $name;
    $body = "From: $name <$email>\n\n$message";

    mail($to, $subject, $body);

    header('Location: index.php?sent=1');
    exit;
}
?>
```

### Adding Analytics

Add to `index.php` before `</head>`:

```html
<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=YOUR-ID"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'YOUR-ID');
</script>
```

### Adding Social Media Links

In `index.php` contact section:

```html
<div class="social-links">
    <a href="https://twitter.com/yourhandle" target="_blank">
        <i class="fab fa-twitter"></i>
    </a>
    <a href="https://github.com/yourhandle" target="_blank">
        <i class="fab fa-github"></i>
    </a>
</div>
```

## Tips for Great Templates

1. **Mobile First**: Design for mobile, enhance for desktop
2. **Fast Loading**: Optimize images, minimize CSS/JS
3. **Accessibility**: Use semantic HTML, ARIA labels, keyboard navigation
4. **SEO**: Add meta tags, structured data, sitemap
5. **Security**: Sanitize inputs, use HTTPS, validate forms

## Example Use Cases

### Portfolio Template
- Showcase projects with image galleries
- Skills/tech stack section
- Resume/CV download
- Contact form

### Blog Template
- Article listing with pagination
- Categories and tags
- Comments section
- RSS feed

### Product Landing Page
- Hero with product image
- Features grid
- Pricing table
- FAQ accordion
- CTA buttons

### Business Site
- Services section
- Team members
- Testimonials
- Location map
- Contact form

## Contributing Templates

Have a great template? Share it!

1. Create your template in `templates/your-name`
2. Add screenshots to `templates/your-name/screenshots/`
3. Document customization in `templates/your-name/README.md`
4. Submit a pull request

---

**Need help?** Check the [main documentation](../README.md) or open an [issue](https://github.com/yourusername/demetrly/issues).
