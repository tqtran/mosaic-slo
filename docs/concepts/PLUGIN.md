# Plugin Architecture

## Purpose

Extends SLO Cloud functionality without modifying core code through a plugin system.

## Plugin Types

**Dashboard** - Add widgets and panels to user dashboards  
**Report** - Create custom assessment reports and visualizations  
**Theme** - Customize application appearance and styling  
**Export** - Add data export formats (CSV, Excel, PDF)  
**Connector** - Bridge to external systems (SIS, LMS, warehouses)

## Core vs Connector Separation

**Core Data Layer** (MySQL 8.0+ required)
- Internal SLO Cloud data (students, assessments, SLOs, programs)
- Direct MySQL implementation with prepared statements
- Accessed via core Models
- Hard requirement, no alternatives

**Data Connector Plugins** (Optional extensibility)
- Import from external systems (SIS, HR, LMS)
- Export to external systems (data warehouses, analytics)
- Synchronize between systems
- Do NOT replace core database
- Do NOT modify core schema

## Plugin Structure

**Manifest** (`plugin.json`)
- Plugin metadata (id, name, version, author, type)
- Dependencies (minimum SLO Cloud version)
- Hook registrations (which events plugin handles)
- Routes (URL paths plugin handles)
- Permissions (access control identifiers)

**Main Class**
- Extends plugin base class
- Implements activation/deactivation logic
- Defines hook handler methods
- Manages plugin lifecycle

**Assets**
- CSS stylesheets for plugin UI
- JavaScript for client-side behavior
- Images, fonts, other static files

**Views**
- Templates for plugin UI
- Rendered by plugin controllers
- Integrated with application layout

**Configuration**
- Default settings
- Environment variable overrides
- User-configurable options

## Hook System

**Purpose**: Allow plugins to respond to application events without core modification

**Pattern**:
- Core system fires hooks at key points
- Plugins register methods to handle specific hooks
- Multiple plugins can handle same hook
- Hooks receive data, plugins can modify and return

**Common Hooks**:
- Dashboard rendering - Add widgets
- Report generation - Transform data
- Theme selection - Register theme options
- Export operations - Add format handlers
- Data operations - Trigger syncs

## Plugin Lifecycle

**Installation** - Copy plugin files to plugins directory  
**Activation** - Enable plugin, create plugin tables, register hooks  
**Operation** - Hooks fire, plugin methods execute  
**Deactivation** - Disable plugin, unregister hooks, keep data  
**Uninstallation** - Remove plugin, delete plugin tables and config

## Data Connector Architecture

### ID Mapping Layer

**Problem**: External system IDs differ from internal auto-increment PKs  
**Solution**: Connector-specific mapping tables link external ↔ internal IDs

**Mapping Table Purpose**:
- Track which external records correspond to internal records
- Enable bidirectional lookups
- Store sync status and timestamps
- Log error details for troubleshooting

### Non-Invasive Pattern

**Principle**: Connectors never modify core schema

**Implementation**:
- Connectors read from external APIs
- Transform to core schema format
- Write via core models (Students, Courses, Enrollments)
- Store mappings in connector-specific tables
- Core tables remain unchanged

### Sync Strategies

**Import** - External system → SLO Cloud  
**Export** - SLO Cloud → External system  
**Bidirectional** - Synchronize both directions  
**Scheduled** - Run on cron/timer  
**Triggered** - Respond to webhooks  
**Manual** - Admin initiates sync

### Conflict Resolution

When data differs between systems:
- **External Wins** - External system is source of truth (typical for imports)
- **Internal Wins** - SLO Cloud data takes precedence (rare)
- **Last-Write-Wins** - Most recent modification preserved
- **Manual Review** - Flag conflicts for admin decision

## Security Model

### Permission System

Plugins define custom permissions:
- Format: `plugin-id.action` (e.g., `sis-connector.sync`)
- Registered in manifest
- Checked before sensitive operations
- Integrated with role-based access control

### Data Access

Plugins access data through:
- Core models (read-only or CRUD depending on permissions)
- Connector-specific tables (full control)
- User authentication context
- Permission checks before operations

### Input Validation

All plugin inputs validated:
- User form submissions
- API requests
- External system data
- File uploads

## MySQL 8.0+ Hard Requirement

**Why MySQL is non-negotiable:**
- Relational integrity critical for student data (FERPA)
- Direct MySQL implementation offers best performance
- Simplifies development (no abstraction layer complexity)
- Standard infrastructure (nearly all institutions have MySQL/MariaDB)
- Clear system requirements for adopters

**If you need different storage:**
- ✅ Use data connector plugins for external systems
- ✅ Use caching layers (Redis, Memcached) for performance
- ✅ Use read replicas for reporting/analytics
- ❌ Do NOT attempt to replace core MySQL database

**What this provides:**
- Predictable performance
- Straightforward deployment
- Clear adopter requirements
- Flexibility through connectors, not core abstraction

## Design Rationale

**Why Plugins vs Core Modification:**
- Upgradability - Core updates don't break custom features
- Maintainability - Clear separation of concerns
- Extensibility - Add features without understanding entire codebase
- Portability - Share plugins between institutions
- Stability - Plugin failures don't break core functionality

**Why Connector Pattern:**
- Institutions have diverse external systems
- SIS/LMS vendors vary by institution
- Data formats differ across systems
- Integration requirements change over time
- One size doesn't fit all

**Why Hard MySQL Requirement:**
- Focus development effort on features, not database compatibility
- Customers meet requirements rather than system accommodating all environments
- Simplicity over theoretical flexibility
- Good software has opinions and constraints

## Related Documentation

**Implementation Guides:**
- [PLUGIN_GUIDE.md](../implementation/PLUGIN_GUIDE.md) - Step-by-step plugin development
- [DATA_CONNECTORS.md](../implementation/DATA_CONNECTORS.md) - Connector implementation patterns

**System Context:**
- [ARCHITECTURE.md](ARCHITECTURE.md) - Overall system design
- [SCHEMA.md](SCHEMA.md) - Core database schema
- [SECURITY.md](SECURITY.md) - Security requirements
