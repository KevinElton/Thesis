<?php
// File: /THESIS/panelist/update_availability.php
session_start();
require_once __DIR__ . '/../classes/database.php';
require_once __DIR__ . '/../classes/audit.php';
require_once __DIR__ . '/../classes/Notification.php';

if (!isset($_SESSION['panelist_id'])) {
    header("Location: ../app/login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();
$panelist_id = $_SESSION['panelist_id'];
$message = '';

// Get panelist details
$stmt = $conn->prepare("SELECT first_name, last_name, email FROM panelist WHERE panelist_id = ?");
$stmt->execute([$panelist_id]);
$panelist = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_slot'])) {
        $day = $_POST['day'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];

        // Validate time range
        if (strtotime($end_time) <= strtotime($start_time)) {
            $message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-4 flex items-start gap-3">
                <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0 mt-0.5"></i>
                <div><strong>Invalid Time Range:</strong> End time must be after start time.</div>
            </div>';
        } else {
            // Check for overlapping slots on the same day
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count FROM availability
                WHERE panelist_id = ? AND day = ?
                AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))
            ");
            $stmt->execute([$panelist_id, $day, $start_time, $start_time, $end_time, $end_time]);
            $overlap = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($overlap['count'] > 0) {
                $message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-4 flex items-start gap-3">
                    <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0 mt-0.5"></i>
                    <div><strong>Overlapping Time:</strong> This slot overlaps with an existing availability on ' . htmlspecialchars($day) . '.</div>
                </div>';
            } else {
                $stmt = $conn->prepare("INSERT INTO availability (panelist_id, day, start_time, end_time) VALUES (?, ?, ?, ?)");
                $stmt->execute([$panelist_id, $day, $start_time, $end_time]);
                
                $timeRange = date('g:i A', strtotime($start_time)) . ' - ' . date('g:i A', strtotime($end_time));
                
                $message = '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-4 flex items-start gap-3">
                    <i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0 mt-0.5"></i>
                    <div><strong>Success!</strong> Availability slot added for ' . htmlspecialchars($day) . ' from ' . $timeRange . '. Admin has been notified.</div>
                </div>';

                // Log audit action
                $audit = new Audit();
                $audit->logAction($panelist_id, 'Panelist', 'Add Availability', 'availability', $conn->lastInsertId(), "Added availability: $day $start_time - $end_time");
                
                // ✅ NOTIFY ADMIN - In-system + Email
                try {
                    $notification = new Notification();
                    $panelistName = $panelist['first_name'] . ' ' . $panelist['last_name'];
                    
                    // In-system notification + Email notification
                    $notification->notifyAdminAvailabilityUpdate(
                        $panelist_id,
                        $panelistName,
                        $panelist['email'],
                        $day,
                        $timeRange
                    );
                } catch (Exception $e) {
                    error_log("Notification failed: " . $e->getMessage());
                }
            }
        }
    } elseif (isset($_POST['delete_slot'])) {
        $availability_id = $_POST['availability_id'];
        
        // Get slot details before deletion
        $stmt = $conn->prepare("SELECT day, start_time, end_time FROM availability WHERE availability_id = ? AND panelist_id = ?");
        $stmt->execute([$availability_id, $panelist_id]);
        $slot = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $conn->prepare("DELETE FROM availability WHERE availability_id = ? AND panelist_id = ?");
        $stmt->execute([$availability_id, $panelist_id]);
        
        if ($slot) {
            $message = '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-4 flex items-start gap-3">
                <i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0 mt-0.5"></i>
                <div><strong>Deleted:</strong> ' . htmlspecialchars($slot['day']) . ' slot (' . date('g:i A', strtotime($slot['start_time'])) . ' - ' . date('g:i A', strtotime($slot['end_time'])) . ') removed successfully.</div>
            </div>';
        }

        // Log audit action
        $audit = new Audit();
        $audit->logAction($panelist_id, 'Panelist', 'Delete Availability', 'availability', $availability_id, 'Deleted availability slot');
    }
}

// Fetch current availability grouped by day
$stmt = $conn->prepare("SELECT * FROM availability WHERE panelist_id = ? ORDER BY FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), start_time");
$stmt->execute([$panelist_id]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by day
$availability_by_day = [];
foreach ($records as $r) {
    $availability_by_day[$r['day']][] = $r;
}

$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Update Availability - Panelist Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<style>
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-slide-in {
    animation: slideIn 0.3s ease-out;
}
.day-card {
    transition: all 0.3s ease;
}
.day-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}
</style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-purple-50 min-h-screen">

<!-- Navigation -->
<nav class="bg-gradient-to-r from-blue-600 to-purple-600 text-white shadow-lg">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-16">
      <div class="flex items-center gap-3">
        <i data-lucide="calendar-clock" class="w-7 h-7"></i>
        <div>
          <h1 class="font-bold text-lg">Manage Availability</h1>
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

