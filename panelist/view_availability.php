<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['panelist_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();
$panelist_id = $_SESSION['panelist_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $day = $_POST['day'];
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];

    $stmt = $conn->prepare("INSERT INTO availability (panelist_id, day, start_time, end_time) VALUES (?, ?, ?, ?)");
    $stmt->execute([$panelist_id, $day, $start, $end]);
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Update Availability</title>
<script src="/Thesis/assets/js/tailwind.js"></script>
<script src="/Thesis/assets/js/lucide.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
<nav class="bg-green-600 text-white p-4 flex justify-between items-center shadow-md">
  <div class="flex items-center gap-2">
    <i data-lucide="calendar-clock" class="w-6 h-6"></i>
    <h1 class="font-semibold text-lg">Panelist Availability</h1>
  </div>
  <a href="dashboard.php" class="bg-white text-green-700 px-3 py-1 rounded-lg hover:bg-gray-100 flex items-center gap-1">
    <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
  </a>
</nav>

<main class="flex-grow flex justify-center items-center p-8">
  <div class="bg-white p-8 rounded-2xl shadow-lg w-full max-w-md border border-gray-200">
    <div class="text-center mb-6">
      <i data-lucide="clock" class="w-10 h-10 mx-auto text-green-600"></i>
      <h2 class="text-2xl font-semibold text-gray-800 mt-2">Set Your Availability</h2>
      <p class="text-gray-500 text-sm mt-1">Please fill in the times when you’re available to serve as a panelist.</p>
    </div>

    <form method="POST" class="space-y-5">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Day</label>
        <select name="day" required class="w-full border-gray-300 rounded-lg px-3 py-2 border focus:ring-green-500 focus:outline-none">
          <option value="">Select a day</option>
          <option>Monday</option>
          <option>Tuesday</option>
          <option>Wednesday</option>
          <option>Thursday</option>
          <option>Friday</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Start Time</label>
        <input type="time" name="start_time" required class="w-full border-gray-300 rounded-lg px-3 py-2 border focus:ring-green-500 focus:outline-none">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">End Time</label>
        <input type="time" name="end_time" required class="w-full border-gray-300 rounded-lg px-3 py-2 border focus:ring-green-500 focus:outline-none">
      </div>

      <button type="submit" class="bg-green-600 text-white w-full py-2.5 rounded-lg hover:bg-green-700 flex justify-center items-center gap-2 font-medium">
        <i data-lucide="save" class="w-5 h-5"></i> Save Availability
      </button>
    </form>
  </div>
</main>

<footer class="bg-gray-50 text-center py-3 text-sm text-gray-500 border-t border-gray-200">
  © 2025 Thesis Scheduling System
</footer>

<script>lucide.createIcons();</script>
</body>
</html>




