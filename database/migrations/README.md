# Database Migrations

This folder contains SQL migration scripts to update existing MOSAIC databases with new schema changes.

## Important Notes

- **ALWAYS backup your database before running migrations!**
- Migrations are numbered sequentially (001, 002, etc.)
- Run migrations in order
- Each migration is idempotent where possible
- Test migrations on a development copy first

## Available Migrations

### 001_simplify_enrollment.sql

**Purpose:** Simplifies enrollment import by removing dependency on course_sections table.

**Changes:**
- Updates `students` table to use `c_number` (Banner C-Number) as primary identifier
- Adds `term_code` and `crn` columns directly to `enrollment` table
- Makes `course_section_fk` optional (nullable) instead of required
- Updates constraints and indexes
- Adds 'withdrawn' to enrollment status enum

**When to apply:** If you have an existing MOSAIC installation and want to import enrollment data directly from Banner ENRs table without pre-populating course_sections.

**How to apply:**

```bash
# Via MySQL command line
mysql -u your_user -p your_database < 001_simplify_enrollment.sql

# Via PowerShell
Get-Content 001_simplify_enrollment.sql | mysql -u your_user -p your_database

# Via phpMyAdmin
# Import the file using the Import tab
```

**Verification:**

```sql
-- Check that term_code and crn are populated
SELECT COUNT(*) FROM enrollment WHERE term_code IS NULL OR crn IS NULL;
-- Should return 0

-- Check students have c_number
SELECT COUNT(*) FROM students WHERE c_number IS NULL;
-- Should return 0

-- Verify enrollment structure
DESC enrollment;
```

## Creating New Migrations

When creating new migrations:

1. **Number sequentially:** `002_description.sql`, `003_description.sql`
2. **Include header comments:** Date, description, what's changing
3. **Make them safe:** Use IF NOT EXISTS, check for existing columns/indexes
4. **Document rollback:** Note how to undo the changes if needed
5. **Add verification queries:** Help users confirm migration succeeded
6. **Update this README:** Document the new migration

## Rollback Strategy

These migrations are designed to be **forward-only**. If you need to rollback:

1. **Restore from backup** (recommended)
2. Manually reverse the changes using inverse SQL operations
3. Drop added columns/indexes, restore original constraints

No automated rollback is provided to encourage proper testing and backups.
