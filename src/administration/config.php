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

// Load core classes
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

// Define constants
define('BASE_URL', $configData['app']['base_url'] ?? '/');
define('SITE_NAME', $configData['app']['name'] ?? 'MOSAIC');
define('DEBUG_MODE', ($configData['app']['debug_mode'] ?? 'false') === 'true' || ($configData['app']['debug_mode'] ?? false) === true);

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
        // Update configuration values
        $config->set('app.name', trim($_POST['app_name'] ?? 'MOSAIC'));
        $config->set('app.timezone', trim($_POST['app_timezone'] ?? 'America/Los_Angeles'));
        $config->set('app.base_url', trim($_POST['app_base_url'] ?? '/'));
        $config->set('app.debug_mode', isset($_POST['app_debug_mode']) ? 'true' : 'false');
        
        // Database settings
        $config->set('database.host', trim($_POST['db_host'] ?? 'localhost'));
        $config->set('database.port', (int)($_POST['db_port'] ?? 3306));
        $config->set('database.name', trim($_POST['db_name'] ?? ''));
        $config->set('database.prefix', trim($_POST['db_prefix'] ?? ''));
        $config->set('database.username', trim($_POST['db_username'] ?? ''));
        
        // Only update password if provided
        if (!empty($_POST['db_password'])) {
            $config->set('database.password', $_POST['db_password']);
        }
        
        // Email settings
        $config->set('email.method', trim($_POST['email_method'] ?? 'disabled'));
        $config->set('email.from_email', trim($_POST['email_from_email'] ?? ''));
        $config->set('email.from_name', trim($_POST['email_from_name'] ?? ''));
        $config->set('email.smtp_host', trim($_POST['email_smtp_host'] ?? ''));
        $config->set('email.smtp_port', (int)($_POST['email_smtp_port'] ?? 587));
        $config->set('email.smtp_username', trim($_POST['email_smtp_username'] ?? ''));
        $config->set('email.smtp_encryption', trim($_POST['email_smtp_encryption'] ?? 'tls'));
        
        // Only update SMTP password if provided
        if (!empty($_POST['email_smtp_password'])) {
            $config->set('email.smtp_password', $_POST['email_smtp_password']);
        }
        
        // Save configuration
        $config->save();
        
        $successMessage = 'Configuration updated successfully';
        
        // Reload config data
        $configData = $config->all();
    } catch (\Exception $e) {
        $errorMessage = 'Failed to save configuration: ' . htmlspecialchars($e->getMessage());
        if (DEBUG_MODE) {
            $errorMessage .= '<br><br><strong>Debug Information:</strong><br>';
            $errorMessage .= '<pre style="text-align: left; font-size: 12px;">';
            $errorMessage .= 'File: ' . htmlspecialchars($e->getFile()) . '<br>';
            $errorMessage .= 'Line: ' . htmlspecialchars((string)$e->getLine()) . '<br>';
            $errorMessage .= 'Trace:<br>' . htmlspecialchars($e->getTraceAsString());
            $errorMessage .= '</pre>';
        }
    }
}

$currentPage = 'admin_config';
$pageTitle = 'Configuration - ' . SITE_NAME;
$bodyClass = 'hold-transition sidebar-mini layout-fixed';
require_once __DIR__ . '/../system/includes/header.php';
?>

<div class="wrapper">
    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="<?= BASE_URL ?>" class="nav-link"><i class="fas fa-home"></i> Home</a>
            </li>
        </ul>
        
        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <span class="nav-link">
                    <strong><?= htmlspecialchars(SITE_NAME) ?></strong>
                </span>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->

