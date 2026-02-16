<?php
/**
 * Theme Demo Page
 * Demonstrates how to use theme plugins with OOP approach
 * 
 * Access: /src/theme_demo.php
 */

// Load configuration
require_once __DIR__ . '/system/Core/Config.php';
$config = new \Mosaic\Core\Config();
$config->load();

// Load theme classes
require_once __DIR__ . '/system/Core/ThemeContext.php';
require_once __DIR__ . '/system/Core/Theme.php';
require_once __DIR__ . '/system/Core/ThemeLoader.php';

use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

// Session security configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? '1' : '0');
ini_set('session.cookie_samesite', 'Strict');
session_start();

// Mock session data for demo
if (!isset($_SESSION['user_name'])) {
    $_SESSION['user_name'] = 'Demo User';
    $_SESSION['user_id'] = 1;
}

// Handle theme/layout selection
$selectedTheme = $_GET['theme'] ?? 'theme-adminlte';
$selectedLayout = $_GET['layout'] ?? null;

// Create theme context
$context = new ThemeContext([
    'layout' => $selectedLayout,
    'pageTitle' => 'Theme Demo',
    'currentPage' => 'admin_dashboard',
    'customCss' => '<style>.demo-highlight { background: #fff3cd; padding: 1rem; border-radius: 4px; }</style>'
]);

// Get theme instance (override for demo)
$theme = ThemeLoader::getActiveTheme($selectedTheme);

// Show header
$theme->showHeader($context);
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Theme Selector</h3>
            </div>
            <div class="card-body">
                <p>Select a theme and layout to preview:</p>
                
                <h5>Available Themes</h5>
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card <?= $selectedTheme === 'theme-bootstrap5' ? 'border-primary' : '' ?>">
                            <div class="card-body">
                                <h5 class="card-title">Bootstrap 5 Theme</h5>
                                <p class="card-text">Clean, minimal theme using vanilla Bootstrap 5</p>
                                <a href="?theme=theme-bootstrap5&layout=simple" class="btn btn-sm btn-outline-primary">Simple</a>
                                <a href="?theme=theme-bootstrap5&layout=navbar" class="btn btn-sm btn-outline-primary">Navbar</a>
                                <a href="?theme=theme-bootstrap5&layout=fluid" class="btn btn-sm btn-outline-primary">Fluid</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card <?= $selectedTheme === 'theme-adminlte' ? 'border-primary' : '' ?>">
                            <div class="card-body">
                                <h5 class="card-title">AdminLTE 4 Theme</h5>
                                <p class="card-text">Professional admin theme with sidebar</p>
                                <a href="?theme=theme-adminlte&layout=admin" class="btn btn-sm btn-outline-primary">Admin</a>
                                <a href="?theme=theme-adminlte&layout=simple" class="btn btn-sm btn-outline-primary">Simple</a>
                                <a href="?theme=theme-adminlte&layout=embedded" class="btn btn-sm btn-outline-primary">Embedded</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card <?= $selectedTheme === 'theme-metis' ? 'border-primary' : '' ?>">
                            <div class="card-body">
                                <h5 class="card-title">Metis Dashboard Theme</h5>
                                <p class="card-text">Modern material design dashboard</p>
                                <a href="?theme=theme-metis&layout=dashboard" class="btn btn-sm btn-outline-primary">Dashboard</a>
                                <a href="?theme=theme-metis&layout=compact" class="btn btn-sm btn-outline-primary">Compact</a>
                                <a href="?theme=theme-metis&layout=simple" class="btn btn-sm btn-outline-primary">Simple</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <strong>Current Selection:</strong> 
                    Theme: <code><?= htmlspecialchars($selectedTheme) ?></code> | 
                    Layout: <code><?= htmlspecialchars($selectedLayout ?? $theme->getDefaultLayout()) ?></code>
                </div>
                
                <div class="demo-highlight">
                    <strong>OOP Pattern:</strong> This page uses the new ThemeContext and ThemeLoader classes.
                    Custom CSS has been injected via context to create this yellow highlight box.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Sample Card 1</h3>
            </div>
            <div class="card-body">
                <p>This is sample content to demonstrate the theme layout.</p>
                <ul>
                    <li>Responsive design</li>
                    <li>Multiple layouts</li>
                    <li>Easy to use</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Sample Card 2</h3>
            </div>
            <div class="card-body">
                <p>Theme plugins allow complete customization without modifying core code.</p>
                <p>Each theme can have multiple layouts for different page types.</p>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Implementation Notes</h3>
            </div>
            <div class="card-body">
                <h5>Naming Convention</h5>
                <p>Theme plugins follow the pattern: <code>theme-{name}</code></p>
                
                <h5>Plugin Structure</h5>
                <pre>src/system/plugins/local/theme-{name}/
├── plugin.yaml
├── layouts/
│   ├── {layout1}/
│   │   ├── header.php
│   │   └── footer.php
│   └── {layout2}/ (NEW OOP Pattern)</h5>
                <pre>&lt;?php
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => 'My Page',
    'currentPage' => 'admin_dashboard',
    'customCss' => '&lt;style&gt;...&lt;/style&gt;',
    'customScripts' => '&lt;script&gt;...&lt;/script&gt;'
]);

$theme = ThemeLoader::getActiveTheme();
$theme-&gt;showHeader($context);
?&gt;

// Show footer
$theme->showFooter($context);

&lt;!-- Your page content --&gt;

&lt;?php $theme-&gt;showFooter($context); ?&gt;</pre>
                
                <h5>Legacy Pattern (Deprecated)</h5>
                <pre>&lt;?php
$pageTitle = 'My Page';
$currentPage = 'admin_dashboard';
$layout = 'admin';
                <h5>Usage in Pages</h5>
                <pre>&lt;?php
$pageTitle = 'My Page';
$currentPage = 'admin_dashboard'; // for sidebar active state
$layout = 'admin'; // optional
require_once __DIR__ . '/system/includes/theme_loader.php';
?&gt;

&lt;!-- Your page content --&gt;

&lt;?php closeThemeLayout(); ?&gt;</pre>
            </div>
        </div>
    </div>
</div>

<?php closeThemeLayout(); ?>
