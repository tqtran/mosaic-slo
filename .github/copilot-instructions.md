# MOSAIC - Copilot Instructions

## Project Overview

**MOSAIC** (Modular Outcomes System for Achievement and Institutional Compliance) is an open-source Student Learning Outcomes (SLO) assessment platform for higher education with LTI 1.1/1.3 integration. Built in PHP 8.1+ with MySQL using organized procedural architecture (Page Controller pattern). FERPA-compliant student data handling is critical.

**Current Status:** Early development phase with comprehensive design docs in `design_concepts/` and working demo implementations in `mosaic-slo/demo/` using AdminLTE 4 framework.

AVOID UNICODE characters in code and documentation to ensure compatibility across all environments. Use ASCII characters only.

- X (BALLOT X) → X or [X] or [FAIL]
- OK (CHECK MARK) → OK or [OK] or [PASS]
- +===+ (box drawing) → +===+ or similar ASCII
- | (box drawing) → |

## Architecture-First Principle

**ALWAYS consult architecture docs before implementing features or making architectural decisions.** 

### Documentation Structure

**Architecture Concepts** (`docs/concepts/`)
- High-level system design and architectural decisions
- What the system does and why
- Non-negotiable requirements and constraints

**Implementation Guides** (`docs/implementation/`)
- Step-by-step patterns and workflows
- How to build features
- Code organization and best practices

### Before You Code

- **Configuration**: Read [CONFIGURATION.md](../docs/concepts/CONFIGURATION.md) for config structure and available constants
- **Code Structure**: Read [CODE_ORGANIZATION.md](../docs/concepts/CODE_ORGANIZATION.md) for architecture overview
- **Implementation**: Read [CODE_GUIDE.md](../docs/implementation/CODE_GUIDE.md) for practical patterns
- **Database**: Read [SCHEMA.md](../docs/concepts/SCHEMA.md) for table structure and naming conventions
- **Auth**: Read [AUTH.md](../docs/concepts/AUTH.md) and [SECURITY.md](../docs/concepts/SECURITY.md)
- **Plugins**: Read [PLUGIN.md](../docs/concepts/PLUGIN.md), then [PLUGIN_GUIDE.md](../docs/implementation/PLUGIN_GUIDE.md)
- **Data Connectors**: Read [PLUGIN.md](../docs/concepts/PLUGIN.md), then [DATA_CONNECTORS.md](../docs/implementation/DATA_CONNECTORS.md)
- **Tests**: Read [TESTING.md](../docs/concepts/TESTING.md)

**All implementations must align with architecture docs.** Concepts explain *what* and *why*, guides explain *how*.

## Architecture Overview

**Drop-in Install:** The `src/` directory is designed as the web root for drop-in installation. Point your web server document root at `src/` and the application is ready to run after database setup. See [ARCHITECTURE.md](../docs/concepts/ARCHITECTURE.md) for security requirements.

**Base URL Handling:** The application automatically detects its installation path during setup and stores it in `config.yaml` for optimal performance. The `BASE_URL` constant is available throughout the application for building URLs. This supports installations at domain root (`/`) or subdirectories (`/beta/`, `/mosaic/`). Use `BASE_URL` for all internal links and redirects.

### Hierarchical Assessment Model

```text
Institution → Institutional Outcomes → Program Outcomes → SLOs → Assessments
Department → Program → Course → Course Section → Student Enrollment
```

**Critical Files:**

- [docs/concepts/ARCHITECTURE.md](../docs/concepts/ARCHITECTURE.md) - System architecture and entity relationships
- [docs/concepts/CODE_ORGANIZATION.md](../docs/concepts/CODE_ORGANIZATION.md) - Code structure and patterns
- [docs/concepts/CONFIGURATION.md](../docs/concepts/CONFIGURATION.md) - Configuration structure, constants, and settings
- [docs/concepts/SCHEMA.md](../docs/concepts/SCHEMA.md) - Complete database schema with naming conventions
- [docs/implementation/CODE_GUIDE.md](../docs/implementation/CODE_GUIDE.md) - Practical implementation patterns

### Code Structure

Organized procedural PHP with optional data access layer:

