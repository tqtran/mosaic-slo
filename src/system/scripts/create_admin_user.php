#!/usr/bin/env php
<?php
/**
 * Create Admin User Script
 * 
 * Creates an initial admin user in the MOSAIC system.
 * Must be run after src/setup.php has initialized the database.
 */

declare(strict_types=1);

// Setup log file in project root logs directory
$logFile = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'admin_user_' . date('Y-m-d_His') . '.log';
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

// Security logging to database
function log_security_event(mysqli $db, string $eventType, string $description, ?int $userFk = null, ?string $username = null): void {
    $stmt = $db->prepare(
        "INSERT INTO security_log (event_type, event_description, user_fk, username, ip_address, severity) 
         VALUES (?, ?, ?, ?, ?, 'info')"
    );
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $stmt->bind_param("ssiss", $eventType, $description, $userFk, $username, $ipAddress);
    $stmt->execute();
    $stmt->close();
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
    
    // Also log to file
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

function validate_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_password(string $password): bool {
    return strlen($password) >= 12;
}

// ============================================================================
// MAIN SCRIPT
// ============================================================================

echo PHP_EOL;
color_output("+===============================================================+", 'blue');
color_output("|          MOSAIC Admin User Creation                          |", 'blue');
color_output("+===============================================================+", 'blue');
echo PHP_EOL;

log_message("========================================");
log_message("Admin User Creation Started");
log_message("Log file: $logFile");
log_message("========================================");

// Check if config exists
$configFile = __DIR__ . '/../config/config.yaml';
if (!file_exists($configFile)) {
    log_message("Configuration file not found: $configFile", 'ERROR');
    color_output("[X] Configuration file not found. Please run src/setup.php first.", 'red');
    exit(1);
}

log_message("Reading configuration from: $configFile");

// Parse YAML config (simple parser for our use case)
$config = [];
$yaml = file_get_contents($configFile);
if (preg_match('/host:\s*(.+)/', $yaml, $matches)) $config['host'] = trim($matches[1]);
if (preg_match('/port:\s*(\d+)/', $yaml, $matches)) $config['port'] = (int)$matches[1];
if (preg_match('/database:\s*(.+)/', $yaml, $matches)) $config['database'] = trim($matches[1]);
if (preg_match('/username:\s*(.+)/', $yaml, $matches)) $config['username'] = trim($matches[1]);
if (preg_match('/password:\s*(.+)/', $yaml, $matches)) $config['password'] = trim($matches[1]);

// Connect to database
try {
    log_message("Connecting to database: {$config['database']} at {$config['host']}");
    $mysqli = new mysqli(
        $config['host'],
        $config['username'],
        $config['password'],
        $config['database']
    );
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    log_message("Database connection successful");
} catch (Exception $e) {
    log_message($e->getMessage(), 'ERROR');
    color_output("[X] " . $e->getMessage(), 'red');
    exit(1);
}

color_output("[OK] Connected to database", 'green');
echo PHP_EOL;

// Collect user information
color_output("Enter admin user details:", 'yellow');
echo PHP_EOL;

$userId = prompt("Username (user_id)");
if (empty($userId)) {
    log_message("Username validation failed: empty username", 'ERROR');
    color_output("[X] Username is required", 'red');
    exit(1);
}

log_message("Validating username: $userId");

$firstName = prompt("First Name");
if (empty($firstName)) {
    color_output("[X] First name is required", 'red');
    exit(1);
}

$lastName = prompt("Last Name");
if (empty($lastName)) {
    color_output("[X] Last name is required", 'red');
    exit(1);
}

$email = prompt("Email");
if (!validate_email($email)) {
    log_message("Email validation failed: $email", 'ERROR');
    color_output("[X] Invalid email address", 'red');
    exit(1);
}

log_message("Email validated: $email");

$password = prompt_hidden("Password (minimum 12 characters)");
if (!validate_password($password)) {
    log_message("Password validation failed: length < 12", 'ERROR');
    color_output("[X] Password must be at least 12 characters", 'red');
    exit(1);
}

$passwordConfirm = prompt_hidden("Confirm Password");
if ($password !== $passwordConfirm) {
    log_message("Password confirmation failed: mismatch", 'ERROR');
    color_output("[X] Passwords do not match", 'red');
    exit(1);
}

log_message("Password validated successfully");

echo PHP_EOL;
color_output("Creating admin user...", 'yellow');

// Hash password with bcrypt cost 12
log_message("Hashing password with bcrypt cost 12");
$passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Insert user
log_message("Inserting user record for: $userId");
$stmt = $mysqli->prepare(
    "INSERT INTO users (user_id, first_name, last_name, email, password_hash, is_active) 
     VALUES (?, ?, ?, ?, ?, 1)"
);

if (!$stmt) {
    color_output("[X] Failed to prepare statement: " . $mysqli->error, 'red');
    exit(1);
}

$stmt->bind_param("sssss", $userId, $firstName, $lastName, $email, $passwordHash);

if (!$stmt->execute()) {
    log_message("Failed to create user: " . $stmt->error, 'ERROR');
    color_output("[X] Failed to create user: " . $stmt->error, 'red');
    exit(1);
}

$userPk = $stmt->insert_id;
$stmt->close();

log_message("User created successfully with PK: $userPk");
color_output("[OK] User created with ID: $userPk", 'green');

// Log security event
log_security_event($mysqli, 'admin_user_created', "Admin user created: $userId ($firstName $lastName)", $userPk, $userId);

// Assign admin role
log_message("Assigning admin role to user PK: $userPk");
$stmt = $mysqli->prepare(
    "INSERT INTO user_roles (user_fk, role_fk, context_type, context_id) 
     VALUES (?, (SELECT roles_pk FROM roles WHERE role_name = 'admin'), NULL, NULL)"
);

if (!$stmt) {
    color_output("✗ Failed to prepare role statement: " . $mysqli->error, 'red');
    exit(1);
}

$stmt->bind_param("i", $userPk);

if (!$stmt->execute()) {
    log_message("Failed to assign admin role: " . $stmt->error, 'ERROR');
    color_output("✗ Failed to assign admin role: " . $stmt->error, 'red');
    exit(1);
}

$stmt->close();

log_message("Admin role assigned successfully");
log_security_event($mysqli, 'admin_role_assigned', "Admin role assigned to user: $userId", $userPk, $userId);

$mysqli->close();

color_output("✓ Admin role assigned", 'green');
echo PHP_EOL;

// Success summary
log_message("========================================");
log_message("Admin User Created Successfully");
log_message("Username: $userId");
log_message("Email: $email");
log_message("Role: Global Admin");
log_message("========================================");

color_output("+===============================================================+", 'green');
color_output("|                  Admin User Created!                         |", 'green');
color_output("+===============================================================+", 'green');
echo PHP_EOL;

color_output("Username: $userId", 'green');
color_output("Email: $email", 'green');
color_output("Role: Global Admin", 'green');
color_output("Log: $logFile", 'green');
echo PHP_EOL;

color_output("You can now log in to MOSAIC using these credentials.", 'yellow');
echo PHP_EOL;

exit(0);
