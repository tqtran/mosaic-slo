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

## Security

This directory is protected by `.htaccess` (Apache) to prevent direct access.
The `config.yaml` file contains sensitive information (database credentials) and must not be web-accessible.
