# Contributing to SubForge

Thank you for considering contributing to SubForge! We welcome contributions from the community.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How Can I Contribute?](#how-can-i-contribute)
- [Development Setup](#development-setup)
- [Pull Request Process](#pull-request-process)
- [Coding Standards](#coding-standards)
- [Testing](#testing)

## Code of Conduct

- Be respectful and inclusive
- Welcome newcomers and encourage diverse perspectives
- Focus on what is best for the community
- Show empathy towards others

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check existing issues. When creating a bug report, include:

- **Clear title and description**
- **Steps to reproduce**
- **Expected vs actual behavior**
- **Screenshots** (if applicable)
- **Environment details**:
  - PHP version
  - cPanel version
  - Browser (for UI issues)
  - Operating system

### Suggesting Features

Feature suggestions are welcome! Please:

- **Check existing feature requests** first
- **Provide clear use case** - why is this needed?
- **Describe the solution** you'd like
- **Consider alternatives** you've thought about
- **Additional context** that might be helpful

### Code Contributions

We especially welcome contributions in these areas:

#### High Priority
- [ ] Unit and integration tests
- [ ] DirectAdmin support
- [ ] Plesk support
- [ ] WordPress integration
- [ ] Docker deployment

#### Medium Priority
- [ ] Multi-language support (i18n)
- [ ] Mobile PWA
- [ ] Advanced analytics
- [ ] Bulk operations UI
- [ ] Template marketplace

#### Nice to Have
- [ ] Dark mode UI
- [ ] Keyboard shortcuts
- [ ] Accessibility improvements
- [ ] Performance optimizations

## Development Setup

### Prerequisites

```bash
# Required
PHP 8.0+
SQLite 3
Composer (for future dependencies)

# Optional for testing
cPanel test server
Cloudflare account
AI API keys (Claude or Gemini)
```

### Local Setup

```bash
# 1. Fork and clone
git clone https://github.com/YOUR_USERNAME/subforge.git
cd subforge

# 2. Create config
cp creator_config.sample.php creator_config.php
# Edit with your test credentials

# 3. Create test database
mkdir -p /tmp/subforge-data
php test-db.php

# 4. Run local PHP server
php -S localhost:8000

# 5. Access
# http://localhost:8000
```

### Testing Environment

For cPanel-related testing, you'll need:
- Test cPanel server or local development environment
- Test domain/subdomain
- Test Cloudflare zone (optional)

**Tip**: Use a staging environment, not production!

## Pull Request Process

### Before Submitting

1. **Create an issue** first (for non-trivial changes)
2. **Fork the repository**
3. **Create a feature branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```
4. **Make your changes**
5. **Test thoroughly**
6. **Update documentation** if needed

### PR Guidelines

1. **Clear title**: Describe what the PR does
2. **Description**: Include:
   - What changes were made and why
   - Screenshots (for UI changes)
   - Related issue number (`Fixes #123`)
   - Testing done
   - Breaking changes (if any)

3. **Small focused PRs**: One feature/fix per PR
4. **Code quality**:
   - Follow coding standards
   - Add comments for complex logic
   - No debugging code (console.log, var_dump, etc.)
   - No commented-out code

5. **Commits**:
   - Clear commit messages
   - Atomic commits (one logical change per commit)
   - Follow conventional commits (optional):
     - `feat: Add DirectAdmin support`
     - `fix: Resolve deletion path validation`
     - `docs: Update installation guide`
     - `refactor: Extract cPanel functions`

### After Submitting

- **Respond to feedback** promptly
- **Make requested changes** in the same branch
- **Keep PR updated** with main branch if needed

## Coding Standards

### PHP

```php
// PSR-12 style guide
// Use type hints
function createSubdomain(string $name, array $config): array {
    // Indentation: 4 spaces
    // Opening braces on same line
    // Clear variable names
    $subdomainData = [];

    // Use early returns
    if (empty($name)) {
        return ['success' => false, 'error' => 'Name required'];
    }

    // Comments for complex logic
    // Avoid deep nesting
    return ['success' => true];
}

// Constants in UPPER_CASE
define('MAX_SUBDOMAINS', 5);

// Class names in PascalCase
class SubdomainManager {
    // ...
}
```

### JavaScript

```javascript
// ES6+ features welcome
// Use const/let, not var
const subdomains = await fetchSubdomains();

// Clear function names
function escapeHtml(text) {
    if (!text) return text;
    // ...
}

// Consistent indentation (2 spaces)
if (condition) {
  doSomething();
}

// Avoid inline styles
// Use CSS classes
```

### CSS

```css
/* BEM naming convention */
.subdomain-table { }
.subdomain-table__row { }
.subdomain-table__row--active { }

/* Mobile-first approach */
.container {
    padding: 10px;
}

@media (min-width: 768px) {
    .container {
        padding: 20px;
    }
}
```

### SQL

```sql
-- Use uppercase for keywords
SELECT * FROM subdomains
WHERE status = 'active'
ORDER BY created_at DESC;

-- Descriptive table/column names
CREATE TABLE deletion_log (
    id INTEGER PRIMARY KEY,
    subdomain_id INTEGER NOT NULL
);
```

## Testing

### Manual Testing

Before submitting a PR, test:

1. **Happy path**: Feature works as expected
2. **Error cases**: Handles errors gracefully
3. **Edge cases**: Empty inputs, special characters, etc.
4. **Cross-browser** (for UI changes): Chrome, Firefox, Safari
5. **Mobile responsive** (for UI changes)

### Automated Testing (Future)

We plan to add:
- PHPUnit for backend tests
- Jest for JavaScript tests
- Integration tests for API calls

For now, manual testing is required.

### Test Checklist

```markdown
- [ ] Feature works as expected
- [ ] No console errors
- [ ] No PHP warnings/notices
- [ ] Mobile responsive
- [ ] Accessibility (keyboard navigation)
- [ ] Security implications considered
- [ ] Documentation updated
```

## Documentation

### Code Comments

```php
/**
 * Creates a new subdomain with full infrastructure
 *
 * @param string $name Subdomain name (alphanumeric with hyphens)
 * @param array $config Configuration array
 * @return array Result with success status and details
 */
function createSubdomain(string $name, array $config): array {
    // Implementation...
}
```

### README Updates

Update README.md if your PR:
- Adds new features
- Changes configuration options
- Modifies installation process
- Adds new dependencies

### Changelog

For significant changes, update CHANGELOG.md:

```markdown
## [1.1.0] - 2026-01-15

### Added
- DirectAdmin support
- Bulk CSV import

### Fixed
- Deletion path validation
- Session timeout handling

### Changed
- Improved AI prompt generation
```

## Getting Help

- **Questions**: Use [GitHub Discussions](https://github.com/yourusername/subforge/discussions)
- **Bugs**: Create an [issue](https://github.com/yourusername/subforge/issues)
- **Chat**: (Future: Discord or Slack channel)

## Recognition

Contributors will be:
- Listed in CONTRIBUTORS.md
- Mentioned in release notes
- Credited in the README

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

---

**Thank you for contributing to SubForge!** 🎉

Every contribution, no matter how small, is valued and appreciated.