- **Feature Directories** (`src/dashboard/`, `src/lti/`): Pages organized by feature area
- **System Infrastructure** (`src/system/`): Core framework code
  - **Core** (`src/system/Core/`): Database, Config, Logger, Path helpers
  - **Models** (`src/system/Models/`): Optional data access layer - Base class in `src/system/Core/Model.php` for shared patterns
  - **Common Includes** (`src/system/includes/`): Shared page components
    - `header.php` - Loads all framework assets (Bootstrap 5, jQuery, AdminLTE 4, Font Awesome)
    - `footer.php` - Loads JavaScript libraries
    - `sidebar.php` - AdminLTE sidebar navigation (for admin pages)
    - `message_page.php` - Helper function for error/success pages
  - **Plugins** (`src/system/plugins/`): Plugin framework

**Using Common Includes:**

```php
<?php
$pageTitle = 'My Page Title';
$bodyClass = 'custom-class'; // optional
require_once __DIR__ . '/system/includes/header.php';
?>

<!-- Your page content here -->

<?php require_once __DIR__ . '/system/includes/footer.php'; ?>
```

**Naming Conventions:**

- Primary keys: `{table_name}_pk` (e.g., `courses_pk`, `students_pk`)
- Foreign keys: `{referenced_table}_fk` (e.g., `course_fk`, `program_fk`)
- Ordering fields: `sequence_num`
- Soft deletes: `is_active` BOOLEAN

**Database Query Pattern:**

All database queries MUST use the `$dbPrefix` variable for table names. This variable is set by `init.php` based on the configured table prefix in `config.yaml`.

```php
<?php
require_once __DIR__ . '/system/includes/init.php';

// CORRECT: Use $dbPrefix variable
$result = $db->query(
    "SELECT * FROM {$dbPrefix}users WHERE users_pk = ?",
    [$userId],
    'i'
);

// CORRECT: Multiple tables
$result = $db->query("
    SELECT u.*, r.role_name 
    FROM {$dbPrefix}users u
    LEFT JOIN {$dbPrefix}user_roles ur ON u.users_pk = ur.user_fk
    LEFT JOIN {$dbPrefix}roles r ON ur.role_fk = r.roles_pk
    WHERE u.users_pk = ?
", [$userId], 'i');

// WRONG: Hardcoded table names will break if prefix is configured
$result = $db->query("SELECT * FROM users WHERE users_pk = ?", [$userId], 'i');
$result = $db->query("SELECT * FROM tbl_users WHERE users_pk = ?", [$userId], 'i');
```

**Key Points:**
- Always include `init.php` to get `$db` and `$dbPrefix` variables
- Use string interpolation: `{$dbPrefix}tablename` in all queries
- Table names in schema are prefixed with `tbl_` which gets replaced during setup
- Application code uses `$dbPrefix` which reflects the actual configured prefix
- Never hardcode table names without the prefix variable

## Authentication & Security

**Three concurrent auth methods:**

1. **Dashboard**: Local Argon2id (64MB memory, 4 iterations) with session-based auth
2. **LTI**: OAuth 1.0 (LTI 1.1) or JWT/JWKS (LTI 1.3) from Learning Management Systems
3. **SAML SSO**: SAML 2.0 federation with institutional Identity Providers

**Emergency Admin (Break Glass)**:
- Configuration-based account in `config.yaml` (default: sloadmin@breakglass.local/slopass)
- Email format required for login form compatibility
- Bypasses database for emergency recovery access
- Password stored in plain text - change immediately after setup
- Checked BEFORE database authentication
- Disable by setting `emergency_admin.enabled: false`

**Security Requirements** (see [docs/concepts/SECURITY.md](../docs/concepts/SECURITY.md)):

- **Always use prepared statements** - no string concatenation in SQL queries
- **Escape all output** - HTML escape user-generated content
- **CSRF tokens** - required on all POST/PUT/DELETE operations
- **HttpOnly, Secure, SameSite=Strict** cookies
- **2-hour session timeout** with ID regeneration on privilege change

## Development Workflow

### Local Development Server

```powershell
# Start from project root
php -S localhost:8000

# Access main application (once index.php exists)
http://localhost:8000/src/

# Access demo portal
http://localhost:8000/mosaic-slo/demo/
```

### Current Working Demo

- **Portal**: `mosaic-slo/demo/index.php` - Landing page with demo cards
- **Dashboard**: `mosaic-slo/demo/dashboard.php` - Admin analytics with AdminLTE sidebar
- **SLO Management**: `mosaic-slo/demo/admin_slo.php` - Outcome hierarchy management
- **Student Management**: `mosaic-slo/demo/admin_users.php` - Enrollment tracking
- **LTI Simulation**: `mosaic-slo/demo/lti_endpoint.php` - Instructor assessment interface (no sidebar)
- **Sample Data**: `mosaic-slo/demo/sample.csv` - Multi-course assessment data

