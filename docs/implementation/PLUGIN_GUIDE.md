# Plugin Implementation Guide

This guide provides practical patterns for building plugins as described in [../concepts/PLUGIN.md](../concepts/PLUGIN.md).

## Plugin Directory Structure

```
plugins/local/{plugin-id}/
├── plugin.json           # Manifest (metadata, hooks, routes)
├── {PluginName}.php      # Main plugin class
├── controllers/          # Plugin controllers
├── views/                # Plugin templates
├── assets/               # CSS, JS, images
├── config.php            # Configuration defaults
└── README.md            # Plugin documentation
```

## Creating a Plugin Manifest

### Basic Manifest Structure

**File: `plugin.json`**

Include required fields:
- `id` - Unique identifier (lowercase-with-dashes)
- `name` - Display name
- `version` - Semantic version (1.0.0)
- `description` - Brief description
- `author` - Author name/organization
- `type` - Plugin type (dashboard, report, theme, export, connector)

Include hook registrations:
- Map hook names to plugin method names
- System calls these methods when hooks fire

Include routes (if plugin has UI):
- Define URL paths, controller, action
- Specify required permissions

Include permissions (if plugin adds new actions):
- Define permission identifiers
- Follow `plugin-id.action` naming

### Dependency Declaration

Specify requirements:
- Minimum SLO Cloud version
- PHP version/extensions needed
- Other plugins (if dependent)

## Implementing Plugin Class

### Class Structure

Main plugin class:
- Extends base Plugin class
- Named after plugin (CamelCase)
- Located in plugin root directory

Constructor receives:
- Plugin ID
- Plugin file path
- Base class stores these

### Required Methods

**activate()**
- Called when plugin is enabled
- Create plugin-specific tables
- Copy assets to public directory
- Initialize configuration
- Return true on success, false on failure

**deactivate()**
- Called when plugin is disabled
- Clean up temporary data
- Keep user data intact
- Return true on success

**uninstall()**
- Called when plugin is removed
- Drop plugin-specific tables
- Remove assets
- Delete configuration
- Cannot be undone

### Hook Handler Methods

Methods that handle hook events:
- Receive data array as parameter
- Process or modify data
- Return modified data or result
- Use return null to pass through unchanged

## Plugin Types

### Dashboard Widget Plugin

Adds card/widget to admin dashboard.

**Implementation steps:**
1. Hook into `dashboard.widgets` 
2. Return widget metadata (title, icon, content)
3. Generate widget HTML content
4. Include inline styles or load CSS asset
5. Optionally add JavaScript for interactivity

**Widget metadata structure:**
- `id` - Unique widget identifier
- `title` - Display title
- `icon` - Bootstrap icon class
- `content` - HTML content to display
- `order` - Display ordering (lower = earlier)
- `permissions` - Required permissions to view

### Report Plugin

Adds new report type to reporting system.

**Implementation steps:**
1. Hook into `reports.types`
2. Define report metadata (id, name, description)
3. Implement report generation method
4. Query required data from models
5. Format data for display
6. Support multiple output formats (HTML, CSV, PDF)

### Theme Plugin

Provides alternative visual styling.

**Implementation steps:**
1. Create CSS file with style overrides
2. On activation, copy theme CSS to public directory
3. Hook into `themes.available`
4. Add theme option to list
5. System loads chosen theme CSS for user

### Export Plugin

Adds new export format for reports/data.

**Implementation steps:**
1. Hook into `export.formats`
2. Register format (id, name, MIME type, file extension)
3. Implement export method
4. Transform data array to format
5. Return formatted content
6. Set appropriate headers for download

### Data Connector Plugin

See [DATA_CONNECTORS.md](DATA_CONNECTORS.md) for comprehensive guide.

## Database Operations

### Creating Plugin Tables

In `activate()` method:
1. Get database instance
2. Execute CREATE TABLE statement
3. Use InnoDB engine, UTF8MB4 charset
4. Prefix table name with plugin ID
5. Include primary key, indexes
6. Return true on success

**Table naming:** `{plugin_id}_table_name`

**Table structure:**
- Auto-increment primary key
- Appropriate data types
- Foreign keys to core tables (if needed)
- Timestamps for auditing
- Indexes on frequently queried columns

### Dropping Plugin Tables

In `uninstall()` method:
1. Get database instance
2. Execute DROP TABLE statement
3. Drop tables in reverse dependency order
4. Handle errors gracefully

## Accessing Core Functionality

### Using Core Models

Load and use core models:
1. Reference model class
2. Instantiate model
3. Use model methods for CRUD
4. Never write directly to core tables with SQL

### Checking Permissions

Before sensitive actions:
1. Get authentication system
2. Check if user has permission
3. Throw exception if denied
4. Continue if authorized

**Permission check pattern:**
- Get current user
- Call permission check with permission string
- Handle forbidden exception if needed

