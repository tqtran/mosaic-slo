# MOSAIC Setup

This directory contains the web-based setup interface for MOSAIC.

## Prerequisites

- **PHP 8.1+** with mysqli extension enabled
- **MySQL 8.0+** server running
- Database credentials with permission to CREATE DATABASE

## Quick Start

### 1. Initial Installation

When you first access MOSAIC in your browser:

1. Browse to your MOSAIC installation (e.g., `http://localhost/` or `http://yourserver/beta/`)
2. You'll be automatically redirected to `/setup/` (or `/beta/setup/` if in subdirectory)
3. Fill in the database connection form:
   - **Database Host**: Usually `localhost` or `127.0.0.1`
   - **Port**: Default MySQL port is `3306`
   - **Database Name**: Will be created if it doesn't exist (e.g., `mosaic`)
   - **Username**: MySQL username with CREATE DATABASE privileges
   - **Password**: MySQL user password
   - **Base URL Path**: Auto-detected (e.g., `/` or `/beta/`). Confirm or adjust if needed.

4. Click "Install MOSAIC"

The setup wizard will:
- Auto-detect the base URL path (supports subdirectory installations)
- Test the database connection
- Create the database if it doesn't exist
- Execute the complete schema (all tables, indexes, foreign keys)
- Save credentials and base URL to `src/config/config.yaml`
- Protect the config directory with `.htaccess`

**Note:** Authentication system is in development. For now, all administration pages are accessible without login.

### 2. Verify Setup

Start the development server:

```powershell
php -S localhost:8000
```

Access the demo portal:
```
http://localhost:8000/mosaic-slo/demo/
```

## Files

### `index.php`
Web-based database setup wizard. Creates database, executes schema, and saves configuration.

**Features:**
- Browser-based form interface (no CLI required)
- Auto-detects base URL path for subdirectory installations
- Connection testing before schema execution
- UTF8MB4 charset enforcement
- User-friendly success/error messages
- Security: Protects config directory with .htaccess after installation

**Process:**
1. Displays form for database credentials
2. Tests connection on form submission
3. Creates database if it doesn't exist
4. Executes schema.sql (all tables, indexes, constraints)
5. Saves config.yaml with credentials
6. Creates .htaccess to protect config directory
7. Shows success message with next steps

### `../src/system/database/schema.sql`
Complete MySQL schema for MOSAIC platform.

**Includes:**
- 21 tables covering all entities
- Foreign key constraints with appropriate cascades
- Indexes for performance optimization
- Standard roles (admin, department_chair, program_coordinator, instructor, assessment_coordinator)
- Audit trail fields (created_by, updated_by, created_at, updated_at)
- **Logging tables:**
  - `audit_log` - Change history tracking (INSERT/UPDATE/DELETE operations)
  - `error_log` - Application errors with stack traces
  - `security_log` - Security events (logins, permission denials, threats)

**Key tables:**
- `institution` (includes LTI consumer keys), `institutional_outcomes`
- `departments`, `programs`, `program_outcomes`
- `courses`, `slo_sets`, `student_learning_outcomes`
- `terms`, `course_sections`, `enrollment`
- `students`, `assessments`
- `users`, `roles`, `user_roles`
- `lti_nonces`

## Configuration File

After running the setup wizard, credentials are saved to:

```
src/config/config.yaml
```

**Structure:**
```yaml
database:
  host: localhost
  port: 3306
  name: mosaic_slo
  username: mosaic_user
  password: ********
  charset: utf8mb4

app:
  name: MOSAIC
  timezone: America/Los_Angeles
  base_url: /
```

**base_url Examples:**
- Root installation: `base_url: /`
- Subdirectory installation: `base_url: /beta/`
- Multiple levels: `base_url: /apps/mosaic/`

The `base_url` is used throughout the application for generating correct URLs and redirects. It's auto-detected during setup but can be manually adjusted in the config file if you move the installation.

**Security Note:** The config file contains sensitive credentials. On Unix-like systems, permissions are automatically set to `0600` (owner read/write only). Do not commit this file to version control.

## Logging System

MOSAIC includes comprehensive logging for audit trails, errors, and security events.

### Log Types

1. **Audit Logs** (`audit_log` table)
   - Tracks all data changes (INSERT, UPDATE, DELETE)
   - Records before/after values
   - Includes user context and IP address
   - Used for compliance and forensic analysis

2. **Error Logs** (`error_log` table + `logs/error_*.log`)
   - Application errors with stack traces
   - SQL errors and exceptions
   - Request context for debugging
   - Severity levels: debug, info, warning, error, critical

3. **Security Logs** (`security_log` table + `logs/security_*.log`)
   - Login attempts (success/failure)
   - Permission denials
   - LTI launch attempts
   - Password changes
   - Potential threats flagged

### Log Files

