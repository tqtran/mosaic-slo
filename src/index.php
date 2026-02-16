<?php
declare(strict_types=1);

/**
 * MOSAIC Front Controller
 * 
 * Entry point for all application requests.
 * Routes requests to appropriate controllers.
 * 
 * @package Mosaic
 */

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Session configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? '1' : '0');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');

// Error reporting (adjust for production)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Ensure logs directory exists
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Define application constants
define('APP_ROOT', __DIR__);
define('CONFIG_PATH', APP_ROOT . '/config/config.yaml');

// Load path helper for URL routing
require_once APP_ROOT . '/Core/Path.php';

// Load configuration if it exists
if (file_exists(CONFIG_PATH)) {
    // Configuration exists - application is set up
    require_once APP_ROOT . '/Core/Config.php';
    
    try {
        $config = \Mosaic\Core\Config::getInstance(CONFIG_PATH);
        
        // Set base URL from config for better performance
        $baseUrl = $config->get('app.base_url', '/');
        \Mosaic\Core\Path::setConfiguredBaseUrl($baseUrl);
        
        // Load application configuration
        $siteName = $config->get('app.name', 'MOSAIC');
        $dbPrefix = $config->get('database.prefix', '');
        $emailMethod = $config->get('email.method', 'disabled');
        $emailFromEmail = $config->get('email.from_email', '');
        $emailFromName = $config->get('email.from_name', $siteName);
        
    } catch (Exception $e) {
        error_log('Configuration error: ' . $e->getMessage());
        http_response_code(500);
        require_once APP_ROOT . '/includes/message_page.php';
        render_message_page('error', 'Configuration Error', 
            'Unable to load application configuration. Please check your config.yaml file.',
            'fa-exclamation-triangle text-danger');
        exit(1);
    }
    
    // Define constants for easy access throughout the application
    define('BASE_URL', \Mosaic\Core\Path::getBaseUrl());
    define('BASE_PATH', \Mosaic\Core\Path::getBasePath());
    define('SITE_NAME', $siteName);
    define('DB_PREFIX', $dbPrefix);
    define('EMAIL_METHOD', $emailMethod);
    define('EMAIL_FROM_EMAIL', $emailFromEmail);
    define('EMAIL_FROM_NAME', $emailFromName);
    define('DEBUG_MODE', ($config->get('app.debug_mode', 'false') === 'true' || $config->get('app.debug_mode', false) === true));
    
    // Update error display based on debug mode
    if (DEBUG_MODE) {
        ini_set('display_errors', '1');
    }
    
    // TODO: Autoloader will go here
    // require_once APP_ROOT . '/Core/Autoloader.php';
    
    // TODO: Router will go here
    // require_once APP_ROOT . '/Core/Router.php';
    // $router = new \Mosaic\Core\Router();
    // $router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
    
    // Temporary: Show application is ready
    $pageTitle = SITE_NAME . ' Application';
    $bodyClass = 'bg-light';
    require_once APP_ROOT . '/includes/header.php';
    ?>
    
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-check-circle fa-4x text-success"></i>
                        </div>
                        <h1 class="h3 text-center mb-3"><?php echo htmlspecialchars(SITE_NAME); ?></h1>
                        <p class="text-center text-muted mb-4">Student Learning Outcomes Assessment Platform</p>
                        
                        <div class="card bg-light mb-3">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-tachometer-alt mr-2"></i>Main Application
                                </h5>
                                <div class="d-grid gap-2">
                                    <a href="<?= BASE_URL ?>admin_institution.php" class="btn btn-primary btn-lg">
                                        <i class="fas fa-chart-line"></i> Dashboard (Coming Soon)
                                    </a>
                                    <a href="<?= BASE_URL ?>lti_test.html" class="btn btn-info btn-lg">
                                        <i class="fas fa-microscope"></i> LTI Test Harness
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card bg-light mb-3">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-cogs mr-2"></i>Administration
                                </h5>
                                <div class="d-grid gap-2">
                                    <a href="<?= BASE_URL ?>admin_config.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-cog"></i> System Configuration
                                    </a>
                                    <a href="<?= BASE_URL ?>admin_institution.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-university"></i> Institution Management
                                    </a>
                                    <a href="<?= BASE_URL ?>admin_institutional_outcomes.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-graduation-cap"></i> Institutional Outcomes
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card bg-light mb-3">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-plug mr-2"></i>LTI Launch URL
                                </h5>
                                <p class="mb-2">
                                    <strong>Configure this URL in your LMS:</strong>
                                </p>
                                <div class="input-group mb-2">
                                    <?php
                                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                                    $ltiUrl = $protocol . $host . BASE_URL . 'lti_launch.php';
                                    ?>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($ltiUrl) ?>" readonly id="ltiUrl">
                                    <button class="btn btn-outline-primary" type="button" onclick="copyLtiUrl()">
                                        <i class="fas fa-copy"></i> Copy
                                    </button>
                                </div>
                                <p class="mb-0 small text-muted">
                                    <i class="fas fa-info-circle"></i> Add this as an external tool in Canvas, Blackboard, Moodle, etc.
                                    Use the LTI Test Harness above to simulate launches during development.
                                </p>
                            </div>
                        </div>
                        
                        <script>
                        function copyLtiUrl() {
                            const urlField = document.getElementById('ltiUrl');
                            urlField.select();
                            urlField.setSelectionRange(0, 99999);
                            document.execCommand('copy');
                            
                            const btn = event.target.closest('button');
                            const originalHtml = btn.innerHTML;
                            btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                            btn.classList.remove('btn-outline-primary');
                            btn.classList.add('btn-success');
                            
                            setTimeout(() => {
                                btn.innerHTML = originalHtml;
                                btn.classList.remove('btn-success');
                                btn.classList.add('btn-outline-primary');
                            }, 2000);
                        }
                        </script>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    require_once APP_ROOT . '/includes/footer.php';
    
} else {
    // No configuration - redirect to web-based setup
    if (file_exists(APP_ROOT . '/setup/index.php')) {
        // Use Path helper for proper base-aware redirection
        \Mosaic\Core\Path::redirect('setup/');
    } else {
        http_response_code(500);
        require_once APP_ROOT . '/includes/message_page.php';
        render_message_page('error', 'Installation Error',
            'Configuration file not found and setup script is missing.<br>Please reinstall or create <code>src/config/config.yaml</code> manually.',
            'fa-times-circle text-danger');
        exit(1);
    }
}
