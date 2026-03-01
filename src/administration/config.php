<?php
declare(strict_types=1);

/**
 * Configuration Editor
 * 
 * Edit system configuration settings.
 * Uses pragmatic page pattern (logic + template in one file).
 * 
 * @package Mosaic
 */

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

// Session configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? '1' : '0');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
    
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize common variables and database (config.php needs custom handling)
require_once __DIR__ . '/../system/Core/Config.php';
require_once __DIR__ . '/../system/Core/Path.php';

// Check if configured
$configPath = __DIR__ . '/../config/config.yaml';
if (!file_exists($configPath)) {
    \Mosaic\Core\Path::redirect('setup/');
}

// Load configuration
$config = \Mosaic\Core\Config::getInstance($configPath);
$configData = $config->all();

// Parse template for field metadata
$templatePath = __DIR__ . '/../config/config.yaml.template';
$fields = parseTemplate($templatePath);

// Define variables (config.php doesn't use database)
$baseUrl = \Mosaic\Core\Path::getBaseUrl();
$basePath = \Mosaic\Core\Path::getBasePath();
$siteName = $config->get('app.name', 'MOSAIC');
$debugMode = $config->get('app.debug_mode', false) === true || $config->get('app.debug_mode', 'false') === 'true';

// Define constants for templates
if (!defined('BASE_URL')) define('BASE_URL', $baseUrl);
if (!defined('BASE_PATH')) define('BASE_PATH', $basePath);
if (!defined('SITE_NAME')) define('SITE_NAME', $siteName);

/**
 * Parse template to extract field metadata
 */
function parseTemplate(string $path): array {
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    $fields = [];
    $currentSection = null;
    $currentField = null;
    $meta = [];
    
    foreach ($lines as $line) {
        // Metadata comment
        if (preg_match('/^\s*# @(\w+):\s*(.+)$/', $line, $m)) {
            $meta[$m[1]] = $m[2];
        }
        // Field definition
        elseif (preg_match('/^(\s*)(\w+):\s*(.*)$/', $line, $m) && !str_starts_with($line, '#')) {
            $indent = strlen($m[1]);
            $key = $m[2];
            
            if ($indent === 0) {
                $currentSection = $key;
            } elseif ($indent === 2 && $currentSection) {
                $fieldKey = $currentSection . '.' . $key;
                if (!empty($meta)) {
                    $fields[$fieldKey] = $meta;
                    $meta = [];
                }
            }
        }
    }
    
    return $fields;
}

/**
 * Render form field from metadata
 */
function renderField(string $key, array $meta, $value): string {
    $parts = explode('.', $key);
    $name = implode('_', $parts);
    $id = $name;
    $type = $meta['type'] ?? 'string';
    $label = $meta['label'] ?? ucfirst($parts[1] ?? $key);
    $required = ($meta['required'] ?? 'false') === 'true';
    $help = $meta['help'] ?? '';
    
    // Fields that cannot be changed after installation (would break existing data)
    $lockedFields = [
        'database.driver' => 'Database driver cannot be changed after installation. Changing this requires data migration.',
        'database.name' => 'Database name cannot be changed after installation. Changing this would lose access to your existing data.',
        'database.prefix' => 'Table prefix cannot be changed after installation. Changing this would lose access to your existing tables.'
    ];
    
    $html = '<div class="mb-3">';
    $html .= '<label for="' . htmlspecialchars($id) . '" class="form-label">';
    $html .= htmlspecialchars($label);
    if ($required) $html .= ' <span class="text-danger">*</span>';
    $html .= '</label>';
    
    // Check if this field is locked
    if (isset($lockedFields[$key])) {
        // Render as disabled field with hidden input to preserve value
        if ($type === 'select') {
            $html .= '<select class="form-select" disabled>';
            $options = explode(',', $meta['options'] ?? '');
            foreach ($options as $opt) {
                if (str_contains($opt, '|')) {
                    [$val, $lbl] = explode('|', $opt, 2);
                } else {
                    $val = $lbl = $opt;
                }
                $selected = ($value == $val) ? 'selected' : '';
                $html .= '<option value="' . htmlspecialchars($val) . '" ' . $selected . '>';
                $html .= htmlspecialchars($lbl) . '</option>';
            }
            $html .= '</select>';
        } else {
            $html .= '<input type="text" class="form-control" value="' . htmlspecialchars((string)$value) . '" disabled>';
        }
        
        // Hidden input to preserve value in POST
        $html .= '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars((string)$value) . '">';
        
        // Warning message
        $html .= '<small class="form-text text-warning">';
        $html .= '<i class="fas fa-lock mr-1"></i>' . htmlspecialchars($lockedFields[$key]);
        $html .= '</small>';
        $html .= '</div>';
        return $html;
    }
    
    switch ($type) {
        case 'password':
            $html .= '<input type="password" class="form-control" id="' . htmlspecialchars($id) . '" ';
            $html .= 'name="' . htmlspecialchars($name) . '" placeholder="Leave blank to keep current">';
            break;
            
        case 'boolean':
            $checked = ($value === true || $value === 'true') ? 'checked' : '';
            $html .= '<div class="form-check"><input type="checkbox" class="form-check-input" ';
            $html .= 'id="' . htmlspecialchars($id) . '" name="' . htmlspecialchars($name) . '" ' . $checked . '>';
            $html .= '<label class="form-check-label" for="' . htmlspecialchars($id) . '">' . htmlspecialchars($meta['description'] ?? '') . '</label></div>';
            break;
            
        case 'select':
            $html .= '<select class="form-select" id="' . htmlspecialchars($id) . '" name="' . htmlspecialchars($name) . '"';
            if ($required) $html .= ' required';
            $html .= '>';
            
            $options = explode(',', $meta['options'] ?? '');
            foreach ($options as $opt) {
                if (str_contains($opt, '|')) {
                    [$val, $lbl] = explode('|', $opt, 2);
                } else {
                    $val = $lbl = $opt;
                }
                $selected = ($value == $val) ? 'selected' : '';
                $html .= '<option value="' . htmlspecialchars($val) . '" ' . $selected . '>';
                $html .= htmlspecialchars($lbl) . '</option>';
            }
            $html .= '</select>';
            break;
            
        case 'integer':
            $html .= '<input type="number" class="form-control" id="' . htmlspecialchars($id) . '" ';
            $html .= 'name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars((string)$value) . '"';
            if ($required) $html .= ' required';
            if (isset($meta['validation']) && str_contains($meta['validation'], 'min=')) {
                preg_match('/min=(\d+)/', $meta['validation'], $m);
                $html .= ' min="' . $m[1] . '"';
            }
            if (isset($meta['validation']) && str_contains($meta['validation'], 'max=')) {
                preg_match('/max=(\d+)/', $meta['validation'], $m);
                $html .= ' max="' . $m[1] . '"';
            }
            $html .= '>';
            break;
            
        default: // string, email, timezone
            $inputType = ($type === 'email') ? 'email' : 'text';
            $html .= '<input type="' . $inputType . '" class="form-control" id="' . htmlspecialchars($id) . '" ';
            $html .= 'name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars((string)$value) . '"';
            if ($required) $html .= ' required';
            $html .= '>';
    }
    
    if ($help) {
        $html .= '<small class="form-text text-muted">' . htmlspecialchars($help) . '</small>';
    }
    
    $html .= '</div>';
    return $html;
}

