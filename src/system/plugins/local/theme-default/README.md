# Default Theme

Clean, minimal theme using Bootstrap 5 framework - the system default.

## Layouts

### Default Layout
- Basic container layout
- Minimal styling
- Used as fallback for other layouts
- Perfect for simple pages and forms

### Navbar Layout
- Top navigation bar
- Responsive menu
- User dropdown (when authenticated)
- Good for public-facing pages

### Fluid Layout
- Full-width container
- Gradient header
- Maximizes screen space
- Ideal for dashboards and data tables

## Usage

```php
<?php
$pageTitle = 'My Page';
$layout = 'navbar'; // or 'simple', 'fluid'
require_once __DIR__ . '/system/includes/theme_loader.php';
?>

<!-- Your page content here -->

<?php closeThemeLayout(); ?>
```

## Customization

Add custom styles or scripts:

```php
<?php
$customStyles = '<style>.my-class { color: red; }</style>';
$customScripts = '<script>console.log("Hello");</script>';
?>
```

## Features

- Responsive design
- Bootstrap Icons included
- Modern, clean aesthetic
- No jQuery dependency
- Lightweight and fast
