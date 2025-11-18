<?php
// notification_bell.php - Notification Bell Component
if (!isset($_SESSION)) session_start();

// Get user role and ID based on session
$user_type = 'admin'; // Default
$user_id = 0;

if (isset($_SESSION['admin_id'])) {
    $user_type = 'admin';
    $user_id = $_SESSION['admin_id'];
} elseif (isset($_SESSION['panelist_id'])) {
    $user_type = 'panelist';
    $user_id = $_SESSION['panelist_id'];
}

// Fetch unread notification count
require_once __DIR__ . '/../database.php';

try {
    $db = new Database();
    $conn = $db->connect(); // Changed from getConnection() to connect()
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE user_id = ? AND user_type = ? AND is_read = 0
    ");
    $stmt->execute([$user_id, $user_type]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $unread_count = $result['unread_count'] ?? 0;
} catch (PDOException $e) {
    $unread_count = 0;
}
?>

<!-- Notification Bell -->
<div class="relative notification-bell-container">
    <button id="notificationBell" class="relative p-2 rounded-lg hover:bg-blue-600 transition focus:outline-none focus:ring-2 focus:ring-blue-400">
        <i data-lucide="bell" class="w-6 h-6 text-white"></i>
        <?php if ($unread_count > 0): ?>
            <span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                <?= $unread_count > 9 ? '9+' : $unread_count ?>
            </span>
        <?php endif; ?>
    </button>

    <!-- Notification Dropdown -->
    <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl z-50 max-h-96 overflow-y-auto border border-gray-200">
        <div class="p-3 border-b border-gray-200 flex justify-between items-center bg-gray-50">
            <h3 class="font-semibold text-gray-800">Notifications</h3>
            <button id="markAllRead" class="text-xs text-blue-600 hover:text-blue-800 hover:underline">Mark all as read</button>
        </div>
        <div id="notificationList" class="divide-y divide-gray-100">
            <div class="p-4 text-center text-gray-500">
                <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 text-gray-400"></i>
                <p class="text-sm">Loading notifications...</p>
            </div>
        </div>
    </div>
</div>

<style>
    .notification-bell-container {
        display: inline-block;
    }
    
    .notification-item {
        padding: 0.75rem 1rem;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    .notification-item:hover {
        background-color: #f3f4f6;
    }
    
    .notification-item.unread {
        background-color: #eff6ff;
        border-left: 3px solid #3b82f6;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bell = document.getElementById('notificationBell');
    const dropdown = document.getElementById('notificationDropdown');
    const notificationList = document.getElementById('notificationList');
    const badge = document.getElementById('notificationBadge');
    const markAllReadBtn = document.getElementById('markAllRead');

    // Toggle dropdown
    bell.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('hidden');
        if (!dropdown.classList.contains('hidden')) {
            loadNotifications();
        }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });

    // Load notifications
    function loadNotifications() {
        fetch('api/get_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayNotifications(data.notifications);
                    updateBadge(data.unread_count);
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                notificationList.innerHTML = '<div class="p-4 text-center text-red-500 text-sm">Failed to load notifications</div>';
            });
    }

    // Display notifications
    function displayNotifications(notifications) {
        if (notifications.length === 0) {
            notificationList.innerHTML = `
                <div class="p-8 text-center text-gray-500">
                    <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-2 text-gray-300"></i>
                    <p class="text-sm">No notifications</p>
                </div>
            `;
            lucide.createIcons();
            return;
        }

        notificationList.innerHTML = notifications.map(notif => `
            <div class="notification-item ${notif.is_read == 0 ? 'unread' : ''}" data-id="${notif.notification_id}">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 mt-1">
                        <div class="w-2 h-2 bg-blue-600 rounded-full ${notif.is_read == 1 ? 'opacity-0' : ''}"></div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">${notif.title}</p>
                        <p class="text-xs text-gray-600 mt-1 line-clamp-2">${notif.message}</p>
                        <p class="text-xs text-gray-400 mt-1">${notif.time_ago}</p>
                    </div>
                </div>
            </div>
        `).join('');

        // Mark as read when clicked
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function() {
                const notifId = this.dataset.id;
                markAsRead(notifId);
            });
        });

        lucide.createIcons();
    }

    // Mark notification as read
    function markAsRead(notifId) {
        fetch('api/mark_notification_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notification_id: notifId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
            }
        });
    }

    // Mark all as read
    markAllReadBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        fetch('api/mark_all_read.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
            }
        });
    });

    // Update badge
    function updateBadge(count) {
        if (count > 0) {
            if (badge) {
                badge.textContent = count > 9 ? '9+' : count;
                badge.classList.remove('hidden');
            } else {
                bell.insertAdjacentHTML('beforeend', `
                    <span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                        ${count > 9 ? '9+' : count}
                    </span>
                `);
            }
        } else if (badge) {
            badge.classList.add('hidden');
        }
    }

    // Auto-refresh every 30 seconds
    setInterval(function() {
        fetch('api/get_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateBadge(data.unread_count);
                }
            })
            .catch(error => console.error('Error refreshing notifications:', error));
    }, 30000);
});
</script>