<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';

// Initialize secure session
initSecureSession();
setSecurityHeaders();

// Require admin authentication
requireAuth('Admin');

$db = new Database();
$conn = $db->connect();
$message = '';

// Create settings table if not exists
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS system_settings (
        setting_id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_description TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Insert default settings if not exist
    $defaults = [
        ['defense_duration', '120', 'Default defense duration in minutes'],
        ['max_panelist_load', '10', 'Maximum defenses per panelist per month'],
        ['notification_enabled', '1', 'Enable email notifications'],
        ['academic_year', '2024-2025', 'Current academic year'],
        ['semester', '2nd Semester', 'Current semester'],
        ['allow_weekends', '0', 'Allow scheduling on weekends'],
        ['min_panel_members', '3', 'Minimum panel members required'],
        ['evaluation_threshold', '75', 'Passing grade threshold (%)']
    ];
    
    foreach ($defaults as $default) {
        $stmt = $conn->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_description) VALUES (?, ?, ?)");
        $stmt->execute($default);
    }
} catch (PDOException $e) {
    // Table might already exist
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="bg-red-100 text-red-700 p-4 rounded-lg mb-4">Invalid request. Please try again.</div>';
    } else {
        try {
            foreach ($_POST as $key => $value) {
                if ($key !== 'update_settings' && $key !== 'csrf_token') {
                    $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                    $stmt->execute([$value, $key]);
                }
            }
            $message = '<div class="bg-green-100 text-green-700 p-4 rounded-lg mb-4"><i data-lucide="check-circle" class="inline w-5 h-5 mr-2"></i>Settings updated successfully!</div>';
        } catch (PDOException $e) {
            error_log("Settings error: " . $e->getMessage());
            $message = '<div class="bg-red-100 text-red-700 p-4 rounded-lg mb-4"><i data-lucide="alert-triangle" class="inline w-5 h-5 mr-2"></i>Error updating settings.</div>';
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="bg-red-100 text-red-700 p-4 rounded-lg mb-4">Invalid request. Please try again.</div>';
    } else {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        
        if ($new !== $confirm) {
            $message = '<div class="bg-red-100 text-red-700 p-4 rounded-lg mb-4">Passwords do not match!</div>';
        } else {
            // Validate new password strength
            $passwordErrors = validatePassword($new);
            if (!empty($passwordErrors)) {
                $message = '<div class="bg-red-100 text-red-700 p-4 rounded-lg mb-4">' . implode(' ', $passwordErrors) . '</div>';
            } else {
                $stmt = $conn->prepare("SELECT password FROM admin WHERE id = ?");
                $stmt->execute([$_SESSION['admin_id']]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Only use password_verify, no plain-text fallback
                if (password_verify($current, $admin['password'])) {
                    $hashed = password_hash($new, PASSWORD_ARGON2ID);
                    $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed, $_SESSION['admin_id']]);
                    $message = '<div class="bg-green-100 text-green-700 p-4 rounded-lg mb-4"><i data-lucide="check-circle" class="inline w-5 h-5 mr-2"></i>Password changed successfully!</div>';
                } else {
                    $message = '<div class="bg-red-100 text-red-700 p-4 rounded-lg mb-4">Current password is incorrect!</div>';
                }
            }
        }
    }
}

// Fetch all settings
$settings = $conn->query("SELECT * FROM system_settings ORDER BY setting_key")->fetchAll(PDO::FETCH_ASSOC);
$settings_map = [];
foreach ($settings as $s) {
    $settings_map[$s['setting_key']] = $s['setting_value'];
}

