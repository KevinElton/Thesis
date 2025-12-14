<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';

// Initialize secure session
initSecureSession();
setSecurityHeaders();

// Require panelist authentication
requireAuth('Panelist');

$db = new Database();
$conn = $db->connect();
$panelist_id = $_SESSION['panelist_id'];

// Get panelist info
$stmt = $conn->prepare("SELECT * FROM panelist WHERE panelist_id = ?");
$stmt->execute([$panelist_id]);
$panelist = $stmt->fetch(PDO::FETCH_ASSOC);

// Get assignments with schedule information
$stmt = $conn->prepare("
    SELECT a.*, tg.leader_name, t.title as thesis_title, 
           tg.course, s.date as schedule_date, s.time as schedule_time, 
           s.end_time as schedule_end_time, s.room
    FROM assignment a
    INNER JOIN thesis_group tg ON a.group_id = tg.group_id
    LEFT JOIN thesis t ON tg.group_id = t.group_id
    LEFT JOIN schedule s ON tg.group_id = s.group_id
    WHERE a.panelist_id = ?
    ORDER BY s.date DESC, s.time DESC
");
$stmt->execute([$panelist_id]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get availability count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM availability WHERE panelist_id = ?");
$stmt->execute([$panelist_id]);
$availability_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get upcoming defenses
$stmt = $conn->prepare("
    SELECT a.*, tg.leader_name, t.title as thesis_title, 
           s.date as schedule_date, s.time as schedule_time, 
           s.end_time as schedule_end_time, s.room
    FROM assignment a
    INNER JOIN thesis_group tg ON a.group_id = tg.group_id
    LEFT JOIN thesis t ON tg.group_id = t.group_id
    INNER JOIN schedule s ON tg.group_id = s.group_id
    WHERE a.panelist_id = ? AND s.date >= CURDATE()
    ORDER BY s.date ASC, s.time ASC
    LIMIT 5
");
$stmt->execute([$panelist_id]);
$upcoming_defenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panelist Dashboard</title>
<script src="/Thesis/assets/js/tailwind.js"></script>
<script src="/Thesis/assets/js/lucide.min.js"></script>
<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.fade-in {
    animation: fadeIn 0.5s ease-out;
}
.stat-card {
    transition: all 0.3s ease;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
}
</style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-purple-50 to-pink-50 min-h-screen">

<!-- Navigation -->
<nav class="bg-gradient-to-r from-blue-600 via-purple-600 to-pink-600 text-white shadow-2xl">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-16">
      <div class="flex items-center gap-3">
        <div class="p-2 bg-white/20 rounded-lg backdrop-blur-sm">
          <i data-lucide="layout-dashboard" class="w-6 h-6"></i>
        </div>
        <div>
          <h1 class="font-bold text-lg">Panelist Dashboard</h1>
          <p class="text-xs text-blue-100">Welcome back, <?= htmlspecialchars($panelist['first_name']) ?></p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <a href="update_availability.php" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg flex items-center gap-2 transition backdrop-blur-sm">
          <i data-lucide="calendar-clock" class="w-4 h-4"></i>
          <span class="hidden sm:inline">Manage Availability</span>
        </a>
        <a href="../auth/logout.php" class="bg-red-500/80 hover:bg-red-600 px-4 py-2 rounded-lg flex items-center gap-2 transition">
          <i data-lucide="log-out" class="w-4 h-4"></i>
          <span class="hidden sm:inline">Logout</span>
        </a>
      </div>
    </div>
  </div>
</nav>

<div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
  <!-- Welcome Section -->
  <div class="mb-8 fade-in">
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl shadow-2xl p-8 text-white">
      <div class="flex items-center gap-4">
        <div class="p-4 bg-white/20 rounded-2xl backdrop-blur-sm">
          <i data-lucide="user-circle" class="w-16 h-16"></i>
        </div>
        <div>
          <h2 class="text-3xl font-bold mb-2"><?= htmlspecialchars($panelist['first_name'] . ' ' . $panelist['last_name']) ?></h2>
          <p class="text-blue-100 text-lg"><?= htmlspecialchars($panelist['expertise'] ?? 'Panelist') ?></p>
          <p class="text-blue-200 text-sm mt-1">
            <i data-lucide="mail" class="inline w-4 h-4"></i>
            <?= htmlspecialchars($panelist['email']) ?>
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Statistics Cards -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="stat-card bg-white rounded-2xl shadow-lg p-6 fade-in">
      <div class="flex items-center justify-between mb-4">
        <div class="p-3 bg-blue-100 rounded-xl">
          <i data-lucide="briefcase" class="w-8 h-8 text-blue-600"></i>
        </div>
        <span class="text-3xl font-bold text-blue-600"><?= count($assignments) ?></span>
      </div>
      <h3 class="text-gray-600 font-semibold">Total Assignments</h3>
      <p class="text-sm text-gray-500 mt-1">All panel assignments</p>
    </div>

    <div class="stat-card bg-white rounded-2xl shadow-lg p-6 fade-in" style="animation-delay: 0.1s;">
      <div class="flex items-center justify-between mb-4">
        <div class="p-3 bg-green-100 rounded-xl">
          <i data-lucide="calendar-check" class="w-8 h-8 text-green-600"></i>
        </div>
        <span class="text-3xl font-bold text-green-600"><?= count($upcoming_defenses) ?></span>
      </div>
      <h3 class="text-gray-600 font-semibold">Upcoming Defenses</h3>
      <p class="text-sm text-gray-500 mt-1">Scheduled defenses</p>
    </div>

    <div class="stat-card bg-white rounded-2xl shadow-lg p-6 fade-in" style="animation-delay: 0.2s;">
      <div class="flex items-center justify-between mb-4">
        <div class="p-3 bg-purple-100 rounded-xl">
          <i data-lucide="clock" class="w-8 h-8 text-purple-600"></i>
        </div>
        <span class="text-3xl font-bold text-purple-600"><?= $availability_count ?></span>
      </div>
      <h3 class="text-gray-600 font-semibold">Availability Slots</h3>
      <p class="text-sm text-gray-500 mt-1">Time slots set</p>
      <a href="update_availability.php" class="text-xs text-purple-600 hover:text-purple-800 mt-2 inline-flex items-center gap-1">
        <i data-lucide="edit" class="w-3 h-3"></i> Update
      </a>
    </div>
  </div>

  <!-- Main Content Grid -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Upcoming Defenses -->
    <div class="bg-white rounded-2xl shadow-lg p-6 fade-in" style="animation-delay: 0.3s;">
      <h2 class="text-2xl font-bold mb-6 flex items-center gap-2 text-gray-800">
        <div class="p-2 bg-green-100 rounded-lg">
          <i data-lucide="calendar-days" class="w-6 h-6 text-green-600"></i>
        </div>
        Upcoming Defenses
      </h2>

      <?php if ($upcoming_defenses): ?>
        <div class="space-y-4">
          <?php foreach ($upcoming_defenses as $defense): ?>
            <div class="border-2 border-gray-200 rounded-xl p-4 hover:border-blue-400 transition hover:shadow-md">
              <div class="flex justify-between items-start mb-3">
                <div>
                  <h3 class="font-bold text-gray-800"><?= htmlspecialchars($defense['leader_name']) ?></h3>
                  <?php if ($defense['thesis_title']): ?>
                    <p class="text-sm text-gray-600 italic mt-1"><?= htmlspecialchars($defense['thesis_title']) ?></p>
                  <?php endif; ?>
                </div>
                <span class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded-full">
                  <?= htmlspecialchars($defense['role']) ?>
                </span>
              </div>
              
              <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="flex items-center gap-2 text-gray-600">
                  <i data-lucide="calendar" class="w-4 h-4 text-blue-600"></i>
                  <span><?= date('M j, Y', strtotime($defense['schedule_date'])) ?></span>
                </div>
                <div class="flex items-center gap-2 text-gray-600">
                  <i data-lucide="clock" class="w-4 h-4 text-blue-600"></i>
                  <span>
                    <?= date('g:i A', strtotime($defense['schedule_time'])) ?>
                    <?php if ($defense['schedule_end_time']): ?>
                      - <?= date('g:i A', strtotime($defense['schedule_end_time'])) ?>
                    <?php endif; ?>
                  </span>
                </div>
                <?php if ($defense['room']): ?>
                  <div class="flex items-center gap-2 text-gray-600 col-span-2">
                    <i data-lucide="map-pin" class="w-4 h-4 text-blue-600"></i>
                    <span><?= htmlspecialchars($defense['room']) ?></span>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="text-center py-12 bg-gray-50 rounded-xl">
          <i data-lucide="calendar-x" class="w-16 h-16 mx-auto mb-4 text-gray-400"></i>
          <p class="text-gray-600 font-semibold">No Upcoming Defenses</p>
          <p class="text-sm text-gray-500 mt-2">You don't have any scheduled defenses at the moment.</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- All Assignments -->
    <div class="bg-white rounded-2xl shadow-lg p-6 fade-in" style="animation-delay: 0.4s;">
      <h2 class="text-2xl font-bold mb-6 flex items-center gap-2 text-gray-800">
        <div class="p-2 bg-purple-100 rounded-lg">
          <i data-lucide="list-checks" class="w-6 h-6 text-purple-600"></i>
        </div>
        All Assignments
      </h2>

      <?php if ($assignments): ?>
        <div class="space-y-3 max-h-[600px] overflow-y-auto pr-2">
          <?php foreach ($assignments as $assignment): ?>
            <div class="border border-gray-200 rounded-lg p-4 hover:border-purple-400 transition hover:shadow-sm">
              <div class="flex justify-between items-start mb-2">
                <div class="flex-1">
                  <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($assignment['leader_name']) ?></h3>
                  <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($assignment['course']) ?></p>
                </div>
                <span class="px-2.5 py-1 bg-purple-100 text-purple-700 text-xs font-bold rounded-full">
                  <?= htmlspecialchars($assignment['role']) ?>
                </span>
              </div>
              
              <?php if ($assignment['thesis_title']): ?>
                <p class="text-sm text-gray-600 italic mb-2 line-clamp-2"><?= htmlspecialchars($assignment['thesis_title']) ?></p>
              <?php endif; ?>
              
              <?php if ($assignment['schedule_date']): ?>
                <div class="flex items-center gap-4 text-xs text-gray-600 mt-3 pt-3 border-t border-gray-200">
                  <span class="flex items-center gap-1">
                    <i data-lucide="calendar" class="w-3 h-3"></i>
                    <?= date('M j, Y', strtotime($assignment['schedule_date'])) ?>
                  </span>
                  <span class="flex items-center gap-1">
                    <i data-lucide="clock" class="w-3 h-3"></i>
                    <?= date('g:i A', strtotime($assignment['schedule_time'])) ?>
                    <?php if ($assignment['schedule_end_time']): ?>
                      - <?= date('g:i A', strtotime($assignment['schedule_end_time'])) ?>
                    <?php endif; ?>
                  </span>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="text-center py-12 bg-gray-50 rounded-xl">
          <i data-lucide="inbox" class="w-16 h-16 mx-auto mb-4 text-gray-400"></i>
          <p class="text-gray-600 font-semibold">No Assignments Yet</p>
          <p class="text-sm text-gray-500 mt-2">You haven't been assigned to any thesis defenses.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Availability Reminder -->
  <?php if ($availability_count === 0): ?>
    <div class="mt-6 bg-yellow-50 border-l-4 border-yellow-500 p-6 rounded-lg fade-in" style="animation-delay: 0.5s;">
      <div class="flex items-start gap-4">
        <i data-lucide="alert-triangle" class="w-8 h-8 text-yellow-600 flex-shrink-0"></i>
        <div>
          <h3 class="font-bold text-yellow-800 mb-2">Set Your Availability</h3>
          <p class="text-yellow-700 mb-4">You haven't set your availability yet. Please update your available time slots so admins can assign you to thesis defense panels.</p>
          <a href="update_availability.php" class="inline-flex items-center gap-2 bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg font-semibold transition">
            <i data-lucide="calendar-plus" class="w-4 h-4"></i>
            Set Availability Now
          </a>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    if (window.lucide) {
        lucide.createIcons();
    }
});
</script>
</body>
</html>



