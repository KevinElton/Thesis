<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../classes/database.php';

$db = new Database();
$conn = $db->connect();

// Get all active panelists with their availability
$panelistsQuery = "SELECT 
    p.panelist_id,
    p.first_name,
    p.last_name,
    p.email,
    p.contact_number,
    p.department,
    p.expertise,
    COUNT(DISTINCT a.assignment_id) as total_assignments,
    COUNT(DISTINCT av.availability_id) as availability_slots
FROM panelist p
LEFT JOIN assignment a ON p.panelist_id = a.panelist_id
LEFT JOIN availability av ON p.panelist_id = av.panelist_id
WHERE p.status = 'active'
GROUP BY p.panelist_id
ORDER BY p.last_name, p.first_name";

$panelists = $conn->query($panelistsQuery)->fetchAll(PDO::FETCH_ASSOC);

// Get availability details for each panelist
foreach ($panelists as &$panelist) {
    $availQuery = "SELECT day, start_time, end_time 
                   FROM availability 
                   WHERE panelist_id = :pid 
                   ORDER BY FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
    $availStmt = $conn->prepare($availQuery);
    $availStmt->bindParam(':pid', $panelist['panelist_id']);
    $availStmt->execute();
    $panelist['availability'] = $availStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get upcoming assignments
    $upcomingQuery = "SELECT s.date, s.time, tg.title, a.role
                      FROM assignment a
                      JOIN schedule s ON a.schedule_id = s.schedule_id
                      JOIN thesis_group tg ON a.group_id = tg.group_id
                      WHERE a.panelist_id = :pid AND s.date >= CURDATE()
                      ORDER BY s.date, s.time
                      LIMIT 5";
    $upcomingStmt = $conn->prepare($upcomingQuery);
    $upcomingStmt->bindParam(':pid', $panelist['panelist_id']);
    $upcomingStmt->execute();
    $panelist['upcoming'] = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Days of the week for the grid
$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panelist Availability Report</title>
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
            size: A4 landscape;
            margin: 1cm;
        }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Control Panel (No Print) -->
    <div class="no-print bg-white shadow-lg p-6 mb-6">
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold text-gray-800">Panelist Availability Report</h2>
                <div class="flex gap-3">
                    <button onclick="window.print()" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        Print Report
                    </button>
                    <a href="reports.php?tab=panelists" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 flex items-center gap-2">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Printable Content -->
    <div class="max-w-7xl mx-auto bg-white p-8">
        
        <!-- Header -->
        <div class="text-center mb-8 border-b-2 border-gray-800 pb-6">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">PANELIST AVAILABILITY REPORT</h1>
            <p class="text-lg text-gray-700">Academic Year 2024-2025 | 2nd Semester</p>
            <p class="text-xs text-gray-500 mt-2">Generated: <?= date('F d, Y h:i A') ?></p>
        </div>

        <!-- Summary Statistics -->
        <div class="grid grid-cols-4 gap-4 mb-8">
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                <p class="text-sm text-gray-600">Total Panelists</p>
                <p class="text-3xl font-bold text-blue-600"><?= count($panelists) ?></p>
            </div>
            <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                <p class="text-sm text-gray-600">With Availability</p>
                <p class="text-3xl font-bold text-green-600">
                    <?= count(array_filter($panelists, fn($p) => $p['availability_slots'] > 0)) ?>
                </p>
            </div>
            <div class="bg-orange-50 p-4 rounded-lg border border-orange-200">
                <p class="text-sm text-gray-600">Total Assignments</p>
                <p class="text-3xl font-bold text-orange-600">
                    <?= array_sum(array_column($panelists, 'total_assignments')) ?>
                </p>
            </div>
            <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                <p class="text-sm text-gray-600">Avg Workload</p>
                <p class="text-3xl font-bold text-purple-600">
                    <?= count($panelists) > 0 ? number_format(array_sum(array_column($panelists, 'total_assignments')) / count($panelists), 1) : 0 ?>
                </p>
            </div>
        </div>

        <!-- Panelist Details -->
        <?php foreach ($panelists as $index => $panelist): ?>
        <div class="mb-8 border-2 border-gray-300 rounded-lg p-6 <?= ($index + 1) % 2 === 0 ? 'page-break' : '' ?>">
            
            <!-- Panelist Header -->
            <div class="flex justify-between items-start mb-6 pb-4 border-b">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center">
                        <span class="text-white font-bold text-2xl">
                            <?= strtoupper(substr($panelist['first_name'], 0, 1) . substr($panelist['last_name'], 0, 1)) ?>
                        </span>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-gray-900">
                            <?= htmlspecialchars($panelist['first_name'] . ' ' . $panelist['last_name']) ?>
                        </h3>
                        <p class="text-gray-600"><?= htmlspecialchars($panelist['email']) ?></p>
                        <?php if ($panelist['contact_number']): ?>
                        <p class="text-gray-600"><?= htmlspecialchars($panelist['contact_number']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-right">
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-semibold">
                        <?= htmlspecialchars($panelist['department']) ?>
                    </span>
                    <p class="text-sm text-gray-600 mt-2">
                        <span class="font-semibold">Expertise:</span> <?= htmlspecialchars($panelist['expertise']) ?>
                    </p>
                    <p class="text-sm text-gray-600 mt-1">
                        <span class="font-semibold">Workload:</span> <?= $panelist['total_assignments'] ?> assignments
                    </p>
                </div>
            </div>

            <!-- Weekly Availability Grid -->
            <div class="mb-6">
                <h4 class="font-semibold text-gray-800 mb-3">Weekly Availability Schedule:</h4>
                <div class="grid grid-cols-7 gap-2">
                    <?php foreach ($daysOfWeek as $day): ?>
                        <?php
                        $dayAvailability = array_filter($panelist['availability'], fn($a) => $a['day'] === $day);
                        ?>
                        <div class="border rounded-lg p-3 <?= empty($dayAvailability) ? 'bg-gray-50' : 'bg-green-50 border-green-200' ?>">
                            <p class="text-xs font-bold text-gray-700 mb-2"><?= substr($day, 0, 3) ?></p>
                            <?php if (!empty($dayAvailability)): ?>
                                <?php foreach ($dayAvailability as $avail): ?>
                                    <div class="text-xs text-gray-900 mb-1">
                                        <?= date('h:i A', strtotime($avail['start_time'])) ?><br>
                                        <?= date('h:i A', strtotime($avail['end_time'])) ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-xs text-gray-400">N/A</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Detailed Availability List -->
            <?php if (!empty($panelist['availability'])): ?>
            <div class="mb-6">
                <h4 class="font-semibold text-gray-800 mb-3">Availability Details:</h4>
                <div class="grid grid-cols-2 gap-3">
                    <?php foreach ($panelist['availability'] as $avail): ?>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                            <p class="font-semibold text-gray-800"><?= htmlspecialchars($avail['day']) ?></p>
                            <p class="text-sm text-gray-600">
                                <?= date('h:i A', strtotime($avail['start_time'])) ?> - 
                                <?= date('h:i A', strtotime($avail['end_time'])) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-yellow-800 flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                    No availability schedule set
                </p>
            </div>
            <?php endif; ?>

            <!-- Upcoming Assignments -->
            <?php if (!empty($panelist['upcoming'])): ?>
            <div class="border-t pt-4">
                <h4 class="font-semibold text-gray-800 mb-3">Upcoming Assignments:</h4>
                <div class="space-y-2">
                    <?php foreach ($panelist['upcoming'] as $assignment): ?>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 flex justify-between items-center">
                            <div>
                                <p class="font-semibold text-gray-900"><?= htmlspecialchars($assignment['title']) ?></p>
                                <p class="text-sm text-gray-600">
                                    <?= date('M d, Y', strtotime($assignment['date'])) ?> at 
                                    <?= date('h:i A', strtotime($assignment['time'])) ?>
                                </p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold
                                <?= $assignment['role'] === 'Chair' ? 'bg-purple-100 text-purple-800' : 
                                    ($assignment['role'] === 'Critic' ? 'bg-orange-100 text-orange-800' : 
                                    'bg-gray-100 text-gray-800') ?>">
                                <?= htmlspecialchars($assignment['role']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

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