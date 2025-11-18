<?php
// File: /THESIS/panelist/notifications.php
session_start();
require_once __DIR__ . '/../classes/database.php';
require_once __DIR__ . '/../classes/Notification.php';

if (!isset($_SESSION['panelist_id'])) {
    header("Location: ../app/login.php");
    exit;
}

$notification = new Notification();
$userId = $_SESSION['panelist_id'];
$userType = 'panelist';

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

// Get panelist name
$db = new Database();
$conn = $db->connect();
$stmt = $conn->prepare("SELECT first_name, last_name FROM panelist WHERE panelist_id = ?");
$stmt->execute([$userId]);
$panelist = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications - Panelist Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<style>
@keyframes slideIn {
    from { opacity: 0; transform: translateX(-20px); }
    to { opacity: 1; transform: translateX(0); }
}
.notification-item {
    animation: slideIn 0.3s ease-out;
}
.unread {
    background: linear-gradient(to right, #f0fdf4, #dcfce7);
    border-left: 4px solid #10b981;
}
</style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-purple-50 min-h-screen">

<!-- Navigation -->
<nav class="bg-gradient-to-r from-blue-600 to-purple-600 text-white shadow-lg">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-16">
      <div class="flex items-center gap-3">
        <i data-lucide="bell" class="w-7 h-7"></i>
        <div>
          <h1 class="font-bold text-lg">Notifications</h1>
          <p class="text-xs text-blue-100"><?= htmlspecialchars($panelist['first_name'] . ' ' . $panelist['last_name']) ?></p>
        </div>
      </div>
      <a href="dashboard.php" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg flex items-center gap-2 transition backdrop-blur-sm">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        <span class="hidden sm:inline">Back to Dashboard</span>
      </a>
    </div>
  </div>
</nav>

<div class="max-w-5xl mx-auto p-4 sm:p-6 lg:p-8">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <h2 class="text-3xl font-bold text-gray-800 flex items-center gap-2">
                Your Notifications
                <?php if ($unreadCount > 0): ?>
                    <span class="px-3 py-1 bg-green-500 text-white text-sm font-bold rounded-full">
                        <?= $unreadCount ?> new
                    </span>
                <?php endif; ?>
            </h2>
            
            <?php if ($unreadCount > 0): ?>
            <form method="POST" class="inline">
                <button type="submit" name="mark_all_read" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2 transition shadow-md">
                    <i data-lucide="check-check" class="w-4 h-4"></i>
                    Mark All as Read
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

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
                                if ($notif['type'] === 'assignment') {
                                    $icon = 'user-check';
                                    $iconClass = 'text-green-600';
                                } elseif ($notif['type'] === 'schedule') {
                                    $icon = 'calendar';
                                    $iconClass = 'text-purple-600';
                                }
                                ?>
                                <div class="p-2 bg-gray-100 rounded-lg">
                                    <i data-lucide="<?= $icon ?>" class="w-5 h-5 <?= $iconClass ?>"></i>
                                </div>
                                <h3 class="font-bold text-gray-800"><?= htmlspecialchars($notif['title']) ?></h3>
                                <?php if (!$notif['is_read']): ?>
                                    <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">NEW</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-gray-600 text-sm mb-2 ml-12 leading-relaxed"><?= htmlspecialchars($notif['message']) ?></p>
                            <div class="flex items-center gap-4 ml-12">
                                <p class="text-xs text-gray-400 flex items-center gap-1">
                                    <i data-lucide="clock" class="w-3 h-3"></i>
                                    <?= date('F j, Y g:i A', strtotime($notif['created_at'])) ?>
                                </p>
                                <?php if ($notif['type'] === 'assignment' && $notif['related_id']): ?>
                                    <a href="viewAssignment.php?group_id=<?= $notif['related_id'] ?>" class="text-xs text-blue-600 hover:text-blue-800 font-semibold flex items-center gap-1">
                                        <i data-lucide="external-link" class="w-3 h-3"></i>
                                        View Details
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="flex gap-2 ml-4">
                            <?php if (!$notif['is_read']): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="notification_id" value="<?= $notif['notification_id'] ?>">
                                    <button type="submit" name="mark_read" class="text-green-600 hover:text-green-800 p-2 hover:bg-green-50 rounded-lg transition" title="Mark as read">
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

<script>
document.addEventListener("DOMContentLoaded", function() {
    if (window.lucide) lucide.createIcons();
});
</script>
</body>
</html>