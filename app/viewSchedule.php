<?php
session_start();
require_once __DIR__ . '/../classes/database.php';
require_once __DIR__ . '/../classes/email.php';
require_once __DIR__ . '/../classes/audit.php';
require_once __DIR__ . '/../classes/faculty.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();
$message = '';
$conflicts = [];

/**
 * Helper: check if a table exists in the current database
 */
function tableExists(PDO $pdo, string $tableName): bool {
    $sql = "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tableName]);
    return (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
}

/**
 * Helper: check if a column exists in a table
 */
function columnExists(PDO $pdo, string $tableName, string $columnName): bool {
    $sql = "SELECT COUNT(*) as cnt FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tableName, $columnName]);
    return (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
}

// Detect presence of rooms table and end_time column
$hasRoomsTable = tableExists($conn, 'rooms');
$hasEndTimeColumn = columnExists($conn, 'schedule', 'end_time');
$hasThesisTable = tableExists($conn, 'thesis');

// Informational message if rooms or end_time missing
if (!$hasRoomsTable) {
    $message .= '<div class="bg-yellow-50 text-yellow-800 border-l-4 border-yellow-400 p-4 mb-4 rounded">⚠️ <strong>Rooms table not found.</strong> Room selection will be disabled until you create a <code>rooms</code> table.</div>';
}
if (!$hasEndTimeColumn) {
    $message .= '<div class="bg-yellow-50 text-yellow-800 border-l-4 border-yellow-400 p-4 mb-4 rounded">⚠️ <strong>No <code>end_time</code> column found in <code>schedule</code> table.</strong> Overlap checks will fallback to simple time-equality checks. Consider adding an <code>end_time</code> column for accurate conflict detection.</div>';
}

// Handle schedule creation with ENHANCED conflict checking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_schedule'])) {
    $group_id = $_POST['group_id'] ?? null;
    $date = $_POST['date'] ?? null;
    $time = $_POST['time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;
    $room_id = $_POST['room_id'] ?? null;
    $defense_type = $_POST['defense_type'] ?? null;
    $panelists = $_POST['panelists'] ?? [];
    $roles = $_POST['roles'] ?? [];

    $hasConflict = false;

    // Basic validation
    if (!$group_id || !$date || !$time || !$defense_type) {
        $conflicts[] = "Please fill required fields (group, date, time, defense type).";
        $hasConflict = true;
    }

    // 1. Check if date is a weekend
    if ($date) {
        $dayOfWeek = date('w', strtotime($date));
        if ($dayOfWeek == 0 || $dayOfWeek == 6) {
            $conflicts[] = "Selected date falls on a weekend";
            $hasConflict = true;
        }
    }

    // 2. Check room availability (if room selected and rooms table exists)
    if ($room_id && $hasRoomsTable) {
        if ($hasEndTimeColumn && $end_time) {
            $sql = "SELECT COUNT(*) as count FROM schedule 
                    WHERE room_id = ? AND date = ? AND status != 'Cancelled' 
                    AND ((time <= ? AND end_time > ?) OR (time < ? AND end_time >= ?))";
            $stmt = $conn->prepare($sql);
            $params = [$room_id, $date, $time, $time, $end_time, $end_time];
        } else {
            $sql = "SELECT COUNT(*) as count FROM schedule WHERE room_id = ? AND date = ? AND time = ? AND status != 'Cancelled'";
            $stmt = $conn->prepare($sql);
            $params = [$room_id, $date, $time];
        }
        $stmt->execute($params);
        $count = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        if ($count > 0) {
            if ($hasRoomsTable) {
                $roomName = 'Selected room';
                $rstmt = $conn->prepare("SELECT name, room_id FROM rooms WHERE room_id = ?");
                $rstmt->execute([$room_id]);
                $rrow = $rstmt->fetch(PDO::FETCH_ASSOC);
                if ($rrow && !empty($rrow['name'])) $roomName = $rrow['name'];
                $conflicts[] = "Room '{$roomName}' is already booked for this time slot";
            } else {
                $conflicts[] = "Selected room is already booked for this time slot";
            }
            $hasConflict = true;
        }
    }

    // 3. Check panelist availability and conflicts
    foreach ($panelists as $index => $panelist_id) {
        if (!$panelist_id) continue;

        if ($hasEndTimeColumn && $end_time) {
            $sql = "SELECT COUNT(*) as count FROM assignment a 
                    INNER JOIN schedule s ON a.schedule_id = s.schedule_id 
                    WHERE a.panelist_id = ? AND s.date = ? AND s.status != 'Cancelled' 
                    AND ((s.time <= ? AND s.end_time > ?) OR (s.time < ? AND s.end_time >= ?))";
            $params = [$panelist_id, $date, $time, $time, $end_time, $end_time];
        } else {
            $sql = "SELECT COUNT(*) as count FROM assignment a 
                    INNER JOIN schedule s ON a.schedule_id = s.schedule_id 
                    WHERE a.panelist_id = ? AND s.date = ? AND s.time = ? AND s.status != 'Cancelled'";
            $params = [$panelist_id, $date, $time];
        }
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $pcount = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        if ($pcount > 0) {
            $stmt2 = $conn->prepare("SELECT COALESCE(CONCAT(first_name, ' ', last_name), 'Faculty') as name FROM panelist WHERE panelist_id = ?");
            $stmt2->execute([$panelist_id]);
            $name = $stmt2->fetch(PDO::FETCH_ASSOC)['name'] ?? 'Faculty';
            $conflicts[] = "Panelist $name has another defense at this time";
            $hasConflict = true;
        }

        // 4. Check if adviser is in the panel (conflict of interest) - FIXED QUERY
        if ($hasThesisTable) {
            $stmt3 = $conn->prepare("SELECT adviser_id FROM thesis WHERE group_id = ?");
            $stmt3->execute([$group_id]);
            $thesis = $stmt3->fetch(PDO::FETCH_ASSOC);
            if ($thesis && $thesis['adviser_id'] == $panelist_id) {
                $stmt4 = $conn->prepare("SELECT COALESCE(CONCAT(first_name, ' ', last_name), 'Faculty') as name FROM panelist WHERE panelist_id = ?");
                $stmt4->execute([$panelist_id]);
                $name = $stmt4->fetch(PDO::FETCH_ASSOC)['name'] ?? 'Faculty';
                $conflicts[] = "Cannot assign $name as panelist - they are the thesis adviser";
                $hasConflict = true;
            }
        }
    }

    // 5. Check for duplicate panelists
    $nonEmptyPanelists = array_values(array_filter($panelists));
    if (count($nonEmptyPanelists) !== count(array_unique($nonEmptyPanelists))) {
        $conflicts[] = "Cannot assign the same panelist to multiple roles";
        $hasConflict = true;
    }

    // 6. Check panelist workload (max 10 per month)
    foreach ($nonEmptyPanelists as $panelist_id) {
        $month = date('Y-m-01', strtotime($date));
        $sql = "SELECT COUNT(*) as count FROM assignment a
                INNER JOIN schedule s ON a.schedule_id = s.schedule_id
                WHERE a.panelist_id = ? AND DATE_FORMAT(s.date, '%Y-%m-01') = ? 
                AND s.status != 'Cancelled'";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$panelist_id, $month]);
        $count = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        if ($count >= 10) {
            $stmt2 = $conn->prepare("SELECT COALESCE(CONCAT(first_name, ' ', last_name), 'Faculty') as name FROM panelist WHERE panelist_id = ?");
            $stmt2->execute([$panelist_id]);
            $name = $stmt2->fetch(PDO::FETCH_ASSOC)['name'] ?? 'Faculty';
            $conflicts[] = "Panelist $name has reached maximum workload (10 defenses this month)";
            $hasConflict = true;
        }
    }

    // If no conflicts, insert schedule
    if (!$hasConflict) {
        try {
            $conn->beginTransaction();

            // Check if schedule already exists for this group
            $stmt = $conn->prepare("SELECT schedule_id FROM schedule WHERE group_id = ? AND status != 'Cancelled'");
            $stmt->execute([$group_id]);
            $existing_schedule = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_schedule) {
                // Update existing schedule
                $schedule_id = $existing_schedule['schedule_id'];
                if ($hasEndTimeColumn) {
                    $stmt = $conn->prepare("UPDATE schedule SET date = ?, time = ?, end_time = ?, room_id = ?, defense_type = ?, status = 'Confirmed' WHERE schedule_id = ?");
                    $stmt->execute([$date, $time, $end_time, $room_id, $defense_type, $schedule_id]);
                } else {
                    $stmt = $conn->prepare("UPDATE schedule SET date = ?, time = ?, room_id = ?, defense_type = ?, status = 'Confirmed' WHERE schedule_id = ?");
                    $stmt->execute([$date, $time, $room_id, $defense_type, $schedule_id]);
                }
                
                // Delete old assignments
                $stmt = $conn->prepare("DELETE FROM assignment WHERE schedule_id = ?");
                $stmt->execute([$schedule_id]);
            } else {
                // Insert new schedule
                if ($hasEndTimeColumn) {
                    $stmt = $conn->prepare("INSERT INTO schedule (group_id, date, time, end_time, room_id, defense_type, status) 
                                            VALUES (?, ?, ?, ?, ?, ?, 'Confirmed')");
                    $stmt->execute([$group_id, $date, $time, $end_time, $room_id, $defense_type]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO schedule (group_id, date, time, room_id, defense_type, status) 
                                            VALUES (?, ?, ?, ?, ?, 'Confirmed')");
                    $stmt->execute([$group_id, $date, $time, $room_id, $defense_type]);
                }
                $schedule_id = $conn->lastInsertId();
            }

            // Assign panelists with roles
            foreach ($nonEmptyPanelists as $index => $panelist_id) {
                $role = $roles[$index] ?? 'Member';
                $stmt2 = $conn->prepare("INSERT INTO assignment (schedule_id, group_id, panelist_id, role) 
                                         VALUES (?, ?, ?, ?)");
                $stmt2->execute([$schedule_id, $group_id, $panelist_id, $role]);
            }

            $conn->commit();

            // Send notifications and log audit
            $email = new Email();
            $audit = new Audit();
            $notificationCount = 0;

            foreach ($nonEmptyPanelists as $index => $panelist_id) {
                if ($email->sendPanelAssignmentNotification($panelist_id, $group_id, $schedule_id)) {
                    $notificationCount++;
                }
            }

            $audit->logAction(
                $_SESSION['user_id'] ?? 1,
                'Admin',
                'CREATE_SCHEDULE',
                'schedule',
                $schedule_id,
                "Created defense schedule for group $group_id with " . count($nonEmptyPanelists) . " panelists"
            );

            $message .= '<div class="bg-green-100 text-green-700 border border-green-400 p-4 rounded-lg mb-4 flex items-center gap-2">
                         <i data-lucide="check-circle" class="w-5 h-5"></i>
                         <span>Defense scheduled successfully! ' . $notificationCount . ' panelists notified via email.</span>
                         </div>';
        } catch (PDOException $e) {
            $conn->rollBack();
            $message .= '<div class="bg-red-100 text-red-700 border border-red-400 p-4 rounded-lg mb-4 flex items-center gap-2">
                         <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                         <span>Error: ' . htmlspecialchars($e->getMessage()) . '</span>
                         </div>';
        }
    } else {
        $message .= '<div class="bg-red-100 text-red-700 border border-red-400 p-4 rounded-lg mb-4">
                     <div class="flex items-center gap-2 mb-2">
                         <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                         <strong>Scheduling Conflicts Detected:</strong>
                     </div>
                     <ul class="list-disc ml-8 mt-2 space-y-1">';
        foreach ($conflicts as $conflict) {
            $message .= '<li>' . htmlspecialchars($conflict) . '</li>';
        }
        $message .= '</ul></div>';
    }
}

// Build the SELECT for listing schedules - FIXED QUERY
$selectFields = "s.schedule_id, s.date, s.time, s.defense_type, s.status, tg.group_id, tg.leader_name, tg.course, tg.title";
if ($hasEndTimeColumn) {
    $selectFields .= ", s.end_time";
}
$selectFields .= ", GROUP_CONCAT(CONCAT(p.first_name, ' ', p.last_name, ' (', a.role, ')') SEPARATOR ', ') as panelists";

$fromClause = "
    FROM schedule s
    INNER JOIN thesis_group tg ON s.group_id = tg.group_id
    LEFT JOIN assignment a ON s.schedule_id = a.schedule_id
    LEFT JOIN panelist p ON a.panelist_id = p.panelist_id
";

if ($hasRoomsTable) {
    $selectFields .= ", r.name as room_name, r.mode as room_mode";
    $fromClause = "
    FROM schedule s
    INNER JOIN thesis_group tg ON s.group_id = tg.group_id
    LEFT JOIN rooms r ON s.room_id = r.room_id
    LEFT JOIN assignment a ON s.schedule_id = a.schedule_id
    LEFT JOIN panelist p ON a.panelist_id = p.panelist_id
    ";
}

// Query schedules
try {
    $sql = "SELECT $selectFields $fromClause WHERE s.status != 'Cancelled' GROUP BY s.schedule_id ORDER BY s.date DESC, s.time DESC";
    $stmt = $conn->query($sql);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $schedules = [];
    $message .= '<div class="bg-red-50 text-red-700 border border-red-200 p-4 rounded mb-4">Failed to fetch schedules: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Get ALL thesis groups including those with assignments but no confirmed schedule - FIXED QUERY
try {
    $unscheduledQuery = "
        SELECT DISTINCT tg.group_id, tg.leader_name, tg.title, tg.course,
               COUNT(DISTINCT a.assignment_id) as panel_count
        FROM thesis_group tg
        LEFT JOIN assignment a ON tg.group_id = a.group_id
        WHERE tg.group_id NOT IN (SELECT group_id FROM schedule WHERE status = 'Confirmed')
        GROUP BY tg.group_id
        ORDER BY tg.created_at DESC
    ";
    
    $stmt = $conn->query($unscheduledQuery);
    $unscheduled_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $unscheduled_groups = [];
    $message .= '<div class="bg-red-50 text-red-700 border border-red-200 p-4 rounded mb-4">Failed to fetch thesis groups: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Get available rooms
try {
    if ($hasRoomsTable) {
        $stmt = $conn->query("SELECT * FROM rooms WHERE is_available = 1 ORDER BY name ASC");
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $rooms = [];
    }
} catch (PDOException $e) {
    $rooms = [];
}

// Get all panelists for panelist selection - FIXED TO USE panelist TABLE
try {
    $stmt = $conn->query("SELECT panelist_id, first_name, last_name, expertise, department FROM panelist WHERE status = 'active' ORDER BY last_name ASC");
    $faculty_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $faculty_list = [];
    $message .= '<div class="bg-red-50 text-red-700 border border-red-200 p-4 rounded mb-4">Failed to fetch panelists: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Get pre-assigned panelists for each group (from assign_panel.php)
$group_assignments = [];
if (!empty($unscheduled_groups)) {
    $group_ids = array_column($unscheduled_groups, 'group_id');
    $placeholders = implode(',', array_fill(0, count($group_ids), '?'));
    
    try {
        $stmt = $conn->prepare("
            SELECT a.group_id, a.panelist_id, a.role, 
                   CONCAT(p.first_name, ' ', p.last_name) as panelist_name
            FROM assignment a
            INNER JOIN panelist p ON a.panelist_id = p.panelist_id
            WHERE a.group_id IN ($placeholders)
            ORDER BY 
                CASE a.role 
                    WHEN 'Chair' THEN 1 
                    WHEN 'Critic' THEN 2 
                    WHEN 'Member' THEN 3 
                    ELSE 4 
                END
        ");
        $stmt->execute($group_ids);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($assignments as $assign) {
            if (!isset($group_assignments[$assign['group_id']])) {
                $group_assignments[$assign['group_id']] = [];
            }
            $group_assignments[$assign['group_id']][] = $assign;
        }
    } catch (PDOException $e) {
        // Silently handle error
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Schedules</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gray-100 flex min-h-screen">

<?php include 'sidebar.php'; ?>

<!-- Main Content -->
<main class="flex-1 ml-64 p-10">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-2">
            <i data-lucide="calendar"></i> Defense Schedule Management
        </h1>
        <button onclick="toggleModal()" class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2 shadow-md transition">
            <i data-lucide="plus-circle"></i> Schedule Defense
        </button>
    </div>

    <?= $message ?>

    <!-- Unscheduled Groups Alert -->
    <?php if (!empty($unscheduled_groups)): ?>
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded-lg">
        <div class="flex items-center gap-2 mb-2">
            <i data-lucide="alert-circle" class="w-5 h-5 text-yellow-600"></i>
            <p class="font-semibold text-yellow-800"><?= count($unscheduled_groups) ?> thesis groups need to be scheduled</p>
        </div>
        <button onclick="toggleModal()" class="text-yellow-700 hover:text-yellow-900 underline text-sm">
            Schedule them now →
        </button>
    </div>
    <?php endif; ?>

    <!-- Schedules List -->
    <div class="bg-white shadow-lg rounded-2xl p-6 overflow-x-auto">
        <h2 class="text-xl font-semibold mb-4 flex items-center gap-2 text-gray-800">
            <i data-lucide="list"></i> All Defense Schedules
        </h2>
        <table class="min-w-full border border-gray-200 text-sm">
            <thead class="bg-blue-600 text-white">
                <tr>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Time</th>
                    <th class="px-4 py-3 text-left">Group Leader</th>
                    <th class="px-4 py-3 text-left">Thesis Title</th>
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-left">Room</th>
                    <th class="px-4 py-3 text-left">Panelists</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($schedules)): ?>
                    <?php foreach ($schedules as $sched): ?>
                        <tr class="border-b hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-semibold"><?= date('M d, Y', strtotime($sched['date'])) ?></td>
                            <td class="px-4 py-3">
                                <?php
                                    $start = $sched['time'] ?? null;
                                    $end = $sched['end_time'] ?? null;
                                    if ($start) echo date('g:i A', strtotime($start));
                                    if ($end) echo ' - ' . date('g:i A', strtotime($end));
                                ?>
                            </td>
                            <td class="px-4 py-3"><?= htmlspecialchars($sched['leader_name'] ?? '') ?></td>
                            <td class="px-4 py-3 max-w-xs truncate" title="<?= htmlspecialchars($sched['title'] ?? 'No title') ?>">
                                <?= htmlspecialchars(substr($sched['title'] ?? 'No title', 0, 50)) . (strlen($sched['title'] ?? '') > 50 ? '...' : '') ?>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs font-medium">
                                    <?= htmlspecialchars($sched['defense_type'] ?? '') ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <?php if (!empty($sched['room_name'])): ?>
                                    <div class="flex flex-col">
                                        <span class="font-medium"><?= htmlspecialchars($sched['room_name']) ?></span>
                                        <span class="text-xs text-gray-500"><?= htmlspecialchars($sched['room_mode'] ?? '') ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400">TBA</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-xs max-w-xs truncate" title="<?= htmlspecialchars($sched['panelists'] ?? 'Not assigned') ?>">
                                <?php if (!empty($sched['panelists'])): ?>
                                    <span class="text-green-700 font-semibold"><?= htmlspecialchars($sched['panelists']) ?></span>
                                <?php else: ?>
                                    <span class="text-red-500 font-semibold">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold 
                                    <?php
                                        $statusClass = 'bg-yellow-100 text-yellow-700';
                                        switch ($sched['status'] ?? '') {
                                            case 'Confirmed': $statusClass = 'bg-green-100 text-green-700'; break;
                                            case 'Completed': $statusClass = 'bg-blue-100 text-blue-700'; break;
                                            case 'Done': $statusClass = 'bg-blue-100 text-blue-700'; break;
                                            case 'Cancelled': $statusClass = 'bg-red-100 text-red-700'; break;
                                        }
                                    echo $statusClass;
                                    ?>">
                                    <?= htmlspecialchars($sched['status'] ?? '') ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="inline-flex items-center gap-2">
                                    <button onclick="viewScheduleDetails(<?= intval($sched['schedule_id']) ?>)" class="text-blue-600 hover:text-blue-800" title="View">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </button>
                                    <button onclick="confirmDelete(<?= intval($sched['schedule_id']) ?>)" class="text-red-600 hover:text-red-800" title="Cancel">
                                        <i data-lucide="x-circle" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="text-center py-6 text-gray-500">No schedules found. Create one to get started!</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Create Schedule Modal -->
<div id="scheduleModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl my-8 max-h-[90vh] overflow-y-auto">
        <div class="bg-blue-600 text-white p-6 rounded-t-2xl flex justify-between items-center sticky top-0 z-10">
            <h3 class="text-2xl font-bold flex items-center gap-2">
                <i data-lucide="calendar-plus"></i> Schedule Defense
            </h3>
            <button onclick="toggleModal()" class="text-white hover:bg-blue-700 p-2 rounded-full transition">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        
        <form method="POST" class="p-6 space-y-5" onsubmit="return validateForm()">
            <input type="hidden" name="create_schedule" value="1">
            
            <!-- Group Selection -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Select Thesis Group *</label>
                <select name="group_id" id="groupSelect" required class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500" onchange="updateGroupInfo(this)">
                    <option value="">-- Select Group --</option>
                    <?php foreach ($unscheduled_groups as $group): ?>
                        <option value="<?= htmlspecialchars($group['group_id']) ?>" 
                                data-title="<?= htmlspecialchars($group['title'] ?? 'No title') ?>" 
                                data-leader="<?= htmlspecialchars($group['leader_name'] ?? '') ?>"
                                data-panel-count="<?= intval($group['panel_count'] ?? 0) ?>">
                            <?= htmlspecialchars(($group['course'] ?? '') . ' - ' . ($group['leader_name'] ?? '')) ?>
                            <?php if (isset($group_assignments[$group['group_id']]) && count($group_assignments[$group['group_id']]) > 0): ?>
                                ✓ Panel Assigned (<?= count($group_assignments[$group['group_id']]) ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="groupInfo" class="mt-2 text-sm bg-blue-50 p-3 rounded hidden">
                    <p><strong>Leader:</strong> <span id="groupLeader"></span></p>
                    <p><strong>Title:</strong> <span id="groupTitle"></span></p>
                    <div id="existingPanel" class="mt-2 hidden">
                        <p class="text-green-700 font-semibold flex items-center gap-1">
                            <i data-lucide="check-circle" class="w-4 h-4"></i>
                            Pre-assigned Panel:
                        </p>
                        <ul id="panelList" class="ml-5 mt-1 text-xs space-y-1"></ul>
                    </div>
                </div>
            </div>

            <!-- Date and Time -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Date *</label>
                    <input type="date" name="date" id="defenseDate" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                           class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500"
                           onchange="checkWeekend(this)">
                    <p id="weekendWarning" class="text-xs text-red-600 mt-1 hidden">⚠️ Weekend selected</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Start Time *</label>
                    <input type="time" name="time" required
                           class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <?php if ($hasEndTimeColumn): ?>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">End Time *</label>
                    <input type="time" name="end_time" required
                           class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500">
                    <?php else: ?>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">End Time (Optional)</label>
                    <input type="time" name="end_time"
                           class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500">
                    <?php endif; ?>
                </div>
            </div>

            <!-- Room and Defense Type -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Room</label>
                    <?php if ($hasRoomsTable): ?>
                    <select name="room_id" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Select Room (Optional) --</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?= htmlspecialchars($room['room_id']) ?>">
                                <?= htmlspecialchars(($room['name'] ?? '') . ' (' . ($room['mode'] ?? '') . ') - Cap: ' . ($room['capacity'] ?? 'N/A')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <select disabled class="w-full border border-gray-300 rounded-lg p-3 bg-gray-100">
                        <option>No rooms available (table missing)</option>
                    </select>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Defense Type *</label>
                    <select name="defense_type" required class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Select Type --</option>
                        <option value="Proposal Defense">Proposal Defense</option>
                        <option value="Pre-Defense">Pre-Defense</option>
                        <option value="Final Defense">Final Defense</option>
                    </select>
                </div>
            </div>

            <!-- Panel Composition -->
            <div class="border-t pt-4">
                <h4 class="text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2">
                    <i data-lucide="users" class="w-5 h-5 text-blue-600"></i> Panel Composition
                </h4>
                
                <div id="panelistContainer" class="space-y-3">
                    <!-- Chair -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-3 bg-blue-50 rounded-lg">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Chair *</label>
                            <select name="panelists[]" required class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-blue-500 panelist-select" data-role="Chair">
                                <option value="">-- Select Chair --</option>
                                <?php foreach ($faculty_list as $fac): ?>
                                    <option value="<?= htmlspecialchars($fac['panelist_id']) ?>" data-name="<?= htmlspecialchars($fac['first_name'] . ' ' . $fac['last_name']) ?>">
                                        <?= htmlspecialchars($fac['first_name'] . ' ' . $fac['last_name'] . ' (' . ($fac['expertise'] ?? '') . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" name="roles[]" value="Chair">
                        <div class="flex items-end">
                            <span class="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold">CHAIR</span>
                        </div>
                    </div>

                    <!-- Critic -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-3 bg-green-50 rounded-lg">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Critic *</label>
                            <select name="panelists[]" required class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-green-500 panelist-select" data-role="Critic">
                                <option value="">-- Select Critic --</option>
                                <?php foreach ($faculty_list as $fac): ?>
                                    <option value="<?= htmlspecialchars($fac['panelist_id']) ?>" data-name="<?= htmlspecialchars($fac['first_name'] . ' ' . $fac['last_name']) ?>">
                                        <?= htmlspecialchars($fac['first_name'] . ' ' . $fac['last_name'] . ' (' . ($fac['expertise'] ?? '') . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" name="roles[]" value="Critic">
                        <div class="flex items-end">
                            <span class="px-3 py-2 bg-green-600 text-white rounded-lg text-sm font-semibold">CRITIC</span>
                        </div>
                    </div>

                    <!-- Member -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-3 bg-yellow-50 rounded-lg">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Member *</label>
                            <select name="panelists[]" required class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-yellow-500 panelist-select" data-role="Member">
                                <option value="">-- Select Member --</option>
                                <?php foreach ($faculty_list as $fac): ?>
                                    <option value="<?= htmlspecialchars($fac['panelist_id']) ?>" data-name="<?= htmlspecialchars($fac['first_name'] . ' ' . $fac['last_name']) ?>">
                                        <?= htmlspecialchars($fac['first_name'] . ' ' . $fac['last_name'] . ' (' . ($fac['expertise'] ?? '') . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" name="roles[]" value="Member">
                        <div class="flex items-end">
                            <span class="px-3 py-2 bg-yellow-600 text-white rounded-lg text-sm font-semibold">MEMBER</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 pt-4 border-t">
                <button type="submit"
                        class="flex-1 bg-blue-600 text-white font-semibold py-3 rounded-lg hover:bg-blue-700 transition flex items-center justify-center gap-2">
                    <i data-lucide="save"></i> Create Schedule
                </button>
                <button type="button" onclick="toggleModal()"
                        class="px-6 bg-gray-200 text-gray-700 font-semibold py-3 rounded-lg hover:bg-gray-300 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    lucide.createIcons();
    
    // Store group assignments from PHP
    const groupAssignments = <?= json_encode($group_assignments) ?>;

    function toggleModal() {
        const modal = document.getElementById('scheduleModal');
        modal.classList.toggle('hidden');
    }

    function updateGroupInfo(select) {
        const option = select.options[select.selectedIndex];
        if (option.value) {
            const groupId = option.value;
            document.getElementById('groupInfo').classList.remove('hidden');
            document.getElementById('groupLeader').textContent = option.getAttribute('data-leader');
            document.getElementById('groupTitle').textContent = option.getAttribute('data-title');

            // Check if this group has pre-assigned panelists
            if (groupAssignments[groupId] && groupAssignments[groupId].length > 0) {
                const existingPanel = document.getElementById('existingPanel');
                const panelList = document.getElementById('panelList');
                panelList.innerHTML = '';
                
                groupAssignments[groupId].forEach(assignment => {
                    const li = document.createElement('li');
                    li.className = 'text-gray-700';
                    li.innerHTML = `<strong>${assignment.role}:</strong> ${assignment.panelist_name}`;
                    panelList.appendChild(li);
                    
                    // Auto-select the panelist in the dropdown
                    const panelistSelect = document.querySelector(`.panelist-select[data-role="${assignment.role}"]`);
                    if (panelistSelect) {
                        panelistSelect.value = assignment.panelist_id;
                    }
                });
                
                existingPanel.classList.remove('hidden');
                lucide.createIcons();
            } else {
                document.getElementById('existingPanel').classList.add('hidden');
                // Clear all selections
                document.querySelectorAll('.panelist-select').forEach(select => {
                    select.value = '';
                });
            }
        } else {
            document.getElementById('groupInfo').classList.add('hidden');
            document.getElementById('existingPanel').classList.add('hidden');
            // Clear all selections
            document.querySelectorAll('.panelist-select').forEach(select => {
                select.value = '';
            });
        }
    }

    function checkWeekend(input) {
        const date = new Date(input.value);
        const day = date.getDay();
        const warning = document.getElementById('weekendWarning');

        if (day === 0 || day === 6) {
            warning.classList.remove('hidden');
        } else {
            warning.classList.add('hidden');
        }
    }

    function validateForm() {
        const panelistSelects = document.querySelectorAll('.panelist-select');
        const selectedPanelists = [];

        for (const select of panelistSelects) {
            if (select.value) {
                if (selectedPanelists.includes(select.value)) {
                    alert('Error: Cannot assign the same panelist to multiple roles!');
                    return false;
                }
                selectedPanelists.push(select.value);
            }
        }

        const startTime = document.querySelector('input[name="time"]').value;
        const endTime = document.querySelector('input[name="end_time"]').value;

        if (startTime && endTime && startTime >= endTime) {
            alert('Error: End time must be after start time!');
            return false;
        }

        return true;
    }

    function viewScheduleDetails(scheduleId) {
        window.location.href = 'viewSchedule.php?id=' + scheduleId;
    }

    function confirmDelete(scheduleId) {
        if (confirm('Are you sure you want to cancel this defense schedule? This action cannot be undone.')) {
            window.location.href = 'cancelSchedule.php?id=' + scheduleId;
        }
    }

    document.getElementById('scheduleModal').addEventListener('transitionend', function() {
        lucide.createIcons();
    });
</script>
</body>
</html>