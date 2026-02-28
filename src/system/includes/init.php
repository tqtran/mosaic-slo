<?php
declare(strict_types=1);

/**
 * Common Initialization
 * 
 * Sets up configuration, database connection, and common variables.
 * Include this at the top of every page that needs database access.
 * 
 * Available after include:
 *   $config      - Config instance (use $config->get('key'))
 *   $configData  - Full config array (use $configData['section']['key'])
 *   $db          - Database connection instance
 *   $logger      - Logger instance for error and security logging
 *   $baseUrl     - Application base URL
 *   $basePath    - Application base filesystem path
 *   $siteName    - Site name from config
 *   $dbPrefix    - Database table prefix
 *   $debugMode   - Debug mode boolean
 *   $appVersion  - Application version from VERSION file
 *   BASE_URL     - Constant alternative to $baseUrl
 *   BASE_PATH    - Constant alternative to $basePath
 *   SITE_NAME    - Constant alternative to $siteName
 *   APP_VERSION  - Constant alternative to $appVersion
 *   EMERGENCY_ADMIN_ENABLED  - Whether emergency admin is enabled
 *   EMERGENCY_ADMIN_USERNAME - Emergency admin username (break glass)
 *   EMERGENCY_ADMIN_PASSWORD - Emergency admin password (break glass)
 * 
 * Usage:
 *   require_once __DIR__ . '/../system/includes/init.php';
 */

// Security initialization
if (session_status() === PHP_SESSION_NONE) {
    // Only configure session if not already started (allows custom session config in LTI files)
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? '1' : '0');
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// Load core classes
require_once __DIR__ . '/../Core/Config.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/Path.php';
require_once __DIR__ . '/../Core/Logger.php';

// Check if configured
if (!file_exists(__DIR__ . '/../../config/config.yaml')) {
    \Mosaic\Core\Path::redirect('setup/');
}

// Load configuration
$config = \Mosaic\Core\Config::getInstance(__DIR__ . '/../../config/config.yaml');
$configData = $config->all();

// Common variables (no constants - easier to override if needed)
$baseUrl = \Mosaic\Core\Path::getBaseUrl();
$basePath = \Mosaic\Core\Path::getBasePath();
$siteName = $config->get('app.name', 'MOSAIC');
$dbPrefix = $config->get('database.prefix', '');
$debugMode = $config->get('app.debug_mode', false) === true || $config->get('app.debug_mode', 'false') === 'true';
$appVersion = trim(file_get_contents(__DIR__ . '/../../VERSION'));

// Configure error display based on debug mode
if ($debugMode) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

// Define constants for template convenience
if (!defined('BASE_URL')) define('BASE_URL', $baseUrl);
if (!defined('BASE_PATH')) define('BASE_PATH', $basePath);
if (!defined('SITE_NAME')) define('SITE_NAME', $siteName);
if (!defined('APP_VERSION')) define('APP_VERSION', $appVersion);
if (!defined('DEBUG_MODE')) define('DEBUG_MODE', $debugMode);

// Emergency admin (break glass account)
if (!defined('EMERGENCY_ADMIN_ENABLED')) {
    define('EMERGENCY_ADMIN_ENABLED', $config->get('emergency_admin.enabled', false) === true);
}
if (!defined('EMERGENCY_ADMIN_USERNAME')) {
    define('EMERGENCY_ADMIN_USERNAME', $config->get('emergency_admin.username', null));
}
if (!defined('EMERGENCY_ADMIN_PASSWORD')) {
    define('EMERGENCY_ADMIN_PASSWORD', $config->get('emergency_admin.password', null));
}

// Initialize database connection
$db = \Mosaic\Core\Database::getInstance($configData['database']);

// Initialize logger
$logger = \Mosaic\Core\Logger::getInstance($configData, $db->getConnection());

// Ensure critical variables are in GLOBALS for theme access
$GLOBALS['db'] = $db;
$GLOBALS['dbPrefix'] = $dbPrefix;
$GLOBALS['config'] = $config;

/**
 * Get the currently selected term ID with session persistence
 * 
 * Handles term selection across page navigation:
 * 1. If term_fk is in GET/POST, use it and update session
 * 2. Otherwise, use session value
 * 3. If no session value, return null (all terms)
 * 
 * @return int|null Selected term primary key or null for all terms
 */
function getSelectedTermFk(): ?int {
    // Check if term_fk is in request
    if (isset($_GET['term_fk']) && $_GET['term_fk'] !== '') {
        $termFk = (int)$_GET['term_fk'];
        $_SESSION['selected_term_fk'] = $termFk;
        return $termFk;
    }
    
    if (isset($_POST['term_fk']) && $_POST['term_fk'] !== '') {
        $termFk = (int)$_POST['term_fk'];
        $_SESSION['selected_term_fk'] = $termFk;
        return $termFk;
    }
    
    // Use session value if available
    if (isset($_SESSION['selected_term_fk']) && $_SESSION['selected_term_fk'] !== '') {
        return (int)$_SESSION['selected_term_fk'];
    }
    
    // No term selected - return null for "all terms"
    return null;
}

// TODO: Authentication and authorization checks will go here
