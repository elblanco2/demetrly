# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |

## Reporting a Vulnerability

We take security seriously. If you discover a security vulnerability in SubForge, please report it responsibly.

### How to Report

**DO NOT** create a public GitHub issue for security vulnerabilities.

Instead, please email: **security@apiprofe.com** (replace with your actual email)

Include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Any suggested fixes (optional)

### What to Expect

- **Initial Response**: Within 48 hours
- **Status Update**: Within 7 days
- **Fix Timeline**: Depends on severity (critical issues within 24-72 hours)

### Security Best Practices for Users

1. **Configuration Security**
   - Store `creator_config.php` outside web root
   - Use permissions: `chmod 600 creator_config.php`
   - Never commit config to version control

2. **Password Security**
   - Use strong admin password (12+ characters)
   - Rotate passwords regularly
   - Use `password_hash()` with PASSWORD_DEFAULT

3. **API Token Security**
   - Grant minimum required permissions
   - Rotate API tokens periodically
   - Monitor API usage for anomalies

4. **Server Security**
   - Keep PHP updated (8.0+)
   - Use HTTPS only (no HTTP)
   - Enable secure session cookies
   - Regular security audits

5. **Database Security**
   - Store SQLite database outside web root
   - Regular backups
   - Limit file permissions (644 or 600)

6. **Monitoring**
   - Review logs regularly
   - Monitor for unusual subdomain creation patterns
   - Check rate limiting effectiveness
   - Alert on deletion attempts

## Known Security Considerations

### Authentication
- Single admin password (no multi-user support yet)
- Session timeout: 30 minutes
- CSRF protection on all state-changing operations

### Rate Limiting
- Creation: 5 per hour
- Deletion: 3 per hour
- Adjustable in configuration

### Path Validation
- Triple-check before file deletion
- Must contain domain name
- Must be under web root
- Cannot delete web root itself

### SQL Injection
- All queries use PDO prepared statements
- Input validation on all user data

### XSS Protection
- HTML escaping on all output
- JavaScript sanitization
- CSP headers recommended

### SSL/TLS
- SSL verification enabled for all API calls
- Requires HTTPS in production

## Security Disclosure History

None yet - first release.

## Security Credits

We acknowledge security researchers who responsibly disclose vulnerabilities:

- (None yet)

Thank you for helping keep SubForge secure!