// Handle POST requests
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
    
    try {
        // Fields that cannot be changed after installation
        $lockedFields = ['database.driver', 'database.name', 'database.prefix'];
        
        // Dynamically update all fields from template
        foreach ($fields as $key => $meta) {
            // Skip locked fields - they cannot be changed post-installation
            if (in_array($key, $lockedFields)) {
                continue;
            }
            
            $postKey = str_replace('.', '_', $key);
            $type = $meta['type'] ?? 'string';
            
            if ($type === 'password') {
                // Only update if provided
                if (!empty($_POST[$postKey])) {
                    $config->set($key, $_POST[$postKey]);
                }
            } elseif ($type === 'boolean') {
                $config->set($key, isset($_POST[$postKey]) ? 'true' : 'false');
            } elseif ($type === 'integer') {
                $config->set($key, (int)($_POST[$postKey] ?? $meta['default'] ?? 0));
            } else {
                $config->set($key, trim($_POST[$postKey] ?? $meta['default'] ?? ''));
            }
        }
        
        // Save configuration
        $config->save();
        
        $successMessage = 'Configuration updated successfully';
        
        // Reload config data
        $configData = $config->all();
    } catch (\Exception $e) {
        $errorMessage = 'Failed to save configuration: ' . htmlspecialchars($e->getMessage());
        if ($debugMode) {
            $errorMessage .= '<br><br><strong>Debug Information:</strong><br>';
            $errorMessage .= '<pre style="text-align: left; font-size: 12px;">';
            $errorMessage .= 'File: ' . htmlspecialchars($e->getFile()) . '<br>';
            $errorMessage .= 'Line: ' . htmlspecialchars((string)$e->getLine()) . '<br>';
            $errorMessage .= 'Trace:<br>' . htmlspecialchars($e->getTraceAsString());
            $errorMessage .= '</pre>';
        }
    }
}

// Group fields by section
$sections = [];
foreach ($fields as $key => $meta) {
    $section = explode('.', $key)[0];
    if (!isset($sections[$section])) {
        $sections[$section] = [];
    }
    $sections[$section][$key] = $meta;
}

// Section icons and titles
$sectionConfig = [
    'database' => ['icon' => 'fas fa-database', 'title' => 'Database Settings'],
    'app' => ['icon' => 'fas fa-laptop', 'title' => 'Application Settings'],
    'theme' => ['icon' => 'fas fa-palette', 'title' => 'Theme Settings'],
    'email' => ['icon' => 'fas fa-envelope', 'title' => 'Email Settings'],
];

// Load theme system (auto-loads ThemeContext and Theme)
require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => 'System Configuration',
    'currentPage' => 'admin_config',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'Configuration']
    ]
]);

$theme = ThemeLoader::getActiveTheme(null, 'admin');
$theme->showHeader($context);
?>

<?php if ($successMessage): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle"></i> <?= $errorMessage ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> Incorrect settings can break your installation. Make sure you have a backup of your config.yaml file.
</div>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    
    <?php foreach ($sections as $sectionName => $sectionFields): ?>
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">
                <i class="<?= $sectionConfig[$sectionName]['icon'] ?? 'fas fa-cog' ?>"></i>
                <?= $sectionConfig[$sectionName]['title'] ?? ucfirst($sectionName) ?>
            </h3>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($sectionFields as $key => $meta): ?>
                <div class="col-md-6">
                    <?php
                    $value = $config->get($key, $meta['default'] ?? '');
                    echo renderField($key, $meta, $value);
                    ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Submit Buttons -->
    <div class="card">
        <div class="card-body">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> Save Configuration
            </button>
            <a href="<?= BASE_URL ?>" class="btn btn-secondary btn-lg">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </div>
</form>

<?php $theme->showFooter($context); ?>
