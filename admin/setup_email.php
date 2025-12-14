<?php
/**
 * EMAIL CONFIGURATION SETUP
 * 
 * Follow these steps to set up your own Gmail for sending emails:
 * 
 * 1. Go to https://myaccount.google.com/security
 * 2. Enable "2-Step Verification" if not already enabled
 * 3. Go to https://myaccount.google.com/apppasswords
 * 4. Select "Mail" and "Windows Computer"
 * 5. Click "Generate"
 * 6. Copy the 16-character password (no spaces)
 * 7. Paste it below in GMAIL_APP_PASSWORD
 */

// ========== YOUR GMAIL SETTINGS ==========
define('GMAIL_USERNAME', 'YOUR_GMAIL@gmail.com');        // Your Gmail address
define('GMAIL_APP_PASSWORD', 'xxxx xxxx xxxx xxxx');     // 16-char App Password from Google
define('GMAIL_FROM_NAME', 'Thesis Defense System');       // Sender name
// ==========================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../classes/PHPMailer.php';
require_once __DIR__ . '/../classes/SMTP.php';
require_once __DIR__ . '/../classes/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$result = null;
$to_email = 'ae202401395@wmsu.edu.ph';
$gmail_user = isset($_POST['gmail']) ? $_POST['gmail'] : '';
$gmail_pass = isset($_POST['app_password']) ? $_POST['app_password'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $gmail_user && $gmail_pass) {
    $to_email = $_POST['email'] ?? $to_email;
    
    $mail = new PHPMailer(true);
    
    try {
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $gmail_user;
        $mail->Password = str_replace(' ', '', $gmail_pass); // Remove spaces
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->Timeout = 60;
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->setFrom($gmail_user, GMAIL_FROM_NAME);
        $mail->addAddress($to_email);
        
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "✅ Email Test Successful - Thesis Defense System";
        $mail->Body = "
        <div style='font-family:Arial;padding:30px;background:linear-gradient(135deg,#10b981,#3b82f6);border-radius:15px;'>
            <div style='background:white;padding:30px;border-radius:10px;'>
                <h1 style='color:#10b981;margin:0 0 20px 0;'>🎉 Email Configuration Successful!</h1>
                <p style='color:#333;font-size:16px;'>Your <strong>Thesis Defense Scheduling System</strong> can now send emails.</p>
                <p style='color:#666;'>You will receive notifications when:</p>
                <ul style='color:#555;'>
                    <li>A panelist updates their availability</li>
                    <li>Your panel assignment changes</li>
                </ul>
                <p style='color:#999;font-size:12px;margin-top:20px;'>Sent: " . date('F j, Y g:i:s A') . "</p>
            </div>
        </div>";
        $mail->AltBody = 'Email test successful! Your Thesis Defense System can now send emails.';
        
        $mail->send();
        $result = 'success';
        
        // Save configuration to a file
        $config = "<?php\n// Auto-generated email configuration\ndefine('GMAIL_USERNAME', '" . addslashes($gmail_user) . "');\ndefine('GMAIL_APP_PASSWORD', '" . addslashes(str_replace(' ', '', $gmail_pass)) . "');\ndefine('GMAIL_FROM_NAME', 'Thesis Defense System');\n";
        file_put_contents(__DIR__ . '/../config/email_config.php', $config);
        
    } catch (Exception $e) {
        $result = $mail->ErrorInfo;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup Email</title>
    <script src="/Thesis/assets/js/tailwind.js"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-purple-50 min-h-screen p-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <h1 class="text-3xl font-bold text-center mb-2 text-gray-800">📧 Email Setup</h1>
            <p class="text-center text-gray-600 mb-6">Configure your Gmail to send email notifications</p>
            
            <?php if ($result === 'success'): ?>
            <div class="bg-green-100 border-2 border-green-500 text-green-800 p-6 rounded-xl text-center mb-6">
                <h2 class="text-2xl font-bold mb-2">✅ SUCCESS!</h2>
                <p>Email sent to <strong><?= htmlspecialchars($to_email) ?></strong></p>
                <p class="text-sm mt-2">Your email configuration has been saved!</p>
            </div>
            <?php elseif ($result): ?>
            <div class="bg-red-100 border-2 border-red-500 text-red-800 p-4 rounded-xl mb-6">
                <strong>❌ Error:</strong> <?= htmlspecialchars($result) ?>
            </div>
            <?php endif; ?>
            
            <!-- Instructions -->
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-lg mb-6">
                <h3 class="font-bold text-blue-800 mb-2">📋 How to get your Gmail App Password:</h3>
                <ol class="text-sm text-blue-700 space-y-1 list-decimal ml-4">
                    <li>Go to <a href="https://myaccount.google.com/security" target="_blank" class="underline font-bold">Google Account Security</a></li>
                    <li>Enable <strong>2-Step Verification</strong> if not enabled</li>
                    <li>Go to <a href="https://myaccount.google.com/apppasswords" target="_blank" class="underline font-bold">App Passwords</a></li>
                    <li>Click <strong>"Select app"</strong> → Choose <strong>"Mail"</strong></li>
                    <li>Click <strong>"Generate"</strong></li>
                    <li>Copy the <strong>16-character password</strong></li>
                </ol>
            </div>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Your Gmail Address:</label>
                    <input type="email" name="gmail" value="<?= htmlspecialchars($gmail_user) ?>" 
                           placeholder="youremail@gmail.com"
                           class="w-full border-2 border-gray-300 rounded-xl p-3 focus:border-blue-500" required>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">App Password (16 characters):</label>
                    <input type="text" name="app_password" value="<?= htmlspecialchars($gmail_pass) ?>"
                           placeholder="xxxx xxxx xxxx xxxx"
                           class="w-full border-2 border-gray-300 rounded-xl p-3 font-mono focus:border-blue-500" required>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Send test email to:</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($to_email) ?>"
                           class="w-full border-2 border-gray-300 rounded-xl p-3 focus:border-blue-500" required>
                </div>
                
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-green-600 to-blue-600 text-white py-4 rounded-xl font-bold text-lg hover:from-green-700 hover:to-blue-700 transition shadow-lg">
                    📨 Test & Save Configuration
                </button>
            </form>
            
            <div class="mt-6 flex gap-4">
                <a href="dashboard.php" class="flex-1 text-center bg-gray-100 text-gray-700 py-3 rounded-xl font-semibold hover:bg-gray-200">
                    ← Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>


