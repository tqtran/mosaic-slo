# Theme Plugins

Theme plugins provide visual presentation layers for MOSAIC pages using a declarative, codeless approach.

## Naming Convention

**Format:** `theme-{name}`

**Examples:**
- `theme-bootstrap5` - Plain Bootstrap 5 theme
- `theme-adminlte` - AdminLTE 4 admin theme
- `theme-metis` - Metis material design theme

**Benefits:**
- Self-documenting (type visible in plugin ID)
- Easy filtering and discovery
- Prevents naming conflicts
- Consistent with other plugin types

## Plugin Structure

```
src/system/plugins/local/theme-{name}/
├── plugin.yaml              # Theme configuration
├── layouts/                 # Layout directories
│   ├── {layout-name}/
│   │   ├── header.php       # Layout header
│   │   └── footer.php       # Layout footer
│   └── {another-layout}/
│       ├── header.php
│       └── footer.php
├── assets/                  # Optional assets
│   ├── css/
│   └── js/
└── README.md               # Theme documentation
```

**Note:** Internal structure is flexible. Theme designers organize files however they prefer.

## Plugin Configuration (plugin.yaml)

**Required fields:**
```yaml
id: theme-name
name: Display Name
version: 1.0.0
type: theme
description: Brief description
author: Author Name

theme:
  default_layout: layout-name
  layouts:
    layout-name:
      name: Layout Display Name
      description: Layout description
      header: path/to/header.php
      footer: path/to/footer.php
```

**Example:**
```yaml
id: theme-adminlte
name: AdminLTE 4 Theme
version: 1.0.0
type: theme
description: Professional responsive admin theme
author: MOSAIC Project

theme:
  default_layout: admin
  layouts:
    admin:
      name: Admin Dashboard
      description: Full sidebar for administration pages
      header: layouts/admin/header.php
      footer: layouts/admin/footer.php
    simple:
      name: Simple Layout
      description: Basic layout with navbar only
      header: layouts/simple/header.php
      footer: layouts/simple/footer.php
```

## Declarative Approach

**No PHP handler code required.** Theme designers:
1. Create layout header/footer PHP files
2. Declare paths in plugin.yaml
3. Theme loader handles includes automatically

**Benefits:**
- Non-programmers can create themes (HTML/CSS skills only)
- No complex theme API to learn
- Declarative configuration
- Standardized structure

## Layout Files

### Header (header.php)

Responsibilities:
- `<!DOCTYPE html>` and opening `<html>` tag
- `<head>` section with meta tags, title, CSS
- Opening `<body>` tag with classes
- Navigation elements (navbar, sidebar)
- Opening content wrapper elements

**Variables available:**
- `$pageTitle` - Page title string
- `$currentPage` - Current page identifier (for active menu states)
- `$customStyles` - Optional custom CSS
- `$customScripts` - Optional custom JavaScript
- `$_SESSION` - Session data (user name, etc.)
- `BASE_URL` - Base URL constant
- `SITE_NAME` - Site name constant

### Footer (footer.php)

Responsibilities:
- Close all opened HTML elements
- Load JavaScript libraries
- Include custom scripts
- Closing `</body>` and `</html>` tags

## Usage in Pages

```php
<?php
// Load configuration
require_once __DIR__ . '/system/Core/Config.php';
$config = new \Mosaic\Core\Config();
$config->load();

// Session handling
session_start();

// Page variables
$pageTitle = 'My Page Title';
$currentPage = 'admin_dashboard'; // for sidebar active states
$layout = 'admin'; // optional, uses theme default if omitted

// Load theme
require_once __DIR__ . '/system/includes/theme_loader.php';
?>

<!-- Your page content here -->
<div class="row">
    <div class="col-12">
        <h2>Page Content</h2>
        <p>This content renders within the theme layout.</p>
    </div>
</div>

<?php closeThemeLayout(); ?>
```

## Theme Loader

**Location:** `src/system/includes/theme_loader.php`

**How it works:**
1. Determines active theme (hard-coded for now, later from database)
2. Loads theme's plugin.yaml
3. Validates requested layout exists
4. Includes appropriate header file
5. Stores footer path for closeThemeLayout()

**Fallback behavior:**
- If theme not found → use legacy includes (header.php/footer.php)
- If layout not found → use theme's default layout
- If header file missing → error message
- If footer file missing → basic HTML close tags

## Available Themes

### theme-bootstrap5

Clean, minimal theme using vanilla Bootstrap 5.

**Layouts:**
- **simple** - Basic container layout
- **navbar** - Layout with top navigation bar
- **fluid** - Full-width fluid container

**Features:**
- No jQuery dependency
- Lightweight and fast
- Responsive design
- Bootstrap Icons included

**Best for:**
- Simple pages
- Public-facing content
- Forms and basic interactions

### theme-adminlte

Professional admin theme based on AdminLTE 4.

**Layouts:**
- **admin** - Full sidebar navigation for administration
- **simple** - Top navbar only, no sidebar
- **embedded** - Minimal layout for LTI/iframe embedding

**Features:**
- AdminLTE 4 framework
- Bootstrap 5 foundation
- Font Awesome icons
- Responsive sidebar
- Mobile-friendly

**Best for:**
- Administration pages
- Dashboard interfaces
- Data management

### theme-metis

Modern material design dashboard theme.

**Layouts:**
- **dashboard** - Full sidebar with material design
- **compact** - Collapsed sidebar (70px wide, icons only)
- **simple** - No sidebar, gradient banner

