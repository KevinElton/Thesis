<?php
session_start();
require_once __DIR__ . '/../classes/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id'])) {
    $db = new Database();
    $conn = $db->connect();
    $group_id = $_GET['id'];
    
    try {
        $conn->beginTransaction();
        
        // Delete related records first (due to foreign keys)
        $conn->prepare("DELETE FROM assignment WHERE group_id = ?")->execute([$group_id]);
        $conn->prepare("DELETE FROM schedule WHERE group_id = ?")->execute([$group_id]);
        $conn->prepare("DELETE FROM thesis WHERE group_id = ?")->execute([$group_id]);
        $conn->prepare("DELETE FROM thesis_group WHERE group_id = ?")->execute([$group_id]);
        
        $conn->commit();
        
        $_SESSION['message'] = '<div class="bg-green-100 text-green-700 border border-green-400 p-4 rounded-lg mb-4">
                                Thesis group deleted successfully!
                                </div>';
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['message'] = '<div class="bg-red-100 text-red-700 border border-red-400 p-4 rounded-lg mb-4">
                                Error: ' . htmlspecialchars($e->getMessage()) . '
                                </div>';
    }
}

header("Location: manageGroups.php");
exit;
?>