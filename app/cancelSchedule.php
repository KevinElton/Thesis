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
    $schedule_id = $_GET['id'];
    
    try {
        // Update schedule status to Cancelled instead of deleting
        $stmt = $conn->prepare("UPDATE schedule SET status = 'Cancelled' WHERE schedule_id = ?");
        $stmt->execute([$schedule_id]);
        
        $_SESSION['message'] = '<div class="bg-green-100 text-green-700 border border-green-400 p-4 rounded-lg mb-4">
                                <i data-lucide="check-circle" class="inline w-5 h-5 mr-2"></i>
                                Schedule cancelled successfully!
                                </div>';
    } catch (PDOException $e) {
        $_SESSION['message'] = '<div class="bg-red-100 text-red-700 border border-red-400 p-4 rounded-lg mb-4">
                                <i data-lucide="alert-triangle" class="inline w-5 h-5 mr-2"></i>
                                Error: ' . htmlspecialchars($e->getMessage()) . '
                                </div>';
    }
}

header("Location: manageSchedules.php");
exit;
?>