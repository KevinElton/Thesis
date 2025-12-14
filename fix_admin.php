<?php
require_once __DIR__ . '/config/database.php';

$db = new Database();
$conn = $db->connect();

// Set a new hashed password for all admins
$newPassword = 'Admin123';
$hashedPassword = password_hash($newPassword, PASSWORD_ARGON2ID);

// Update all admin passwords
$stmt = $conn->prepare("UPDATE admin SET password = ?");
$stmt->execute([$hashedPassword]);

echo "<div style='font-family:Arial;max-width:500px;margin:50px auto;padding:30px;background:#d4edda;border-radius:15px;text-align:center;'>";
echo "<h1 style='color:#155724;'>✅ Admin Password Reset!</h1>";
echo "<p style='font-size:18px;'>Your admin password has been set to:</p>";
echo "<div style='background:#fff;padding:20px;border-radius:10px;margin:20px 0;'>";
echo "<p style='margin:0;font-size:14px;color:#666;'>Email:</p>";
echo "<p style='margin:5px 0;font-size:20px;font-weight:bold;'>ae202401395@wmsu.edu.ph</p>";
echo "<p style='margin:15px 0 0;font-size:14px;color:#666;'>Password:</p>";
echo "<p style='margin:5px 0;font-size:24px;font-weight:bold;color:#28a745;'>Admin123</p>";
echo "</div>";
echo "<a href='auth/login.php' style='display:inline-block;background:#28a745;color:white;padding:15px 30px;text-decoration:none;border-radius:10px;font-weight:bold;'>→ Go to Login</a>";
echo "<p style='margin-top:20px;color:#856404;font-size:12px;'>⚠️ Delete this file (fix_admin.php) after logging in!</p>";
echo "</div>";
?>