**Demo Pattern:**

- No authentication required (bypassed for demos)
- Uses `mosaic-slo/demo/includes/header.php` and `footer.php` (includes AdminLTE sidebar for admin pages)
- CSV parsing with file operations (`fopen`, `fgetcsv`)
- Session storage for selected course (`$_SESSION['selected_crn']`)
- GET parameter filtering with validation
- AdminLTE 4 framework with responsive sidebar navigation

**Application Pattern:**

- Uses `src/system/includes/header.php` and `footer.php` (no sidebar, just framework assets)
- For pages with AdminLTE sidebar, copy pattern from demo includes

### Session Handling Pattern

```php
// Always configure session security
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
ini_set('session.cookie_samesite', 'Strict');
session_start();

// Regenerate ID periodically (every 30 min)
if (time() - $_SESSION['created'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}
```

## Plugin Architecture

Plugins extend functionality without modifying core code. Types: Dashboard widgets, Reports, Themes, Export formats.

**Structure** (see [docs/concepts/PLUGIN.md](../docs/concepts/PLUGIN.md)):

```text
src/system/plugins/local/{plugin-id}/
├── plugin.json         # Manifest with hooks, routes, permissions
├── {PluginName}.php    # Main class extending Plugin base
└── assets/             # CSS, JS, images
```

**Plugin Base**: `src/system/Core/Plugin.php`

## Testing Strategy

See [docs/concepts/TESTING.md](../docs/concepts/TESTING.md) for comprehensive testing approach.

**Key testing areas:**

- Unit tests: Models, validation, business logic (80%+ coverage target)
- Integration tests: Controller-Model interactions, auth flows
- Security tests: SQL injection, XSS, CSRF, authorization violations
- E2E tests: LTI launches, assessment workflows

**No test files exist yet** - refer to TESTING.md when implementing.

## Code Style & Patterns

**PHP:**

- Strict types: `declare(strict_types=1);`
- Type hints on all method signatures
- PSR-12 coding standard
- No short PHP tags (`<?=` allowed in views only)

**HTML/CSS:**

- AdminLTE 4 framework (CDN: `https://cdn.jsdelivr.net/npm/adminlte4@4.0.0-rc.6.20260104/dist/css/adminlte.min.css`)
- Bootstrap 5 (AdminLTE dependency)
- jQuery 3.7 (optional, for custom scripting)
- Font Awesome icons (`https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css`)
- Semantic HTML5 elements
- Color scheme: `--primary-dark: #0D47A1`, `--accent-blue: #1976D2`, `--brand-teal: #1565C0`

## Common Tasks

### Adding a Page

1. **Determine directory** - `src/dashboard/` for admin pages, `src/lti/` for LTI tools, etc.
2. **Read [SCHEMA.md](../docs/concepts/SCHEMA.md)** - Understand required tables and relationships
3. **Review [CODE_GUIDE.md](../docs/implementation/CODE_GUIDE.md)** - Follow page structure patterns
4. **Include init.php** - Always `require_once __DIR__ . '/system/includes/init.php'` to get `$db` and `$dbPrefix`
5. **Include header/footer** - Use `includes/header.php` and `includes/footer.php`
6. **Database queries** - Use prepared statements with `{$dbPrefix}tablename` pattern (never hardcode table names)
7. **Follow security patterns** from [SECURITY.md](../docs/concepts/SECURITY.md) - CSRF tokens, output escaping, prepared statements

### Adding an Optional Model (When Useful)

1. **Read [SCHEMA.md](../docs/concepts/SCHEMA.md) first** - Verify table structure, relationships, and naming conventions
2. **Review [CODE_GUIDE.md](../docs/implementation/CODE_GUIDE.md)** - See when models help vs. direct queries
3. **Extend base Model class** (from `src/system/Core/Model.php`) if shared patterns emerge
4. **Set `$table` and `$primaryKey`** properties - Use base table name without prefix (e.g., `users` not `tbl_users`)
5. **Use `$dbPrefix` in queries** - Model methods must use `{$this->dbPrefix}tablename` pattern
6. **Implement domain-specific methods** - Query, validation, business logic
7. **Follow security patterns** from [SECURITY.md](../docs/concepts/SECURITY.md) - use prepared statements with table prefixes

