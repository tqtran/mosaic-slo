# Database Files Moved

**Note:** The database files have been moved to `src/database/` for deployment purposes.

All operational files now reside in the `src/` directory, which is the only directory deployed to the web server.

## New Location

- Schema: `src/database/schema.sql`
- Setup script: `src/setup.php`

## Usage

```powershell
# From project root
php src/setup.php
```

See `setup/README.md` for complete setup documentation.
