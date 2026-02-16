# Configuration Guide

## Overview

MOSAIC uses a YAML-based configuration file (`config.yaml`) for all system settings. This file is generated during the web-based setup wizard and should never be committed to version control.

**Location:** `src/config/config.yaml`

## Configuration Structure

### Application Settings

```yaml
app:
  name: Springfield University Assessment
  timezone: America/Los_Angeles
  base_url: /beta/
```

- **name**: Display name for your installation (used in page titles, emails, etc.)
- **timezone**: PHP timezone identifier for date/time formatting
- **base_url**: Installation path (/ for root, /subdirectory/ for subdirectory installs)

### Database Settings

```yaml
database:
  host: localhost
  port: 3306
  name: mosaic
  prefix: msc_
  username: root
  password: secret
  charset: utf8mb4
```

- **host**: MySQL server address (usually "localhost")
- **port**: MySQL port (default: 3306)
- **name**: Database name (created automatically during setup)
- **username**: MySQL username
- **password**: MySQL password
- **charset**: Character set (always utf8mb4 for full Unicode support)

### Email Settings

```yaml
email:
  method: smtp
  from_email: noreply@university.edu
  from_name: Springfield University Assessment
  smtp_host: smtp.gmail.com
  smtp_port: 587
  smtp_username: user@gmail.com
  smtp_password: app_password_here
  smtp_encryption: tls
```

- **method**: Email delivery method
  - `disabled`: No email notifications
  - `server`: Use PHP mail() function
  - `smtp`: Use SMTP server (recommended)
- **from_email**: Sender email address for outgoing notifications
- **from_name**: Display name for outgoing emails
- **smtp_host**: SMTP server address (required if method = smtp)
- **smtp_port**: SMTP port (587 for TLS, 465 for SSL)
- **smtp_username**: SMTP authentication username
- **smtp_password**: SMTP authentication password
- **smtp_encryption**: Encryption method (tls, ssl, or none)

#### Email Method Comparison

| Method | Pros | Cons |
|--------|------|------|
| **disabled** | No configuration needed | No notifications |
| **server** | Simple, uses server mail | May be blocked by spam filters, limited reliability |
| **smtp** | Reliable delivery, better reputation | Requires SMTP credentials |

#### Gmail SMTP Setup

For Gmail, use these settings:
- Host: `smtp.gmail.com`
- Port: `587`
- Encryption: `tls`
- Username: Your full Gmail address
- Password: [App Password](https://support.google.com/accounts/answer/185833) (not your Gmail password)

## Configuration Constants

After loading `config.yaml`, these constants are available throughout the application:

### Core Constants

```php
BASE_URL      // Installation path (e.g., '/beta/')
BASE_PATH     // Filesystem path to src/ directory
SITE_NAME     // Display name for the installation
CONFIG_PATH   // Path to config.yaml
APP_ROOT      // Same as BASE_PATH
```

### Email Constants

```php
EMAIL_METHOD      // disabled|server|smtp
EMAIL_FROM_EMAIL  // Sender email address
EMAIL_FROM_NAME   // Sender display name
```

## Accessing Configuration Values

### Using Constants (Recommended)

```php
// Display site name
echo htmlspecialchars(SITE_NAME);

// Check if email is enabled
if (EMAIL_METHOD !== 'disabled') {
    // Send notification
}
```

### Using Config Object

```php
// Get Config instance
$config = \Mosaic\Core\Config::getInstance(CONFIG_PATH);

// Read any config value
$timezone = $config->get('app.timezone', 'America/Los_Angeles');
$smtpHost = $config->get('email.smtp_host', '');

// Check if key exists
if ($config->has('email.smtp_host')) {
    // SMTP is configured
}
```

## Security Considerations

### Protecting config.yaml

The setup wizard automatically creates protection files:

**`.htaccess`** (Apache):
```apache
Deny from all
```

**`index.php`** (fallback):
```php
<?php
http_response_code(403);
exit('Forbidden');
```

### Version Control

**NEVER commit `config.yaml` to version control.** Add to `.gitignore`:

```gitignore
src/config/config.yaml
```

Provide a template instead:

```yaml
# config.yaml.example
# Copy to config.yaml and fill in your values

database:
  host: localhost
  port: 3306
  name: mosaic
  prefix: 
  username: your_username
  password: your_password
  charset: utf8mb4

app:
  name: Your Institution Name
  timezone: America/Los_Angeles
  base_url: /

email:
  method: disabled
  from_email: 
  from_name: 
```

## Reconfiguration

To change configuration after initial setup:

1. **Via Config File**: Edit `src/config/config.yaml` directly
2. **Via Reinstall**: Delete `config.yaml` and run setup wizard again (WARNING: This does NOT drop existing database tables)

For database changes, you may need to:
- Run migrations if schema changes
- Export/import data if changing database

## Troubleshooting

### Config Not Loading

**Symptoms**: HTTP 500 error, "Configuration Error" message

**Solutions**:
1. Check that `config.yaml` exists in `src/config/`
2. Verify file permissions (readable by web server)
3. Check `logs/php_errors.log` for YAML parsing errors
4. Validate YAML syntax (no tabs, proper indentation)

### Email Not Sending

**Check Config**:
```php
echo EMAIL_METHOD; // Should be 'smtp' or 'server', not 'disabled'
echo EMAIL_FROM_EMAIL; // Should have a value
```

**Test SMTP Credentials**:
- Verify host, port, username, password
- Try telnet to SMTP server
- Check for firewall blocking outbound connections
- Review SMTP server logs

### Database Connection Issues

Better approach: Set prefix correctly during initial setup.

## Related Documentation

- [ARCHITECTURE.md](ARCHITECTURE.md) - System architecture overview
- [SCHEMA.md](SCHEMA.md) - Database schema with table naming conventions
- [MVC_GUIDE.md](../implementation/MVC_GUIDE.md) - Using config in MVC components
- [SECURITY.md](SECURITY.md) - Security best practices
