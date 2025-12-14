<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Notification.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../auth/login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();
$notification = new Notification();
$userId = $_SESSION['user_id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_read'])) {
        $notification->markAsRead($_POST['notification_id']);
        header("Location: viewNotifications.php?success=marked");
        exit;
    } elseif (isset($_POST['mark_all_read'])) {
        $notification->markAllAsRead($userId, 'admin');
        header("Location: viewNotifications.php?success=all_marked");
        exit;
    } elseif (isset($_POST['delete'])) {
        $notification->delete($_POST['notification_id']);
        header("Location: viewNotifications.php?success=deleted");
        exit;
    } elseif (isset($_POST['delete_all_read'])) {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND user_type = 'admin' AND is_read = 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        header("Location: viewNotifications.php?success=cleared");
        exit;
    }
}

// Get filter parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT n.*, 
          CASE 
              WHEN n.type = 'assignment' THEN tg.title
              WHEN n.type = 'availability_update' THEN CONCAT(p.first_name, ' ', p.last_name)
              WHEN n.type = 'schedule' THEN CONCAT('Defense on ', DATE_FORMAT(s.date, '%M %d, %Y'))
              ELSE NULL
          END as related_info,
          CASE 
              WHEN n.type = 'assignment' THEN tg.course
              WHEN n.type = 'availability_update' THEN p.department
              WHEN n.type = 'schedule' THEN s.venue
              ELSE NULL
          END as extra_info
          FROM notifications n
          LEFT JOIN thesis_group tg ON n.type = 'assignment' AND n.related_id = tg.group_id
          LEFT JOIN panelist p ON n.type = 'availability_update' AND n.related_id = p.panelist_id
          LEFT JOIN schedule s ON n.type = 'schedule' AND n.related_id = s.schedule_id
          WHERE n.user_id = ? AND n.user_type = 'admin'";

$params = [$userId];
$types = "i";

// Apply filters
if ($filter === 'unread') {
    $query .= " AND n.is_read = 0";
} elseif ($filter === 'read') {
    $query .= " AND n.is_read = 1";
}

if ($type !== 'all') {
    $query .= " AND n.type = ?";
    $params[] = $type;
    $types .= "s";
}

if (!empty($search)) {
    $query .= " AND (n.title LIKE ? OR n.message LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
}

$query .= " ORDER BY n.is_read ASC, n.created_at DESC LIMIT 100";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$notifications = $stmt->get_result();

