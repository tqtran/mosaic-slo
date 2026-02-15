#!/usr/bin/env php
<?php
/**
 * MOSAIC Database Setup Script
 * 
 * This script:
 * 1. Prompts for database credentials
 * 2. Creates the database if it doesn't exist
 * 3. Executes the schema SQL file
 * 4. Saves credentials to src/config/config.yaml
 */

declare(strict_types=1);

// Setup log file
$logFile = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'setup_' . date('Y-m-d_His') . '.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Logging function
function log_message(string $message, string $level = 'INFO'): void {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Color output for CLI
function color_output(string $message, string $color = 'green'): void {
    $colors = [
        'red' => "\033[0;31m",
        'green' => "\033[0;32m",
        'yellow' => "\033[0;33m",
        'blue' => "\033[0;34m",
        'reset' => "\033[0m"
    ];
    echo $colors[$color] . $message . $colors['reset'] . PHP_EOL;
    
    // Also log to file (strip color codes)
    log_message($message, $color === 'red' ? 'ERROR' : ($color === 'yellow' ? 'INFO' : 'SUCCESS'));
}

function prompt(string $message, string $default = ''): string {
    $defaultText = $default ? " [$default]" : '';
    echo $message . $defaultText . ': ';
    $input = trim(fgets(STDIN));
    return $input ?: $default;
}

function prompt_hidden(string $message): string {
    echo $message . ': ';
    
    // Windows-specific hidden input
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $command = 'powershell -Command "$password = Read-Host -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($password))"';
        $password = shell_exec($command);
        return trim($password);
    }
    
    // Unix-based systems
    system('stty -echo');
    $password = trim(fgets(STDIN));
    system('stty echo');
    echo PHP_EOL;
    return $password;
}

