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
$assignment_id = $_GET['assignment_id'] ?? null;

if (!$assignment_id) {
    header("Location: dashboard.php");
    exit;
}

// Get assignment details with all related info
$stmt = $conn->prepare("
    SELECT 
        a.*,
        tg.*,
        t.title as thesis_title,
        t.abstract,
        CONCAT(adv.first_name, ' ', adv.last_name) as adviser_name,
        adv.email as adviser_email,
        s.schedule_id,
        s.date as defense_date,
        s.time as defense_time,
        s.end_time,
        s.defense_type,
        s.status as schedule_status,
        r.name as room_name,
        r.mode as room_mode,
        r.location_details
    FROM assignment a
    INNER JOIN thesis_group tg ON a.group_id = tg.group_id
    LEFT JOIN thesis t ON tg.group_id = t.group_id
    LEFT JOIN faculty adv ON t.adviser_id = adv.panelist_id
    LEFT JOIN schedule s ON tg.group_id = s.group_id
    LEFT JOIN rooms r ON s.room_id = r.room_id
    WHERE a.assignment_id = ? AND a.panelist_id = ?
");
$stmt->execute([$assignment_id, $panelist_id]);
$assignment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assignment) {
    header("Location: dashboard.php");
    exit;
}

// Get all panel members for this group
$stmt = $conn->prepare("
    SELECT a.role, CONCAT(f.first_name, ' ', f.last_name) as name, f.email, f.expertise
    FROM assignment a
    INNER JOIN faculty f ON a.panelist_id = f.panelist_id
    WHERE a.group_id = ?
    ORDER BY FIELD(a.role, 'Chair', 'Critic', 'Member')
");
$stmt->execute([$assignment['group_id']]);
$panel_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Assignment Details</title>
<script src="/Thesis/assets/js/tailwind.js"></script>
<script src="/Thesis/assets/js/lucide.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">

<nav class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-4 shadow-lg">
  <div class="container mx-auto flex justify-between items-center">
    <h1 class="text-xl font-bold flex items-center gap-2">
      <i data-lucide="file-text"></i> Assignment Details
    </h1>
    <a href="dashboard.php" class="bg-white text-indigo-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition flex items-center gap-2">
      <i data-lucide="arrow-left"></i> Back to Dashboard
    </a>
  </div>
</nav>

<div class="container mx-auto p-8 max-w-5xl space-y-6">
  
  <!-- Header Card -->
  <div class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-2xl shadow-lg p-6">
    <div class="flex justify-between items-start">
      <div>
        <p class="text-sm opacity-80">Group #<?= $assignment['group_id'] ?></p>
        <h2 class="text-3xl font-bold mt-1"><?= htmlspecialchars($assignment['leader_name']) ?></h2>
        <p class="mt-1 text-lg"><?= htmlspecialchars($assignment['course']) ?></p>
      </div>
      <div class="text-right">
        <span class="px-4 py-2 bg-white bg-opacity-20 rounded-full text-sm font-bold block mb-2">
          Your Role: <?= htmlspecialchars($assignment['role']) ?>
        </span>
        <span class="px-3 py-1 bg-white bg-opacity-20 rounded-full text-xs font-semibold">
          <?= htmlspecialchars($assignment['status']) ?>
        </span>
      </div>
    </div>
  </div>

  <!-- Thesis Information -->
  <div class="bg-white rounded-2xl shadow-lg p-6">
    <h3 class="text-xl font-bold mb-4 flex items-center gap-2 text-indigo-600">
      <i data-lucide="book-open"></i> Thesis Information
    </h3>
    
    <div class="space-y-4">
      <div>
        <label class="text-sm font-semibold text-gray-500">Title</label>
        <p class="text-gray-800 text-lg"><?= htmlspecialchars($assignment['thesis_title'] ?? $assignment['title'] ?? 'No title provided') ?></p>
      </div>
      
      <?php if ($assignment['abstract']): ?>
      <div>
        <label class="text-sm font-semibold text-gray-500">Abstract</label>
        <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($assignment['abstract'])) ?></p>
      </div>
      <?php endif; ?>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-blue-50 p-4 rounded-lg">
          <label class="text-sm font-semibold text-blue-700 flex items-center gap-2 mb-2">
            <i data-lucide="user-check" class="w-4 h-4"></i> Thesis Adviser
          </label>
          <p class="font-semibold text-gray-800"><?= htmlspecialchars($assignment['adviser_name'] ?? 'Not assigned') ?></p>
          <?php if ($assignment['adviser_email']): ?>
            <p class="text-sm text-gray-600"><?= htmlspecialchars($assignment['adviser_email']) ?></p>
          <?php endif; ?>
        </div>
        
        <div class="bg-green-50 p-4 rounded-lg">
          <label class="text-sm font-semibold text-green-700 flex items-center gap-2 mb-2">
            <i data-lucide="calendar" class="w-4 h-4"></i> Assignment Date
          </label>
          <p class="font-semibold text-gray-800"><?= date('F d, Y', strtotime($assignment['assigned_date'])) ?></p>
          <p class="text-sm text-gray-600"><?= date('g:i A', strtotime($assignment['assigned_date'])) ?></p>
        </div>
      </div>
    </div>
  </div>

  <!-- Defense Schedule -->
  <?php if ($assignment['schedule_id']): ?>
  <div class="bg-white rounded-2xl shadow-lg p-6">
    <h3 class="text-xl font-bold mb-4 flex items-center gap-2 text-purple-600">
      <i data-lucide="calendar-days"></i> Defense Schedule
    </h3>
    
    <div class="bg-purple-50 rounded-lg p-5">
      <div class="flex justify-between items-start mb-4">
        <span class="px-3 py-1 bg-purple-600 text-white rounded-full text-sm font-bold">
          <?= htmlspecialchars($assignment['defense_type']) ?>
        </span>
        <span class="px-3 py-1 rounded-full text-sm font-bold <?php 
          echo match($assignment['schedule_status']) {
            'Confirmed' => 'bg-green-600 text-white',
            'Completed' => 'bg-blue-600 text-white',
            'Cancelled' => 'bg-red-600 text-white',
            default => 'bg-yellow-600 text-white'
          };
        ?>"><?= htmlspecialchars($assignment['schedule_status']) ?></span>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg p-4">
          <div class="flex items-center gap-2 mb-2">
            <i data-lucide="calendar" class="w-5 h-5 text-purple-600"></i>
            <span class="text-sm text-gray-500">Date</span>
          </div>
          <p class="text-lg font-bold text-gray-800"><?= date('F d, Y', strtotime($assignment['defense_date'])) ?></p>
          <p class="text-sm text-gray-500"><?= date('l', strtotime($assignment['defense_date'])) ?></p>
        </div>
        
        <div class="bg-white rounded-lg p-4">
          <div class="flex items-center gap-2 mb-2">
            <i data-lucide="clock" class="w-5 h-5 text-purple-600"></i>
            <span class="text-sm text-gray-500">Time</span>
          </div>
          <p class="text-lg font-bold text-gray-800">
            <?= date('g:i A', strtotime($assignment['defense_time'])) ?>
          </p>
          <?php if ($assignment['end_time']): ?>
            <p class="text-sm text-gray-500">Until <?= date('g:i A', strtotime($assignment['end_time'])) ?></p>
          <?php endif; ?>
        </div>
        
        <div class="bg-white rounded-lg p-4">
          <div class="flex items-center gap-2 mb-2">
            <i data-lucide="map-pin" class="w-5 h-5 text-purple-600"></i>
            <span class="text-sm text-gray-500">Venue</span>
          </div>
          <p class="text-lg font-bold text-gray-800"><?= htmlspecialchars($assignment['room_name'] ?? 'TBA') ?></p>
          <?php if ($assignment['room_mode']): ?>
            <p class="text-sm text-gray-500"><?= htmlspecialchars($assignment['room_mode']) ?></p>
          <?php endif; ?>
          <?php if ($assignment['location_details']): ?>
            <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($assignment['location_details']) ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php else: ?>
  <div class="bg-yellow-50 border-l-4 border-yellow-400 p-5 rounded-r-lg">
    <div class="flex items-center gap-2 mb-2">
      <i data-lucide="calendar-x" class="w-5 h-5 text-yellow-600"></i>
      <h4 class="font-bold text-yellow-800">No Defense Scheduled</h4>
    </div>
    <p class="text-yellow-700 text-sm">The defense schedule for this thesis group has not been set yet. You will be notified once it's scheduled.</p>
  </div>
  <?php endif; ?>

  <!-- Panel Members -->
  <div class="bg-white rounded-2xl shadow-lg p-6">
    <h3 class="text-xl font-bold mb-4 flex items-center gap-2 text-blue-600">
      <i data-lucide="users"></i> Panel Members
    </h3>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <?php foreach ($panel_members as $member): ?>
        <div class="border-2 rounded-lg p-4 <?php 
          echo match($member['role']) {
            'Chair' => 'border-blue-300 bg-blue-50',
            'Critic' => 'border-green-300 bg-green-50',
            'Member' => 'border-yellow-300 bg-yellow-50',
            default => 'border-gray-300 bg-gray-50'
          };
        ?>">
          <span class="px-2 py-1 rounded text-xs font-bold <?php 
            echo match($member['role']) {
              'Chair' => 'bg-blue-600 text-white',
              'Critic' => 'bg-green-600 text-white',
              'Member' => 'bg-yellow-600 text-white',
              default => 'bg-gray-600 text-white'
            };
          ?>"><?= htmlspecialchars($member['role']) ?></span>
          <p class="font-bold text-gray-800 mt-3"><?= htmlspecialchars($member['name']) ?></p>
          <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($member['expertise']) ?></p>
          <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($member['email']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<script>lucide.createIcons();</script>
</body>
</html>