// Get statistics
$stats = [];
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
    SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read,
    SUM(CASE WHEN type = 'assignment' THEN 1 ELSE 0 END) as assignments,
    SUM(CASE WHEN type = 'availability_update' THEN 1 ELSE 0 END) as availability,
    SUM(CASE WHEN type = 'schedule' THEN 1 ELSE 0 END) as schedules,
    SUM(CASE WHEN type = 'evaluation' THEN 1 ELSE 0 END) as evaluations,
    SUM(CASE WHEN type = 'reminder' THEN 1 ELSE 0 END) as reminders,
    SUM(CASE WHEN type = 'general' THEN 1 ELSE 0 END) as general
    FROM notifications 
    WHERE user_id = ? AND user_type = 'admin'";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("i", $userId);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Notifications - Admin Dashboard</title>
    <script src="/Thesis/assets/js/tailwind.js"></script>
    <script src="/Thesis/assets/js/lucide.min.js"></script>
    <style>
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert { animation: slideDown 0.3s ease-out; }
        .notification-card {
            transition: all 0.3s ease;
        }
        .notification-card:hover {
            transform: translateX(5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-blue-50 to-purple-50 min-h-screen flex">
    <?php include 'sidebar.php'; ?>

    <main class="flex-1 ml-64 p-8">
        <div class="max-w-7xl mx-auto">
            
            <!-- Success Messages -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg flex items-center gap-3">
                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                    <span>
                        <?php
                        switch ($_GET['success']) {
                            case 'marked': echo 'Notification marked as read!'; break;
                            case 'all_marked': echo 'All notifications marked as read!'; break;
                            case 'deleted': echo 'Notification deleted successfully!'; break;
                            case 'cleared': echo 'All read notifications cleared!'; break;
                        }
                        ?>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-4xl font-bold mb-2 flex items-center gap-3 text-gray-800">
                    <div class="p-3 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl shadow-lg">
                        <i data-lucide="bell" class="w-8 h-8 text-white"></i>
                    </div>
                    All Notifications
                    <?php if ($stats['unread'] > 0): ?>
                        <span class="px-4 py-2 bg-red-500 text-white text-lg font-bold rounded-full animate-pulse">
                            <?= $stats['unread'] ?> new
                        </span>
                    <?php endif; ?>
                </h1>
                <p class="text-gray-600 ml-16">Manage and view all system notifications</p>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-md p-4 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Total</p>
                            <p class="text-2xl font-bold text-gray-800"><?= $stats['total'] ?></p>
                        </div>
                        <i data-lucide="inbox" class="w-8 h-8 text-blue-500"></i>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Unread</p>
                            <p class="text-2xl font-bold text-gray-800"><?= $stats['unread'] ?></p>
                        </div>
                        <i data-lucide="mail" class="w-8 h-8 text-red-500"></i>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Assignments</p>
                            <p class="text-2xl font-bold text-gray-800"><?= $stats['assignments'] ?></p>
                        </div>
                        <i data-lucide="user-check" class="w-8 h-8 text-green-500"></i>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Availability</p>
                            <p class="text-2xl font-bold text-gray-800"><?= $stats['availability'] ?></p>
                        </div>
                        <i data-lucide="calendar-clock" class="w-8 h-8 text-purple-500"></i>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border-l-4 border-orange-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Schedules</p>
                            <p class="text-2xl font-bold text-gray-800"><?= $stats['schedules'] ?></p>
                        </div>
                        <i data-lucide="calendar" class="w-8 h-8 text-orange-500"></i>
                    </div>
                </div>
            </div>

            <!-- Filters and Actions -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <form method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Read Status Filter -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                            <select name="filter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Notifications</option>
                                <option value="unread" <?= $filter === 'unread' ? 'selected' : '' ?>>Unread Only</option>
                                <option value="read" <?= $filter === 'read' ? 'selected' : '' ?>>Read Only</option>
                            </select>
                        </div>

                        <!-- Type Filter -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Type</label>
                            <select name="type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All Types</option>
                                <option value="assignment" <?= $type === 'assignment' ? 'selected' : '' ?>>Panel Assignments</option>
                                <option value="availability_update" <?= $type === 'availability_update' ? 'selected' : '' ?>>Availability Updates</option>
                                <option value="schedule" <?= $type === 'schedule' ? 'selected' : '' ?>>Schedule Changes</option>
                                <option value="evaluation" <?= $type === 'evaluation' ? 'selected' : '' ?>>Evaluations</option>
                                <option value="reminder" <?= $type === 'reminder' ? 'selected' : '' ?>>Reminders</option>
                                <option value="general" <?= $type === 'general' ? 'selected' : '' ?>>General</option>
                            </select>
                        </div>

                        <!-- Search -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Search</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Search notifications..." 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <!-- Apply Button -->
                        <div class="flex items-end">
                            <button type="submit" class="w-full bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition flex items-center justify-center gap-2">
                                <i data-lucide="filter" class="w-4 h-4"></i>
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Quick Actions -->
                <div class="mt-4 pt-4 border-t border-gray-200 flex flex-wrap gap-3">
                    <?php if ($stats['unread'] > 0): ?>
                        <form method="POST" class="inline">
                            <button type="submit" name="mark_all_read" 
                                    class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition flex items-center gap-2">
                                <i data-lucide="check-check" class="w-4 h-4"></i>
                                Mark All as Read
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($stats['read'] > 0): ?>
                        <form method="POST" class="inline" onsubmit="return confirm('Delete all read notifications? This action cannot be undone.');">
                            <button type="submit" name="delete_all_read" 
                                    class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition flex items-center gap-2">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                Clear All Read
                            </button>
                        </form>
                    <?php endif; ?>

                    <a href="viewNotifications.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition flex items-center gap-2">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        Reset Filters
                    </a>
                </div>
            </div>

            <!-- Notifications List -->
            <div class="space-y-3">
                <?php if ($notifications->num_rows === 0): ?>
                    <div class="bg-white rounded-2xl shadow-lg p-16 text-center">
                        <i data-lucide="inbox" class="w-24 h-24 mx-auto mb-6 text-gray-300"></i>
                        <h3 class="text-2xl font-bold text-gray-700 mb-3">No Notifications Found</h3>
                        <p class="text-gray-600 mb-6">
                            <?php
                            if (!empty($search)) {
                                echo "No notifications match your search criteria.";
                            } elseif ($filter === 'unread') {
                                echo "You're all caught up! No unread notifications.";
                            } else {
                                echo "Try adjusting your filters to see more notifications.";
                            }
                            ?>
                        </p>
                        <a href="viewNotifications.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                            View All Notifications
                        </a>
                    </div>
                <?php else: ?>
                    <?php while ($notif = $notifications->fetch_assoc()): ?>
                        <div class="notification-card bg-white rounded-xl shadow-md p-6 hover:shadow-xl <?= !$notif['is_read'] ? 'border-l-4 border-blue-500 bg-gradient-to-r from-blue-50 to-white' : '' ?>">
                            <div class="flex items-start gap-4">
                                <!-- Icon -->
                                <div class="flex-shrink-0">
                                    <?php
                                    $iconConfig = [
                                        'assignment' => ['icon' => 'user-check', 'bg' => 'bg-green-100', 'text' => 'text-green-600'],
                                        'availability_update' => ['icon' => 'calendar-clock', 'bg' => 'bg-purple-100', 'text' => 'text-purple-600'],
                                        'schedule' => ['icon' => 'calendar', 'bg' => 'bg-orange-100', 'text' => 'text-orange-600'],
                                        'evaluation' => ['icon' => 'clipboard-check', 'bg' => 'bg-yellow-100', 'text' => 'text-yellow-600'],
                                        'reminder' => ['icon' => 'bell-ring', 'bg' => 'bg-red-100', 'text' => 'text-red-600'],
                                        'general' => ['icon' => 'info', 'bg' => 'bg-gray-100', 'text' => 'text-gray-600'],
                                    ];
                                    $config = $iconConfig[$notif['type']] ?? $iconConfig['general'];
                                    ?>
                                    <div class="w-12 h-12 rounded-xl <?= $config['bg'] ?> flex items-center justify-center">
                                        <i data-lucide="<?= $config['icon'] ?>" class="w-6 h-6 <?= $config['text'] ?>"></i>
                                    </div>
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-4 mb-2">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-1">
                                                <h3 class="font-bold text-lg text-gray-800">
                                                    <?= htmlspecialchars($notif['title']) ?>
                                                </h3>
                                                <?php if (!$notif['is_read']): ?>
                                                    <span class="px-2 py-1 bg-blue-500 text-white text-xs font-bold rounded-full">NEW</span>
                                                <?php endif; ?>
                                                <span class="px-2 py-1 bg-gray-200 text-gray-700 text-xs font-semibold rounded-full uppercase">
                                                    <?= str_replace('_', ' ', $notif['type']) ?>
                                                </span>
                                            </div>
                                            <p class="text-gray-600 mb-2">
                                                <?= htmlspecialchars($notif['message']) ?>
                                            </p>
                                            
                                            <?php if ($notif['related_info']): ?>
                                                <div class="flex items-center gap-4 text-sm text-gray-500 mb-2">
                                                    <span class="flex items-center gap-1">
                                                        <i data-lucide="tag" class="w-4 h-4"></i>
                                                        <?= htmlspecialchars($notif['related_info']) ?>
                                                    </span>
                                                    <?php if ($notif['extra_info']): ?>
                                                        <span class="flex items-center gap-1">
                                                            <i data-lucide="info" class="w-4 h-4"></i>
                                                            <?= htmlspecialchars($notif['extra_info']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <p class="text-xs text-gray-400 flex items-center gap-1">
                                                <i data-lucide="clock" class="w-3 h-3"></i>
                                                <?= date('F j, Y g:i A', strtotime($notif['created_at'])) ?>
                                                <span class="mx-2">•</span>
                                                <?= $notification->timeAgo($notif['created_at']) ?>
                                            </p>
                                        </div>

                                        <!-- Actions -->
                                        <div class="flex items-center gap-2">
                                            <?php if (!$notif['is_read']): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="notification_id" value="<?= $notif['notification_id'] ?>">
                                                    <button type="submit" name="mark_read" 
                                                            class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition"
                                                            title="Mark as read">
                                                        <i data-lucide="check" class="w-5 h-5"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" class="inline" onsubmit="return confirm('Delete this notification?');">
                                                <input type="hidden" name="notification_id" value="<?= $notif['notification_id'] ?>">
                                                <button type="submit" name="delete" 
                                                        class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition"
                                                        title="Delete">
                                                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>

            <!-- Showing results info -->
            <?php if ($notifications->num_rows > 0): ?>
                <div class="mt-6 text-center text-gray-600">
                    Showing <?= $notifications->num_rows ?> notification(s)
                    <?php if ($filter !== 'all' || $type !== 'all' || !empty($search)): ?>
                        <span class="text-blue-600 font-semibold">with filters applied</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            if (window.lucide) lucide.createIcons();
            
            // Auto-dismiss success messages after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
        });
    </script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>