<div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
  <?= $message ?>

  <!-- Info Banner -->
  <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg mb-6 animate-slide-in">
    <div class="flex items-start gap-3">
      <i data-lucide="info" class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5"></i>
      <div class="text-sm text-blue-800">
        <p class="font-semibold mb-1">Set your weekly availability schedule</p>
        <p>Your availability will be used by admins when assigning panel members to thesis defense schedules. <strong>Admin will be notified when you update your availability.</strong></p>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- Add New Slot Form -->
    <div class="lg:col-span-1">
      <div class="bg-white rounded-2xl shadow-lg p-6 sticky top-6">
        <h2 class="text-xl font-bold mb-6 flex items-center gap-2 text-gray-800">
          <div class="p-2 bg-green-100 rounded-lg">
            <i data-lucide="plus-circle" class="w-5 h-5 text-green-600"></i>
          </div>
          Add Time Slot
        </h2>
        
        <form method="POST" class="space-y-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Day of Week</label>
            <select name="day" required class="w-full border-2 border-gray-200 rounded-lg p-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition">
              <option value="">Select Day</option>
              <?php foreach ($days_of_week as $day): ?>
                <option value="<?= $day ?>"><?= $day ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Start Time</label>
            <input type="time" name="start_time" required class="w-full border-2 border-gray-200 rounded-lg p-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition">
          </div>
          
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">End Time</label>
            <input type="time" name="end_time" required class="w-full border-2 border-gray-200 rounded-lg p-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition">
          </div>
          
          <button type="submit" name="add_slot" class="w-full bg-gradient-to-r from-green-600 to-green-500 text-white px-4 py-3 rounded-lg hover:from-green-700 hover:to-green-600 flex items-center justify-center gap-2 font-semibold transition shadow-lg hover:shadow-xl">
            <i data-lucide="plus" class="w-5 h-5"></i>
            Add Availability Slot
          </button>
        </form>

        <!-- Quick Stats -->
        <div class="mt-6 pt-6 border-t border-gray-200">
          <div class="grid grid-cols-2 gap-4">
            <div class="text-center p-3 bg-blue-50 rounded-lg">
              <p class="text-2xl font-bold text-blue-600"><?= count($records) ?></p>
              <p class="text-xs text-gray-600 mt-1">Total Slots</p>
            </div>
            <div class="text-center p-3 bg-purple-50 rounded-lg">
              <p class="text-2xl font-bold text-purple-600"><?= count($availability_by_day) ?></p>
              <p class="text-xs text-gray-600 mt-1">Active Days</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Current Availability Schedule -->
    <div class="lg:col-span-2">
      <div class="bg-white rounded-2xl shadow-lg p-6">
        <h2 class="text-xl font-bold mb-6 flex items-center gap-2 text-gray-800">
          <div class="p-2 bg-blue-100 rounded-lg">
            <i data-lucide="calendar" class="w-5 h-5 text-blue-600"></i>
          </div>
          Your Weekly Schedule
        </h2>
        
        <?php if ($records): ?>
          <div class="space-y-4">
            <?php foreach ($days_of_week as $day): ?>
              <div class="day-card border-2 border-gray-200 rounded-xl p-4 <?= isset($availability_by_day[$day]) ? 'bg-gradient-to-r from-blue-50 to-purple-50 border-blue-300' : 'bg-gray-50' ?>">
                <div class="flex items-center justify-between mb-3">
                  <h3 class="font-bold text-gray-800 flex items-center gap-2">
                    <i data-lucide="calendar-days" class="w-5 h-5 <?= isset($availability_by_day[$day]) ? 'text-blue-600' : 'text-gray-400' ?>"></i>
                    <?= $day ?>
                  </h3>
                  <?php if (isset($availability_by_day[$day])): ?>
                    <span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">
                      <?= count($availability_by_day[$day]) ?> slot<?= count($availability_by_day[$day]) > 1 ? 's' : '' ?>
                    </span>
                  <?php else: ?>
                    <span class="px-3 py-1 bg-gray-200 text-gray-500 text-xs font-semibold rounded-full">
                      No availability
                    </span>
                  <?php endif; ?>
                </div>
                
                <?php if (isset($availability_by_day[$day])): ?>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <?php foreach ($availability_by_day[$day] as $slot): ?>
                      <div class="bg-white border border-gray-200 rounded-lg p-3 flex items-center justify-between hover:shadow-md transition">
                        <div class="flex items-center gap-2">
                          <i data-lucide="clock" class="w-4 h-4 text-blue-600"></i>
                          <span class="text-sm font-semibold text-gray-800">
                            <?= date('g:i A', strtotime($slot['start_time'])) ?>
                          </span>
                          <span class="text-gray-400">→</span>
                          <span class="text-sm font-semibold text-gray-800">
                            <?= date('g:i A', strtotime($slot['end_time'])) ?>
                          </span>
                        </div>
                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this time slot?')">
                          <input type="hidden" name="availability_id" value="<?= $slot['availability_id'] ?>">
                          <button type="submit" name="delete_slot" class="text-red-500 hover:text-red-700 hover:bg-red-50 p-1.5 rounded transition">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                          </button>
                        </form>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <p class="text-sm text-gray-500 italic flex items-center gap-2">
                    <i data-lucide="calendar-x" class="w-4 h-4"></i>
                    No time slots set for this day
                  </p>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-center py-12 bg-gradient-to-br from-gray-50 to-blue-50 rounded-xl border-2 border-dashed border-gray-300">
            <i data-lucide="calendar-x" class="w-16 h-16 mx-auto mb-4 text-gray-400"></i>
            <h3 class="text-lg font-bold text-gray-700 mb-2">No Availability Set</h3>
            <p class="text-gray-600 mb-4 max-w-md mx-auto">
              You haven't added any availability slots yet. Add your available times using the form on the left to be considered for panel assignments.
            </p>
            <div class="inline-flex items-center gap-2 text-sm text-blue-600">
              <i data-lucide="arrow-left" class="w-4 h-4"></i>
              Start by adding your first time slot
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    if (window.lucide) {
        lucide.createIcons();
    }
    
    // Auto-dismiss success messages after 5 seconds
    const messages = document.querySelectorAll('.bg-green-100');
    messages.forEach(msg => {
        setTimeout(() => {
            msg.style.transition = 'opacity 0.5s';
            msg.style.opacity = '0';
            setTimeout(() => msg.remove(), 500);
        }, 5000);
    });
});
</script>
</body>
</html>