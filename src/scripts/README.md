# Scripts Moved

**Note:** All scripts have been moved to `src/scripts/` for deployment purposes.

All operational files now reside in the `src/` directory, which is the only directory deployed to the web server.

## New Location

Scripts are now located at:
- `src/scripts/create_admin_user.php`
- `src/scripts/cleanup_logs.php`

## Usage

```powershell
# From project root
php src/scripts/create_admin_user.php
php src/scripts/cleanup_logs.php 90
```

See `setup/README.md` for complete documentation.
