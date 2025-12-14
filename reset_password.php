<?php
/**
 * Admin Password Reset Tool
 * DELETE THIS FILE AFTER USE!
 */

require_once __DIR__ . '/config/database.php';

$db = new Database();
$conn = $db->connect();

// Check current admin accounts
echo "<h2>Current Admin Accounts:</h2>";
$stmt = $conn->query("SELECT id, email, name, LEFT(password, 10) as pwd_preview FROM admin");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
foreach ($admins as $admin) {
    $isHashed = substr($admin['pwd_preview'], 0, 1) === '$' ? '✅ Hashed' : '❌ Plain text';
    echo "ID: {$admin['id']} | Email: {$admin['email']} | Name: {$admin['name']} | Password: {$isHashed}\n";
}
echo "</pre>";

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
    $email = $_POST['email'];
    $newPassword = $_POST['new_password'];
    
    // Hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_ARGON2ID);
    
    $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE email = ?");
    if ($stmt->execute([$hashedPassword, $email])) {
        echo "<div style='background:green;color:white;padding:20px;margin:20px 0;border-radius:10px;'>";
        echo "✅ Password updated successfully for: " . htmlspecialchars($email);
        echo "<br><br><strong>You can now login with your new password!</strong>";
        echo "<br><br><a href='auth/login.php' style='color:white;'>→ Go to Login Page</a>";
        echo "</div>";
    } else {
        echo "<div style='background:red;color:white;padding:20px;'>❌ Failed to update password</div>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Admin Password</title>
    <style>
        body { font-family: Arial; max-width: 600px; margin: 50px auto; padding: 20px; }
        form { background: #f5f5f5; padding: 30px; border-radius: 10px; }
        input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 15px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #45a049; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>🔐 Reset Admin Password</h1>
    
    <div class="warning">
        ⚠️ <strong>Security Notice:</strong> DELETE this file (reset_password.php) after you've reset your password!
    </div>
    
    <form method="POST">
        <label><strong>Admin Email:</strong></label>
        <input type="email" name="email" placeholder="Enter admin email" required>
        
        <label><strong>New Password:</strong></label>
        <input type="password" name="new_password" placeholder="Enter new password (min 8 chars)" required minlength="8">
        
        <button type="submit" name="reset" value="1">🔑 Reset Password</button>
    </form>
    
    <p style="margin-top:20px;color:#666;">
        After resetting, go to <a href="auth/login.php">Login Page</a>
    </p>
</body>
</html>
