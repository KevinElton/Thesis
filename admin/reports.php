<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->connect();

// Get filter parameters
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'groups';

// === CHART DATA QUERIES ===

// 1. Schedules per month (last 12 months)
$monthlySchedulesQuery = "SELECT 
    DATE_FORMAT(date, '%Y-%m') as month,
    DATE_FORMAT(date, '%b %Y') as month_label,
    COUNT(*) as count
FROM schedule
WHERE date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
GROUP BY DATE_FORMAT(date, '%Y-%m')
ORDER BY month";
$monthlySchedules = $conn->query($monthlySchedulesQuery)->fetchAll(PDO::FETCH_ASSOC);

// 2. Groups per course/department
$groupsByCourseQuery = "SELECT 
    course,
    COUNT(*) as count
FROM thesis_group
GROUP BY course
ORDER BY count DESC";
$groupsByCourse = $conn->query($groupsByCourseQuery)->fetchAll(PDO::FETCH_ASSOC);

// 3. Panelist availability status
$panelistStatusQuery = "SELECT 
    CASE 
        WHEN EXISTS (SELECT 1 FROM availability WHERE availability.panelist_id = panelist.panelist_id) 
        THEN 'With Availability' 
        ELSE 'No Availability' 
    END as availability_status,
    COUNT(*) as count
FROM panelist
WHERE status = 'active'
GROUP BY availability_status";
$panelistStatus = $conn->query($panelistStatusQuery)->fetchAll(PDO::FETCH_ASSOC);

// 4. Defense type distribution
$defenseTypeQuery = "SELECT 
    defense_type,
    COUNT(*) as count
FROM schedule
GROUP BY defense_type
ORDER BY count DESC";
$defenseTypes = $conn->query($defenseTypeQuery)->fetchAll(PDO::FETCH_ASSOC);

// 5. Thesis group status distribution
$groupStatusQuery = "SELECT 
    status,
    COUNT(*) as count
FROM thesis_group
GROUP BY status
ORDER BY count DESC";
$groupStatus = $conn->query($groupStatusQuery)->fetchAll(PDO::FETCH_ASSOC);

// 6. Top 10 panelists by workload
$panelistWorkloadQuery = "SELECT 
    CONCAT(p.first_name, ' ', p.last_name) as panelist_name,
    COUNT(a.assignment_id) as assignment_count
FROM panelist p
LEFT JOIN assignment a ON p.panelist_id = a.panelist_id
WHERE p.status = 'active'
GROUP BY p.panelist_id, panelist_name
HAVING assignment_count > 0
ORDER BY assignment_count DESC
LIMIT 10";
$panelistWorkload = $conn->query($panelistWorkloadQuery)->fetchAll(PDO::FETCH_ASSOC);

// 7. Schedule status distribution
$scheduleStatusQuery = "SELECT 
    status,
    COUNT(*) as count
FROM schedule
GROUP BY status
ORDER BY count DESC";
$scheduleStatus = $conn->query($scheduleStatusQuery)->fetchAll(PDO::FETCH_ASSOC);

// === EXISTING QUERIES ===

$groupsQuery = "SELECT 
    tg.group_id,
    tg.title,
    tg.leader_name,
    tg.course,
    tg.status,
    tg.created_at,
    s.schedule_id,
    s.date,
    s.time,
    s.end_time,
    s.room,
    s.defense_type,
    s.status as schedule_status
FROM thesis_group tg
LEFT JOIN schedule s ON tg.group_id = s.group_id
WHERE 1=1";

if ($filterStatus !== 'all') {
    $groupsQuery .= " AND tg.status = :status";
}

if (!empty($searchTerm)) {
    $groupsQuery .= " AND (tg.title LIKE :search OR tg.leader_name LIKE :search OR tg.course LIKE :search)";
}

$groupsQuery .= " ORDER BY tg.created_at DESC";

$stmt = $conn->prepare($groupsQuery);

if ($filterStatus !== 'all') {
    $stmt->bindParam(':status', $filterStatus);
}

if (!empty($searchTerm)) {
    $searchParam = "%{$searchTerm}%";
    $stmt->bindParam(':search', $searchParam);
}

$stmt->execute();
$thesisGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

$panelistsQuery = "SELECT 
    p.panelist_id,
    p.first_name,
    p.last_name,
    p.email,
    p.department,
    p.expertise,
    p.status,
    COUNT(DISTINCT a.assignment_id) as assignment_count
