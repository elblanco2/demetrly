<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{DESCRIPTION}}">
    <title>{{SITE_NAME}}</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Hero Section -->
    <section class="hero">
        <nav class="nav">
            <div class="container">
                <div class="logo">{{SITE_NAME}}</div>
                <ul class="nav-links">
                    <li><a href="#about">About</a></li>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </div>
        </nav>

        <div class="hero-content container">
            <h1 class="hero-title">Welcome to {{SITE_NAME}}</h1>
            <p class="hero-subtitle">{{TAGLINE}}</p>
            <div class="hero-buttons">
                <a href="#features" class="btn btn-primary">Get Started</a>
                <a href="#about" class="btn btn-secondary">Learn More</a>
            </div>
        </div>

        <div class="hero-decoration">
            <div class="blob blob-1"></div>
            <div class="blob blob-2"></div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="section">
        <div class="container">
            <h2 class="section-title">About This Site</h2>
            <div class="about-grid">
                <div class="about-content">
                    <p>{{ABOUT_TEXT}}</p>
                    <p>This site was automatically provisioned using Demetrly - complete cPanel subdomain automation.</p>
                </div>
                <div class="about-stats">
                    <div class="stat">
                        <div class="stat-number">{{STAT_1_NUMBER}}</div>
                        <div class="stat-label">{{STAT_1_LABEL}}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number">{{STAT_2_NUMBER}}</div>
                        <div class="stat-label">{{STAT_2_LABEL}}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number">{{STAT_3_NUMBER}}</div>
                        <div class="stat-label">{{STAT_3_LABEL}}</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="section section-alt">
        <div class="container">
            <h2 class="section-title">Features</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🚀</div>
                    <h3>Fast & Reliable</h3>
                    <p>Built on modern infrastructure with high performance and uptime.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <h3>Secure</h3>
                    <p>SSL encryption and regular security updates keep your data safe.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h3>Responsive</h3>
                    <p>Perfectly optimized for desktop, tablet, and mobile devices.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">⚡</div>
                    <h3>Easy to Customize</h3>
                    <p>Simple structure makes it easy to modify and extend.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="section cta-section">
        <div class="container">
            <h2>Ready to Get Started?</h2>
            <p>{{CTA_TEXT}}</p>
            <a href="#contact" class="btn btn-primary btn-lg">Get In Touch</a>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="section">
        <div class="container">
            <h2 class="section-title">Get In Touch</h2>
            <div class="contact-grid">
                <div class="contact-info">
                    <h3>Contact Information</h3>
                    <p><strong>Email:</strong> <a href="mailto:{{CONTACT_EMAIL}}">{{CONTACT_EMAIL}}</a></p>
                    <p><strong>Website:</strong> <a href="{{WEBSITE_URL}}" target="_blank">{{WEBSITE_URL}}</a></p>
                    <div class="social-links">
                        <!-- Add your social media links here -->
                    </div>
                </div>
                <div class="contact-form">
                    <form action="contact.php" method="POST">
                        <div class="form-group">
                            <input type="text" name="name" placeholder="Your Name" required>
                        </div>
                        <div class="form-group">
                            <input type="email" name="email" placeholder="Your Email" required>
                        </div>
                        <div class="form-group">
                            <textarea name="message" rows="5" placeholder="Your Message" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> {{SITE_NAME}}. All rights reserved.</p>
            <p class="powered-by">Powered by <a href="https://github.com/yourusername/demetrly" target="_blank">Demetrly</a></p>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>