function test_connection(string $host, string $user, string $pass): bool {
    try {
        log_message("Testing connection to $host...");
        $mysqli = new mysqli($host, $user, $pass);
        if ($mysqli->connect_error) {
            log_message("Connection failed: " . $mysqli->connect_error, 'ERROR');
            return false;
        }
        $mysqli->close();
        log_message("Connection successful to $host");
        return true;
    } catch (Exception $e) {
        log_message("Connection exception: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

function create_database(string $host, string $user, string $pass, string $dbname): bool {
    try {
        log_message("Creating database: $dbname");
        $mysqli = new mysqli($host, $user, $pass);
        
        if ($mysqli->connect_error) {
            throw new Exception("Connection failed: " . $mysqli->connect_error);
        }
        
        // Create database with UTF8MB4
        $sql = "CREATE DATABASE IF NOT EXISTS `$dbname` 
                CHARACTER SET utf8mb4 
                COLLATE utf8mb4_unicode_ci";
        
        log_message("Executing: $sql");
        if (!$mysqli->query($sql)) {
            throw new Exception("Failed to create database: " . $mysqli->error);
        }
        
        log_message("Database '$dbname' created successfully");
        $mysqli->close();
        return true;
    } catch (Exception $e) {
        log_message($e->getMessage(), 'ERROR');
        color_output("Error: " . $e->getMessage(), 'red');
        return false;
    }
}

function execute_schema(string $host, string $user, string $pass, string $dbname, string $schemaFile): bool {
    try {
        log_message("Connecting to database '$dbname' for schema execution");
        $mysqli = new mysqli($host, $user, $pass, $dbname);
        
        if ($mysqli->connect_error) {
            throw new Exception("Connection failed: " . $mysqli->connect_error);
        }
        
        // Read schema file
        if (!file_exists($schemaFile)) {
            throw new Exception("Schema file not found: $schemaFile");
        }
        
        log_message("Reading schema from: $schemaFile");
        $sql = file_get_contents($schemaFile);
        $fileSize = strlen($sql);
        log_message("Schema file size: $fileSize bytes");
        
        // Execute multi-query
        log_message("Executing schema SQL...");
        $mysqli->multi_query($sql);
        
        // Process all results
        $queryCount = 0;
        do {
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
            $queryCount++;
        } while ($mysqli->more_results() && $mysqli->next_result());
        
        log_message("Executed $queryCount SQL statements");
        
        // Check for errors
        if ($mysqli->error) {
            throw new Exception("Schema execution error: " . $mysqli->error);
        }
        
        log_message("Schema execution completed successfully");
        $mysqli->close();
        return true;
    } catch (Exception $e) {
        log_message($e->getMessage(), 'ERROR');
        log_message("Stack trace: " . $e->getTraceAsString(), 'ERROR');
        color_output("Error: " . $e->getMessage(), 'red');
        return false;
    }
}

function save_config(string $host, string $user, string $pass, string $dbname, int $port, string $configFile): bool {
    try {
        log_message("Preparing configuration file: $configFile");
        
        $yaml = <<<YAML
# MOSAIC Configuration File
# Generated: {date}

# Database Connection Settings
secrets:
  database:
    host: {host}
    port: {port}
    database: {dbname}
    username: {user}
    password: {pass}
    charset: utf8mb4

# Session Configuration
session:
  cookie_httponly: true
  cookie_secure: true
  cookie_samesite: 'Strict'
  timeout: 7200  # 2 hours in seconds

# Security Settings
security:
  bcrypt_cost: 12
  csrf_token_length: 32
  password_min_length: 12

# Logging Configuration
logging:
  enabled: true
  log_to_database: true
  log_to_file: true
  log_directory: 'logs'
  max_log_age_days: 90
  log_level: 'error'
  audit_enabled: true
  security_log_enabled: true

# Application Settings
app:
  name: 'MOSAIC'
  timezone: 'America/Los_Angeles'
  debug: false
YAML;

        $yaml = str_replace(
            ['{date}', '{host}', '{port}', '{dbname}', '{user}', '{pass}'],
            [date('Y-m-d H:i:s'), $host, $port, $dbname, $user, $pass],
            $yaml
        );
        
        // Create directory if it doesn't exist
        $configDir = dirname($configFile);
        if (!is_dir($configDir)) {
            log_message("Creating config directory: $configDir");
            mkdir($configDir, 0755, true);
        }
        
        // Write config file
        log_message("Writing configuration file");
        if (file_put_contents($configFile, $yaml) === false) {
            throw new Exception("Failed to write config file");
        }
        
        log_message("Configuration file written successfully");
        
        // Set restrictive permissions (Unix-like systems)
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            log_message("Setting file permissions to 0600");
            chmod($configFile, 0600);
        }
        
        log_message("Configuration saved successfully to: $configFile");
        return true;
    } catch (Exception $e) {
        log_message($e->getMessage(), 'ERROR');
        log_message("Stack trace: " . $e->getTraceAsString(), 'ERROR');
        color_output("Error: " . $e->getMessage(), 'red');
        return false;
    }
}

// ============================================================================
// MAIN SETUP SCRIPT
// ============================================================================

echo PHP_EOL;
color_output("╔═══════════════════════════════════════════════════════════════╗", 'blue');
color_output("║          MOSAIC Database Setup Script                        ║", 'blue');
color_output("╚═══════════════════════════════════════════════════════════════╝", 'blue');
echo PHP_EOL;

log_message("========================================");
log_message("MOSAIC Database Setup Started");
log_message("Log file: $logFile");
log_message("========================================");

// Get project root directory
$rootDir = dirname(__DIR__);
$schemaFile = $rootDir . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql';
$configFile = $rootDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.yaml';

// Step 1: Collect database credentials
color_output("Step 1: Database Connection Information", 'yellow');
echo PHP_EOL;

$host = prompt("Database host", "localhost");
$port = (int)prompt("Database port", "3306");
$user = prompt("Database username", "root");
$pass = prompt_hidden("Database password");
$dbname = prompt("Database name", "mosaic_slo");

log_message("Collected credentials - Host: $host, Port: $port, User: $user, Database: $dbname");

echo PHP_EOL;

// Step 2: Test connection
color_output("Step 2: Testing database connection...", 'yellow');

if (!test_connection($host, $user, $pass)) {
    color_output("✗ Connection failed. Please check your credentials.", 'red');
    exit(1);
}

color_output("✓ Connection successful", 'green');
echo PHP_EOL;

// Step 3: Create database
color_output("Step 3: Creating database '$dbname'...", 'yellow');

if (!create_database($host, $user, $pass, $dbname)) {
    color_output("✗ Failed to create database", 'red');
    exit(1);
}

color_output("✓ Database created (or already exists)", 'green');
echo PHP_EOL;

// Step 4: Execute schema
color_output("Step 4: Executing schema SQL...", 'yellow');

if (!execute_schema($host, $user, $pass, $dbname, $schemaFile)) {
    color_output("✗ Failed to execute schema", 'red');
    exit(1);
}

color_output("✓ Schema executed successfully", 'green');
echo PHP_EOL;

// Step 5: Save configuration
color_output("Step 5: Saving configuration...", 'yellow');

if (!save_config($host, $user, $pass, $dbname, $port, $configFile)) {
    color_output("✗ Failed to save configuration", 'red');
    exit(1);
}

color_output("✓ Configuration saved to: $configFile", 'green');
echo PHP_EOL;

// Success summary
log_message("========================================");
log_message("MOSAIC Database Setup Completed Successfully");
log_message("Database: $dbname at $host:$port");
log_message("Config: $configFile");
log_message("========================================");

color_output("╔═══════════════════════════════════════════════════════════════╗", 'green');
color_output("║                     Setup Complete!                          ║", 'green');
color_output("╚═══════════════════════════════════════════════════════════════╝", 'green');
echo PHP_EOL;

color_output("Database: $dbname", 'green');
color_output("Host: $host:$port", 'green');
color_output("Config: $configFile", 'green');
color_output("Log: $logFile", 'green');
echo PHP_EOL;

color_output("Next steps:", 'yellow');
echo "  1. Create an admin user (see scripts/create_admin_user.php)" . PHP_EOL;
echo "  2. Configure your web server to point to the mosaic-slo/ directory" . PHP_EOL;
echo "  3. Access the application at http://localhost:8000" . PHP_EOL;
echo PHP_EOL;

exit(0);
