# Configuration Directory

This directory contains the `config.yaml` file created during setup.

## Files

- **config.yaml** - Main configuration file (created by setup)
- **config.yaml.template** - Template with all available options

## Manual Configuration

If you need to manually create or update the configuration:

1. Copy `config.yaml.template` to `config.yaml`
2. Update all placeholders with your actual values
3. Set appropriate file permissions (readable by web server only)

## Theme Configuration

Available themes:
- `theme-default` - Clean Bootstrap 5 theme (system default, minimal)
- `theme-adminlte` - Professional admin dashboard with sidebar (recommended for admin pages)
- `theme-metis` - Modern material design theme

To change the active theme, edit `config.yaml`:

```yaml
theme:
  active_theme: theme-adminlte
```

**Recommendation:** Use `theme-adminlte` for installations that primarily use the administration interface. Use `theme-default` for simple deployments or public-facing pages.

If no theme is specified, `theme-default` is used automatically.

## Debug Mode

Debug mode enables detailed error messages and logging throughout the application:

```yaml
app:
  debug_mode: true  # Set to false in production
```

When enabled:
- Displays detailed error messages
- Logs authentication attempts (emergency admin checks, login details)
- Logs configuration values for troubleshooting

**Security Warning:** Debug mode may log sensitive information. Always set to `false` in production.

## Security

This directory is protected by `.htaccess` (Apache) to prevent direct access.
The `config.yaml` file contains sensitive information (database credentials) and must not be web-accessible.

## Emergency Admin Account (Break Glass)

The configuration includes an emergency admin account for recovery scenarios:

```yaml
emergency_admin:
  enabled: true
  username: sloadmin@breakglass.idx
  password: slopass
```

**What it does:**
- Provides emergency access when database users are locked/deleted
- Bypasses all database authentication
- Checked BEFORE normal login attempts
- Uses email format for login form compatibility

**Security Warnings:**
- Password is stored in **PLAIN TEXT** in config.yaml
- Has full system admin access (user_id: 0)
- All logins logged with "Emergency admin login used" warning
- **CHANGE DEFAULT CREDENTIALS IMMEDIATELY** after setup

**To disable:**
- Set `enabled: false`, OR
- Remove the entire `emergency_admin` section

**Best practices:**
- Use complex, unique credentials
- Disable when not actively needed
- Re-enable only for emergency recovery
- Monitor logs for emergency admin usage
