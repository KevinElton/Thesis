<?php
// notification_bell.php - Premium Notification Bell Component
if (!isset($_SESSION)) session_start();

// Get user role and ID based on session
$user_type = 'admin';
$user_id = 0;

if (isset($_SESSION['admin_id'])) {
    $user_type = 'admin';
    $user_id = $_SESSION['admin_id'];
} elseif (isset($_SESSION['panelist_id'])) {
    $user_type = 'panelist';
    $user_id = $_SESSION['panelist_id'];
}

// Fetch unread notification count
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $conn = $db->connect();
    
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

<!-- Premium Notification Bell -->
<div class="notification-wrapper">
    <button id="notificationBell" class="notification-btn" title="Notifications">
        <div class="bell-icon-wrapper">
            <svg class="bell-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <?php if ($unread_count > 0): ?>
                <span id="notificationBadge" class="notification-badge pulse">
                    <?= $unread_count > 99 ? '99+' : $unread_count ?>
                </span>
            <?php endif; ?>
        </div>
    </button>

    <!-- Notification Dropdown Panel -->
    <div id="notificationDropdown" class="notification-dropdown hidden">
        <!-- Header -->
        <div class="dropdown-header">
            <div class="header-title">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <span>Notifications</span>
            </div>
            <button id="markAllRead" class="mark-all-btn">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                Mark all read
            </button>
        </div>
        
        <!-- Notification List -->
        <div id="notificationList" class="notification-list">
            <div class="empty-state">
                <div class="empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                </div>
                <p>Loading notifications...</p>
            </div>
        </div>
        
        <!-- Footer -->
        <a href="viewNotifications.php" class="dropdown-footer">
            View All Notifications
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </a>
    </div>
</div>

<style>
/* Notification Wrapper */
.notification-wrapper {
    position: relative;
    display: inline-block;
}

/* Bell Button */
.notification-btn {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0.05) 100%);
    border: 1px solid rgba(255,255,255,0.2);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    backdrop-filter: blur(10px);
}

.notification-btn:hover {
    background: linear-gradient(135deg, rgba(255,255,255,0.25) 0%, rgba(255,255,255,0.1) 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
}

.notification-btn:active {
    transform: translateY(0);
}

.bell-icon-wrapper {
    position: relative;
}

.bell-icon {
    width: 22px;
    height: 22px;
    color: white;
    transition: transform 0.3s ease;
}

.notification-btn:hover .bell-icon {
    animation: bellRing 0.5s ease;
}

@keyframes bellRing {
    0%, 100% { transform: rotate(0); }
    20% { transform: rotate(15deg); }
    40% { transform: rotate(-15deg); }
    60% { transform: rotate(10deg); }
    80% { transform: rotate(-10deg); }
}

/* Badge */
.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    font-size: 11px;
    font-weight: 700;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.5);
    border: 2px solid rgba(30, 58, 138, 0.8);
}

.notification-badge.pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
    50% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
}

/* Dropdown */
.notification-dropdown {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 360px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2), 0 0 0 1px rgba(0,0,0,0.05);
    z-index: 1000;
    overflow: hidden;
    opacity: 0;
    transform: translateY(-10px) scale(0.95);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.notification-dropdown.show {
    opacity: 1;
    transform: translateY(0) scale(1);
}

.notification-dropdown.hidden {
    display: none;
}

/* Dropdown Header */
.dropdown-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
    color: white;
}

.header-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 15px;
}

.mark-all-btn {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    background: rgba(255,255,255,0.2);
    border: none;
    border-radius: 8px;
    color: white;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
}

.mark-all-btn:hover {
    background: rgba(255,255,255,0.3);
}

/* Notification List */
.notification-list {
    max-height: 350px;
    overflow-y: auto;
}

.notification-list::-webkit-scrollbar {
    width: 6px;
}

.notification-list::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

/* Notification Item */
.notification-item {
    display: flex;
    gap: 12px;
    padding: 14px 20px;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: all 0.2s;
}

.notification-item:hover {
    background: #f8fafc;
}

