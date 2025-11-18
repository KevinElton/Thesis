<?php
// File: /THESIS/includes/notification_bell.php
// Include this in your admin dashboard and panelist dashboard

require_once __DIR__ . '/../classes/Notification.php';

// Determine user info based on session
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') {
    $userId = $_SESSION['user_id'];
    $userType = 'admin';
    $notificationUrl = '../app/notifications.php';
} elseif (isset($_SESSION['panelist_id'])) {
    $userId = $_SESSION['panelist_id'];
    $userType = 'panelist';
    $notificationUrl = '../panelist/notifications.php';
} else {
    return; // No valid session
}

$notification = new Notification();
$unreadCount = $notification->getUnreadCount($userId, $userType);
?>

<!-- Notification Bell -->
<div class="relative">
    <a href="<?= $notificationUrl ?>" class="relative inline-flex items-center p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
        <i data-lucide="bell" class="w-6 h-6"></i>
        
        <?php if ($unreadCount > 0): ?>
            <span class="absolute top-0 right-0 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full transform translate-x-1/4 -translate-y-1/4 animate-pulse">
                <?= $unreadCount > 9 ? '9+' : $unreadCount ?>
            </span>
        <?php endif; ?>
    </a>
</div>

<script>
// Auto-refresh notification count every 30 seconds
setInterval(() => {
    fetch('<?= $notificationUrl ?>?ajax=count')
        .then(r => r.json())
        .then(data => {
            const bellIcon = document.querySelector('[data-lucide="bell"]');
            if (!bellIcon) return;
            
            const badge = bellIcon.parentElement.querySelector('.bg-red-500');
            
            if (data.count > 0) {
                if (badge) {
                    badge.textContent = data.count > 9 ? '9+' : data.count;
                } else {
                    // Create badge if it doesn't exist
                    const newBadge = document.createElement('span');
                    newBadge.className = 'absolute top-0 right-0 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full transform translate-x-1/4 -translate-y-1/4 animate-pulse';
                    newBadge.textContent = data.count > 9 ? '9+' : data.count;
                    bellIcon.parentElement.appendChild(newBadge);
                }
            } else if (badge) {
                badge.remove();
            }
        })
        .catch(err => console.error('Notification refresh failed:', err));
}, 30000);
</script>