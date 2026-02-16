# Metis Dashboard Theme

Modern material design dashboard theme with beautiful gradients and clean layouts.

## Layouts

### Dashboard Layout
- Full sidebar with material design
- Gradient sidebar background
- Icon-based navigation
- User panel
- Perfect for administration

### Compact Layout
- Collapsed sidebar (70px wide)
- Icon-only navigation with tooltips
- Maximizes content space
- Great for data-heavy pages

### Simple Layout
- No sidebar
- Gradient top banner
- Clean and minimal
- Good for public pages or embedded content

## Usage

```php
<?php
$pageTitle = 'Dashboard';
$currentPage = 'admin_dashboard'; // for sidebar active state
$layout = 'dashboard'; // or 'compact', 'simple'
require_once __DIR__ . '/system/includes/theme_loader.php';
?>

<!-- Your page content here -->

<?php closeThemeLayout(); ?>
```

## Design Features

- Material Design principles
- Roboto font family
- Gradient backgrounds
- Card-based content
- Clean shadows and spacing
- Responsive layout

## Color Scheme

- Primary Dark: #0D47A1
- Accent Blue: #1976D2
- Brand Teal: #1565C0
- Gradient sidebar backgrounds
- Material design elevation

## Customization

Add custom styles or scripts:

```php
<?php
$customStyles = '<style>.my-card { background: #fff; }</style>';
$customScripts = '<script>console.log("Metis Theme");</script>';
?>
```

## Framework

- Bootstrap 5
- Bootstrap Icons
- Material Icons
- Google Fonts (Roboto)
- Custom CSS for material design
