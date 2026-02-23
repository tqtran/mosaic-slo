<?php
/**
 * Logout Handler
 * 
 * Destroys user session and redirects to login page.
 */

declare(strict_types=1);

require_once __DIR__ . '/system/includes/init.php';

use Mosaic\Core\Logger;

// Log the logout if user was logged in
if (isset($_SESSION['user_id'])) {
    Logger::getInstance()->info('User logged out', [
        'user_id' => $_SESSION['user_id'],
        'email' => $_SESSION['user_email'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'session_duration' => isset($_SESSION['logged_in_at']) ? (time() - $_SESSION['logged_in_at']) : 0
    ]);
}

// Destroy session
session_unset();
session_destroy();

// Start new session for flash message
session_start();
$_SESSION['flash_message'] = 'You have been successfully logged out.';

// Redirect to login page
header('Location: ' . BASE_URL . 'login.php');
exit;
