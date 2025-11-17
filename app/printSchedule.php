<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../classes/database.php';

$db = new Database();
$conn = $db->connect();

// Get date range filter
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d', strtotime('+30 days'));

// Get all schedules within date range
$schedulesQuery = "SELECT 
    s.schedule_id,
    s.date,
    s.time,
    s.end_time,
    s.room,
    s.defense_type,
    s.status,
    s.mode,
    tg.group_id,
    tg.title as group_title,
    tg.leader_name,
    tg.members,
    tg.course
FROM schedule s
JOIN thesis_group tg ON s.group_id = tg.group_id
WHERE s.date BETWEEN :start_date AND :end_date
ORDER BY s.date, s.time";

$stmt = $conn->prepare($schedulesQuery);
$stmt->bindParam(':start_date', $startDate);
$stmt->bindParam(':end_date', $endDate);
$stmt->execute();
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get panelists for each schedule
foreach ($schedules as &$schedule) {
    $panelQuery = "SELECT 
        p.panelist_id,
        p.first_name,
        p.last_name,
        p.email,
        p.contact_number,
        a.role
    FROM assignment a
    JOIN panelist p ON a.panelist_id = p.panelist_id
    WHERE a.schedule_id = :sid
    ORDER BY 
        CASE a.role
            WHEN 'Chair' THEN 1
            WHEN 'Critic' THEN 2
            WHEN 'Member' THEN 3
        END";
    
    $panelStmt = $conn->prepare($panelQuery);
    $panelStmt->bindParam(':sid', $schedule['schedule_id']);
    $panelStmt->execute();
    $schedule['panelists'] = $panelStmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Defense Schedule Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            .page-break {
                page-break-after: always;
            }
        }
        @page {
            size: A4;
            margin: 1cm;
        }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Control Panel (No Print) -->
    <div class="no-print bg-white shadow-lg p-6 mb-6">
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-bold text-gray-800">Defense Schedule Report</h2>
                <div class="flex gap-3">
                    <button onclick="window.print()" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        Print Report
                    </button>
                    <a href="reports.php?tab=schedules" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 flex items-center gap-2">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Back
                    </a>
                </div>
            </div>
            
            <!-- Date Range Filter -->
            <form method="GET" class="flex gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                    <input type="date" name="start_date" value="<?= $startDate ?>" 
                           class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                    <input type="date" name="end_date" value="<?= $endDate ?>" 
                           class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    Filter
                </button>
            </form>
        </div>
    </div>

    <!-- Printable Content -->
    <div class="max-w-7xl mx-auto bg-white p-8">
        
        <!-- Header -->
        <div class="text-center mb-8 border-b-2 border-gray-800 pb-6">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">THESIS DEFENSE SCHEDULE</h1>
            <p class="text-lg text-gray-700">Academic Year 2024-2025 | 2nd Semester</p>
            <p class="text-sm text-gray-600 mt-2">
                Date Range: <?= date('F d, Y', strtotime($startDate)) ?> - <?= date('F d, Y', strtotime($endDate)) ?>
            </p>
            <p class="text-xs text-gray-500 mt-2">Generated: <?= date('F d, Y h:i A') ?></p>
        </div>

        <!-- Summary Statistics -->
        <div class="grid grid-cols-3 gap-4 mb-8">
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                <p class="text-sm text-gray-600">Total Schedules</p>
                <p class="text-3xl font-bold text-blue-600"><?= count($schedules) ?></p>
            </div>
            <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                <p class="text-sm text-gray-600">Confirmed</p>
                <p class="text-3xl font-bold text-green-600">
                    <?= count(array_filter($schedules, fn($s) => $s['status'] === 'Confirmed')) ?>
                </p>
            </div>
            <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                <p class="text-sm text-gray-600">Pending</p>
                <p class="text-3xl font-bold text-yellow-600">
                    <?= count(array_filter($schedules, fn($s) => $s['status'] === 'Pending')) ?>
                </p>
            </div>
        </div>

        <?php if (empty($schedules)): ?>
            <div class="text-center py-12 text-gray-500">
                <i data-lucide="calendar-x" class="w-16 h-16 mx-auto mb-4 text-gray-400"></i>
                <p class="text-lg">No schedules found for the selected date range</p>
            </div>
        <?php else: ?>
            <!-- Schedule Details -->
            <?php 
            $currentDate = null;
            foreach ($schedules as $index => $schedule): 
                $scheduleDate = date('Y-m-d', strtotime($schedule['date']));
                
                // Print date header if date changes
                if ($currentDate !== $scheduleDate):
                    $currentDate = $scheduleDate;
            ?>
                <div class="mt-8 mb-4">
                    <h2 class="text-2xl font-bold text-gray-800 border-b-2 border-gray-300 pb-2">
                        <?= date('l, F d, Y', strtotime($schedule['date'])) ?>
                    </h2>
                </div>
            <?php endif; ?>

            <div class="mb-6 border-2 border-gray-300 rounded-lg p-6 <?= ($index + 1) % 2 === 0 ? 'page-break' : '' ?>">
                <!-- Schedule Header -->
                <div class="flex justify-between items-start mb-4">
                    <div class="flex-1">
                        <h3 class="text-xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($schedule['group_title']) ?></h3>
                        <p class="text-gray-600">
                            <span class="font-semibold">Group Leader:</span> <?= htmlspecialchars($schedule['leader_name']) ?>
                        </p>
                        <p class="text-gray-600">
                            <span class="font-semibold">Course:</span> 
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-sm">
                                <?= htmlspecialchars($schedule['course']) ?>
                            </span>
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="px-4 py-2 rounded-lg text-sm font-semibold
                            <?= $schedule['status'] === 'Confirmed' ? 'bg-green-100 text-green-800 border border-green-300' : 
                                ($schedule['status'] === 'Done' ? 'bg-blue-100 text-blue-800 border border-blue-300' : 
                                'bg-yellow-100 text-yellow-800 border border-yellow-300') ?>">
                            <?= htmlspecialchars($schedule['status']) ?>
                        </span>
                    </div>
                </div>

                <!-- Schedule Details -->
                <div class="grid grid-cols-2 gap-4 mb-4 bg-gray-50 p-4 rounded-lg">
                    <div>
                        <p class="text-sm text-gray-600 font-semibold mb-1">Time</p>
                        <p class="text-gray-900">
                            <?= date('h:i A', strtotime($schedule['time'])) ?> - 
                            <?= date('h:i A', strtotime($schedule['end_time'])) ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 font-semibold mb-1">Room/Venue</p>
                        <p class="text-gray-900"><?= htmlspecialchars($schedule['room'] ?: 'TBA') ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 font-semibold mb-1">Defense Type</p>
                        <p class="text-gray-900"><?= htmlspecialchars($schedule['defense_type']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 font-semibold mb-1">Mode</p>
                        <p class="text-gray-900"><?= htmlspecialchars(ucfirst($schedule['mode'])) ?></p>
                    </div>
                </div>

                <!-- Panel Members -->
                <div class="border-t pt-4">
                    <h4 class="font-semibold text-gray-800 mb-3">Panel Members:</h4>
                    <?php if (!empty($schedule['panelists'])): ?>
                        <div class="grid grid-cols-1 gap-3">
                            <?php foreach ($schedule['panelists'] as $panelist): ?>
                                <div class="flex items-center justify-between bg-white p-3 rounded-lg border">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                            <span class="text-blue-600 font-bold text-sm">
                                                <?= strtoupper(substr($panelist['first_name'], 0, 1) . substr($panelist['last_name'], 0, 1)) ?>
                                            </span>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900">
                                                <?= htmlspecialchars($panelist['first_name'] . ' ' . $panelist['last_name']) ?>
                                            </p>
                                            <p class="text-sm text-gray-600"><?= htmlspecialchars($panelist['email']) ?></p>
                                        </div>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold
                                        <?= $panelist['role'] === 'Chair' ? 'bg-purple-100 text-purple-800' : 
                                            ($panelist['role'] === 'Critic' ? 'bg-orange-100 text-orange-800' : 
                                            'bg-gray-100 text-gray-800') ?>">
                                        <?= htmlspecialchars($panelist['role']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 italic">No panel members assigned yet</p>
                    <?php endif; ?>
                </div>

                <!-- Signature Section -->
                <div class="grid grid-cols-3 gap-8 mt-8 pt-6 border-t">
                    <div class="text-center">
                        <div class="border-t-2 border-gray-800 pt-2 mt-12">
                            <p class="text-sm font-semibold">Panel Chair</p>
                            <p class="text-xs text-gray-600">Signature over Printed Name</p>
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="border-t-2 border-gray-800 pt-2 mt-12">
                            <p class="text-sm font-semibold">Panel Critic</p>
                            <p class="text-xs text-gray-600">Signature over Printed Name</p>
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="border-t-2 border-gray-800 pt-2 mt-12">
                            <p class="text-sm font-semibold">Panel Member</p>
                            <p class="text-xs text-gray-600">Signature over Printed Name</p>
                        </div>
                    </div>
                </div>
            </div>

            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Footer -->
        <div class="mt-8 pt-6 border-t-2 border-gray-800 text-center text-sm text-gray-600">
            <p class="mb-2">This is an official document from the Thesis Paneling Schedule System</p>
            <p>For inquiries, please contact the Academic Affairs Office</p>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>