<?php
/**
 * Security Helper Functions
 * Include this file at the start of every page for security
 */

// Load environment config
require_once __DIR__ . '/../config/env.php';

/**
 * Initialize secure session with proper settings
 */
function initSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Secure session settings
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        // Only use secure cookies in production (HTTPS)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }
        
        session_start();
        
        // Regenerate session ID periodically to prevent fixation
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        } else if (time() - $_SESSION['_created'] > 1800) {
            // Regenerate session ID every 30 minutes
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }
    }
}

/**
 * Set security headers to protect against common attacks
 */
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Note: CSP is commented out as it may break inline scripts/styles
    // header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval'");
}

/**
 * Generate a CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token']) || 
        !isset($_SESSION['csrf_token_time']) || 
        time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_EXPIRE) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from form submission
 */
function validateCSRFToken($token) {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF input field HTML
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';
}

/**
 * Check and track login attempts for rate limiting
 */
function checkLoginAttempts($identifier) {
    $key = 'login_attempts_' . md5($identifier);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
    }
    
    $attempts = $_SESSION[$key];
    
    // Reset if lockout time has passed
    if ($attempts['count'] >= LOGIN_MAX_ATTEMPTS) {
        if (time() - $attempts['first_attempt'] > LOGIN_LOCKOUT_TIME) {
            $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
            return true;
        }
        return false; // Still locked out
    }
    
    return true; // Can attempt login
}

/**
 * Record a failed login attempt
 */
function recordFailedLogin($identifier) {
    $key = 'login_attempts_' . md5($identifier);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
    }
    
    $_SESSION[$key]['count']++;
}

/**
 * Clear login attempts after successful login
 */
function clearLoginAttempts($identifier) {
    $key = 'login_attempts_' . md5($identifier);
    unset($_SESSION[$key]);
}

/**
 * Get remaining lockout time in seconds
 */
function getRemainingLockoutTime($identifier) {
    $key = 'login_attempts_' . md5($identifier);
    
    if (!isset($_SESSION[$key]) || $_SESSION[$key]['count'] < LOGIN_MAX_ATTEMPTS) {
        return 0;
    }
    
    $elapsed = time() - $_SESSION[$key]['first_attempt'];
    $remaining = LOGIN_LOCKOUT_TIME - $elapsed;
    
    return $remaining > 0 ? $remaining : 0;
}

/**
 * Validate password strength
 */
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long.";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number.";
    }
    
    return $errors;
}

/**
 * Sanitize output to prevent XSS
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Check if user is authenticated
 */
function requireAuth($role = null) {
    initSecureSession();
    
    if (!isset($_SESSION['role'])) {
        header("Location: " . APP_URL . "/auth/login.php");
        exit;
    }
    
    if ($role !== null && $_SESSION['role'] !== $role) {
        header("Location: " . APP_URL . "/auth/login.php");
        exit;
    }
}

/**
 * Disable error display in production
 */
function configureErrorReporting() {
    if (defined('APP_DEBUG') && APP_DEBUG === true) {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    } else {
        error_reporting(E_ALL);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
    }
}

// Auto-configure on include
configureErrorReporting();
?>
