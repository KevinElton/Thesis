<?php
/**
 * Email Test - Send a test email to verify the system works
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../classes/Mailer.php';

$result = null;
$to_email = 'ae202401395@wmsu.edu.ph';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to_email = $_POST['email'] ?? $to_email;
    
    echo "<pre style='background:#000;color:#0f0;padding:20px;max-height:400px;overflow:auto;'>";
    echo "Sending email to: {$to_email}\n\n";
    
    $result = Mailer::sendDebug(
        $to_email,
        "✅ Test Email - Thesis Defense System",
        "
        <div style='font-family:Arial;padding:30px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:15px;'>
            <div style='background:white;padding:30px;border-radius:10px;'>
                <h1 style='color:#667eea;margin:0 0 20px 0;'>🎉 Email System Working!</h1>
                <p style='color:#333;font-size:16px;'>
                    This is a test email from your <strong>Thesis Defense Scheduling System</strong>.
                </p>
                <p style='color:#666;'>If you received this, your email notifications are configured correctly!</p>
                <div style='background:#f0f4ff;padding:15px;border-radius:8px;margin-top:20px;'>
                    <p style='margin:0;color:#3b82f6;font-size:14px;'>
                        📧 Sent at: " . date('F j, Y g:i:s A') . "
                    </p>
                </div>
            </div>
        </div>
        "
    );
    
    echo "\n\n";
    if ($result === true) {
        echo "✅ SUCCESS! Email sent successfully!\n";
    } else {
        echo "❌ FAILED: {$result}\n";
    }
    echo "</pre>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Test Email</title>
    <script src="/Thesis/assets/js/tailwind.js"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-purple-50 min-h-screen p-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <h1 class="text-3xl font-bold text-center mb-2 text-gray-800">📧 Email Test</h1>
            <p class="text-center text-gray-600 mb-8">Send a test email to verify the system works</p>
            
            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Recipient Email:</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($to_email) ?>" 
                           class="w-full border-2 border-gray-300 rounded-xl p-4 text-lg focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                           required>
                </div>
                
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-4 rounded-xl font-bold text-lg hover:from-blue-700 hover:to-purple-700 transition shadow-lg">
                    📨 Send Test Email
                </button>
            </form>
            
            <?php if ($result === true): ?>
            <div class="mt-6 bg-green-100 border-2 border-green-500 text-green-800 p-4 rounded-xl text-center">
                <strong>✅ SUCCESS!</strong> Check your inbox (and spam folder) at <?= htmlspecialchars($to_email) ?>
            </div>
            <?php elseif ($result !== null): ?>
            <div class="mt-6 bg-red-100 border-2 border-red-500 text-red-800 p-4 rounded-xl">
                <strong>❌ Error:</strong> <?= htmlspecialchars($result) ?>
            </div>
            <?php endif; ?>
            
            <div class="mt-8 flex gap-4">
                <a href="dashboard.php" class="flex-1 text-center bg-gray-100 text-gray-700 py-3 rounded-xl font-semibold hover:bg-gray-200">
                    ← Dashboard
                </a>
                <a href="activity_monitor.php" class="flex-1 text-center bg-purple-100 text-purple-700 py-3 rounded-xl font-semibold hover:bg-purple-200">
                    Activity Monitor
                </a>
            </div>
        </div>
    </div>
</body>
</html>


