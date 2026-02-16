<?php
/**
 * Example Page Using OOP Theme Pattern
 * 
 * This demonstrates the recommended approach for using themes.
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

// Session handling (if needed)
session_start();

// Create theme context with all rendering variables
$context = new ThemeContext([
    'layout' => 'admin',                    // Which layout to use (optional, uses theme default if omitted)
    'pageTitle' => 'Example Page',          // Page title for <title> and <h1>
    'currentPage' => 'admin_dashboard',     // For sidebar active state
    'customCss' => '<style>
        .example-box {
            background: #e3f2fd;
            padding: 1.5rem;
            border-radius: 4px;
            border-left: 4px solid #2196f3;
        }
    </style>',
    'customScripts' => '<script>
        console.log("Page loaded successfully");
        // Your custom JavaScript here
    </script>'
]);

// Get active theme instance
$theme = ThemeLoader::getActiveTheme();

// Show header
$theme->showHeader($context);
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="example-box">
            <h2>Example Content</h2>
            <p>This is an example page using the OOP theme pattern.</p>
            <p>The theme context encapsulates all rendering variables and passes them to both header and footer.</p>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Benefits</h3>
            </div>
            <div class="card-body">
                <ul>
                    <li>Type-safe context object</li>
                    <li>Explicit method calls</li>
                    <li>No global variables</li>
                    <li>Easy to test</li>
                    <li>Clean separation of concerns</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Context Variables</h3>
            </div>
            <div class="card-body">
                <dl>
                    <dt>layout</dt>
                    <dd>Which theme layout to use</dd>
                    
                    <dt>pageTitle</dt>
                    <dd>Page title and heading</dd>
                    
                    <dt>currentPage</dt>
                    <dd>Page identifier for active menu states</dd>
                    
                    <dt>customCss</dt>
                    <dd>Custom CSS injected in header</dd>
                    
                    <dt>customScripts</dt>
                    <dd>Custom JS injected in footer</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<?php
// Show footer
$theme->showFooter($context);
?>
