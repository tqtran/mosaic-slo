#!/usr/bin/env php
<?php
/**
 * MOSAIC Log Cleanup Script
 * 
 * Removes old log entries from database and log files.
 * Run this script periodically via cron or Task Scheduler.
 * 
 * Usage:
 *   php src/scripts/cleanup_logs.php [days]
 * 
 * Example:
 *   php src/scripts/cleanup_logs.php 90    # Keep logs for 90 days
 */

declare(strict_types=1);

require_once __DIR__ . '/../Core/Logger.php';

use MOSAIC\Core\Logger;

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
}

// Parse command line arguments
$daysToKeep = isset($argv[1]) ? (int)$argv[1] : 90;

if ($daysToKeep < 1) {
    color_output("Error: Days must be a positive integer", 'red');
    exit(1);
}

echo PHP_EOL;
color_output("+===============================================================+", 'blue');
color_output("|          MOSAIC Log Cleanup Script                           |", 'blue');
color_output("+===============================================================+", 'blue');
echo PHP_EOL;

color_output("Keeping logs from the last $daysToKeep days", 'yellow');
echo PHP_EOL;

// Check if config exists
$configFile = __DIR__ . '/../config/config.yaml';
if (!file_exists($configFile)) {
    color_output("[X] Configuration file not found. Please run src/setup.php first.", 'red');
    exit(1);
}

// Parse YAML config
$config = [];
$yaml = file_get_contents($configFile);
if (preg_match('/host:\s*(.+)/', $yaml, $matches)) $config['host'] = trim($matches[1]);
if (preg_match('/port:\s*(\d+)/', $yaml, $matches)) $config['port'] = (int)$matches[1];
if (preg_match('/database:\s*(.+)/', $yaml, $matches)) $config['database'] = trim($matches[1]);
if (preg_match('/username:\s*(.+)/', $yaml, $matches)) $config['username'] = trim($matches[1]);
if (preg_match('/password:\s*(.+)/', $yaml, $matches)) $config['password'] = trim($matches[1]);

// Parse logging config
$loggingConfig = [
    'logging' => [
        'enabled' => true,
        'log_to_database' => true,
        'log_to_file' => true,
        'log_directory' => __DIR__ . '/../../logs',
        'max_log_age_days' => $daysToKeep,
        'audit_enabled' => true,
        'security_log_enabled' => true
    ],
    'app' => [
        'debug' => false
    ]
];

// Connect to database
try {
    $mysqli = new mysqli(
        $config['host'],
        $config['username'],
        $config['password'],
        $config['database']
    );
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    color_output("[OK] Connected to database", 'green');
} catch (Exception $e) {
    color_output("[X] " . $e->getMessage(), 'red');
    exit(1);
}

// Initialize logger
$logger = Logger::getInstance($loggingConfig, $mysqli);

// Run cleanup
try {
    color_output("Cleaning up old log entries...", 'yellow');
    $logger->cleanup($daysToKeep);
    color_output("[OK] Cleanup completed successfully", 'green');
    
    // Log the cleanup action
    $logger->info("Log cleanup completed", ['days_kept' => $daysToKeep]);
    
} catch (Exception $e) {
    color_output("[X] Cleanup failed: " . $e->getMessage(), 'red');
    exit(1);
} finally {
    $mysqli->close();
}

echo PHP_EOL;
color_output("+===============================================================+", 'green');
color_output("|                  Cleanup Complete!                           |", 'green');
color_output("+===============================================================+", 'green');
echo PHP_EOL;

color_output("Log entries older than $daysToKeep days have been removed.", 'green');
echo PHP_EOL;

// Display cleanup summary if possible
try {
    // Count remaining logs
    $result = $mysqli->query("SELECT COUNT(*) as count FROM audit_log");
    $auditCount = $result->fetch_assoc()['count'];
    
    $result = $mysqli->query("SELECT COUNT(*) as count FROM error_log");
    $errorCount = $result->fetch_assoc()['count'];
    
    $result = $mysqli->query("SELECT COUNT(*) as count FROM security_log");
    $securityCount = $result->fetch_assoc()['count'];
    
    color_output("Remaining entries:", 'yellow');
    echo "  Audit log: $auditCount" . PHP_EOL;
    echo "  Error log: $errorCount" . PHP_EOL;
    echo "  Security log: $securityCount" . PHP_EOL;
    echo PHP_EOL;
} catch (Exception $e) {
    // Silently ignore if connection was already closed
}

exit(0);
