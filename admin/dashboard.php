<?php
require_once __DIR__ . '/../includes/security.php';

// Initialize secure session
initSecureSession();
setSecurityHeaders();

// Require admin authentication
requireAuth('Admin');

require_once __DIR__ . '/../classes/faculty.php';
require_once __DIR__ . '/../config/database.php';

function tableExists($conn, $tableName) {
    try {
        $stmt = $conn->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

$faculty = new Faculty();
$db = new Database();
$conn = $db->connect();

// Get real statistics
try {
    $totalFaculty = (int) $faculty->getFacultyCount();

    // Count upcoming schedules (next 7 days)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM schedule WHERE status IN ('Pending', 'Confirmed') AND date >= CURDATE() AND date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
    $stmt->execute();
    $upcomingSchedules = (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Count thesis groups
    $stmt = $conn->query("SELECT COUNT(*) as count FROM thesis_group");
    $totalGroups = (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Count completed defenses
    $stmt = $conn->query("SELECT COUNT(*) as count FROM schedule WHERE status = 'Completed'");
    $totalCompleted = (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get workload overview - panelists with most assignments
    $stmt = $conn->query("
        SELECT CONCAT(f.first_name, ' ', f.last_name) as name, COUNT(a.assignment_id) as workload
        FROM faculty f
        LEFT JOIN assignment a ON f.panelist_id = a.panelist_id
        GROUP BY f.panelist_id
        ORDER BY workload DESC LIMIT 5
    ");
    $workloadStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent activities from audit logs if available
    $recentActivities = [];
    if (tableExists($conn, 'audit_logs')) {
        $stmt = $conn->query("
            SELECT action as type, details as name, timestamp
            FROM audit_logs
            ORDER BY timestamp DESC LIMIT 5
        ");
        $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Fallback to faculty additions
        $stmt = $conn->query("
            SELECT 'faculty' as type, CONCAT(first_name, ' ', last_name) as name, Date as timestamp
            FROM faculty
            ORDER BY Date DESC LIMIT 3
        ");
        $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $totalFaculty = 0;
    $upcomingSchedules = 0;
    $totalGroups = 0;
    $totalCompleted = 0;
    $workloadStats = [];
    $recentActivities = [];
}

// Get admin info
$admin_name = "Admin User";
if (isset($_SESSION['admin_id'])) {
    try {
        $stmt = $conn->prepare("SELECT email FROM admin WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($admin) {
            $admin_name = $admin['email'];
        }
    } catch (PDOException $e) {
        // If column doesn't exist, use default name
        $admin_name = "Administrator";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Admin Dashboard</title>
    <script src="/Thesis/assets/js/tailwind.js"></script>
    <script src="/Thesis/assets/js/lucide.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

    <?php include 'sidebar.php'; ?>



    <!-- Main Content -->
    <main class="flex-1 ml-64 p-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-2 flex items-center gap-3">
                <i data-lucide="layout-dashboard" class="w-10 h-10 text-blue-600"></i>
                Dashboard Overview
            </h1>
            <p class="text-gray-600">Welcome back! Here's what's happening with your thesis scheduling system.</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Faculty -->
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-lg rounded-2xl p-6 hover:shadow-xl transition transform hover:-translate-y-1">
                <div class="flex justify-between items-start mb-4">
                    <div class="bg-white bg-opacity-30 p-3 rounded-xl">
                        <i data-lucide="users" class="w-8 h-8"></i>
                    </div>
                    <span class="text-xs bg-white bg-opacity-20 px-2 py-1 rounded-full">Active</span>
                </div>
                <p class="text-3xl font-bold mb-1"><?= htmlspecialchars($totalFaculty) ?></p>
                <p class="text-blue-100 text-sm">Total Faculty</p>
            </div>

            <!-- Upcoming Schedules -->
            <div class="bg-gradient-to-br from-green-500 to-green-600 text-white shadow-lg rounded-2xl p-6 hover:shadow-xl transition transform hover:-translate-y-1">
                <div class="flex justify-between items-start mb-4">
                    <div class="bg-white bg-opacity-30 p-3 rounded-xl">
                        <i data-lucide="calendar" class="w-8 h-8"></i>
                    </div>
                    <span class="text-xs bg-white bg-opacity-20 px-2 py-1 rounded-full">Upcoming</span>
                </div>
                <p class="text-3xl font-bold mb-1"><?= htmlspecialchars($upcomingSchedules) ?></p>
                <p class="text-green-100 text-sm">Pending Schedules</p>
            </div>

            <!-- Thesis Groups -->
            <div class="bg-gradient-to-br from-yellow-500 to-orange-500 text-white shadow-lg rounded-2xl p-6 hover:shadow-xl transition transform hover:-translate-y-1">
                <div class="flex justify-between items-start mb-4">
                    <div class="bg-white bg-opacity-30 p-3 rounded-xl">
                        <i data-lucide="book-open" class="w-8 h-8"></i>
                    </div>
                    <span class="text-xs bg-white bg-opacity-20 px-2 py-1 rounded-full">Registered</span>
                </div>
                <p class="text-3xl font-bold mb-1"><?= htmlspecialchars($totalGroups) ?></p>
                <p class="text-yellow-100 text-sm">Thesis Groups</p>
            </div>

            <!-- Completed Defenses -->
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 text-white shadow-lg rounded-2xl p-6 hover:shadow-xl transition transform hover:-translate-y-1">
                <div class="flex justify-between items-start mb-4">
                    <div class="bg-white bg-opacity-30 p-3 rounded-xl">
                        <i data-lucide="check-circle" class="w-8 h-8"></i>
                    </div>
                    <span class="text-xs bg-white bg-opacity-20 px-2 py-1 rounded-full">Done</span>
                </div>
                <p class="text-3xl font-bold mb-1"><?= htmlspecialchars($totalCompleted) ?></p>
                <p class="text-purple-100 text-sm">Completed Defenses</p>
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Recent Activities -->
            <div class="lg:col-span-2 bg-white shadow-lg rounded-2xl p-6">
                <h2 class="text-xl font-semibold mb-5 flex items-center gap-2 text-gray-800">
                    <i data-lucide="activity" class="w-6 h-6 text-blue-600"></i>
                    Recent Activities
                </h2>
                <div class="space-y-4">
                    <?php if (!empty($recentActivities)): ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                <div class="bg-blue-100 p-3 rounded-full">
                                    <i data-lucide="user-plus" class="w-5 h-5 text-blue-600"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="font-medium text-gray-800">New faculty added: <strong><?= htmlspecialchars($activity['name']) ?></strong></p>
                                    <p class="text-sm text-gray-500"><?= date('F j, Y g:i A', strtotime($activity['timestamp'])) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-2 text-gray-400"></i>
                            <p>No recent activities</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white shadow-lg rounded-2xl p-6">
                <h2 class="text-xl font-semibold mb-5 flex items-center gap-2 text-gray-800">
                    <i data-lucide="zap" class="w-6 h-6 text-yellow-600"></i>
                    Quick Actions
                </h2>
                <div class="space-y-3">
                    <a href="faculty/add.php" class="menu-link">
    <i data-lucide="users"></i>
    Manage Panelists
</a>
                    <a href="groups/manage.php" class="flex items-center gap-3 p-3 bg-green-50 rounded-lg hover:bg-green-100 transition">
                        <i data-lucide="users" class="w-5 h-5 text-green-600"></i>
                        <span class="font-medium text-gray-700">Create Thesis Group</span>
                    </a>
                    <a href="schedules/manage.php" class="flex items-center gap-3 p-3 bg-purple-50 rounded-lg hover:bg-purple-100 transition">
                        <i data-lucide="calendar-plus" class="w-5 h-5 text-purple-600"></i>
                        <span class="font-medium text-gray-700">Schedule Defense</span>
                    </a>
                    <a href="reports.php" class="flex items-center gap-3 p-3 bg-orange-50 rounded-lg hover:bg-orange-100 transition">
                        <i data-lucide="file-text" class="w-5 h-5 text-orange-600"></i>
                        <span class="font-medium text-gray-700">Generate Report</span>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>






