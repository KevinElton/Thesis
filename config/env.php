<?php
/**
 * Environment Configuration
 * 
 * SECURITY: This file contains sensitive credentials.
 * In production, move this file OUTSIDE the web root directory
 * and use $_ENV variables or a .env file instead.
 */

// Email Configuration (Gmail SMTP)
define('MAIL_USERNAME', 'standinkevin30@gmail.com');
define('MAIL_PASSWORD', 'gyunkbhxcqlwpuzn');  // Gmail App Password
define('MAIL_FROM_NAME', 'Thesis Defense System');

// Application Settings
define('APP_DEBUG', false);  // Set to false in production
define('APP_URL', 'http://localhost/Thesis');

// Security Settings
define('SESSION_LIFETIME', 3600);        // 1 hour
define('LOGIN_MAX_ATTEMPTS', 5);         // Max failed login attempts
define('LOGIN_LOCKOUT_TIME', 300);       // 5 minutes lockout
define('PASSWORD_MIN_LENGTH', 8);        // Minimum password length
define('CSRF_TOKEN_EXPIRE', 3600);       // CSRF token expiration
?>