### Adding a Demo Page

1. **Review existing demos** in `mosaic-slo/demo/` for patterns
2. Create file in `mosaic-slo/demo/`
3. Include `includes/header.php` and `includes/sidebar.php` for admin pages (or omit sidebar for LTI pages)
4. Use `__DIR__` for relative paths to `sample.csv`
5. Implement session security pattern (see Session Handling Pattern above)
6. Add card to `mosaic-slo/demo/index.php` portal
7. Update `mosaic-slo/demo/README.md`

### Implementing LTI Integration

**Read [AUTH.md](../docs/concepts/AUTH.md) first** for complete LTI launch validation patterns:

- LTI 1.1: OAuth 1.0 signature validation
- LTI 1.3: JWKS public key verification
- Auto-provision users from launch parameters
- Role mapping to internal RBAC system per [AUTH.md](../docs/concepts/AUTH.md) role hierarchy

### Adding Application Logic

1. **Keep it simple** - Direct logic in page files unless complexity warrants extraction
2. **Review [CODE_GUIDE.md](../docs/implementation/CODE_GUIDE.md)** for request handling patterns
3. **Implement validation** per [SECURITY.md](../docs/concepts/SECURITY.md)
4. **Use CSRF tokens** on all POST/PUT/DELETE operations
5. **Follow authorization patterns** from [AUTH.md](../docs/concepts/AUTH.md)
6. **Extract to functions/classes** only when patterns repeat 3+ times

### Building Plugins

1. **Read [PLUGIN.md](../docs/concepts/PLUGIN.md)** for architecture and plugin types
2. **Review [PLUGIN_GUIDE.md](../docs/implementation/PLUGIN_GUIDE.md) or [DATA_CONNECTORS.md](../docs/implementation/DATA_CONNECTORS.md)** for step-by-step patterns
3. **Keep plugins simple** - Direct logic, no framework overhead required
4. **Follow non-invasive principle** - Never modify core schema
5. **Database access** - Use Core\Database directly or via Models, always use `{$dbPrefix}tablename`
6. **Follow authorization patterns** from [AUTH.md](../docs/concepts/AUTH.md)

## Key Dependencies

**Required (Hard Requirements):**

- PHP 8.1+ with extensions: mysqli, mbstring, openssl, json
- MySQL 8.0+ (InnoDB engine, UTF8MB4 charset)
  - No PostgreSQL, SQLite, or other database support
  - No ORM or database abstraction layer

**Frontend (CDN):**

- AdminLTE 4.0 (includes Bootstrap 5.3.2)
- jQuery 3.7.0 (optional, for custom scripting)
- Font Awesome 6.4.0

**Note:** For external system integration (SIS, LMS), use data connector plugins rather than attempting database substitution. See [docs/concepts/PLUGIN.md](../docs/concepts/PLUGIN.md).

## Database Schema Requirements

**CRITICAL: Two-part table naming pattern:**

1. **Schema file** (`src/system/database/schema.sql`) uses `tbl_` prefix
2. **Application code** uses `{$dbPrefix}` variable from `init.php`

### Schema File Location

The canonical schema file is **`src/system/database/schema.sql`**. This is the only version setup reads during installation.

The `database/schema.sql` at project root is optional (for version control reference only). All schema changes must be made to `src/system/database/schema.sql`.

### Schema File Pattern

Setup process replaces `tbl_` with configured prefix (or removes it if no prefix configured):
- User configures table prefix during setup (e.g., `mosaic_`, `slo_`, or empty for no prefix)
- `src/setup/index.php` reads `database/schema.sql` and replaces all `tbl_` instances
- This allows flexible deployment without maintaining multiple schema files

**Required naming pattern in schema.sql:**

```sql
CREATE TABLE tbl_users (
    users_pk INT AUTO_INCREMENT PRIMARY KEY,
    ...
);

CREATE TABLE tbl_roles (
    roles_pk INT AUTO_INCREMENT PRIMARY KEY,
    ...
    FOREIGN KEY (user_fk) REFERENCES tbl_users(users_pk)
);
```

**DO NOT write schema tables without the `tbl_` prefix.** Setup will fail to apply configured prefix.

When adding or modifying tables in schema:
1. Always use `tbl_` prefix in CREATE TABLE statements
2. Always use `tbl_` prefix in FOREIGN KEY REFERENCES
3. Always use `tbl_` prefix in DROP TABLE statements
4. Always use `tbl_` prefix in INSERT INTO statements
5. Test by running setup with different prefix configurations