FROM panelist p
LEFT JOIN assignment a ON p.panelist_id = a.panelist_id
WHERE p.status = 'active'
GROUP BY p.panelist_id
ORDER BY p.last_name, p.first_name";

$panelists = $conn->query($panelistsQuery)->fetchAll(PDO::FETCH_ASSOC);

foreach ($panelists as &$panelist) {
    $availQuery = "SELECT day, start_time, end_time FROM availability WHERE panelist_id = :pid";
    $availStmt = $conn->prepare($availQuery);
    $availStmt->bindParam(':pid', $panelist['panelist_id']);
    $availStmt->execute();
    $panelist['availability'] = $availStmt->fetchAll(PDO::FETCH_ASSOC);
}

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
    tg.course
FROM schedule s
JOIN thesis_group tg ON s.group_id = tg.group_id
ORDER BY s.date DESC, s.time DESC";

$schedules = $conn->query($schedulesQuery)->fetchAll(PDO::FETCH_ASSOC);

foreach ($schedules as &$schedule) {
    $panelQuery = "SELECT 
        p.first_name,
        p.last_name,
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

$evalQuery = "SELECT 
    COUNT(*) as total_evaluations,
    AVG(overall_score) as avg_score,
    MAX(overall_score) as highest_score,
    MIN(overall_score) as lowest_score
FROM evaluation";
$evalStats = $conn->query($evalQuery)->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Thesis Paneling System</title>
    <script src="/Thesis/assets/js/tailwind.js"></script>
    <script src="/Thesis/assets/js/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 ml-64 p-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-2 flex items-center gap-3">
                <i data-lucide="bar-chart-3" class="w-10 h-10 text-blue-600"></i>
                Reports & Analytics
            </h1>
            <p class="text-gray-600">Visual insights and detailed reports of your thesis scheduling system</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6 transform transition hover:scale-105">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Groups</p>
                        <p class="text-3xl font-bold text-blue-600"><?= count($thesisGroups) ?></p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i data-lucide="users" class="w-6 h-6 text-blue-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 transform transition hover:scale-105">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Active Panelists</p>
                        <p class="text-3xl font-bold text-green-600"><?= count($panelists) ?></p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full">
                        <i data-lucide="user-check" class="w-6 h-6 text-green-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 transform transition hover:scale-105">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Schedules</p>
                        <p class="text-3xl font-bold text-purple-600"><?= count($schedules) ?></p>
                    </div>
                    <div class="bg-purple-100 p-3 rounded-full">
                        <i data-lucide="calendar" class="w-6 h-6 text-purple-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 transform transition hover:scale-105">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Avg Score</p>
                        <p class="text-3xl font-bold text-orange-600">
                            <?= $evalStats['avg_score'] ? number_format($evalStats['avg_score'], 1) : 'N/A' ?>
                        </p>
                    </div>
                    <div class="bg-orange-100 p-3 rounded-full">
                        <i data-lucide="star" class="w-6 h-6 text-orange-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-8">
            <!-- Monthly Schedules Chart -->
            <div class="bg-white rounded-xl shadow-lg p-4">
                <h3 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                    <i data-lucide="trending-up" class="w-4 h-4 text-blue-600"></i>
                    Defense Schedules Per Month
                </h3>
                <div style="height: 200px;">
                    <canvas id="monthlySchedulesChart"></canvas>
                </div>
            </div>

            <!-- Groups by Course Chart -->
            <div class="bg-white rounded-xl shadow-lg p-4">
                <h3 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                    <i data-lucide="pie-chart" class="w-4 h-4 text-green-600"></i>
                    Groups by Course
                </h3>
                <div style="height: 200px;">
                    <canvas id="groupsByCourseChart"></canvas>
                </div>
            </div>

            <!-- Panelist Availability Chart -->
            <div class="bg-white rounded-xl shadow-lg p-4">
                <h3 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                    <i data-lucide="user-check" class="w-4 h-4 text-purple-600"></i>
                    Panelist Availability Status
                </h3>
                <div style="height: 200px;">
                    <canvas id="panelistAvailabilityChart"></canvas>
                </div>
            </div>

            <!-- Defense Type Distribution Chart -->
            <div class="bg-white rounded-xl shadow-lg p-4">
                <h3 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                    <i data-lucide="layout-grid" class="w-4 h-4 text-orange-600"></i>
                    Defense Type Distribution
                </h3>
                <div style="height: 200px;">
                    <canvas id="defenseTypeChart"></canvas>
                </div>
            </div>

            <!-- Group Status Chart -->
            <div class="bg-white rounded-xl shadow-lg p-4">
                <h3 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                    <i data-lucide="activity" class="w-4 h-4 text-indigo-600"></i>
                    Thesis Group Status
                </h3>
                <div style="height: 200px;">
                    <canvas id="groupStatusChart"></canvas>
                </div>
            </div>

            <!-- Schedule Status Chart -->
            <div class="bg-white rounded-xl shadow-lg p-4">
                <h3 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                    <i data-lucide="clock" class="w-4 h-4 text-pink-600"></i>
                    Schedule Status Overview
                </h3>
                <div style="height: 200px;">
                    <canvas id="scheduleStatusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Panelist Workload Chart (Full Width) -->
        <?php if (!empty($panelistWorkload)): ?>
        <div class="bg-white rounded-xl shadow-lg p-4 mb-8">
            <h3 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                <i data-lucide="bar-chart-2" class="w-4 h-4 text-teal-600"></i>
                Top 10 Panelists by Workload
            </h3>
            <div style="height: 250px;">
                <canvas id="panelistWorkloadChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tabs Navigation -->
        <div class="bg-white rounded-t-xl shadow-lg">
            <div class="flex border-b">
                <a href="?tab=groups" 
                   class="px-6 py-4 font-medium <?= $activeTab === 'groups' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
                    <i data-lucide="users" class="w-4 h-4 inline mr-2"></i>
                    Thesis Groups
                </a>
                <a href="?tab=panelists" 
                   class="px-6 py-4 font-medium <?= $activeTab === 'panelists' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
                    <i data-lucide="user-check" class="w-4 h-4 inline mr-2"></i>
                    Panelists
                </a>
                <a href="?tab=schedules" 
                   class="px-6 py-4 font-medium <?= $activeTab === 'schedules' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
                    <i data-lucide="calendar" class="w-4 h-4 inline mr-2"></i>
                    Schedules
                </a>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="bg-white rounded-b-xl shadow-lg p-6">
            
            <!-- Search and Filter Bar -->
            <div class="flex flex-col md:flex-row gap-4 mb-6">
                <div class="flex-1">
                    <div class="relative">
                        <i data-lucide="search" class="w-5 h-5 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" 
                               id="searchInput"
                               placeholder="Search..." 
                               class="w-full pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?= htmlspecialchars($searchTerm) ?>">
                    </div>
                </div>
                
                <?php if ($activeTab === 'groups'): ?>
                <select id="statusFilter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="all">All Status</option>
                    <option value="Pending" <?= $filterStatus === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="For Defense" <?= $filterStatus === 'For Defense' ? 'selected' : '' ?>>For Defense</option>
                    <option value="Defended" <?= $filterStatus === 'Defended' ? 'selected' : '' ?>>Defended</option>
                </select>
                <?php endif; ?>

                <button onclick="window.print()" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    Print
                </button>

                <button onclick="exportToCSV()" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    Export CSV
                </button>
            </div>

            <!-- THESIS GROUPS TAB -->
            <?php if ($activeTab === 'groups'): ?>
            <div class="overflow-x-auto">
                <table class="w-full" id="dataTable">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Group ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leader</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Defense Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($thesisGroups as $group): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($group['group_id']) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?= htmlspecialchars($group['title']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($group['leader_name']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">
                                    <?= htmlspecialchars($group['course']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-3 py-1 rounded-full text-xs font-medium
                                    <?= $group['status'] === 'For Defense' ? 'bg-yellow-100 text-yellow-800' : 
                                        ($group['status'] === 'Defended' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800') ?>">
                                    <?= htmlspecialchars($group['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= $group['date'] ? date('M d, Y', strtotime($group['date'])) : 'Not Scheduled' ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm no-print">
                                <a href="groups/view.php?id=<?= $group['group_id'] ?>" 
                                   class="text-blue-600 hover:text-blue-800 mr-3">View</a>
                                <a href="groups/edit.php?id=<?= $group['group_id'] ?>" 
                                   class="text-green-600 hover:text-green-800">Edit</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- PANELISTS TAB -->
            <?php if ($activeTab === 'panelists'): ?>
            <div class="overflow-x-auto">
                <table class="w-full" id="dataTable">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expertise</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Workload</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Availability</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($panelists as $panelist): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($panelist['panelist_id']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($panelist['first_name'] . ' ' . $panelist['last_name']) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?= htmlspecialchars($panelist['email']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($panelist['department']) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs">
                                    <?= htmlspecialchars($panelist['expertise']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                                    <?= $panelist['assignment_count'] ?> assignments
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?php if (!empty($panelist['availability'])): ?>
                                    <div class="space-y-1">
                                        <?php foreach ($panelist['availability'] as $avail): ?>
                                            <div class="text-xs bg-green-50 px-2 py-1 rounded">
                                                <?= htmlspecialchars($avail['day']) ?>: 
                                                <?= date('h:i A', strtotime($avail['start_time'])) ?> - 
                                                <?= date('h:i A', strtotime($avail['end_time'])) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">No availability set</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- SCHEDULES TAB -->
            <?php if ($activeTab === 'schedules'): ?>
            <div class="overflow-x-auto">
                <table class="w-full" id="dataTable">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Schedule ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Group</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Panel Members</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($schedules as $schedule): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                #<?= htmlspecialchars($schedule['schedule_id']) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <div class="font-medium"><?= htmlspecialchars($schedule['group_title']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($schedule['leader_name']) ?> (<?= htmlspecialchars($schedule['course']) ?>)</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div class="font-medium"><?= date('M d, Y', strtotime($schedule['date'])) ?></div>
                                <div class="text-xs text-gray-500">
                                    <?= date('h:i A', strtotime($schedule['time'])) ?> - 
                                    <?= date('h:i A', strtotime($schedule['end_time'])) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($schedule['room'] ?: 'TBA') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span class="px-2 py-1 bg-indigo-100 text-indigo-800 rounded-full text-xs">
                                    <?= htmlspecialchars($schedule['defense_type']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?php if (!empty($schedule['panelists'])): ?>
                                    <div class="space-y-1">
                                        <?php foreach ($schedule['panelists'] as $panelist): ?>
                                            <div class="text-xs">
                                                <span class="font-medium"><?= htmlspecialchars($panelist['first_name'] . ' ' . $panelist['last_name']) ?></span>
                                                <span class="text-gray-500">(<?= htmlspecialchars($panelist['role']) ?>)</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">No panelists assigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-3 py-1 rounded-full text-xs font-medium
                                    <?= $schedule['status'] === 'Confirmed' ? 'bg-green-100 text-green-800' : 
                                        ($schedule['status'] === 'Done' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                    <?= htmlspecialchars($schedule['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        </div>
    </main>

    <script>
        lucide.createIcons();

        // Prepare chart data from PHP
        const chartData = {
            monthlySchedules: <?= json_encode($monthlySchedules) ?>,
            groupsByCourse: <?= json_encode($groupsByCourse) ?>,
            panelistStatus: <?= json_encode($panelistStatus) ?>,
            defenseTypes: <?= json_encode($defenseTypes) ?>,
            groupStatus: <?= json_encode($groupStatus) ?>,
            panelistWorkload: <?= json_encode($panelistWorkload) ?>,
            scheduleStatus: <?= json_encode($scheduleStatus) ?>
        };

        // Chart color schemes
        const colors = {
            blue: ['#3B82F6', '#60A5FA', '#93C5FD', '#BFDBFE', '#DBEAFE'],
            green: ['#10B981', '#34D399', '#6EE7B7', '#A7F3D0', '#D1FAE5'],
            purple: ['#8B5CF6', '#A78BFA', '#C4B5FD', '#DDD6FE', '#EDE9FE'],
            orange: ['#F59E0B', '#FBBF24', '#FCD34D', '#FDE68A', '#FEF3C7'],
            pink: ['#EC4899', '#F472B6', '#F9A8D4', '#FBCFE8', '#FCE7F3'],
            teal: ['#14B8A6', '#2DD4BF', '#5EEAD4', '#99F6E4', '#CCFBF1'],
            indigo: ['#6366F1', '#818CF8', '#A5B4FC', '#C7D2FE', '#E0E7FF']
        };

        // 1. Monthly Schedules Chart (Line Chart)
        const monthlyCtx = document.getElementById('monthlySchedulesChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: chartData.monthlySchedules.map(d => d.month_label),
                datasets: [{
                    label: 'Defense Schedules',
                    data: chartData.monthlySchedules.map(d => d.count),
                    borderColor: colors.blue[0],
                    backgroundColor: colors.blue[0] + '20',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 8,
                        titleFont: { size: 11 },
                        bodyFont: { size: 10 }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 10 } }
                    },
                    x: {
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });

        // 2. Groups by Course Chart (Doughnut Chart)
        const courseCtx = document.getElementById('groupsByCourseChart').getContext('2d');
        new Chart(courseCtx, {
            type: 'doughnut',
            data: {
                labels: chartData.groupsByCourse.map(d => d.course),
                datasets: [{
                    data: chartData.groupsByCourse.map(d => d.count),
                    backgroundColor: colors.green,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 8, font: { size: 10 } }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 8,
                        titleFont: { size: 11 },
                        bodyFont: { size: 10 }
                    }
                }
            }
        });

        // 3. Panelist Availability Chart (Pie Chart)
        const availCtx = document.getElementById('panelistAvailabilityChart').getContext('2d');
        new Chart(availCtx, {
            type: 'pie',
            data: {
                labels: chartData.panelistStatus.map(d => d.availability_status),
                datasets: [{
                    data: chartData.panelistStatus.map(d => d.count),
                    backgroundColor: colors.purple,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 8, font: { size: 10 } }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 8,
                        titleFont: { size: 11 },
                        bodyFont: { size: 10 }
                    }
                }
            }
        });

        // 4. Defense Type Distribution Chart (Bar Chart)
        const defenseCtx = document.getElementById('defenseTypeChart').getContext('2d');
        new Chart(defenseCtx, {
            type: 'bar',
            data: {
                labels: chartData.defenseTypes.map(d => d.defense_type),
                datasets: [{
                    label: 'Count',
                    data: chartData.defenseTypes.map(d => d.count),
                    backgroundColor: colors.orange,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 8,
                        titleFont: { size: 11 },
                        bodyFont: { size: 10 }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 10 } }
                    },
                    x: {
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });

        // 5. Group Status Chart (Doughnut Chart)
        const statusCtx = document.getElementById('groupStatusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: chartData.groupStatus.map(d => d.status),
                datasets: [{
                    data: chartData.groupStatus.map(d => d.count),
                    backgroundColor: colors.indigo,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 8, font: { size: 10 } }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 8,
                        titleFont: { size: 11 },
                        bodyFont: { size: 10 }
                    }
                }
            }
        });

        // 6. Schedule Status Chart (Pie Chart)
        const scheduleStatusCtx = document.getElementById('scheduleStatusChart').getContext('2d');
        new Chart(scheduleStatusCtx, {
            type: 'pie',
            data: {
                labels: chartData.scheduleStatus.map(d => d.status),
                datasets: [{
                    data: chartData.scheduleStatus.map(d => d.count),
                    backgroundColor: colors.pink,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 8, font: { size: 10 } }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 8,
                        titleFont: { size: 11 },
                        bodyFont: { size: 10 }
                    }
                }
            }
        });

        // 7. Panelist Workload Chart (Horizontal Bar Chart)
        if (chartData.panelistWorkload.length > 0) {
            const workloadCtx = document.getElementById('panelistWorkloadChart').getContext('2d');
            new Chart(workloadCtx, {
                type: 'bar',
                data: {
                    labels: chartData.panelistWorkload.map(d => d.panelist_name),
                    datasets: [{
                        label: 'Assignments',
                        data: chartData.panelistWorkload.map(d => d.assignment_count),
                        backgroundColor: colors.teal[0],
                        borderWidth: 0
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 8,
                            titleFont: { size: 11 },
                            bodyFont: { size: 10 }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: { precision: 0, font: { size: 10 } }
                        },
                        y: {
                            ticks: { font: { size: 10 } }
                        }
                    }
                }
            });
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                const searchTerm = this.value;
                const currentTab = '<?= $activeTab ?>';
                window.location.href = `?tab=${currentTab}&search=${encodeURIComponent(searchTerm)}`;
            }
        });

        // Filter functionality
        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', function() {
                const status = this.value;
                const searchTerm = document.getElementById('searchInput').value;
                window.location.href = `?tab=groups&status=${status}&search=${encodeURIComponent(searchTerm)}`;
            });
        }

        // Export to CSV function
        function exportToCSV() {
            const table = document.getElementById('dataTable');
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let row of rows) {
                const cols = row.querySelectorAll('td, th');
                const csvRow = [];
                for (let col of cols) {
                    // Skip action columns
                    if (!col.classList.contains('no-print')) {
                        csvRow.push('"' + col.innerText.replace(/"/g, '""') + '"');
                    }
                }
                csv.push(csvRow.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'thesis_report_<?= $activeTab ?>_<?= date('Y-m-d') ?>.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Print styles
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                body * { visibility: hidden; }
                #dataTable, #dataTable * { visibility: visible; }
                #dataTable { 
                    position: absolute;
                    left: 0;
                    top: 0;
                    width: 100%;
                }
                .no-print { display: none !important; }
                aside { display: none !important; }
                main { margin-left: 0 !important; }
                canvas { display: none !important; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>





