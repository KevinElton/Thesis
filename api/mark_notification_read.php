<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$user_type = 'admin';
$user_id = 0;

if (isset($_SESSION['admin_id'])) {
    $user_type = 'admin';
    $user_id = $_SESSION['admin_id'];
} elseif (isset($_SESSION['panelist_id'])) {
    $user_type = 'panelist';
    $user_id = $_SESSION['panelist_id'];
}

$data = json_decode(file_get_contents('php://input'), true);
$notification_id = $data['notification_id'] ?? 0;

try {
    $db = new Database();
    $pdo = $db->connect();
    
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE notification_id = ? AND user_id = ? AND user_type = ?
    ");
    $stmt->execute([$notification_id, $user_id, $user_type]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