**Features:**
- Material Design principles
- Roboto font family
- Gradient backgrounds
- Card-based layouts
- Clean shadows and spacing

**Best for:**
- Modern dashboards
- Data visualization
- Analytics pages

## Creating a New Theme

### Step 1: Create Plugin Directory

```bash
mkdir -p src/system/plugins/local/theme-myname
cd src/system/plugins/local/theme-myname
```

### Step 2: Create plugin.yaml

```yaml
id: theme-myname
name: My Custom Theme
version: 1.0.0
type: theme
description: Custom theme description
author: Your Name

theme:
  default_layout: default
  layouts:
    default:
      name: Default Layout
      description: Standard layout
      header: layouts/default/header.php
      footer: layouts/default/footer.php
```

### Step 3: Create Layout Files

```bash
mkdir -p layouts/default
touch layouts/default/header.php
touch layouts/default/footer.php
```

### Step 4: Implement Header

```php
<?php
// layouts/default/header.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'MOSAIC') ?></title>
    
    <!-- Your CSS here -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    
    <?php if (isset($customStyles)) echo $customStyles; ?>
</head>
<body>
<div class="container">
```

### Step 5: Implement Footer

```php
<?php
// layouts/default/footer.php
?>
</div><!-- /.container -->

<!-- Your JavaScript here -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<?php if (isset($customScripts)) echo $customScripts; ?>
</body>
</html>
```

### Step 6: Add README.md

Document your theme's layouts, features, and usage examples.

### Step 7: Test

Create a test page using your theme and verify all layouts work correctly.

## Customization Variables

### Setting Custom Styles

```php
<?php
$customStyles = <<<CSS
<style>
.my-custom-class {
    background: #f0f0f0;
    padding: 1rem;
}
</style>
CSS;
?>
```

### Setting Custom Scripts

```php
<?php
$customScripts = <<<JS
<script>
console.log('Custom script loaded');
</script>
JS;
?>
```

## Database Integration (Future)

When PluginManager is implemented:

**Active theme selection:**
```sql
SELECT plugin_id FROM plugins 
WHERE type = 'theme' AND is_active = 1 
LIMIT 1
```

**Theme settings storage:**
```json
{
  "default_layout": "admin",
  "custom_colors": {
    "primary": "#0D47A1",
    "accent": "#1976D2"
  }
}
```

**Activation workflow:**
1. Admin selects theme in UI
2. PluginManager deactivates current theme
3. PluginManager activates new theme
4. Settings stored in `plugins.settings` JSON column

## Best Practices

### Keep Layouts Self-Contained

Each layout should work independently:
- Include all necessary assets
- Don't depend on other layouts
- Provide complete HTML structure

### Use Semantic HTML

```php
<!-- Good -->
<nav class="main-nav">...</nav>
<main class="content">...</main>

<!-- Avoid -->
<div class="nav">...</div>
<div class="content">...</div>
```

### Provide Fallbacks

```php
<?= htmlspecialchars($pageTitle ?? 'MOSAIC') ?>
<?= htmlspecialchars($_SESSION['user_name'] ?? 'Guest') ?>
```

### Escape All Output

```php
<!-- Always escape user-generated content -->
<h1><?= htmlspecialchars($pageTitle) ?></h1>
<span><?= htmlspecialchars($_SESSION['user_name']) ?></span>
```

### Use Constants

```php
<!-- Use BASE_URL for links -->
<a href="<?= BASE_URL ?>administration/">Dashboard</a>

<!-- Use SITE_NAME for branding -->
<span><?= htmlspecialchars(SITE_NAME) ?></span>
```

### Support Mobile

All themes should be responsive:
- Mobile-first approach
- Test on various screen sizes
- Collapsible navigation
- Touch-friendly targets

## Testing Themes

### Manual Testing

1. Load theme_demo.php in browser
2. Test all layouts
3. Verify mobile responsiveness
4. Check different page types
5. Test with/without session data

### Test Checklist

- [ ] All layouts render correctly
- [ ] Navigation elements work
- [ ] Links use BASE_URL
- [ ] User name displays (if logged in)
- [ ] Custom styles/scripts work
- [ ] Mobile responsive
- [ ] No console errors
- [ ] Assets load correctly
- [ ] Fallbacks work

## Troubleshooting

### Theme Not Loading

**Check:**
1. Theme directory exists at `/src/system/plugins/local/theme-{name}/`
2. plugin.yaml exists and is valid YAML
3. Layout files exist at paths specified in YAML
4. File permissions allow reading

**Error log:**
```
error_log("Theme not found: {$activeTheme}");
```

### Layout Not Found

**Check:**
1. Layout name matches YAML exactly (case-sensitive)
2. Header/footer paths are correct (relative to plugin directory)
3. Files are named .php (not .html)

### Styles Not Applied

**Check:**
1. CSS links in header.php are correct
2. CDN URLs are accessible
3. Custom styles variable is set
4. Browser cache (hard refresh)

### Active State Not Working

**Check:**
1. `$currentPage` variable is set before theme_loader
2. Value matches menu item identifier exactly
3. Theme layout uses `$currentPage` variable

## Related Documentation

- [PLUGIN.md](../concepts/PLUGIN.md) - Plugin architecture concepts
- [PLUGIN_GUIDE.md](PLUGIN_GUIDE.md) - Plugin development guide
- [CODE_ORGANIZATION.md](../concepts/CODE_ORGANIZATION.md) - Code structure

## Demo

Access the theme demo page to preview all themes and layouts:

```
http://localhost:8000/src/theme_demo.php
```
