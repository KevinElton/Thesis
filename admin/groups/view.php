<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../../auth/login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();

if (!isset($_GET['id'])) {
    header("Location: manage.php");
    exit;
}

$group_id = $_GET['id'];

// Get group details
$stmt = $conn->prepare("
    SELECT 
        tg.*,
        t.title as thesis_title,
        t.status as thesis_status,
        t.adviser_id,
        CONCAT(f.first_name, ' ', f.last_name) as adviser_name,
        f.email as adviser_email
    FROM thesis_group tg
    LEFT JOIN thesis t ON tg.group_id = t.group_id
    LEFT JOIN faculty f ON t.adviser_id = f.panelist_id
    WHERE tg.group_id = ?
");
$stmt->execute([$group_id]);
$group = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    header("Location: manage.php");
    exit;
}

// Get schedule history for this group
$stmt = $conn->prepare("
    SELECT 
        s.*,
        r.name as room_name,
        GROUP_CONCAT(CONCAT(f.first_name, ' ', f.last_name, ' (', a.role, ')') SEPARATOR ', ') as panelists
    FROM schedule s
    LEFT JOIN rooms r ON s.room_id = r.room_id
    LEFT JOIN assignment a ON s.schedule_id = a.schedule_id
    LEFT JOIN faculty f ON a.panelist_id = f.panelist_id
    WHERE s.group_id = ?
    GROUP BY s.schedule_id
    ORDER BY s.date DESC
");
$stmt->execute([$group_id]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Group Details</title>
    <script src="/Thesis/assets/js/tailwind.js"></script>
    <script src="/Thesis/assets/js/lucide.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">

<nav class="bg-gradient-to-r from-green-600 to-green-700 text-white p-4 shadow-lg">
    <div class="container mx-auto flex justify-between items-center">
        <h1 class="text-xl font-bold flex items-center gap-2">
            <i data-lucide="book-open"></i> Thesis Group Details
        </h1>
        <a href="manage.php" class="bg-white text-green-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition flex items-center gap-2">
            <i data-lucide="arrow-left"></i> Back
        </a>
    </div>
</nav>

<div class="container mx-auto p-8 max-w-6xl">
    
    <!-- Group Information Card -->
    <div class="bg-white rounded-2xl shadow-lg p-8 mb-6">
        <div class="flex justify-between items-start mb-6">
            <div>
                <span class="text-sm text-gray-500">Group ID: <?= htmlspecialchars($group['group_id']) ?></span>
                <h2 class="text-3xl font-bold text-gray-800 mt-1"><?= htmlspecialchars($group['leader_name']) ?></h2>
                <p class="text-lg text-gray-600 mt-1"><?= htmlspecialchars($group['course']) ?></p>
            </div>
            <span class="px-4 py-2 rounded-full text-sm font-semibold <?php 
                echo match($group['status'] ?? 'Active') {
                    'Approved' => 'bg-green-100 text-green-700',
                    'For Revision' => 'bg-yellow-100 text-yellow-700',
                    'Defended' => 'bg-blue-100 text-blue-700',
                    'Completed' => 'bg-purple-100 text-purple-700',
                    default => 'bg-gray-100 text-gray-700'
                };
            ?>">
                <?= htmlspecialchars($group['status'] ?? 'Active') ?>
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Thesis Title -->
            <div class="bg-blue-50 p-4 rounded-lg">
                <div class="flex items-center gap-2 mb-2">
                    <i data-lucide="file-text" class="w-5 h-5 text-blue-600"></i>
                    <h3 class="font-semibold text-gray-700">Thesis Title</h3>
                </div>
                <p class="text-gray-800"><?= htmlspecialchars($group['thesis_title'] ?? $group['title'] ?? 'No title provided') ?></p>
            </div>

            <!-- Adviser -->
            <div class="bg-green-50 p-4 rounded-lg">
                <div class="flex items-center gap-2 mb-2">
                    <i data-lucide="user-check" class="w-5 h-5 text-green-600"></i>
                    <h3 class="font-semibold text-gray-700">Thesis Adviser</h3>
                </div>
                <?php if ($group['adviser_name']): ?>
                    <p class="text-gray-800 font-semibold"><?= htmlspecialchars($group['adviser_name']) ?></p>
                    <p class="text-sm text-gray-600"><?= htmlspecialchars($group['adviser_email']) ?></p>
                <?php else: ?>
                    <p class="text-gray-500">Not assigned</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($group['created_at'])): ?>
        <div class="mt-4 pt-4 border-t text-sm text-gray-500">
            <i data-lucide="calendar" class="w-4 h-4 inline"></i>
            Created on <?= date('F j, Y \a\t g:i A', strtotime($group['created_at'])) ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Defense Schedule History -->
    <div class="bg-white rounded-2xl shadow-lg p-6">
        <h3 class="text-xl font-semibold mb-4 flex items-center gap-2 text-purple-600">
            <i data-lucide="calendar-clock"></i> Defense Schedule History
        </h3>
        
        <?php if (!empty($schedules)): ?>
            <div class="space-y-4">
                <?php foreach ($schedules as $sched): ?>
                    <div class="border-l-4 <?php 
                        echo match($sched['status']) {
                            'Confirmed' => 'border-green-500 bg-green-50',
                            'Completed' => 'border-blue-500 bg-blue-50',
                            'Cancelled' => 'border-red-500 bg-red-50',
                            default => 'border-yellow-500 bg-yellow-50'
                        };
                    ?> p-4 rounded-r-lg">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold <?php 
                                        echo match($sched['status']) {
                                            'Confirmed' => 'bg-green-600 text-white',
                                            'Completed' => 'bg-blue-600 text-white',
                                            'Cancelled' => 'bg-red-600 text-white',
                                            default => 'bg-yellow-600 text-white'
                                        };
                                    ?>"><?= htmlspecialchars($sched['status']) ?></span>
                                    <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded text-xs font-semibold">
                                        <?= htmlspecialchars($sched['defense_type']) ?>
                                    </span>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                                    <div>
                                        <p class="text-xs text-gray-500">Date</p>
                                        <p class="font-semibold text-gray-800"><?= date('M d, Y', strtotime($sched['date'])) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Time</p>
                                        <p class="font-semibold text-gray-800">
                                            <?= date('g:i A', strtotime($sched['time'])) ?> - <?= date('g:i A', strtotime($sched['end_time'])) ?>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Venue</p>
                                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($sched['room_name'] ?? 'TBA') ?></p>
                                    </div>
                                </div>
                                
                                <?php if ($sched['panelists']): ?>
                                <div class="mt-3">
                                    <p class="text-xs text-gray-500">Panel Members</p>
                                    <p class="text-sm text-gray-700"><?= htmlspecialchars($sched['panelists']) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <a href="../schedules/view.php?id=<?= $sched['schedule_id'] ?>" 
                               class="ml-4 text-blue-600 hover:text-blue-800 flex items-center gap-1">
                                <i data-lucide="eye" class="w-5 h-5"></i>
                                <span class="text-sm">View</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-12 bg-gray-50 rounded-lg">
                <i data-lucide="calendar-x" class="w-16 h-16 mx-auto mb-3 text-gray-400"></i>
                <p class="text-gray-600 text-lg font-medium mb-1">No defense scheduled yet</p>
                <p class="text-gray-500 text-sm mb-4">Schedule a defense for this group to get started</p>
                <a href="../schedules/manage.php" class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                    <i data-lucide="calendar-plus" class="w-5 h-5"></i>
                    Schedule Defense
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="bg-white rounded-2xl shadow-lg p-6 mt-6">
        <h3 class="text-xl font-semibold mb-4 flex items-center gap-2 text-gray-700">
            <i data-lucide="settings"></i> Actions
        </h3>
        <div class="flex gap-3">
            <a href="edit.php?id=<?= $group_id ?>" 
               class="bg-blue-500 text-white px-6 py-3 rounded-lg hover:bg-blue-600 transition flex items-center gap-2">
                <i data-lucide="edit"></i> Edit Group
            </a>
            <a href="../schedules/manage.php" 
               class="bg-green-500 text-white px-6 py-3 rounded-lg hover:bg-green-600 transition flex items-center gap-2">
                <i data-lucide="calendar-plus"></i> Schedule Defense
            </a>
            <button onclick="if(confirm('Delete this thesis group? This will also delete all related schedules!')) window.location.href='delete.php?id=<?= $group_id ?>'" 
                    class="bg-red-500 text-white px-6 py-3 rounded-lg hover:bg-red-600 transition flex items-center gap-2">
                <i data-lucide="trash-2"></i> Delete Group
            </button>
        </div>
    </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>







