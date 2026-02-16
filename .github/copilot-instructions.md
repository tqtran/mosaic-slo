# MOSAIC - Copilot Instructions

## Project Overview

**MOSAIC** (Modular Outcomes System for Achievement and Institutional Compliance) is an open-source Student Learning Outcomes (SLO) assessment platform for higher education with LTI 1.1/1.3 integration. Built in PHP 8.1+ with MySQL, following MVC architecture. FERPA-compliant student data handling is critical.

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
- **Models**: Read [SCHEMA.md](../docs/concepts/SCHEMA.md) for tables, then [MVC_GUIDE.md](../docs/implementation/MVC_GUIDE.md) for patterns
- **Controllers**: Read [MVC.md](../docs/concepts/MVC.md) for responsibilities, then [MVC_GUIDE.md](../docs/implementation/MVC_GUIDE.md) for implementation
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
- [docs/concepts/CONFIGURATION.md](../docs/concepts/CONFIGURATION.md) - Configuration structure, constants, and settings
- [docs/concepts/SCHEMA.md](../docs/concepts/SCHEMA.md) - Complete database schema with naming conventions
- [docs/concepts/MVC.md](../docs/concepts/MVC.md) - MVC architecture overview
- [docs/implementation/MVC_GUIDE.md](../docs/implementation/MVC_GUIDE.md) - MVC implementation patterns

### MVC Structure (Planned)

- **Models** (`src/Models/`): Base class in `src/Core/Model.php` with CRUD operations. All models extend this base.
- **Controllers** (`src/controllers/`): Handle routing, validation, and business logic
- **Views** (`src/views/`): PHP templates with AdminLTE 4 framework (Bootstrap 5), no template engine
- **Common Includes** (`src/includes/`):
  - `header.php` - Loads all framework assets (Bootstrap 5, jQuery, AdminLTE 4, Font Awesome)
  - `footer.php` - Loads JavaScript libraries
  - `message_page.php` - Helper function for error/success pages

**Using Common Includes:**

```php
<?php
$pageTitle = 'My Page Title';
$bodyClass = 'custom-class'; // optional
require_once __DIR__ . '/includes/header.php';
?>

<!-- Your page content here -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
```

**Naming Conventions:**

- Primary keys: `{table_name}_pk` (e.g., `courses_pk`, `students_pk`)
- Foreign keys: `{referenced_table}_fk` (e.g., `course_fk`, `program_fk`)
- Ordering fields: `sequence_num`
- Soft deletes: `is_active` BOOLEAN

## Authentication & Security

**Three concurrent auth methods:**

1. **Dashboard**: Local bcrypt (cost 12) with session-based auth
2. **LTI**: OAuth 1.0 (LTI 1.1) or JWT/JWKS (LTI 1.3) from Learning Management Systems
3. **SAML SSO**: SAML 2.0 federation with institutional Identity Providers

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

- Uses `src/includes/header.php` and `footer.php` (no sidebar, just framework assets)
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
plugins/local/{plugin-id}/
├── plugin.json         # Manifest with hooks, routes, permissions
├── {PluginName}.php    # Main class extending Plugin base
└── assets/             # CSS, JS, images
```

**Plugin Base**: `src/Core/Plugin.php`

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

- AdminLTE 4 framework (CDN: `https://cdn.jsdelivr.net/npm/admin-lte@4.0/dist/css/adminlte.min.css`)
- Bootstrap 5 (AdminLTE dependency)
- jQuery 3.7 (optional, for custom scripting)
- Font Awesome icons (`https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css`)
- Semantic HTML5 elements
- Color scheme: `--primary-dark: #0D47A1`, `--accent-blue: #1976D2`, `--brand-teal: #1565C0`

## Common Tasks

### Adding a New Model

1. **Read [SCHEMA.md](../docs/concepts/SCHEMA.md) first** - Verify table structure, relationships, and naming conventions
2. Review [MVC_GUIDE.md](../docs/implementation/MVC_GUIDE.md) for implementation patterns
3. Extend base Model class (from `src/Core/Model.php`)
4. Set `$table` and `$primaryKey` properties matching schema exactly
5. Implement domain-specific query methods per MVC patterns
6. Follow security patterns from [SECURITY.md](../docs/concepts/SECURITY.md) - use prepared statements

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

### Adding Controllers

1. **Read [MVC.md](../docs/concepts/MVC.md)** for controller responsibilities
2. **Review [MVC_GUIDE.md](../docs/implementation/MVC_GUIDE.md)** for implementation patterns
3. Implement validation per [SECURITY.md](../docs/concepts/SECURITY.md)
4. Use CSRF tokens on all POST/PUT/DELETE operations
5. Follow authorization patterns from [AUTH.md](../docs/concepts/AUTH.md)

### Building Plugins

1. **Read [PLUGIN.md](../docs/concepts/PLUGIN.md)** for architecture and plugin types
2. **Review [PLUGIN_GUIDE.md](../docs/implementation/PLUGIN_GUIDE.md) or [DATA_CONNECTORS.md](../docs/implementation/DATA_CONNECTORS.md)** for step-by-step patterns
3. Follow non-invasive principle: Never modify core schema
4. Use core models for all core data access
5. Follow authorization patterns from [AUTH.md](../docs/concepts/AUTH.md)

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
