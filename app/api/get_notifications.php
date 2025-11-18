<?php
session_start();
require_once __DIR__ . '/../../classes/database.php';

header('Content-Type: application/json');

// Determine user type and ID
$user_type = 'admin';
$user_id = 0;

if (isset($_SESSION['admin_id'])) {
    $user_type = 'admin';
    $user_id = $_SESSION['admin_id'];
} elseif (isset($_SESSION['panelist_id'])) {
    $user_type = 'panelist';
    $user_id = $_SESSION['panelist_id'];
}

try {
    $db = new Database();
    $conn = $db->connect(); // Changed from getConnection() to connect()
    
    // Get notifications
    $stmt = $conn->prepare("
        SELECT notification_id, title, message, is_read, type,
               CASE 
                   WHEN TIMESTAMPDIFF(MINUTE, created_at, NOW()) < 1 THEN 'Just now'
                   WHEN TIMESTAMPDIFF(MINUTE, created_at, NOW()) < 60 
                   THEN CONCAT(TIMESTAMPDIFF(MINUTE, created_at, NOW()), ' min ago')
                   WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) < 24 
                   THEN CONCAT(TIMESTAMPDIFF(HOUR, created_at, NOW()), ' hours ago')
                   ELSE CONCAT(TIMESTAMPDIFF(DAY, created_at, NOW()), ' days ago')
               END as time_ago
        FROM notifications 
        WHERE user_id = ? AND user_type = ?
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$user_id, $user_type]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unread count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND user_type = ? AND is_read = 0
    ");
    $stmt->execute([$user_id, $user_type]);
    $unread_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread_count
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}