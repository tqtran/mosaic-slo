<?php
declare(strict_types=1);

/**
 * MOSAIC Web Setup Interface
 * 
 * Browser-based installation wizard for configuring MOSAIC.
 * Creates database and saves configuration to config.yaml.
 * 
 * @package Mosaic\Setup
 */

// Load path helper for proper redirects
require_once __DIR__ . '/../system/Core/Path.php';

// Define base URL for easy access in this script
define('BASE_URL', \Mosaic\Core\Path::getBaseUrl());

// Prevent setup from running if already configured
$configFile = __DIR__ . '/../config/config.yaml';
if (file_exists($configFile)) {
    \Mosaic\Core\Path::redirect('administration/');
}

// Initialize session for form data persistence
session_start();

// Process form submission
$error = null;
$success = false;
$step = $_GET['step'] ?? 'form';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_submit'])) {
    // Validate and sanitize input
    $site_name = trim($_POST['site_name'] ?? 'MOSAIC');
    $base_url = trim($_POST['base_url'] ?? BASE_URL);
    $db_driver = trim($_POST['db_driver'] ?? 'mysql');
    $db_host = trim($_POST['db_host'] ?? '');
    $db_port = filter_var($_POST['db_port'] ?? 3306, FILTER_VALIDATE_INT);
    $db_name = trim($_POST['db_name'] ?? '');
    $db_prefix = trim($_POST['db_prefix'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = $_POST['db_pass'] ?? ''; // Don't trim password
    
    // Email Configuration (optional)
    $mail_method = trim($_POST['mail_method'] ?? 'disabled');
    $smtp_host = trim($_POST['smtp_host'] ?? '');
    $smtp_port = filter_var($_POST['smtp_port'] ?? 587, FILTER_VALIDATE_INT);
    $smtp_user = trim($_POST['smtp_user'] ?? '');
    $smtp_pass = $_POST['smtp_pass'] ?? ''; // Don't trim password
    $smtp_from_email = trim($_POST['smtp_from_email'] ?? '');
    $smtp_from_name = trim($_POST['smtp_from_name'] ?? $site_name);
    $smtp_encryption = trim($_POST['smtp_encryption'] ?? 'tls');
    
    // Validation
    if (empty($db_host) || empty($db_name) || empty($db_user)) {
        $error = 'Database host, name, and username are required.';
    } elseif (!in_array($db_driver, ['mysql', 'mssql'])) {
        $error = 'Invalid database driver. Must be mysql or mssql.';
    } elseif (!empty($db_prefix) && !preg_match('/^[a-zA-Z0-9_]+$/', $db_prefix)) {
        $error = 'Table prefix can only contain letters, numbers, and underscores.';
    } elseif (empty($base_url) || !str_starts_with($base_url, '/')) {
        $error = 'Base URL is required and must start with /';
    } elseif ($db_port === false || $db_port < 1 || $db_port > 65535) {
        $error = 'Invalid port number. Must be between 1 and 65535.';
    } else {
        // Attempt connection with PDO (supports both MySQL and MSSQL)
        try {
            // Build DSN based on driver
            if ($db_driver === 'mssql' || $db_driver === 'sqlsrv') {
                $dsn = sprintf('sqlsrv:Server=%s,%d', $db_host, $db_port);
            } else {
                $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db_host, $db_port);
            }
            
            $pdo = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
        } catch (PDOException $e) {
            $error = 'Connection failed: ' . $e->getMessage();
            $pdo = null;
        }
        
        if ($pdo !== null) {
            // Connection successful - try to create database (may fail on shared hosting)
            $createSuccess = false;
            try {
                if ($db_driver === 'mssql' || $db_driver === 'sqlsrv') {
                    // MSSQL database creation
                    $pdo->exec("CREATE DATABASE [$db_name]");
                    $createSuccess = true;
                } else {
                    // MySQL database creation
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $createSuccess = true;
                }
            } catch (PDOException $e) {
                // User doesn't have CREATE DATABASE privilege - that's okay on shared hosting
                // We'll try to connect to existing database below
                $createSuccess = false;
            }
            
            // Reconnect with database selected
            try {
                if ($db_driver === 'mssql' || $db_driver === 'sqlsrv') {
                    $dsn = sprintf('sqlsrv:Server=%s,%d;Database=%s', $db_host, $db_port, $db_name);
                } else {
                    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db_host, $db_port, $db_name);
                }
                
                $pdo = new PDO($dsn, $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                
                // Database connected successfully - proceed with schema installation
                // Use appropriate schema file based on driver
                $schemaFile = ($db_driver === 'mssql' || $db_driver === 'sqlsrv') 
                    ? __DIR__ . '/../system/database/schema_mssql.sql'
                    : __DIR__ . '/../system/database/schema.sql';
                
                if (!file_exists($schemaFile)) {
                    $error = 'Schema file not found at: ' . $schemaFile;
                } else {
                    $schema = file_get_contents($schemaFile);
                    
                    // Drop existing tables if they exist (for clean reinstall)
                    $tables = [
                        'lti_nonces', 'security_log', 'error_log', 'audit_log',
                        'assessments', 'enrollment', 'students', 'terms',
                        'student_learning_outcomes', 'slo_sets',
                        'program_outcomes', 'programs',
                        'institutional_outcomes', 'institution',
                        'user_roles', 'roles', 'users'
                    ];
                        
                    try {
                        if ($db_driver === 'mysql') {
                            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                        }
                        foreach ($tables as $table) {
                            $tableName = $db_prefix . $table;
                            if ($db_driver === 'mssql' || $db_driver === 'sqlsrv') {
                                @$pdo->exec("DROP TABLE IF EXISTS [$tableName]");
                            } else {
                                @$pdo->exec("DROP TABLE IF EXISTS `$tableName`");
                            }
                        }
                        if ($db_driver === 'mysql') {
                            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                        }
                    } catch (PDOException $e) {
                        // Ignore errors from dropping non-existent tables
                    }
                        
                    // Apply table prefix if configured
                    if (!empty($db_prefix)) {
                        // Replace table names with prefixed versions
                        foreach ($tables as $table) {
                            $schema = preg_replace(
                                '/\b' . preg_quote($table, '/') . '\b/',
                                $db_prefix . $table,
                                $schema
                            );
                        }
                    }
                    
                    // Execute schema - split into individual statements for sequential execution
                    try {
                            // Split schema by semicolons (simple approach for our controlled schema files)
                            $statements = array_filter(
                                array_map('trim', explode(';', $schema)),
                                fn($stmt) => !empty($stmt) && !preg_match('/^\s*(--|#)/', $stmt)
                            );
                            
                            foreach ($statements as $statement) {
                                if (!empty(trim($statement))) {
                                    $pdo->exec($statement);
                                }
                            }
                            
                            // Schema executed successfully
                                    // Save configuration
                                    $configDir = __DIR__ . '/../config';
                                    if (!is_dir($configDir)) {
                                        mkdir($configDir, 0755, true);
                                    }
                                    
                                    $configContent = "# MOSAIC Configuration\n";
                                    $configContent .= "# Generated: " . date('Y-m-d H:i:s') . "\n\n";
                                    $configContent .= "database:\n";
                                    $configContent .= "  driver: " . $db_driver . "\n";
                                    $configContent .= "  host: " . $db_host . "\n";
                                    $configContent .= "  port: " . $db_port . "\n";
                                    $configContent .= "  name: " . $db_name . "\n";
                                    $configContent .= "  prefix: " . $db_prefix . "\n";
                                    $configContent .= "  username: " . $db_user . "\n";
                                    $configContent .= "  password: " . $db_pass . "\n";
                                    $configContent .= "  charset: utf8mb4\n\n";
                                    $configContent .= "app:\n";
                                    $configContent .= "  name: " . $site_name . "\n";
                                    $configContent .= "  timezone: America/Los_Angeles\n";
                                    $configContent .= "  base_url: " . $base_url . "\n";
                                    $configContent .= "  debug_mode: true\n\n";
                                    $configContent .= "# Theme Configuration\n";
                                    $configContent .= "# Available themes: theme-default, theme-adminlte, theme-metis\n";
                                    $configContent .= "theme:\n";
                                    $configContent .= "  active_theme: theme-default\n\n";
                                    $configContent .= "# Email configuration for notifications\n";
                                    $configContent .= "email:\n";
                                    $configContent .= "  method: " . $mail_method . "\n";
                                    $configContent .= "  from_email: " . $smtp_from_email . "\n";
                                    $configContent .= "  from_name: " . $smtp_from_name . "\n";
                                    $configContent .= "  smtp_host: " . $smtp_host . "\n";
                                    $configContent .= "  smtp_port: " . ($smtp_port ?: 587) . "\n";
                                    $configContent .= "  smtp_username: " . $smtp_user . "\n";
                                    $configContent .= "  smtp_password: " . $smtp_pass . "\n";
                                    $configContent .= "  smtp_encryption: " . $smtp_encryption . "\n";
                                    
                                    if (file_put_contents($configFile, $configContent)) {
                                        // Create .htaccess to protect config directory
                                        $htaccessFile = $configDir . '/.htaccess';
                                        file_put_contents($htaccessFile, "Deny from all\n");
                                        
                                        // Create index.php fallback
                                        $indexFile = $configDir . '/index.php';
                                        file_put_contents($indexFile, "<?php\nhttp_response_code(403);\nexit('Forbidden');\n");
                                        
                                        $success = true;
                                        $step = 'complete';
                                    } else {
                                        $error = 'Failed to write configuration file. Check directory permissions.';
                                    }
                                
                        } catch (PDOException $e) {
                            $error = 'Schema execution failed: ' . $e->getMessage();
                        }
                } // End else (schema file exists / complete installation)
            } catch (PDOException $e) {
                // Database doesn't exist and we couldn't create or access it
                $error = 'Cannot access database "' . htmlspecialchars($db_name) . '". ';
                $error .= 'Error: ' . htmlspecialchars($e->getMessage()) . '. ';
                if (!isset($createSuccess) || !$createSuccess) {
                    $error .= 'You may need to create this database first through your hosting control panel (cPanel, Plesk, etc.) and ensure your user has access to it.';
                }
            }
            
            // PDO connection will be closed automatically when $pdo goes out of scope
        }
    }
}

