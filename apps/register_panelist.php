<?php
session_start();
// Go up one level from /apps/ to /Thesis/ then into /classes/
require_once __DIR__ . '/../classes/database.php';

$db = new Database();
$conn = $db->connect();
$message = '';
$messageType = '';

// Clear form variables
$first_name = $last_name = $email = $department = $expertise = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $expertise = trim($_POST['expertise'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($department) || empty($expertise) || empty($password)) {
        $message = 'All fields are required.';
        $messageType = 'error';
    } elseif ($password !== $confirm_password) {
        $message = 'Passwords do not match.';
        $messageType = 'error';
    } elseif (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters long.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email format.';
        $messageType = 'error';
    } else {
        try {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT email FROM panelist WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $message = 'Email already exists. Please use a different email.';
                $messageType = 'error';
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new panelist with 'pending' status
                $stmt = $conn->prepare("
                    INSERT INTO panelist 
                    (first_name, last_name, email, username, password, role, designation, status, department, expertise, created_at) 
                    VALUES (?, ?, ?, NULL, ?, 'Panel', 'Panelist', 'pending', ?, ?, NOW())
                ");
                
                if ($stmt->execute([$first_name, $last_name, $email, $hashed_password, $department, $expertise])) {
                    $message = 'Registration successful! Your account is pending admin approval. You will be notified once approved.';
                    $messageType = 'success';
                    // Clear form
                    $first_name = $last_name = $email = $department = $expertise = '';
                } else {
                    $message = 'Registration failed. Please try again.';
                    $messageType = 'error';
                }
            }
        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panelist Registration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gradient-to-br from-blue-100 via-blue-50 to-blue-200 min-h-screen flex items-center justify-center py-8">

    <div class="bg-white shadow-2xl rounded-3xl w-full max-w-2xl p-10">
        <div class="flex flex-col items-center mb-8">
            <div class="bg-gradient-to-r from-blue-500 to-indigo-600 p-5 rounded-full mb-4 shadow-lg">
                <i data-lucide="user-plus" class="w-10 h-10 text-white"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-1">Panelist Registration</h1>
            <p class="text-gray-500 text-sm">Create your account and wait for admin approval</p>
        </div>

        <?php if ($message): ?>
            <div class="<?= $messageType === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700' ?> border px-4 py-3 rounded-lg mb-5 flex items-center gap-2">
                <i data-lucide="<?= $messageType === 'success' ? 'check-circle' : 'alert-triangle' ?>" class="w-5 h-5"></i>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="relative">
                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                    <i data-lucide="user" class="absolute left-3 top-10 text-gray-400 w-5 h-5"></i>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($first_name) ?>" required
                        class="w-full border border-gray-300 rounded-lg pl-12 p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200">
                </div>

                <div class="relative">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                    <i data-lucide="user" class="absolute left-3 top-10 text-gray-400 w-5 h-5"></i>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($last_name) ?>" required
                        class="w-full border border-gray-300 rounded-lg pl-12 p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200">
                </div>
            </div>

            <div class="relative">
                <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                <i data-lucide="mail" class="absolute left-3 top-10 text-gray-400 w-5 h-5"></i>
                <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required
                    class="w-full border border-gray-300 rounded-lg pl-12 p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200">
            </div>

            <div class="relative">
                <label class="block text-sm font-medium text-gray-700 mb-1">Department *</label>
                <i data-lucide="building" class="absolute left-3 top-10 text-gray-400 w-5 h-5"></i>
                <input type="text" name="department" value="<?= htmlspecialchars($department) ?>" placeholder="e.g., Computer Science" required
                    class="w-full border border-gray-300 rounded-lg pl-12 p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200">
            </div>

            <div class="relative">
                <label class="block text-sm font-medium text-gray-700 mb-1">Expertise *</label>
                <i data-lucide="award" class="absolute left-3 top-10 text-gray-400 w-5 h-5"></i>
                <input type="text" name="expertise" value="<?= htmlspecialchars($expertise) ?>" placeholder="e.g., Software Engineering, Data Science" required
                    class="w-full border border-gray-300 rounded-lg pl-12 p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="relative">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                    <i data-lucide="lock" class="absolute left-3 top-10 text-gray-400 w-5 h-5"></i>
                    <input type="password" name="password" placeholder="At least 6 characters" required
                        class="w-full border border-gray-300 rounded-lg pl-12 p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200">
                </div>

                <div class="relative">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
                    <i data-lucide="lock" class="absolute left-3 top-10 text-gray-400 w-5 h-5"></i>
                    <input type="password" name="confirm_password" placeholder="Re-enter password" required
                        class="w-full border border-gray-300 rounded-lg pl-12 p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200">
                </div>
            </div>

            <button type="submit"
                class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-3 rounded-lg font-semibold hover:from-blue-700 hover:to-indigo-700 transition duration-300 flex justify-center items-center gap-2 shadow-md">
                <i data-lucide="user-plus" class="w-5 h-5"></i>
                Register Account
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-gray-600 text-sm">
                Already have an account? 
                <a href="login.php" class="text-blue-600 hover:text-blue-800 font-semibold">Login here</a>
            </p>
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