Located in `logs/` directory (outside web root):
- `admin_user_YYYY-MM-DD_HHMMSS.log` - User creation logs
- `error_YYYY-MM-DD.log` - Daily error logs
- `security_YYYY-MM-DD.log` - Daily security logs
- `audit_YYYY-MM-DD.log` - Daily audit logs
- `app_YYYY-MM-DD.log` - General application logs

### Using the Logger

```php
use MOSAIC\Core\Logger;

// Initialize
$logger = Logger::getInstance($config, $db);

// Log an error
$logger->error('Database query failed', 'SQL_ERROR', 'ERR001', 
    $exception->getTraceAsString(), __FILE__, __LINE__, $userPk);

// Log a security event
$logger->security(Logger::EVENT_LOGIN_FAILED, 
    'Invalid credentials for user: admin', null, 'admin', 'warning');

// Log an audit trail
$logger->audit('users', $userPk, 'UPDATE', $currentUserPk, 
    ['email' => 'new@example.com'], ['email' => 'old@example.com']);

// Log general info
$logger->info('Application started');
$logger->warning('Low disk space');
$logger->debug('Variable value', ['var' => $value]);
```

### Log Cleanup

**Note:** Log cleanup functionality will be available through the administration interface. For now, manage logs directly in MySQL if needed.

**Manual Database Cleanup (if needed):**
```sql
-- Keep logs for 90 days
DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
DELETE FROM error_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY) AND is_resolved = 1;
DELETE FROM security_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY) AND is_threat = 0;
```

## Troubleshooting

### Setup Failed

**Error:** Setup wizard shows error message

**Solution:**
- Check MySQL is running and accessible
- Verify username and password are correct
- Ensure user has CREATE DATABASE privilege
- Check browser console for JavaScript errors

```sql
-- Grant privileges to user
GRANT ALL PRIVILEGES ON mosaic_slo.* TO 'mosaic_user'@'localhost';
FLUSH PRIVILEGES;
```

### Cannot Access Setup

**Error:** Browser shows 404 when accessing /setup/

**Solution:**
- Verify web server is running and document root points to src/
- Check that setup/index.php exists in src/setup/ directory
- Verify .htaccess mod_rewrite is enabled (Apache)
- For Nginx, configure proper location blocks

### Schema Execution Error

**Error:** "Schema execution error: Table already exists"

**Solution:**
The schema includes DROP TABLE statements at the beginning. If you encounter errors:

1. Drop the database and recreate:
   ```sql
   DROP DATABASE mosaic_slo;
   CREATE DATABASE mosaic_slo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Re-run setup script

### Password Too Short

**Error:** "Password must be at least 12 characters"

**Solution:**
Use a strong password with at least 12 characters. Recommended:
- Mix of uppercase and lowercase letters
- Numbers
- Special characters

### Config File Not Found

**Error:** "Configuration file not found. Please run setup first."

**Solution:**
Browse to your MOSAIC installation and complete the setup wizard at /setup/. The config file is created by the setup wizard.

## Manual Schema Execution

If you prefer to manually execute the schema:

```powershell
# Using MySQL command line
mysql -u mosaic_user -p mosaic_slo < src/system/database/schema.sql
```

Then manually create the config file in `src/config/config.yaml` using the structure above.

## Next Steps

After setup is complete:

1. **Review Configuration:** Edit `src/config/config.yaml` to adjust settings
2. **Initialize Institution Data:** Add your institution record
3. **Set Up Departments:** Add academic departments
4. **Configure Programs:** Create programs and outcomes
5. **Upload SLO Sets:** Import SLOs for assessment periods
6. **Configure LTI:** Add LMS consumer keys for LTI integration
7. **Test Access:** Log in with admin credentials and verify access

## Development vs Production

### Development
```yaml
app:
  debug: true
  log_level: 'debug'

session:
  cookie_secure: false  # For non-HTTPS local dev
```

### Production
```yaml
app:
  debug: false
  log_level: 'error'

session:
  cookie_secure: true  # HTTPS only
```

**Production Checklist:**
- [ ] Set `debug: false`
- [ ] Set `cookie_secure: true`
- [ ] Use strong database credentials
- [ ] Restrict file permissions on config.yaml
- [ ] Configure firewall rules for MySQL
- [ ] Enable HTTPS on web server
- [ ] Set up database backups
- [ ] Configure log rotation

## Additional Resources

- **Schema Documentation:** [docs/concepts/SCHEMA.md](../docs/concepts/SCHEMA.md)
- **Security Guide:** [docs/concepts/SECURITY.md](../docs/concepts/SECURITY.md)
- **MVC Architecture:** [docs/concepts/MVC.md](../docs/concepts/MVC.md)
- **Authentication:** [docs/concepts/AUTH.md](../docs/concepts/AUTH.md)
