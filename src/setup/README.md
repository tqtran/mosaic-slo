# MOSAIC Database Setup

This directory contains documentation for setting up the MOSAIC database and initial configuration.

**Note:** The actual setup script (`setup.php`) is located in the `src/` directory so it can be deployed with the application to a web server.

## Prerequisites

- **PHP 8.1+** with mysqli extension enabled
- **MySQL 8.0+** server running
- Database credentials with permission to CREATE DATABASE

## Quick Start

### 1. Run Database Setup

```powershell
# From project root
php src/setup.php
```

This script will:
- Prompt for database credentials (host, username, password, database name)
- Test the database connection
- Create the database if it doesn't exist
- Execute the complete schema (all tables, indexes, foreign keys)
- Save credentials to `src/config/config.yaml`

**Example interaction:**
```
Database host [localhost]: localhost
Database port [3306]: 3306
Database username [root]: mosaic_user
Database password: ********
Database name [mosaic_slo]: mosaic_slo
```

### 2. Create Admin User

```powershell
# From project root
php src/scripts/create_admin_user.php
```

This script will:
- Prompt for admin user details
- Hash the password with bcrypt (cost 12)
- Create the user in the `users` table
- Assign the global `admin` role

**Example interaction:**
```
Username (user_id): admin
First Name: System
Last Name: Administrator
Email: admin@example.edu
Password (minimum 12 characters): ************
Confirm Password: ************
```

### 3. Verify Setup

Start the development server:

```powershell
php -S localhost:8000
```

Access the demo portal:
```
http://localhost:8000/mosaic-slo/demo/
```

## Files

### `../src/setup.php`
Main database setup script. Creates database, executes schema, and saves configuration.

**Features:**
- Interactive CLI prompts
- Connection testing before schema execution
- UTF8MB4 charset enforcement
- Colored output for readability
- Error handling with rollback

### `../src/database/schema.sql`
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
- `institution`, `institutional_outcomes`
- `departments`, `programs`, `program_outcomes`
- `courses`, `slo_sets`, `student_learning_outcomes`
- `terms`, `course_sections`, `enrollment`
- `students`, `assessments`
- `users`, `roles`, `user_roles`
- `lti_consumers`, `lti_nonces`

### `../src/scripts/create_admin_user.php`
Creates an admin user with global privileges.

**Features:**
- Password strength validation (minimum 12 characters)
- Email validation
- Secure password input (hidden on Windows/Unix)
- Bcrypt hashing with cost 12
- Automatic admin role assignment

## Configuration File

After running `setup.php`, credentials are saved to:

```
src/config/config.yaml
```

**Structure:**
```yaml
secrets:
  database:
    host: localhost
    port: 3306
    database: mosaic_slo
    username: mosaic_user
    password: ********
    charset: utf8mb4

session:
  cookie_httponly: true
  cookie_secure: true
  cookie_samesite: 'Strict'
  timeout: 7200

security:
  bcrypt_cost: 12
  csrf_token_length: 32
  password_min_length: 12

logging:
  enabled: true
  log_to_database: true
  log_to_file: true
  log_directory: 'logs'
  max_log_age_days: 90
  audit_enabled: true
  security_log_enabled: true

app:
  name: 'MOSAIC'
  timezone: 'America/Los_Angeles'
  debug: false
```

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

Located in `logs/` directory:
- `setup_YYYY-MM-DD_HHMMSS.log` - Database setup logs
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

Run periodically to remove old logs:

```powershell
# Keep logs for 90 days (default)
php src/scripts/cleanup_logs.php

# Keep logs for 30 days
php src/scripts/cleanup_logs.php 30
```

**Cleanup Policy:**
- Audit logs: All entries older than specified days
- Error logs: Only resolved errors older than specified days
- Security logs: Only non-threat entries older than specified days
- File logs: All files older than specified days

**Automated Cleanup (Windows Task Scheduler):**
```powershell
$action = New-ScheduledTaskAction -Execute 'php' -Argument 'C:\path\to\mosaic-slo\src\scripts\cleanup_logs.php 90'
$trigger = New-ScheduledTaskTrigger -Weekly -DaysOfWeek Sunday -At 2am
Register-ScheduledTask -TaskName "MOSAIC Log Cleanup" -Action $action -Trigger $trigger
```

## Troubleshooting

### Connection Failed

**Error:** "Connection failed: Access denied for user"

**Solution:**
- Verify MySQL is running
- Check username and password
- Ensure user has CREATE DATABASE privilege

```sql
-- Grant privileges to user
GRANT ALL PRIVILEGES ON mosaic_slo.* TO 'mosaic_user'@'localhost';
FLUSH PRIVILEGES;
```

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

**Error:** "Configuration file not found. Please run setup.php first."

**Solution:**
Run `src/setup.php` before `src/scripts/create_admin_user.php`. The config file is created by the setup script.

## Manual Schema Execution

If you prefer to manually execute the schema:

```powershell
# Using MySQL command line
mysql -u mosaic_user -p mosaic_slo < src/database/schema.sql
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