.notification-item.unread {
    background: linear-gradient(90deg, #eff6ff 0%, #ffffff 100%);
    border-left: 3px solid #3b82f6;
}

.notif-icon {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notif-icon.info { background: #dbeafe; color: #2563eb; }
.notif-icon.success { background: #dcfce7; color: #16a34a; }
.notif-icon.warning { background: #fef3c7; color: #d97706; }
.notif-icon.error { background: #fee2e2; color: #dc2626; }

.notif-content {
    flex: 1;
    min-width: 0;
}

.notif-title {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.notif-message {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 6px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.notif-time {
    font-size: 11px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Empty State */
.empty-state {
    padding: 40px 20px;
    text-align: center;
    color: #94a3b8;
}

.empty-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 16px;
    background: #f1f5f9;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-icon svg {
    width: 30px;
    height: 30px;
    color: #cbd5e1;
}

.empty-state p {
    font-size: 14px;
}

/* Footer */
.dropdown-footer {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px;
    background: #f8fafc;
    color: #3b82f6;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    border-top: 1px solid #e2e8f0;
    transition: background 0.2s;
}

.dropdown-footer:hover {
    background: #f1f5f9;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bell = document.getElementById('notificationBell');
    const dropdown = document.getElementById('notificationDropdown');
    const notificationList = document.getElementById('notificationList');
    const badge = document.getElementById('notificationBadge');
    const markAllReadBtn = document.getElementById('markAllRead');

    // Toggle dropdown with animation
    bell.addEventListener('click', function(e) {
        e.stopPropagation();
        if (dropdown.classList.contains('hidden')) {
            dropdown.classList.remove('hidden');
            setTimeout(() => dropdown.classList.add('show'), 10);
            loadNotifications();
        } else {
            dropdown.classList.remove('show');
            setTimeout(() => dropdown.classList.add('hidden'), 200);
        }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('show');
            setTimeout(() => dropdown.classList.add('hidden'), 200);
        }
    });

    // Load notifications
    function loadNotifications() {
        // Determine the correct API path
        const currentPath = window.location.pathname;
        let apiPath = '/Thesis/api/get_notifications.php';
        
        fetch(apiPath)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayNotifications(data.notifications);
                    updateBadge(data.unread_count);
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                notificationList.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                        </div>
                        <p>Could not load notifications</p>
                    </div>
                `;
            });
    }

    // Display notifications
    function displayNotifications(notifications) {
        if (!notifications || notifications.length === 0) {
            notificationList.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                    </div>
                    <p>No notifications yet</p>
                </div>
            `;
            return;
        }

        notificationList.innerHTML = notifications.map(notif => {
            const iconType = notif.type || 'info';
            return `
                <div class="notification-item ${notif.is_read == 0 ? 'unread' : ''}" data-id="${notif.notification_id}">
                    <div class="notif-icon ${iconType}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            ${iconType === 'success' ? '<polyline points="20 6 9 17 4 12"></polyline>' :
                              iconType === 'warning' ? '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>' :
                              iconType === 'error' ? '<circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line>' :
                              '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>'}
                        </svg>
                    </div>
                    <div class="notif-content">
                        <div class="notif-title">${notif.title || 'Notification'}</div>
                        <div class="notif-message">${notif.message || ''}</div>
                        <div class="notif-time">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            ${notif.time_ago || 'Just now'}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        // Mark as read when clicked
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function() {
                const notifId = this.dataset.id;
                markAsRead(notifId);
            });
        });
    }

    // Mark notification as read
    function markAsRead(notifId) {
        fetch('/Thesis/api/mark_notification_read.php', {
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
        fetch('/Thesis/api/mark_all_read.php', {
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
        let currentBadge = document.getElementById('notificationBadge');
        if (count > 0) {
            if (currentBadge) {
                currentBadge.textContent = count > 99 ? '99+' : count;
                currentBadge.classList.remove('hidden');
            } else {
                const bellWrapper = document.querySelector('.bell-icon-wrapper');
                bellWrapper.insertAdjacentHTML('beforeend', `
                    <span id="notificationBadge" class="notification-badge pulse">
                        ${count > 99 ? '99+' : count}
                    </span>
                `);
            }
        } else if (currentBadge) {
            currentBadge.remove();
        }
    }

    // Auto-refresh every 30 seconds
    setInterval(function() {
        fetch('/Thesis/api/get_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateBadge(data.unread_count);
                }
            })
            .catch(() => {});
    }, 30000);
});
</script>