### Input Validation

For all user input:
1. Define validation rules
2. Create validator instance
3. Run validation
4. Return errors if validation fails
5. Process data if validation passes

**Validation rules:**
- Use same validation system as core
- Define rules as strings or arrays
- Check required, type, length, format
- Custom validators for complex logic

## Asset Management

### Including CSS

Reference CSS in views:
- Use plugin asset path helper
- Load from `plugins/local/{id}/assets/`
- Include in view `<head>` section
- Support theme customization

### Including JavaScript

Reference JavaScript in views:
- Load after page content
- Use plugin asset path helper
- Initialize after DOM ready
- Avoid conflicts with core JS

### Asset Organization

Organize assets by type:
- `assets/css/` - Stylesheets
- `assets/js/` - JavaScript files
- `assets/images/` - Images
- `assets/fonts/` - Fonts (if needed)

## Configuration Management

### Default Configuration

**File: `config.php`**

Return associative array:
- Configuration keys and default values
- Support environment variable overrides
- Document each option
- Use sensible defaults

### Accessing Configuration

In plugin code:
- Use `getConfig($key)` method
- Returns value from config.php
- Checks environment variables first
- Falls back to default

### User Configuration

If plugin needs user settings:
- Create settings page
- Store in plugin-specific table or core settings
- Provide admin UI to modify
- Validate on save

## Routes and Controllers

### Defining Routes

In plugin.json manifest:
- Specify path pattern
- Reference controller and action
- Define required permissions
- Support parameter placeholders

### Controller Implementation

Plugin controllers:
- Handle plugin-specific routes
- Extend base Controller
- Located in `controllers/` subdirectory
- Follow core controller patterns

**Controller responsibilities:**
- Validate input
- Check permissions
- Execute business logic
- Render views or return JSON

### View Rendering

Plugin views:
- Located in `views/` subdirectory
- PHP templates with data interpolation
- Include Bootstrap 5 classes
- Escape all output

## Security Best Practices

### Namespace Isolation

Prefix all plugin identifiers:
- Table names: `pluginid_tablename`
- Config keys: `pluginid.setting`
- CSS classes: `pluginid-classname`
- JavaScript: `PluginId` namespace

### Resource Cleanup

On uninstall:
- Drop all plugin tables
- Remove all assets
- Delete configuration entries
- Leave no traces

### Error Handling

Handle errors gracefully:
- Catch exceptions
- Log errors with context
- Don't break core functionality
- Return meaningful error messages

### Permission Checks

Before every action:
- Check user authentication
- Verify required permissions
- Check context (if scoped)
- Fail securely (deny by default)

## Testing Plugins

### Unit Tests

Test plugin logic:
- Test hook handlers
- Test data transformations
- Test configuration loading
- Mock dependencies

### Integration Tests

Test plugin integration:
- Test activation/deactivation
- Test route handling
- Test database operations
- Test with core system

### Manual Testing

Test plugin functionality:
- Install and activate
- Test all features
- Test with different user roles
- Test deactivation and uninstall
- Verify cleanup

## Plugin Development Workflow

1. **Plan Plugin**
   - Define purpose and features
   - Identify required hooks
   - Design data model (if needed)
   - Plan user interface

2. **Create Structure**
   - Create plugin directory
   - Create manifest file
   - Create main plugin class
   - Set up directory structure

3. **Implement Features**
   - Implement activation logic
   - Write hook handlers
   - Create controllers and views
   - Add assets

4. **Test Thoroughly**
   - Write automated tests
   - Manual testing across scenarios
   - Test edge cases
   - Test cleanup

5. **Document**
   - Write README with installation steps
   - Document configuration options
   - Provide usage examples
   - Note dependencies

6. **Package** (for distribution)
   - Include all necessary files
   - Compress as ZIP
   - Include version number
   - Provide installation instructions

## Common Pitfalls

### Don't Modify Core

- ❌ Never modify core database tables directly
- ❌ Never modify core code files
- ✅ Use hooks to extend behavior
- ✅ Use models to interact with data

### Don't Assume Environment

- ❌ Don't hardcode paths
- ❌ Don't assume permissions
- ✅ Use path helpers
- ✅ Check permissions before actions

### Don't Break on Deactivation

- ❌ Don't leave broken references
- ❌ Don't delete user data on deactivation
- ✅ Gracefully handle missing plugin
- ✅ Only delete data on uninstall

### Don't Conflict with Other Plugins

- ❌ Don't use generic names
- ❌ Don't pollute global namespace
- ✅ Prefix everything with plugin ID
- ✅ Check for conflicts on activation

## Example Plugin Projects

See [../concepts/PLUGIN.md](../concepts/PLUGIN.md) for conceptual examples:
- CSV Export Plugin
- Dark Theme Plugin  
- SIS Connector Plugin

These demonstrate different plugin types and patterns.