<?php require_once __DIR__ . '/../system/includes/sidebar.php'; ?>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><i class="fas fa-cog"></i> System Configuration</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Home</a></li>
                            <li class="breadcrumb-item active">Configuration</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
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
                    
                    <!-- Application Settings -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-laptop"></i> Application Settings</h3>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="appName" class="form-label">Site Name</label>
                                    <input type="text" class="form-control" id="appName" name="app_name" value="<?= htmlspecialchars($configData['app']['name'] ?? 'MOSAIC') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="appTimezone" class="form-label">Timezone</label>
                                    <input type="text" class="form-control" id="appTimezone" name="app_timezone" value="<?= htmlspecialchars($configData['app']['timezone'] ?? 'America/Los_Angeles') ?>" required>
                                    <small class="form-text text-muted">PHP timezone identifier (e.g., America/Los_Angeles)</small>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="appBaseUrl" class="form-label">Base URL</label>
                                    <input type="text" class="form-control" id="appBaseUrl" name="app_base_url" value="<?= htmlspecialchars($configData['app']['base_url'] ?? '/') ?>" required>
                                    <small class="form-text text-muted">Root path where MOSAIC is installed (e.g., / or /mosaic/)</small>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check" style="margin-top: 32px;">
                                        <input type="checkbox" class="form-check-input" id="appDebugMode" name="app_debug_mode" <?= (($configData['app']['debug_mode'] ?? 'false') === 'true' || ($configData['app']['debug_mode'] ?? false) === true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="appDebugMode">
                                            <strong>Debug Mode</strong>
                                            <br><small class="text-muted">Show detailed error messages (disable in production)</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Database Settings -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-database"></i> Database Settings</h3>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label for="dbHost" class="form-label">Host</label>
                                    <input type="text" class="form-control" id="dbHost" name="db_host" value="<?= htmlspecialchars($configData['database']['host'] ?? 'localhost') ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="dbPort" class="form-label">Port</label>
                                    <input type="number" class="form-control" id="dbPort" name="db_port" value="<?= htmlspecialchars($configData['database']['port'] ?? '3306') ?>" required min="1" max="65535">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="dbName" class="form-label">Database Name</label>
                                    <input type="text" class="form-control" id="dbName" name="db_name" value="<?= htmlspecialchars($configData['database']['name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="dbPrefix" class="form-label">Table Prefix</label>
                                    <input type="text" class="form-control" id="dbPrefix" name="db_prefix" value="<?= htmlspecialchars($configData['database']['prefix'] ?? '') ?>">
                                    <small class="form-text text-muted">Optional prefix for all tables</small>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="dbUsername" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="dbUsername" name="db_username" value="<?= htmlspecialchars($configData['database']['username'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="dbPassword" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="dbPassword" name="db_password" placeholder="Leave blank to keep current password">
                                    <small class="form-text text-muted">Only enter to change password</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Settings -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-envelope"></i> Email Settings</h3>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="emailMethod" class="form-label">Mail Method</label>
                                    <select class="form-select" id="emailMethod" name="email_method">
                                        <option value="disabled" <?= ($configData['email']['method'] ?? 'disabled') === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                                        <option value="smtp" <?= ($configData['email']['method'] ?? '') === 'smtp' ? 'selected' : '' ?>>SMTP</option>
                                        <option value="sendmail" <?= ($configData['email']['method'] ?? '') === 'sendmail' ? 'selected' : '' ?>>Sendmail</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="emailFromEmail" class="form-label">From Email</label>
                                    <input type="email" class="form-control" id="emailFromEmail" name="email_from_email" value="<?= htmlspecialchars($configData['email']['from_email'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="emailFromName" class="form-label">From Name</label>
                                    <input type="text" class="form-control" id="emailFromName" name="email_from_name" value="<?= htmlspecialchars($configData['email']['from_name'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div id="smtpSettings" style="<?= ($configData['email']['method'] ?? 'disabled') !== 'smtp' ? 'display: none;' : '' ?>">
                                <hr>
                                <h5>SMTP Configuration</h5>
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <label for="smtpHost" class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control" id="smtpHost" name="email_smtp_host" value="<?= htmlspecialchars($configData['email']['smtp_host'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="smtpPort" class="form-label">SMTP Port</label>
                                        <input type="number" class="form-control" id="smtpPort" name="email_smtp_port" value="<?= htmlspecialchars($configData['email']['smtp_port'] ?? '587') ?>" min="1" max="65535">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="smtpUsername" class="form-label">SMTP Username</label>
                                        <input type="text" class="form-control" id="smtpUsername" name="email_smtp_username" value="<?= htmlspecialchars($configData['email']['smtp_username'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="smtpPassword" class="form-label">SMTP Password</label>
                                        <input type="password" class="form-control" id="smtpPassword" name="email_smtp_password" placeholder="Leave blank to keep current password">
                                        <small class="form-text text-muted">Only enter to change password</small>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="smtpEncryption" class="form-label">Encryption</label>
                                        <select class="form-select" id="smtpEncryption" name="email_smtp_encryption">
                                            <option value="none" <?= ($configData['email']['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                                            <option value="tls" <?= ($configData['email']['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                            <option value="ssl" <?= ($configData['email']['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="card">
                        <div class="card-body">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Save Configuration
                            </button>
                            <a href="<?= BASE_URL ?>" class="btn btn-secondary btn-lg">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <div class="float-end">
                                <small class="text-muted">Config file: <?= htmlspecialchars($configPath) ?></small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#emailMethod').on('change', function() {
        if ($(this).val() === 'smtp') {
            $('#smtpSettings').slideDown();
        } else {
            $('#smtpSettings').slideUp();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../system/includes/footer.php'; ?>
