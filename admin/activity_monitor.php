<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../auth/login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();

// Get current tab
$activeTab = $_GET['tab'] ?? 'accounts';

// Fetch new accounts (pending + recently approved)
$newAccounts = [];
try {
    $stmt = $conn->query("
        SELECT panelist_id, first_name, last_name, email, department, expertise, status, created_at
        FROM panelist 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $newAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist
}

// Fetch availability updates
$availabilityUpdates = [];
try {
    $stmt = $conn->query("
        SELECT a.*, CONCAT(p.first_name, ' ', p.last_name) as panelist_name, p.email
        FROM availability a
        INNER JOIN panelist p ON a.panelist_id = p.panelist_id
        ORDER BY a.created_at DESC
        LIMIT 30
    ");
    $availabilityUpdates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback if created_at doesn't exist
    try {
        $stmt = $conn->query("
            SELECT a.*, CONCAT(p.first_name, ' ', p.last_name) as panelist_name, p.email
            FROM availability a
            INNER JOIN panelist p ON a.panelist_id = p.panelist_id
            LIMIT 30
        ");
        $availabilityUpdates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        // Table doesn't exist
    }
}

// Fetch audit logs if table exists
$auditLogs = [];
try {
    $stmt = $conn->query("
        SELECT * FROM audit_logs 
        ORDER BY timestamp DESC 
        LIMIT 50
    ");
    $auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table doesn't exist
}

// Fetch recent notifications/emails
$recentNotifications = [];
try {
    $stmt = $conn->query("
        SELECT n.*, 
               CASE 
                   WHEN n.user_type = 'panelist' THEN CONCAT(p.first_name, ' ', p.last_name)
                   ELSE 'Admin'
               END as recipient_name
        FROM notifications n
        LEFT JOIN panelist p ON n.user_id = p.panelist_id AND n.user_type = 'panelist'
        ORDER BY n.created_at DESC
        LIMIT 30
    ");
    $recentNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table doesn't exist
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Monitor - Admin Dashboard</title>
<script src="/Thesis/assets/js/tailwind.js"></script>
<script src="/Thesis/assets/js/lucide.min.js"></script>
<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.fade-in { animation: fadeIn 0.3s ease-out; }
.tab-active { 
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    color: white;
}
</style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-blue-50 to-purple-50 min-h-screen flex">
<?php include 'sidebar.php'; ?>

<main class="flex-1 ml-64 p-8">
<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-4xl font-bold mb-2 flex items-center gap-3 text-gray-800">
            <div class="p-3 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl shadow-lg">
                <i data-lucide="activity" class="w-8 h-8 text-white"></i>
            </div>
            Activity Monitor
        </h1>
        <p class="text-gray-600 ml-16">Track accounts, availability updates, and system activities</p>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 mb-6 bg-white p-2 rounded-xl shadow-md">
        <a href="?tab=accounts" class="flex items-center gap-2 px-5 py-3 rounded-lg font-semibold transition <?= $activeTab === 'accounts' ? 'tab-active' : 'text-gray-600 hover:bg-gray-100' ?>">
            <i data-lucide="user-plus" class="w-5 h-5"></i>
            New Accounts
            <?php if (count(array_filter($newAccounts, fn($a) => $a['status'] === 'pending')) > 0): ?>
                <span class="px-2 py-0.5 bg-red-500 text-white text-xs rounded-full"><?= count(array_filter($newAccounts, fn($a) => $a['status'] === 'pending')) ?></span>
            <?php endif; ?>
        </a>
        <a href="?tab=availability" class="flex items-center gap-2 px-5 py-3 rounded-lg font-semibold transition <?= $activeTab === 'availability' ? 'tab-active' : 'text-gray-600 hover:bg-gray-100' ?>">
            <i data-lucide="calendar-clock" class="w-5 h-5"></i>
            Availability Updates
        </a>
        <a href="?tab=notifications" class="flex items-center gap-2 px-5 py-3 rounded-lg font-semibold transition <?= $activeTab === 'notifications' ? 'tab-active' : 'text-gray-600 hover:bg-gray-100' ?>">
            <i data-lucide="mail" class="w-5 h-5"></i>
            Emails & Notifications
        </a>
        <a href="?tab=audit" class="flex items-center gap-2 px-5 py-3 rounded-lg font-semibold transition <?= $activeTab === 'audit' ? 'tab-active' : 'text-gray-600 hover:bg-gray-100' ?>">
            <i data-lucide="scroll-text" class="w-5 h-5"></i>
            Audit Logs
        </a>
    </div>

    <!-- Content -->
    <div class="bg-white rounded-2xl shadow-lg p-6 fade-in">
        
        <?php if ($activeTab === 'accounts'): ?>
        <!-- New Accounts Tab -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i data-lucide="users" class="w-6 h-6 text-blue-600"></i>
                Panelist Accounts
            </h2>
            <a href="faculty/add.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2 transition">
                <i data-lucide="user-plus" class="w-4 h-4"></i>
                Manage Panelists
            </a>
        </div>
        
        <?php if (empty($newAccounts)): ?>
            <div class="text-center py-12">
                <i data-lucide="user-x" class="w-16 h-16 mx-auto mb-4 text-gray-300"></i>
                <p class="text-gray-500">No panelist accounts found</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Name</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Email</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Department</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Status</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Registered</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($newAccounts as $account): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white font-bold">
                                        <?= strtoupper(substr($account['first_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($account['first_name'] . ' ' . $account['last_name']) ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($account['expertise'] ?? 'N/A') ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($account['email']) ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($account['department'] ?? 'N/A') ?></td>
                            <td class="px-4 py-3">
                                <?php if ($account['status'] === 'pending'): ?>
                                    <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-semibold">⏳ Pending</span>
                                <?php elseif ($account['status'] === 'active'): ?>
                                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">✓ Active</span>
                                <?php else: ?>
                                    <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold"><?= htmlspecialchars($account['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                <?= $account['created_at'] ? date('M d, Y g:i A', strtotime($account['created_at'])) : 'N/A' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <?php elseif ($activeTab === 'availability'): ?>
        <!-- Availability Updates Tab -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i data-lucide="calendar-clock" class="w-6 h-6 text-green-600"></i>
                Panelist Availability Updates
            </h2>
            <a href="printPanelistAvailability.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2 transition">
                <i data-lucide="printer" class="w-4 h-4"></i>
                Print All
            </a>
        </div>
        
        <?php if (empty($availabilityUpdates)): ?>
            <div class="text-center py-12">
                <i data-lucide="calendar-x" class="w-16 h-16 mx-auto mb-4 text-gray-300"></i>
                <p class="text-gray-500">No availability updates found</p>
            </div>
        <?php else: ?>
            <div class="grid gap-4">
                <?php 
                $groupedByPanelist = [];
                foreach ($availabilityUpdates as $avail) {
                    $panelistId = $avail['panelist_id'];
                    if (!isset($groupedByPanelist[$panelistId])) {
                        $groupedByPanelist[$panelistId] = [
                            'name' => $avail['panelist_name'],
                            'email' => $avail['email'],
                            'slots' => []
                        ];
                    }
                    $groupedByPanelist[$panelistId]['slots'][] = $avail;
                }
                ?>
                <?php foreach ($groupedByPanelist as $panelistId => $data): ?>
                <div class="border border-gray-200 rounded-xl p-5 hover:shadow-md transition">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-green-400 to-teal-500 flex items-center justify-center text-white font-bold text-lg">
                                <?= strtoupper(substr($data['name'], 0, 1)) ?>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800"><?= htmlspecialchars($data['name']) ?></h3>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($data['email']) ?></p>
                            </div>
                        </div>
                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-semibold">
                            <?= count($data['slots']) ?> slot<?= count($data['slots']) > 1 ? 's' : '' ?>
                        </span>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                        <?php foreach (array_slice($data['slots'], 0, 8) as $slot): ?>
                        <div class="bg-gray-50 px-3 py-2 rounded-lg text-sm">
                            <p class="font-semibold text-gray-700"><?= htmlspecialchars($slot['day_of_week'] ?? 'N/A') ?></p>
                            <p class="text-gray-500"><?= htmlspecialchars($slot['start_time'] ?? '') ?> - <?= htmlspecialchars($slot['end_time'] ?? '') ?></p>
                        </div>
                        <?php endforeach; ?>
                        <?php if (count($data['slots']) > 8): ?>
                        <div class="bg-blue-50 px-3 py-2 rounded-lg text-sm text-blue-600 font-medium flex items-center justify-center">
                            +<?= count($data['slots']) - 8 ?> more
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php elseif ($activeTab === 'notifications'): ?>
        <!-- Notifications/Emails Tab -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i data-lucide="mail" class="w-6 h-6 text-purple-600"></i>
                Emails & Notifications Sent
            </h2>
        </div>
        
        <?php if (empty($recentNotifications)): ?>
            <div class="text-center py-12">
                <i data-lucide="mail-x" class="w-16 h-16 mx-auto mb-4 text-gray-300"></i>
                <p class="text-gray-500">No notifications found</p>
                <p class="text-sm text-gray-400 mt-2">Notifications will appear here when panel assignments or schedule changes are made</p>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($recentNotifications as $notif): ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition flex items-start gap-4">
                    <div class="p-2 bg-purple-100 rounded-lg">
                        <i data-lucide="<?= $notif['type'] === 'assignment' ? 'user-check' : ($notif['type'] === 'schedule' ? 'calendar' : 'bell') ?>" class="w-5 h-5 text-purple-600"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <h4 class="font-semibold text-gray-800"><?= htmlspecialchars($notif['title'] ?? 'Notification') ?></h4>
                            <span class="text-xs text-gray-400">→</span>
                            <span class="text-sm text-gray-600"><?= htmlspecialchars($notif['recipient_name'] ?? 'User') ?></span>
                        </div>
                        <p class="text-sm text-gray-600"><?= htmlspecialchars($notif['message'] ?? '') ?></p>
                        <p class="text-xs text-gray-400 mt-2">
                            <i data-lucide="clock" class="w-3 h-3 inline"></i>
                            <?= isset($notif['created_at']) ? date('M d, Y g:i A', strtotime($notif['created_at'])) : 'N/A' ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php elseif ($activeTab === 'audit'): ?>
        <!-- Audit Logs Tab -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i data-lucide="scroll-text" class="w-6 h-6 text-orange-600"></i>
                System Audit Logs
            </h2>
        </div>
        
        <?php if (empty($auditLogs)): ?>
            <div class="text-center py-12">
                <i data-lucide="file-x" class="w-16 h-16 mx-auto mb-4 text-gray-300"></i>
                <p class="text-gray-500">No audit logs found</p>
                <p class="text-sm text-gray-400 mt-2">System actions will be logged here</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Action</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">User</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Details</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($auditLogs as $log): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-semibold">
                                    <?= htmlspecialchars($log['action'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($log['user_type'] ?? 'System') ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600 max-w-md truncate"><?= htmlspecialchars($log['details'] ?? '') ?></td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                <?= isset($log['timestamp']) ? date('M d, Y g:i A', strtotime($log['timestamp'])) : 'N/A' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
</div>
</main>

<script>
document.addEventListener("DOMContentLoaded", function() {
    if (window.lucide) lucide.createIcons();
});
</script>
</body>
</html>


