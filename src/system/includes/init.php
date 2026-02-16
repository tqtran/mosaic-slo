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
 *   BASE_URL     - Constant for templates (deprecated, use $baseUrl)
 *   BASE_PATH    - Constant for templates (deprecated, use $basePath)
 *   SITE_NAME    - Constant for templates (deprecated, use $siteName)
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

// Define constants for templates (for backward compatibility)
if (!defined('BASE_URL')) define('BASE_URL', $baseUrl);
if (!defined('BASE_PATH')) define('BASE_PATH', $basePath);
if (!defined('SITE_NAME')) define('SITE_NAME', $siteName);

// Initialize database connection
$db = \Mosaic\Core\Database::getInstance($configData['database']);

// Initialize logger
$logger = \Mosaic\Core\Logger::getInstance($configData, $db->getConnection());

// TODO: Authentication and authorization checks will go here
