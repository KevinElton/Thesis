<?php
// File: /THESIS/app/notifications.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Notification.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../auth/login.php");
    exit;
}

$notification = new Notification();
$userId = $_SESSION['user_id'];
$userType = 'admin';

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'count') {
        echo json_encode(['count' => $notification->getUnreadCount($userId, $userType)]);
        exit;
    }
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_read'])) {
        $notification->markAsRead($_POST['notification_id']);
    } elseif (isset($_POST['mark_all_read'])) {
        $notification->markAllAsRead($userId, $userType);
    } elseif (isset($_POST['delete'])) {
        $notification->delete($_POST['notification_id']);
    }
    header("Location: notifications.php");
    exit;
}

// Fetch notifications
$notifications = $notification->getAll($userId, $userType);
$unreadCount = $notification->getUnreadCount($userId, $userType);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications - Admin Dashboard</title>
<script src="/Thesis/assets/js/tailwind.js"></script>
<script src="/Thesis/assets/js/lucide.min.js"></script>
<style>
@keyframes slideIn {
    from { opacity: 0; transform: translateX(-20px); }
    to { opacity: 1; transform: translateX(0); }
}
.notification-item {
    animation: slideIn 0.3s ease-out;
}
.unread {
    background: linear-gradient(to right, #eff6ff, #dbeafe);
    border-left: 4px solid #3b82f6;
}
</style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-blue-50 to-purple-50 min-h-screen flex">
<?php include 'sidebar.php'; ?>

<main class="flex-1 ml-64 p-8">
<div class="max-w-5xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-4xl font-bold mb-2 flex items-center gap-3 text-gray-800">
            <div class="p-3 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl shadow-lg">
                <i data-lucide="bell" class="w-8 h-8 text-white"></i>
            </div>
            Notifications
            <?php if ($unreadCount > 0): ?>
                <span class="px-4 py-2 bg-red-500 text-white text-lg font-bold rounded-full">
                    <?= $unreadCount ?> new
                </span>
            <?php endif; ?>
        </h1>
        <p class="text-gray-600 ml-16">Stay updated with system activities and panelist updates</p>
    </div>

    <!-- Actions -->
    <?php if ($unreadCount > 0): ?>
    <div class="mb-6 flex gap-3">
        <form method="POST" class="inline">
            <button type="submit" name="mark_all_read" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2 transition">
                <i data-lucide="check-check" class="w-4 h-4"></i>
                Mark All as Read
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Notifications List -->
    <div class="space-y-3">
        <?php if (empty($notifications)): ?>
            <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                <i data-lucide="inbox" class="w-20 h-20 mx-auto mb-4 text-gray-300"></i>
                <h3 class="text-xl font-bold text-gray-700 mb-2">No Notifications</h3>
                <p class="text-gray-600">You're all caught up! Check back later for updates.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <div class="notification-item bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition <?= !$notif['is_read'] ? 'unread' : '' ?>">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <?php
                                $iconClass = 'text-blue-600';
                                $icon = 'bell';
                                if ($notif['type'] === 'availability_update') {
                                    $icon = 'calendar-clock';
                                    $iconClass = 'text-green-600';
                                } elseif ($notif['type'] === 'assignment') {
                                    $icon = 'user-check';
                                    $iconClass = 'text-purple-600';
                                }
                                ?>
                                <div class="p-2 bg-gray-100 rounded-lg">
                                    <i data-lucide="<?= $icon ?>" class="w-5 h-5 <?= $iconClass ?>"></i>
                                </div>
                                <h3 class="font-bold text-gray-800"><?= htmlspecialchars($notif['title']) ?></h3>
                                <?php if (!$notif['is_read']): ?>
                                    <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">NEW</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-gray-600 text-sm mb-2 ml-12"><?= htmlspecialchars($notif['message']) ?></p>
                            <p class="text-xs text-gray-400 ml-12">
                                <i data-lucide="clock" class="w-3 h-3 inline"></i>
                                <?= date('F j, Y g:i A', strtotime($notif['created_at'])) ?>
                            </p>
                        </div>
                        
                        <div class="flex gap-2 ml-4">
                            <?php if (!$notif['is_read']): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="notification_id" value="<?= $notif['notification_id'] ?>">
                                    <button type="submit" name="mark_read" class="text-blue-600 hover:text-blue-800 p-2 hover:bg-blue-50 rounded-lg transition" title="Mark as read">
                                        <i data-lucide="check" class="w-5 h-5"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this notification?')">
                                <input type="hidden" name="notification_id" value="<?= $notif['notification_id'] ?>">
                                <button type="submit" name="delete" class="text-red-600 hover:text-red-800 p-2 hover:bg-red-50 rounded-lg transition" title="Delete">
                                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
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




