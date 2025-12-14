<?php
/**
 * Setup Email System Tables
 * Run this file once to create the required tables for the email notification system
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->connect();

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>Setup Email System</title>
    <script src='/Thesis/assets/js/tailwind.js'></script>
</head>
<body class='bg-gray-100 p-8'>
    <div class='max-w-4xl mx-auto'>
        <div class='bg-white rounded-lg shadow-lg p-8'>
            <h1 class='text-3xl font-bold mb-6 text-blue-600'>📧 Email System Setup</h1>";

try {
    // Create email_logs table
    $conn->exec("CREATE TABLE IF NOT EXISTS `email_logs` (
        `log_id` INT AUTO_INCREMENT PRIMARY KEY,
        `recipient_email` VARCHAR(255) NOT NULL,
        `subject` VARCHAR(500) NOT NULL,
        `message` TEXT,
        `status` ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
        `error_message` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    echo "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'>
            ✅ <strong>Success:</strong> email_logs table created!
          </div>";

    // Create notifications table if not exists
    $conn->exec("CREATE TABLE IF NOT EXISTS `notifications` (
        `notification_id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `user_type` ENUM('admin', 'panelist') NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `message` TEXT NOT NULL,
        `type` VARCHAR(50) DEFAULT 'general',
        `related_id` INT,
        `is_read` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    echo "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'>
            ✅ <strong>Success:</strong> notifications table created!
          </div>";

    // Check if admin table exists
    $result = $conn->query("SHOW TABLES LIKE 'admin'")->fetch();
    if (!$result) {
        $conn->exec("CREATE TABLE IF NOT EXISTS `admin` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(100) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `name` VARCHAR(255),
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Insert default admin if it doesn't exist
        $stmt = $conn->prepare("INSERT INTO admin (username, password, email, name) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'standinkevin30@gmail.com', 'System Admin']);
        
        echo "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'>
                ✅ <strong>Success:</strong> admin table created with default admin!
              </div>";
    } else {
        // Check if email column exists
        $checkColumn = $conn->query("SHOW COLUMNS FROM admin LIKE 'email'")->fetch();
        if (!$checkColumn) {
            $conn->exec("ALTER TABLE admin ADD COLUMN email VARCHAR(255) DEFAULT 'standinkevin30@gmail.com'");
            echo "<div class='bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4'>
                    ⚠️ <strong>Updated:</strong> Added email column to admin table!
                  </div>";
        }
        
        // Update admin email if it's empty
        $conn->exec("UPDATE admin SET email = 'standinkevin30@gmail.com' WHERE email IS NULL OR email = ''");
        
        echo "<div class='bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4'>
                ℹ️ <strong>Info:</strong> Admin table already exists!
              </div>";
    }

    echo "<div class='mt-8 p-4 bg-gradient-to-r from-green-50 to-blue-50 border-l-4 border-green-500 rounded'>
            <h3 class='font-bold text-green-800 mb-2'>✅ Email System Ready!</h3>
            <p class='text-green-700 mb-3'>The email notification system is now fully configured.</p>
            <ul class='text-sm text-gray-700 space-y-2'>
                <li>✓ <strong>When a panelist updates availability:</strong> Admin will receive an email</li>
                <li>✓ <strong>When admin assigns a panel:</strong> Panelists will receive emails with schedule details</li>
                <li>✓ <strong>Emails are logged:</strong> Check the email_logs table for history</li>
            </ul>
          </div>";

    echo "<div class='mt-4 p-4 bg-yellow-50 border-l-4 border-yellow-500 rounded'>
            <h3 class='font-bold text-yellow-800 mb-2'>⚠️ Important Configuration</h3>
            <p class='text-yellow-700 mb-2'>Emails are being sent to: <strong>standinkevin30@gmail.com</strong></p>
            <p class='text-sm text-yellow-600'>If you want to change this, update the email in the admin table or Mailer.php</p>
          </div>";

} catch (PDOException $e) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>
            <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "
          </div>";
}

echo "      <div class='mt-6 flex gap-4 flex-wrap'>
                <a href='dashboard.php' class='bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition'>
                    🏠 Go to Dashboard
                </a>
                <a href='activity_monitor.php' class='bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition'>
                    📊 Activity Monitor
                </a>
                <a href='../auth/logout.php' class='bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition'>
                    🚪 Logout
                </a>
            </div>
        </div>
    </div>
</body>
</html>";
?>