### Application Code Pattern

**Never hardcode table names.** Always use `$dbPrefix` variable from `init.php`:

```php
// CORRECT
require_once __DIR__ . '/system/includes/init.php';
$result = $db->query("SELECT * FROM {$dbPrefix}users WHERE users_pk = ?", [$id], 'i');

// WRONG - will break if prefix is configured
$result = $db->query("SELECT * FROM users WHERE users_pk = ?", [$id], 'i');
$result = $db->query("SELECT * FROM tbl_users WHERE users_pk = ?", [$id], 'i');
```

The `$dbPrefix` variable automatically reflects the configured prefix (e.g., `mosaic_`, `slo_`, or empty string).

## Important Constraints

- **FERPA Compliance**: Student data must be protected, audit all access
- **MySQL 8.0+ Required**: No database abstraction layer, direct MySQL with prepared statements
  - For external integrations, use data connector plugins (see [PLUGIN.md](../docs/concepts/PLUGIN.md))
- **No Composer yet**: Dependencies via CDN or manual installation
- **Windows Development**: Use PowerShell commands, path separators handled by PHP

## Project Philosophy: Simplicity Over Flexibility

**Prioritize usability and simplicity first. Be flexible when it doesn't compromise these.**

**Call out overengineering.** If a request adds unnecessary complexity, abstraction, or "flexibility" that isn't needed, push back firmly:

- ❌ "Should we abstract the database layer to support multiple databases?"
  - **Response:** "No. MySQL 8.0+ is a hard requirement. Adding abstraction adds complexity without real-world benefit. Use connector plugins if you need to integrate external systems."

- ❌ "Let's make this configurable so users can customize..."
  - **Response:** "What's the actual use case? Don't add configuration for theoretical needs. Ship with good defaults. Add configurability only when users actually request it."

- ❌ "We should support backward compatibility with..."
  - **Response:** "No. Set clear requirements. If adopters need old versions, they should upgrade their infrastructure first."

- ❌ "What if someone wants to use NoSQL/PostgreSQL/SQLite..."
  - **Response:** "They can't. This is MySQL-only. If they need alternative storage, they can use connector plugins to bridge external systems."

**YAGNI (You Aren't Gonna Need It):**

- Build what's needed now, not what might be needed later
- Hard requirements are better than trying to please everyone
- Complexity is expensive: development time, bugs, maintenance, onboarding
- Good software has opinions and constraints

**When to challenge the user:**

- Premature abstraction or generalization
- Adding flexibility without concrete use cases
- Supporting multiple options when one good option exists
- "Future-proofing" that makes current development harder
- Trying to accommodate edge cases that may never happen

**When to be flexible:**

- When it simplifies the user experience (optional fields, sensible defaults)
- When institutions demonstrably differ (SIS connectors, auth methods, custom reports)
- When flexibility is already simple to implement (configuration over code changes)
- When it prevents forking (plugin hooks for common extensions)

**Push for:**

- Concrete implementations over abstract patterns
- Clear, non-negotiable requirements
- Simple, maintainable code
- Shipping working features over theoretical flexibility
- Real user problems over hypothetical scenarios

## Design Patterns & Code Reuse

**Detect opportunities to reuse, don't theorize abstractions.**

When building multiple similar features (admin tables, reports, data exports), actively look for patterns worth sharing:

**Consistent patterns across similar features:**

- **DataTables**: Use server-side AJAX everywhere for admin tables
  - Even if client-side works for small datasets now
  - Rationale: Shared handler matures over time, benefits all consumers
  - "It's not if, but when" - tables grow, requirements change
  - Consistency reduces cognitive load and maintenance burden

- **CRUD operations**: Establish one pattern, apply consistently
  - Don't mix direct queries in some places and models in others without reason
  - Pick the simplest approach that works for the current case

- **Form validation**: Shared validation handlers for common patterns
  - Email, dates, foreign keys, etc.

**Factor shared components when:**

- You're actively building multiple consumers (not theoretically)
- The pattern has proven itself in 2-3 real implementations
- Sharing reduces duplication without adding complexity

**Don't prematurely abstract when:**

- Building the first version of something
- The "pattern" only exists in one place
- The abstraction adds layers without clear benefit

**User will steer as needed.** Focus on shipping working features, but keep eyes open for practical reuse opportunities.
