<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode([]);
    exit;
}

$schedule_id = $_GET['schedule_id'] ?? null;
if (!$schedule_id) {
    echo json_encode([]);
    exit;
}

$db = new Database();
$conn = $db->connect();

// Get schedule details
$stmt = $conn->prepare("SELECT date, time, end_time FROM schedule WHERE schedule_id = ?");
$stmt->execute([$schedule_id]);
$schedule = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$schedule) {
    echo json_encode([]);
    exit;
}

$start_time = $schedule['time'];
$end_time = $schedule['end_time'];
$date = $schedule['date'];

// Fetch all faculty panelists who are available at this time
$stmt = $conn->prepare("
    SELECT f.panelist_id, CONCAT(f.first_name,' ',f.last_name) AS name, f.expertise
    FROM faculty f
    LEFT JOIN availability a ON f.panelist_id = a.panelist_id AND a.day = DAYNAME(?)
    WHERE a.start_time <= ? AND a.end_time >= ?
    
");
$stmt->execute([$date, $start_time, $end_time]);
$available_panelists = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($available_panelists);







