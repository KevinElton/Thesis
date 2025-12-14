<?php
/**
 * Secure Login Page
 * Includes: CSRF protection, rate limiting, secure session handling
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';

// Initialize secure session
initSecureSession();
setSecurityHeaders();

$db = new Database();
$conn = $db->connect();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please try again.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Check rate limiting
        if (!checkLoginAttempts($clientIP)) {
            $remaining = getRemainingLockoutTime($clientIP);
            $minutes = ceil($remaining / 60);
            $error = "Too many failed attempts. Please try again in {$minutes} minute(s).";
        } else {
            try {
                // ✅ ADMIN LOGIN - Only use password_verify (no plain text fallback)
                $stmt = $conn->prepare("SELECT * FROM admin WHERE email = ?");
                $stmt->execute([$username]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($admin && password_verify($password, $admin['password'])) {
                    // Clear failed attempts
                    clearLoginAttempts($clientIP);
                    
                    // Regenerate session ID to prevent fixation
                    session_regenerate_id(true);
                    
                    $_SESSION['role'] = 'Admin';
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_name'] = $admin['name'];
                    $_SESSION['login_time'] = time();
                    
                    header("Location: ../admin/evaluations.php");
                    exit;
                }

                // ✅ PANELIST LOGIN - Only active accounts, password_verify only
                $stmt = $conn->prepare("SELECT * FROM panelist WHERE (email = ? OR username = ?) AND status = 'active'");
                $stmt->execute([$username, $username]);
                $panelist = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($panelist && password_verify($password, $panelist['password'])) {
                    // Clear failed attempts
                    clearLoginAttempts($clientIP);
                    
                    // Regenerate session ID to prevent fixation
                    session_regenerate_id(true);
                    
                    $_SESSION['role'] = 'Panelist';
                    $_SESSION['panelist_id'] = $panelist['panelist_id'];
                    $_SESSION['panelist_name'] = $panelist['first_name'] . ' ' . $panelist['last_name'];
                    $_SESSION['login_time'] = time();
                    
                    header("Location: ../panelist/dashboard.php");
                    exit;
                }

                // Check if account exists but is pending
                $stmt = $conn->prepare("SELECT status FROM panelist WHERE (email = ? OR username = ?)");
                $stmt->execute([$username, $username]);
                $pending = $stmt->fetch(PDO::FETCH_ASSOC);

                // Record failed attempt
                recordFailedLogin($clientIP);
                
                if ($pending && $pending['status'] === 'pending') {
                    $error = "Your account is pending admin approval. Please wait for confirmation.";
                } else {
                    $error = "Invalid username or password.";
                }

            } catch (PDOException $e) {
                error_log("Login error: " . $e->getMessage());
                $error = "An error occurred. Please try again later.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Thesis Scheduling System</title>
    <script src="/Thesis/assets/js/tailwind.js"></script>
    <script src="/Thesis/assets/js/lucide.min.js"></script>
</head>
<body class="bg-gradient-to-br from-blue-100 via-blue-50 to-blue-200 min-h-screen flex items-center justify-center">

    <div class="bg-white shadow-2xl rounded-3xl w-full max-w-md p-10">
        <div class="flex flex-col items-center mb-8">
            <div class="bg-gradient-to-r from-blue-500 to-indigo-600 p-5 rounded-full mb-4 shadow-lg">
                <i data-lucide="shield-check" class="w-10 h-10 text-white"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-1">Welcome</h1>
            <p class="text-gray-500 text-sm">Thesis Scheduling System</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-5 flex items-center gap-2">
                <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <!-- CSRF Token -->
            <?= csrfField() ?>
            
            <div class="relative">
                <i data-lucide="user" class="absolute left-3 top-3.5 text-gray-400 w-5 h-5"></i>
                <input type="text" name="username" placeholder="Email or Username" required
                    class="w-full border border-gray-300 rounded-lg pl-12 p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                    autocomplete="username">
            </div>

            <div class="relative">
                <i data-lucide="lock" class="absolute left-3 top-3.5 text-gray-400 w-5 h-5"></i>
                <input type="password" name="password" placeholder="Password" required
                    class="w-full border border-gray-300 rounded-lg pl-12 p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                    autocomplete="current-password">
            </div>

            <button type="submit"
                class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-3 rounded-lg font-semibold hover:from-blue-700 hover:to-indigo-700 transition duration-300 flex justify-center items-center gap-2 shadow-md">
                <i data-lucide="log-in" class="w-5 h-5"></i>
                Sign In
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-gray-600 text-sm mb-2">Don't have an account?</p>
            <a href="register.php" class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-800 font-semibold transition">
                <i data-lucide="user-plus" class="w-4 h-4"></i>
                Register as Panelist
            </a>
        </div>

        <div class="mt-6 text-center text-sm text-gray-500">
            © <?= date('Y') ?> Thesis Scheduling System. All rights reserved.
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