// Get statistics
$stats = [
    'total_faculty' => $conn->query("SELECT COUNT(*) as c FROM faculty")->fetch()['c'],
    'total_groups' => $conn->query("SELECT COUNT(*) as c FROM thesis_group")->fetch()['c'],
    'total_schedules' => $conn->query("SELECT COUNT(*) as c FROM schedule")->fetch()['c'],
    'total_evaluations' => $conn->query("SELECT COUNT(*) as c FROM evaluation")->fetch()['c']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>System Settings</title>
<script src="/Thesis/assets/js/tailwind.js"></script>
<script src="/Thesis/assets/js/lucide.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">
<?php include 'sidebar.php'; ?>
<main class="flex-1 ml-64 p-8">
<h1 class="text-3xl font-bold mb-6 flex items-center gap-2">
    <i data-lucide="settings"></i> System Settings
</h1>

<?= $message ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- System Statistics -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold mb-4 flex items-center gap-2 text-blue-600">
                <i data-lucide="bar-chart"></i> System Statistics
            </h2>
            <div class="space-y-4">
                <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                    <span class="text-gray-700">Faculty Members</span>
                    <span class="font-bold text-blue-600"><?= $stats['total_faculty'] ?></span>
                </div>
                <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                    <span class="text-gray-700">Thesis Groups</span>
                    <span class="font-bold text-green-600"><?= $stats['total_groups'] ?></span>
                </div>
                <div class="flex justify-between items-center p-3 bg-purple-50 rounded-lg">
                    <span class="text-gray-700">Schedules</span>
                    <span class="font-bold text-purple-600"><?= $stats['total_schedules'] ?></span>
                </div>
                <div class="flex justify-between items-center p-3 bg-orange-50 rounded-lg">
                    <span class="text-gray-700">Evaluations</span>
                    <span class="font-bold text-orange-600"><?= $stats['total_evaluations'] ?></span>
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center gap-2 text-red-600">
                <i data-lucide="lock"></i> Change Password
            </h2>
            <form method="POST" class="space-y-4">
                <?= csrfField() ?>
                <input type="hidden" name="change_password" value="1">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Current Password</label>
                    <input type="password" name="current_password" required class="w-full border rounded-lg p-2">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">New Password</label>
                    <input type="password" name="new_password" required minlength="6" class="w-full border rounded-lg p-2">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Confirm Password</label>
                    <input type="password" name="confirm_password" required minlength="6" class="w-full border rounded-lg p-2">
                </div>
                <button type="submit" class="w-full bg-red-600 text-white py-2 rounded-lg hover:bg-red-700 flex items-center justify-center gap-2">
                    <i data-lucide="save"></i> Update Password
                </button>
            </form>
        </div>
    </div>

    <!-- System Configuration -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center gap-2 text-green-600">
                <i data-lucide="sliders"></i> System Configuration
            </h2>
            
            <form method="POST" class="space-y-6">
                <?= csrfField() ?>
                <input type="hidden" name="update_settings" value="1">
                
                <!-- Academic Information -->
                <div class="border-b pb-4">
                    <h3 class="font-semibold text-lg mb-3 text-gray-700">Academic Information</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Academic Year</label>
                            <input type="text" name="academic_year" value="<?= htmlspecialchars($settings_map['academic_year'] ?? '') ?>" class="w-full border rounded-lg p-2">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Semester</label>
                            <select name="semester" class="w-full border rounded-lg p-2">
                                <option value="1st Semester" <?= ($settings_map['semester'] ?? '') == '1st Semester' ? 'selected' : '' ?>>1st Semester</option>
                                <option value="2nd Semester" <?= ($settings_map['semester'] ?? '') == '2nd Semester' ? 'selected' : '' ?>>2nd Semester</option>
                                <option value="Summer" <?= ($settings_map['semester'] ?? '') == 'Summer' ? 'selected' : '' ?>>Summer</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Defense Settings -->
                <div class="border-b pb-4">
                    <h3 class="font-semibold text-lg mb-3 text-gray-700">Defense Settings</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Default Duration (minutes)</label>
                            <input type="number" name="defense_duration" value="<?= htmlspecialchars($settings_map['defense_duration'] ?? '') ?>" class="w-full border rounded-lg p-2">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Min Panel Members</label>
                            <input type="number" name="min_panel_members" value="<?= htmlspecialchars($settings_map['min_panel_members'] ?? '') ?>" class="w-full border rounded-lg p-2">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Max Panelist Load/Month</label>
                            <input type="number" name="max_panelist_load" value="<?= htmlspecialchars($settings_map['max_panelist_load'] ?? '') ?>" class="w-full border rounded-lg p-2">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Allow Weekends</label>
                            <select name="allow_weekends" class="w-full border rounded-lg p-2">
                                <option value="1" <?= ($settings_map['allow_weekends'] ?? '') == '1' ? 'selected' : '' ?>>Yes</option>
                                <option value="0" <?= ($settings_map['allow_weekends'] ?? '') == '0' ? 'selected' : '' ?>>No</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Evaluation Settings -->
                <div class="border-b pb-4">
                    <h3 class="font-semibold text-lg mb-3 text-gray-700">Evaluation Settings</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Passing Threshold (%)</label>
                            <input type="number" name="evaluation_threshold" min="0" max="100" value="<?= htmlspecialchars($settings_map['evaluation_threshold'] ?? '') ?>" class="w-full border rounded-lg p-2">
                        </div>
                    </div>
                </div>

                <!-- Notification Settings -->
                <div>
                    <h3 class="font-semibold text-lg mb-3 text-gray-700">Notification Settings</h3>
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="notification_enabled" value="1" <?= ($settings_map['notification_enabled'] ?? '') == '1' ? 'checked' : '' ?> class="w-5 h-5">
                        <label class="text-gray-700">Enable Email Notifications</label>
                    </div>
                </div>

                <div class="pt-4 border-t">
                    <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 flex items-center gap-2">
                        <i data-lucide="save"></i> Save All Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Database Maintenance -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mt-6">
            <h2 class="text-xl font-bold mb-4 flex items-center gap-2 text-orange-600">
                <i data-lucide="database"></i> Database Maintenance
            </h2>
            <div class="grid grid-cols-2 gap-4">
                <button onclick="alert('Backup feature coming soon!')" class="bg-blue-500 text-white px-4 py-3 rounded-lg hover:bg-blue-600 flex items-center justify-center gap-2">
                    <i data-lucide="download"></i> Backup Database
                </button>
                <button onclick="if(confirm('Clear old logs?')) alert('Logs cleared!')" class="bg-orange-500 text-white px-4 py-3 rounded-lg hover:bg-orange-600 flex items-center justify-center gap-2">
                    <i data-lucide="trash-2"></i> Clear Logs
                </button>
            </div>
        </div>
    </div>

</div>
</main>

<script>lucide.createIcons();</script>
</body>
</html>




