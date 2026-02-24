<?php
/**
 * Login Page
 * 
 * User authentication interface for MOSAIC.
 * Handles both GET (display form) and POST (process login).
 */

declare(strict_types=1);

require_once __DIR__ . '/system/includes/init.php';

use Mosaic\Core\Database;
use Mosaic\Core\Logger;

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'administration/');
    exit;
}

$error = '';
$message = '';

// Get flash messages
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// ============================================================================
// HANDLE LOGIN POST
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);
    
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } else {
        // CHECK EMERGENCY ADMIN FIRST (Break Glass Account)
        $emergencyAdminEnabled = EMERGENCY_ADMIN_ENABLED ?? false;
        $emergencyAdminUsername = EMERGENCY_ADMIN_USERNAME ?? null;
        $emergencyAdminPassword = EMERGENCY_ADMIN_PASSWORD ?? null;
        
        // DEBUG: Always log for troubleshooting (remove after testing)
        error_log("[LOGIN] Checking credentials for: " . $email);
        error_log("[LOGIN] Emergency Admin Enabled: " . var_export($emergencyAdminEnabled, true));
        error_log("[LOGIN] Emergency Admin Username from config: " . var_export($emergencyAdminUsername, true));
        error_log("[LOGIN] Submitted Email: " . var_export($email, true));
        error_log("[LOGIN] Passwords match: " . var_export($emergencyAdminEnabled && $emergencyAdminUsername && $password === $emergencyAdminPassword, true));
        
        if ($emergencyAdminEnabled && $emergencyAdminUsername && $emergencyAdminPassword) {
            if ($email === $emergencyAdminUsername && $password === $emergencyAdminPassword) {
                // Emergency admin login successful
                error_log("[LOGIN] Emergency admin login SUCCESS");
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = 0; // Special ID for emergency admin
                $_SESSION['user_email'] = $emergencyAdminUsername;
                $_SESSION['user_name'] = 'Emergency Admin';
                $_SESSION['logged_in_at'] = time();
                $_SESSION['last_activity'] = time();
                $_SESSION['is_emergency_admin'] = true;
                
                Logger::getInstance()->warning('Emergency admin login used', [
                    'username' => $emergencyAdminUsername,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                
                header('Location: ' . BASE_URL . 'administration/');
                exit;
            }
        }
        
        // Normal database authentication
        try {
            $db = Database::getInstance()->getConnection();
            
            // Get user by email
            $stmt = $db->prepare("
                SELECT users_pk, full_name, email, password_hash, is_active
                FROM tbl_users
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            
            if ($user = $stmt->fetch()) {
                // Check if account is active
                if (!$user['is_active']) {
                    $error = 'Your account has been deactivated. Please contact an administrator.';
                    Logger::getInstance()->warning('Login attempt on inactive account', [
                        'email' => $email,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                }
                // Verify password
                elseif (password_verify($password, $user['password_hash'])) {
                    // Successful login
                    
                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['users_pk'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['logged_in_at'] = time();
                    $_SESSION['last_activity'] = time();
                    
                    // Handle "Remember Me"
                    if ($rememberMe) {
                        // Set cookie for 30 days
                        $cookieLifetime = 30 * 24 * 60 * 60; // 30 days
                        ini_set('session.cookie_lifetime', (string)$cookieLifetime);
                    }
                    
                    Logger::getInstance()->info('User logged in', [
                        'user_id' => $user['users_pk'],
                        'email' => $email,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                    
                    // Redirect to dashboard
                    header('Location: ' . BASE_URL . 'administration/');
                    exit;
                } else {
                    // Invalid password
                    $error = 'Invalid email or password.';
                    Logger::getInstance()->warning('Failed login attempt', [
                        'email' => $email,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'reason' => 'invalid_password'
                    ]);
                }
            } else {
                // User not found
                $error = 'Invalid email or password.';
                Logger::getInstance()->warning('Failed login attempt', [
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'reason' => 'user_not_found'
                ]);
            }
            
        } catch (Exception $e) {
            $error = 'An error occurred during login. Please try again.';
            Logger::getInstance()->warning('Login error: ' . $e->getMessage(), [
                'email' => $email,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars(SITE_NAME) ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #0D47A1 0%, #1976D2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 15px;
        }
        .login-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #0D47A1 0%, #1565C0 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .login-header i {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }
        .login-body {
            padding: 2rem;
        }
        .form-floating label {
            color: #666;
        }
        .btn-login {
            background: linear-gradient(135deg, #1976D2 0%, #1565C0 100%);
            border: none;
            padding: 0.75rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #0D47A1 0%, #1976D2 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(13, 71, 161, 0.4);
        }
        .login-footer {
            padding: 1.5rem 2rem;
            background: #f8f9fa;
            text-align: center;
            color: #666;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="bi bi-graph-up"></i>
                <h2 class="mb-0"><?= htmlspecialchars(SITE_NAME) ?></h2>
                <p class="mb-0 mt-1" style="opacity: 0.9;">Student Learning Outcomes Assessment</p>
            </div>
            
            <div class="login-body">
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($message)): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="email" name="email" 
                               placeholder="name@example.com" required autofocus
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        <label for="email"><i class="bi bi-envelope me-2"></i>Email Address</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Password" required>
                        <label for="password"><i class="bi bi-lock me-2"></i>Password</label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                        <label class="form-check-label" for="remember_me">
                            Remember me for 30 days
                        </label>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg btn-login">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="login-footer">
                <i class="bi bi-shield-check me-1"></i>
                Secure encrypted authentication
            </div>
        </div>
        
        <div class="text-center mt-3">
            <small class="text-white">
                <i class="bi bi-c-circle"></i> <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?> - Student Learning Outcomes Assessment
            </small>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