// Setup page variables
$pageTitle = 'Setup';
$bodyClass = '';

// Define inline CSS for setup page
ob_start();
?>
<style>
    body {
        background: linear-gradient(135deg, var(--brand-teal) 0%, var(--primary-dark) 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        font-family: 'Source Sans Pro', sans-serif;
    }
    
    .setup-container {
        background: white;
        border-radius: 8px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        max-width: 600px;
        width: 100%;
        overflow: hidden;
    }
    
    .setup-header {
        background: var(--brand-teal);
        color: white;
        padding: 30px;
        text-align: center;
    }
    
    .setup-header h1 {
        font-size: 28px;
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    .setup-header p {
        font-size: 14px;
        opacity: 0.9;
        margin-bottom: 0;
    }
    
    .setup-body {
        padding: 40px;
    }
    
    .btn-primary {
        background: var(--brand-teal);
        border-color: var(--brand-teal);
    }
    
    .btn-primary:hover {
        background: var(--primary-dark);
        border-color: var(--primary-dark);
    }
    
    .success-icon {
        text-align: center;
        font-size: 64px;
        color: #28a745;
        margin-bottom: 20px;
    }
    
    .requirements {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 4px;
        margin-bottom: 25px;
    }
    
    .requirements ul {
        list-style: none;
        padding-left: 0;
        margin-bottom: 0;
    }
    
    .requirements li {
        padding: 5px 0 5px 24px;
        position: relative;
    }
    
    .requirements li:before {
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute;
        left: 0;
    }
    
    .requirements li.pass:before {
        content: "\f00c";
        color: #28a745;
    }
    
    .requirements li.warning:before {
        content: "\f06a";
        color: #ffc107;
    }
    
    .requirements li.fail:before {
        content: "\f00d";
        color: #dc3545;
    }
    
    .form-section-title {
        font-size: 16px;
        font-weight: 600;
        color: #495057;
        margin-top: 25px;
        margin-bottom: 15px;
        padding-bottom: 8px;
        border-bottom: 2px solid #e9ecef;
    }
</style>
<?php
$customStyles = ob_get_clean();

// Load theme system (auto-loads ThemeContext and Theme)
require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$context = new ThemeContext([
    'layout' => 'default',
    'pageTitle' => 'MOSAIC Setup',
    'customCss' => $customStyles
]);

$theme = ThemeLoader::getActiveTheme();
$theme->showHeader($context);
?>

<div class="setup-container">
    <div class="setup-header">
        <h1>Welcome to MOSAIC!</h1>
        <p>Let's get your student learning outcomes system up and running</p>
    </div>
    
    <div class="setup-body">
        <?php if ($step === 'complete'): ?>
                <!-- Success Page -->
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="text-center">
                    <h2 class="mb-3">You're All Set!</h2>
                    <p class="text-muted mb-2">Your installation was successful.</p>
                    <p class="text-muted mb-4">The database is ready and you can start using your new system.</p>
                    <a href="<?php echo htmlspecialchars(BASE_URL); ?>administration/" class="btn btn-primary btn-lg btn-block">
                        <i class="fas fa-arrow-right mr-2"></i>Get Started
                    </a>
                </div>
                
            <?php else: ?>
                <!-- Preflight Checks -->
                <?php
                // Perform real preflight checks
                $php_version = PHP_VERSION;
                $php_ok = version_compare($php_version, '8.1.0', '>=');
                $pdo_ok = extension_loaded('pdo');
                $pdo_mysql_ok = extension_loaded('pdo_mysql');
                $pdo_sqlsrv_ok = extension_loaded('pdo_sqlsrv');
                $db_driver_ok = $pdo_mysql_ok || $pdo_sqlsrv_ok;
                $all_checks_pass = $php_ok && $pdo_ok && $db_driver_ok;
                ?>
                
                <div class="requirements">
                    <h5 class="mb-3"><i class="fas fa-clipboard-check mr-2"></i>System Requirements</h5>
                    <ul>
                        <li class="<?php echo $php_ok ? 'pass' : 'fail'; ?>">
                            PHP 8.1 or higher <span class="text-muted">(found <?php echo $php_version; ?>)</span>
                        </li>
                        <li class="<?php echo $pdo_ok ? 'pass' : 'fail'; ?>">
                            PDO extension enabled
                        </li>
                        <li class="<?php echo $pdo_mysql_ok ? 'pass' : 'warning'; ?>">
                            PDO MySQL driver (pdo_mysql) <?php echo $pdo_mysql_ok ? '<span class="text-success">Available</span>' : '<span class="text-muted">Not installed</span>'; ?>
                        </li>
                        <li class="<?php echo $pdo_sqlsrv_ok ? 'pass' : 'warning'; ?>">
                            PDO MS SQL Server driver (pdo_sqlsrv) <?php echo $pdo_sqlsrv_ok ? '<span class="text-success">Available</span>' : '<span class="text-muted">Not installed</span>'; ?>
                        </li>
                        <li class="<?php echo $db_driver_ok ? 'pass' : 'fail'; ?>">
                            At least one database driver required
                        </li>
                    </ul>
                </div>
                
                <?php if (!$all_checks_pass): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>Requirements Not Met:</strong> Please fix the issues above before continuing.
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo htmlspecialchars(BASE_URL . 'setup/'); ?>">
                    <!-- Site Configuration -->
                    <div class="form-group">
                        <label for="site_name"><i class="fas fa-graduation-cap mr-2"></i>What would you like to call your site?</label>
                        <input type="text" class="form-control" id="site_name" name="site_name" 
                               value="<?php echo htmlspecialchars($_POST['site_name'] ?? 'MOSAIC'); ?>" 
                               placeholder="e.g., Springfield University Assessment" 
                               required>
                        <small class="form-text text-muted">This name will appear throughout your installation</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="base_url"><i class="fas fa-link mr-2"></i>Installation Path</label>
                        <input type="text" class="form-control" id="base_url" name="base_url" 
                               value="<?php echo htmlspecialchars($_POST['base_url'] ?? BASE_URL); ?>" 
                               required>
                        <small class="form-text text-muted">We've detected your installation path. Most users can leave this as-is.</small>
                    </div>
                    
                    <!-- Database Configuration -->
                    <h6 class="form-section-title"><i class="fas fa-database mr-2"></i>Database Connection</h6>
                    <p class="text-muted mb-3" style="font-size: 14px;">
                        We support MySQL (for development or smaller deployments) and MS SQL Server (for enterprise production). If you're not sure about these settings, contact your hosting provider or system administrator.
                        <br><strong>Shared Hosting Note:</strong> If using cPanel, Plesk, or similar, create your database first through their interface and use those credentials here.
                    </p>
                    
                    <div class="form-group">
                        <label for="db_driver">Database Type</label>
                        <select class="form-control" id="db_driver" name="db_driver" onchange="updatePortDefault()" required>
                            <option value="mysql" <?php echo ($_POST['db_driver'] ?? 'mysql') === 'mysql' ? 'selected' : ''; ?>>MySQL / MariaDB<?php echo !$pdo_mysql_ok ? ' (PDO driver missing: pdo_mysql)' : ''; ?></option>
                            <option value="mssql" <?php echo ($_POST['db_driver'] ?? '') === 'mssql' ? 'selected' : ''; ?>>Microsoft SQL Server<?php echo !$pdo_sqlsrv_ok ? ' (PDO driver missing: pdo_sqlsrv)' : ''; ?></option>
                        </select>
                        <small class="form-text text-muted">Choose MySQL for easy entry and development, or MS SQL Server for production enterprise environments. <strong>This choice is permanent</strong> - the database type cannot be changed after installation.
                        <?php if (!$pdo_mysql_ok || !$pdo_sqlsrv_ok): ?>
                            <br><span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Missing PDO drivers (<?php echo !$pdo_mysql_ok ? 'pdo_mysql' : ''; ?><?php echo !$pdo_mysql_ok && !$pdo_sqlsrv_ok ? ', ' : ''; ?><?php echo !$pdo_sqlsrv_ok ? 'pdo_sqlsrv' : ''; ?>) must be installed on your server before you can use that database type.</span>
                        <?php endif; ?>
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_host">Server Address</label>
                        <input type="text" class="form-control" id="db_host" name="db_host" 
                               value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" 
                               required>
                        <small class="form-text text-muted">Usually "localhost" if MySQL is on the same server</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_port">Port</label>
                        <input type="number" class="form-control" id="db_port" name="db_port" 
                               value="<?php echo htmlspecialchars($_POST['db_port'] ?? '3306'); ?>" 
                               min="1" max="65535" required>
                        <small class="form-text text-muted">MySQL default: 3306, MSSQL default: 1433</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_name">Database Name</label>
                        <input type="text" class="form-control" id="db_name" name="db_name" 
                               value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'mosaic'); ?>" 
                               required>
                        <small class="form-text text-muted">On shared hosting, use the exact database name created in your control panel. On dedicated servers, we'll create it if needed.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_prefix">Table Prefix</label>
                        <input type="text" class="form-control" id="db_prefix" name="db_prefix" 
                               value="<?php echo htmlspecialchars($_POST['db_prefix'] ?? 'tbl_'); ?>" 
                               placeholder="e.g., tbl_ or mosaic_">
                        <small class="form-text text-muted">Prefix for all database tables. Include underscore if desired (e.g., tbl_). Leave blank for no prefix.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_user">Username</label>
                        <input type="text" class="form-control" id="db_user" name="db_user" 
                               value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root'); ?>" 
                               required>
                        <small class="form-text text-muted">Your MySQL username</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_pass">Password</label>
                        <input type="password" class="form-control" id="db_pass" name="db_pass" 
                               value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>">
                        <small class="form-text text-muted">Leave blank if no password is set</small>
                    </div>
                    
                    <!-- Email Configuration (Optional) -->
                    <h6 class="form-section-title"><i class="fas fa-envelope mr-2"></i>Email Notifications <span class="badge badge-secondary" style="font-size: 11px;">Optional</span></h6>
                    <p class="text-muted mb-3" style="font-size: 14px;">Configure email settings to enable notifications for assessments, reminders, and reports. You can skip this and configure it later if needed.</p>
                    
                    <div class="form-group">
                        <label>Email Method</label>
                        <select class="form-control" id="mail_method" name="mail_method" onchange="toggleSmtpFields()">
                            <option value="disabled" <?php echo ($_POST['mail_method'] ?? 'disabled') === 'disabled' ? 'selected' : ''; ?>>Disabled (configure later)</option>
                            <option value="server" <?php echo ($_POST['mail_method'] ?? '') === 'server' ? 'selected' : ''; ?>>Server Mail (PHP mail function)</option>
                            <option value="smtp" <?php echo ($_POST['mail_method'] ?? '') === 'smtp' ? 'selected' : ''; ?>>SMTP (recommended)</option>
                        </select>
                        <small class="form-text text-muted">Server Mail uses your server's built-in mail. SMTP is more reliable for delivery.</small>
                    </div>
                    
                    <div id="smtp-fields" style="display: <?php echo ($_POST['mail_method'] ?? 'disabled') === 'smtp' ? 'block' : 'none'; ?>;">
                        <div class="form-group">
                            <label for="smtp_host">SMTP Server</label>
                            <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                   value="<?php echo htmlspecialchars($_POST['smtp_host'] ?? ''); ?>" 
                                   placeholder="e.g., smtp.gmail.com">
                            <small class="form-text text-muted">Your email server address</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="smtp_port">SMTP Port</label>
                                    <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                           value="<?php echo htmlspecialchars($_POST['smtp_port'] ?? '587'); ?>" 
                                           min="1" max="65535">
                                    <small class="form-text text-muted">Usually 587 (TLS) or 465 (SSL)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="smtp_encryption">Encryption</label>
                                    <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                                        <option value="tls" <?php echo ($_POST['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?php echo ($_POST['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="none" <?php echo ($_POST['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                                    </select>
                                    <small class="form-text text-muted">Recommended: TLS</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_user">SMTP Username</label>
                            <input type="text" class="form-control" id="smtp_user" name="smtp_user" 
                                   value="<?php echo htmlspecialchars($_POST['smtp_user'] ?? ''); ?>" 
                                   placeholder="Your email or username">
                            <small class="form-text text-muted">Usually your full email address</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_pass">SMTP Password</label>
                            <input type="password" class="form-control" id="smtp_pass" name="smtp_pass" 
                                   value="<?php echo htmlspecialchars($_POST['smtp_pass'] ?? ''); ?>" 
                                   placeholder="Your email password or app password">
                            <small class="form-text text-muted">For Gmail, use an <a href="https://support.google.com/accounts/answer/185833" target="_blank">App Password</a></small>
                        </div>
                    </div>
                    
                    <div id="email-common-fields" style="display: <?php echo ($_POST['mail_method'] ?? 'disabled') !== 'disabled' ? 'block' : 'none'; ?>;">
                        <div class="form-group">
                            <label for="smtp_from_email">From Email Address</label>
                            <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email" 
                                   value="<?php echo htmlspecialchars($_POST['smtp_from_email'] ?? ''); ?>" 
                                   placeholder="noreply@yourdomain.edu">
                            <small class="form-text text-muted">Email address that notifications will be sent from</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_from_name">From Name</label>
                            <input type="text" class="form-control" id="smtp_from_name" name="smtp_from_name" 
                                   value="<?php echo htmlspecialchars($_POST['smtp_from_name'] ?? $_POST['site_name'] ?? 'MOSAIC'); ?>" 
                                   placeholder="Your site name">
                            <small class="form-text text-muted">Display name for outgoing emails</small>
                        </div>
                    </div>
                    
                    <script>
                    function toggleSmtpFields() {
                        var method = document.getElementById('mail_method').value;
                        var smtpFields = document.getElementById('smtp-fields');
                        var commonFields = document.getElementById('email-common-fields');
                        
                        if (method === 'smtp') {
                            smtpFields.style.display = 'block';
                            commonFields.style.display = 'block';
                        } else if (method === 'server') {
                            smtpFields.style.display = 'none';
                            commonFields.style.display = 'block';
                        } else {
                            smtpFields.style.display = 'none';
                            commonFields.style.display = 'none';
                        }
                    }
                    
                    function updatePortDefault() {
                        var driver = document.getElementById('db_driver').value;
                        var portField = document.getElementById('db_port');
                        
                        // Only update if port is still at default value
                        if (portField.value === '3306' || portField.value === '1433') {
                            portField.value = (driver === 'mssql') ? '1433' : '3306';
                        }
                    }
                    </script>
                    
                    <button type="submit" name="setup_submit" class="btn btn-primary btn-lg btn-block" <?php echo !$all_checks_pass ? 'disabled' : ''; ?>>
                        <i class="fas fa-rocket mr-2"></i>Install Now
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

<?php $theme->showFooter($context); ?>
