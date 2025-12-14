<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/audit.php';
require_once __DIR__ . '/../../classes/faculty.php';
require_once __DIR__ . '/../../classes/email.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../../auth/login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();
$audit = new Audit();
$emailNotifier = new Email();
$faculty = new Faculty();
$message = '';

// Check for a group filter from the URL
$group_id_filter = $_GET['group_id'] ?? null;
$group_info = null;

// =================================================================
// HANDLE POST ACTIONS
// =================================================================

// --- Handle Create Schedule ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_schedule'])) {
    $group_id = $_POST['group_id'];
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $room_id = $_POST['room_id'];
    $mode = $_POST['mode'];
    $defense_type = $_POST['defense_type'];
    $time = "$start_time - $end_time"; // Combine times

    try {
        $sql = "INSERT INTO schedule (group_id, date, time, end_time, room, mode, defense_type, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$group_id, $date, $time, $end_time, $room_id, $mode, $defense_type]);
        $schedule_id = $conn->lastInsertId();
        
        $audit->logAction($_SESSION['user_id'] ?? 1, 'Admin', 'CREATE_SCHEDULE', 'schedule', $schedule_id, "Created schedule for group $group_id on $date");
        
        // Update group status to 'For Defense'
        $conn->prepare("UPDATE thesis_group SET status = 'For Defense' WHERE group_id = ?")->execute([$group_id]);

        $message = '<div class="bg-green-100 text-green-700 border border-green-400 p-4 rounded-lg mb-4">Schedule created successfully!</div>';

    } catch (PDOException $e) {
        $message = '<div class="bg-red-100 text-red-700 border border-red-400 p-4 rounded-lg mb-4">Error: ' . $e->getMessage() . '</div>';
    }
}

// --- Handle Update Schedule ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_schedule'])) {
    $schedule_id = $_POST['schedule_id'];
    $group_id = $_POST['group_id'];
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $room_id = $_POST['room_id'];
    $mode = $_POST['mode'] ?? 'physical'; 
    $defense_type = $_POST['defense_type'];
    $status = $_POST['status'];
    $time = "$start_time - $end_time";

    try {
        $sql = "UPDATE schedule SET group_id = ?, date = ?, time = ?, end_time = ?, room = ?, mode = ?, defense_type = ?, status = ?
                WHERE schedule_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$group_id, $date, $time, $end_time, $room_id, $mode, $defense_type, $status, $schedule_id]);
        
        $audit->logAction($_SESSION['user_id'] ?? 1, 'Admin', 'UPDATE_SCHEDULE', 'schedule', $schedule_id, "Updated schedule for group $group_id");

        // Notify panelists of the change
        $panelistEmails = $faculty->getPanelistEmailsForSchedule($schedule_id);
        if (!empty($panelistEmails)) {
            $room_name_stmt = $conn->prepare("SELECT name FROM rooms WHERE room_id = ?");
            $room_name_stmt->execute([$room_id]);
            $room_name = $room_name_stmt->fetchColumn() ?? 'TBA';

            $scheduleInfo = [
                'date' => $date, 'time' => $time, 'room' => $room_name,
                'mode' => $mode, 'group_name' => $conn->query("SELECT leader_name FROM thesis_group WHERE group_id = $group_id")->fetchColumn()
            ];
            $emailNotifier->sendScheduleChangeNotification($group_id, $panelistEmails, $scheduleInfo);
        }

        $message = '<div class="bg-green-100 text-green-700 border border-green-400 p-4 rounded-lg mb-4">Schedule updated successfully!</div>';

    } catch (PDOException $e) {
        $message = '<div class="bg-red-100 text-red-700 border border-red-400 p-4 rounded-lg mb-4">Error: ' . $e->getMessage() . '</div>';
    }
}

// --- Handle Assign Panelists ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_panel'])) {
    $schedule_id = $_POST['schedule_id'];
    $panelist_ids = $_POST['panelist_ids'] ?? [];
    
    // Fetch schedule info for notifications
    $stmt = $conn->prepare("SELECT s.group_id, s.date, s.time, s.mode, r.name as room_name, tg.leader_name 
                           FROM schedule s 
                           LEFT JOIN rooms r ON s.room = r.room_id
                           JOIN thesis_group tg ON s.group_id = tg.group_id
                           WHERE s.schedule_id = ?");
    $stmt->execute([$schedule_id]);
    $scheduleInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    $conn->beginTransaction();
    try {
        // 1. Clear existing panelists for this schedule
        $stmt = $conn->prepare("DELETE FROM assignment WHERE schedule_id = ?");
        $stmt->execute([$schedule_id]);

        // 2. Add new panelists
        $panelistEmails = [];
        foreach ($panelist_ids as $panelist_id) {
            // Determine role (first is Chair, rest are Members)
            $role_sql = "SELECT COUNT(*) FROM assignment WHERE schedule_id = ?";
            $role_stmt = $conn->prepare($role_sql);
            $role_stmt->execute([$schedule_id]);
            $count = $role_stmt->fetchColumn();
            $role = ($count == 0) ? 'Chair' : 'Member';

            $sql = "INSERT INTO assignment (schedule_id, group_id, panelist_id, role, assigned_date)
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$schedule_id, $scheduleInfo['group_id'], $panelist_id, $role]);

            // FIXED: Get email from panelist table instead of faculty
            $panelistEmails[] = $conn->query("SELECT email FROM panelist WHERE panelist_id = $panelist_id")->fetchColumn();
        }
        
        $conn->commit();

        // 3. Send notifications
        if (!empty($panelistEmails)) {
            $notifyInfo = [
                'date' => $scheduleInfo['date'],
                'time' => $scheduleInfo['time'],
                'room' => $scheduleInfo['room_name'] ?? 'TBA',
                'mode' => $scheduleInfo['mode'],
                'group_name' => $scheduleInfo['leader_name']
            ];
            $emailNotifier->sendPanelAssignmentNotification($scheduleInfo['group_id'], $panelistEmails, $notifyInfo);
        }

        $audit->logAction($_SESSION['user_id'], 'Admin', 'ASSIGN_PANEL', 'assignment', $schedule_id, "Assigned " . count($panelist_ids) . " panelists");
        $message = '<div class="bg-green-100 text-green-700 border border-green-400 p-4 rounded-lg mb-4">Panelists assigned successfully!</div>';

    } catch (PDOException $e) {
        $conn->rollBack();
        $message = '<div class="bg-red-100 text-red-700 border border-red-400 p-4 rounded-lg mb-4">Error: ' . $e->getMessage() . '</div>';
    }
}

// --- Handle Cancel Schedule ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_schedule'])) {
    $schedule_id = $_POST['schedule_id'];

    try {
        // FIXED: Notify panelists first - changed faculty to panelist
        $stmt_panelists = $conn->prepare("
            SELECT p.email, s.group_id, tg.leader_name
            FROM assignment a
            JOIN panelist p ON a.panelist_id = p.panelist_id
            JOIN schedule s ON a.schedule_id = s.schedule_id
            JOIN thesis_group tg ON s.group_id = tg.group_id
            WHERE a.schedule_id = ?
        ");
        $stmt_panelists->execute([$schedule_id]);
        $panelists = $stmt_panelists->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($panelists)) {
            $panelistEmails = array_column($panelists, 'email');
            $group_id = $panelists[0]['group_id'];
            $group_name = $panelists[0]['leader_name'];
            $cancelInfo = ['date' => 'CANCELLED', 'time' => '', 'room' => '', 'mode' => '', 'group_name' => $group_name];
            $emailNotifier->sendScheduleChangeNotification($group_id, $panelistEmails, $cancelInfo);
        }

        // Update schedule status
        $stmt = $conn->prepare("UPDATE schedule SET status = 'Cancelled' WHERE schedule_id = ?");
        $stmt->execute([$schedule_id]);
        
        $audit->logAction($_SESSION['user_id'], 'Admin', 'CANCEL_SCHEDULE', 'schedule', $schedule_id, "Cancelled schedule");
        $message = '<div class="bg-yellow-100 text-yellow-700 border border-yellow-400 p-4 rounded-lg mb-4">Schedule has been cancelled.</div>';

    } catch (PDOException $e) {
        $message = '<div class="bg-red-100 text-red-700 border border-red-400 p-4 rounded-lg mb-4">Error: ' . $e->getMessage() . '</div>';
    }
}


// =================================================================
// FETCH DATA FOR PAGE
// =================================================================

$params = [];
$whereClause = "";

// If filtering by group, fetch that group's info for the title
if ($group_id_filter && is_numeric($group_id_filter)) {
    try {
        $stmt = $conn->prepare("SELECT leader_name, course FROM thesis_group WHERE group_id = ?");
        $stmt->execute([$group_id_filter]);
        $group_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $whereClause = " WHERE s.group_id = ? ";
        $params[] = $group_id_filter;

    } catch (PDOException $e) {
        $message .= '<div class="bg-red-100 text-red-700 border border-red-400 p-4 rounded-lg mb-4">Error fetching group info.</div>';
    }
}

// FIXED: Fetch all schedules - changed faculty to panelist
try {
    $sql = "
        SELECT 
            s.*, 
            tg.leader_name, 
            tg.course,
            r.name as room_name,
            (SELECT GROUP_CONCAT(CONCAT(p.first_name, ' ', p.last_name) SEPARATOR ', ') 
             FROM assignment a 
             JOIN panelist p ON a.panelist_id = p.panelist_id 
             WHERE a.schedule_id = s.schedule_id) as panelists,
            (SELECT GROUP_CONCAT(a.panelist_id)
             FROM assignment a
             WHERE a.schedule_id = s.schedule_id) as assigned_panelist_ids
        FROM schedule s
        JOIN thesis_group tg ON s.group_id = tg.group_id
        LEFT JOIN rooms r ON s.room = r.room_id
        $whereClause
        ORDER BY s.date DESC, s.time DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $schedules = [];
    $message .= '<div class="bg-red-100 text-red-700 border border-red-400 p-4 rounded-lg mb-4">Error fetching schedules: ' . $e->getMessage() . '</div>';
}

// Fetch data for modals
try {
    // Get groups (only those not yet Defended, for the create dropdown)
    $stmt_groups = $conn->query("SELECT group_id, leader_name, course FROM thesis_group WHERE status != 'Defended' ORDER BY leader_name");
    $all_groups = $stmt_groups->fetchAll(PDO::FETCH_ASSOC);

    // Get all rooms
    $stmt_rooms = $conn->query("SELECT room_id, name, location_details FROM rooms WHERE is_available = 1 ORDER BY name");
    $all_rooms = $stmt_rooms->fetchAll(PDO::FETCH_ASSOC);

    // FIXED: Get all active panelists - changed from faculty to panelist
    $stmt_panelists = $conn->query("SELECT panelist_id, first_name, last_name, expertise FROM panelist WHERE status = 'active' ORDER BY last_name");
    $all_panelists = $stmt_panelists->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $all_groups = [];
    $all_rooms = [];
    $all_panelists = [];
    $message .= '<div class="bg-red-100 text-red-700 border border-red-400 p-4 rounded-lg mb-4">Error fetching data for modals.</div>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Defense Schedules</title>
    <script src="/Thesis/assets/js/tailwind.js"></script>
    <script src="/Thesis/assets/js/lucide.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        .select2-container .select2-selection--multiple {
            border: 1px solid #D1D5DB;
            border-radius: 0.5rem;
            padding: 0.5rem;
            min-height: 42px;
        }
        .select2-container .select2-search__field {
            height: 28px;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #3B82F6;
            border: none;
            color: white;
            border-radius: 0.25rem;
            padding: 2px 8px;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: white;
            margin-right: 5px;
        }
        .select2-dropdown {
            border-radius: 0.5rem;
            border: 1px solid #D1D5DB;
        }
    </style>
</head>
<body class="bg-gray-100 flex min-h-screen">

<?php include __DIR__ . '/../sidebar.php'; ?>

<!-- Main Content -->
<main class="flex-1 ml-64 p-10">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-2">
            <i data-lucide="calendar-days"></i>
            <?php if ($group_info): ?>
                Schedules for <?= htmlspecialchars($group_info['leader_name']) ?> (<?= htmlspecialchars($group_info['course']) ?>)
            <?php else: ?>
                Manage All Defense Schedules
            <?php endif; ?>
        </h1>
        <button onclick="toggleModal('createModal')" class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2 shadow-md transition">
            <i data-lucide="plus-circle"></i> Create New Schedule
        </button>
    </div>

    <?= $message ?>

    <!-- Schedules Table -->
    <div class="bg-white shadow-lg rounded-2xl p-6 overflow-x-auto">
        <table class="min-w-full border border-gray-200 text-sm">
            <thead class="bg-blue-600 text-white">
                <tr>
                    <th class="px-4 py-3 text-left">Group</th>
                    <th class="px-4 py-3 text-left">Date & Time</th>
                    <th class="px-4 py-3 text-left">Room</th>
                    <th class="px-4 py-3 text-left">Panelists</th>
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($schedules)): ?>
                    <?php foreach ($schedules as $s): ?>
                        <tr class="border-b hover:bg-gray-50 transition">
                            <td class="px-4 py-3">
                                <div class="font-semibold"><?= htmlspecialchars($s['leader_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($s['course']) ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold"><?= date('M d, Y', strtotime($s['date'])) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($s['time']) ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold"><?= htmlspecialchars($s['room_name'] ?? 'N/A') ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($s['mode']) ?></div>
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                <?php if (!empty($s['panelists'])): ?>
                                    <span class="text-green-700 font-semibold text-xs"><?= htmlspecialchars($s['panelists']) ?></span>
                                <?php else: ?>
                                    <span class="text-red-500 font-semibold text-xs">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3"><?= htmlspecialchars($s['defense_type']) ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold 
                                    <?php 
                                        if ($s['status'] === 'Pending') echo 'bg-yellow-100 text-yellow-700';
                                        elseif ($s['status'] === 'Confirmed') echo 'bg-blue-100 text-blue-700';
                                        elseif ($s['status'] === 'Done') echo 'bg-green-100 text-green-700';
                                        elseif ($s['status'] === 'Cancelled') echo 'bg-red-100 text-red-700';
                                        else echo 'bg-gray-100 text-gray-700';
                                    ?>">
                                    <?= htmlspecialchars($s['status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="inline-flex items-center gap-3">
                                    <button onclick='assignPanel(<?= $s['schedule_id'] ?>, [<?= $s['assigned_panelist_ids'] ?? '' ?>])' 
                                            class="text-purple-600 hover:text-purple-800" title="Assign Panelists">
                                        <i data-lucide="users" class="w-4 h-4"></i>
                                    </button>
                                    <button onclick='editSchedule(<?= htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8') ?>)' 
                                            class="text-blue-600 hover:text-blue-800" title="Edit Schedule">
                                        <i data-lucide="edit" class="w-4 h-4"></i>
                                    </button>
                                    <?php if ($s['status'] !== 'Cancelled'): ?>
                                    <button onclick="confirmCancel(<?= $s['schedule_id'] ?>, '<?= htmlspecialchars(addslashes($s['leader_name']), ENT_QUOTES) ?>', '<?= date('M d, Y', strtotime($s['date'])) ?>')" 
                                            class="text-red-600 hover:text-red-800" title="Cancel Schedule">
                                        <i data-lucide="calendar-x" class="w-4 h-4"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center py-6 text-gray-500">
                        <?php if ($group_info): ?>
                            No schedules found for this group. Create one to get started!
                        <?php else: ?>
                            No schedules found.
                        <?php endif; ?>
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Create Schedule Modal -->
<div id="createModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl">
        <form method="POST">
            <div class="bg-blue-600 text-white p-6 rounded-t-2xl flex justify-between items-center">
                <h3 class="text-2xl font-bold flex items-center gap-2"><i data-lucide="calendar-plus"></i> Create New Schedule</h3>
                <button type="button" onclick="toggleModal('createModal')" class="text-white hover:bg-blue-700 p-2 rounded-full transition"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            
            <div class="p-6 grid grid-cols-2 gap-4">
                <input type="hidden" name="create_schedule" value="1">
                
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Thesis Group *</label>
                    <select name="group_id" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500" 
                        <?php if ($group_id_filter) echo 'readonly'; ?>>
                        <?php foreach ($all_groups as $group): ?>
                            <option value="<?= $group['group_id'] ?>" <?php if ($group_id_filter == $group['group_id']) echo 'selected'; ?>>
                                <?= htmlspecialchars($group['leader_name']) ?> (<?= htmlspecialchars($group['course']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Date *</label>
                    <input type="date" name="date" required class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Room *</label>
                    <select name="room_id" required class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($all_rooms as $room): ?>
                            <option value="<?= $room['room_id'] ?>"><?= htmlspecialchars($room['name']) ?> (<?= htmlspecialchars($room['location_details']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Start Time *</label>
                    <input type="time" name="start_time" required class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">End Time *</label>
                    <input type="time" name="end_time" required class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Mode *</label>
                    <select name="mode" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500">
                        <option value="online">Online</option>
                        <option value="physical" selected>Physical</option>
                        <option value="hybrid">Hybrid</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Defense Type *</label>
                    <select name="defense_type" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500">
                        <option value="Proposal">Proposal Defense</option>
                        <option value="Final">Final Defense</option>
                    </select>
                </div>
            </div>

            <div class="flex gap-3 p-6 border-t">
                <button type="submit" class="flex-1 bg-blue-600 text-white font-semibold py-3 rounded-lg hover:bg-blue-700 transition flex items-center justify-center gap-2">
                    <i data-lucide="save"></i> Save Schedule
                </button>
                <button type="button" onclick="toggleModal('createModal')" class="px-6 bg-gray-200 text-gray-700 font-semibold py-3 rounded-lg hover:bg-gray-300 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Schedule Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl">
        <form method="POST">
            <div class="bg-green-600 text-white p-6 rounded-t-2xl flex justify-between items-center">
                <h3 class="text-2xl font-bold flex items-center gap-2"><i data-lucide="edit"></i> Edit Schedule</h3>
                <button type="button" onclick="toggleModal('editModal')" class="text-white hover:bg-green-700 p-2 rounded-full transition"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            
            <div class="p-6 grid grid-cols-2 gap-4">
                <input type="hidden" name="update_schedule" value="1">
                <input type="hidden" name="schedule_id" id="edit_schedule_id">
                
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Thesis Group *</label>
                    <select name="group_id" id="edit_group_id" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-green-500">
                        <?php foreach ($all_groups as $group): ?>
                            <option value="<?= $group['group_id'] ?>">
                                <?= htmlspecialchars($group['leader_name']) ?> (<?= htmlspecialchars($group['course']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Date *</label>
                    <input type="date" name="date" id="edit_date" required class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Room *</label>
                    <select name="room_id" id="edit_room_id" required class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-green-500">
                        <?php foreach ($all_rooms as $room): ?>
                            <option value="<?= $room['room_id'] ?>"><?= htmlspecialchars($room['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Start Time *</label>
                    <input type="time" name="start_time" id="edit_start_time" required class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">End Time *</label>
                    <input type="time" name="end_time" id="edit_end_time" required class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Mode *</label>
                    <select name="mode" id="edit_mode" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-green-500">
                        <option value="online">Online</option>
                        <option value="physical">Physical</option>
                        <option value="hybrid">Hybrid</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Defense Type *</label>
                    <select name="defense_type" id="edit_defense_type" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-green-500">
                        <option value="Proposal">Proposal Defense</option>
                        <option value="Final">Final Defense</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status *</label>
                    <select name="status" id="edit_status" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-green-500">
                        <option value="Pending">Pending</option>
                        <option value="Confirmed">Confirmed</option>
                        <option value="Done">Done</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-3 p-6 border-t">
                <button type="submit" class="flex-1 bg-green-600 text-white font-semibold py-3 rounded-lg hover:bg-green-700 transition flex items-center justify-center gap-2">
                    <i data-lucide="save"></i> Update Schedule
                </button>
                <button type="button" onclick="toggleModal('editModal')" class="px-6 bg-gray-200 text-gray-700 font-semibold py-3 rounded-lg hover:bg-gray-300 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Panelists Modal -->
<div id="assignModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl">
        <form method="POST">
            <div class="bg-purple-600 text-white p-6 rounded-t-2xl flex justify-between items-center">
                <h3 class="text-2xl font-bold flex items-center gap-2"><i data-lucide="users"></i> Assign Panelists</h3>
                <button type="button" onclick="toggleModal('assignModal')" class="text-white hover:bg-purple-700 p-2 rounded-full transition"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            
            <div class="p-6 space-y-4">
                <input type="hidden" name="assign_panel" value="1">
                <input type="hidden" name="schedule_id" id="assign_schedule_id">
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Select Panelists *</label>
                    <p class="text-xs text-gray-500 mb-2">Select one or more panelists. The first panelist you select will be assigned as the Chair.</p>
                    <select name="panelist_ids[]" id="assign_panelist_ids" multiple="multiple" class="w-full">
                        <?php foreach ($all_panelists as $panelist): ?>
                            <option value="<?= $panelist['panelist_id'] ?>">
                                <?= htmlspecialchars($panelist['last_name']) ?>, <?= htmlspecialchars($panelist['first_name']) ?> (<?= htmlspecialchars($panelist['expertise']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex gap-3 p-6 border-t">
                <button type="submit" class="flex-1 bg-purple-600 text-white font-semibold py-3 rounded-lg hover:bg-purple-700 transition flex items-center justify-center gap-2">
                    <i data-lucide="save"></i> Save Assignments
                </button>
                <button type="button" onclick="toggleModal('assignModal')" class="px-6 bg-gray-200 text-gray-700 font-semibold py-3 rounded-lg hover:bg-gray-300 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Cancel Schedule Modal -->
<div id="cancelModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <form method="POST">
            <div class="bg-red-600 text-white p-6 rounded-t-2xl flex justify-between items-center">
                <h3 class="text-2xl font-bold flex items-center gap-2"><i data-lucide="alert-triangle"></i> Confirm Cancellation</h3>
                <button type="button" onclick="toggleModal('cancelModal')" class="text-white hover:bg-red-700 p-2 rounded-full transition"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            
            <div class="p-6 space-y-4">
                <input type="hidden" name="cancel_schedule" value="1">
                <input type="hidden" name="schedule_id" id="cancel_schedule_id">
                <p class="text-gray-700 text-center">
                    Are you sure you want to cancel the schedule for:
                    <br>
                    <strong id="cancel_group_name" class="font-bold text-lg"></strong>
                    on <strong id="cancel_schedule_date" class="font-bold text-lg"></strong>?
                </p>
                <p class="text-sm text-red-600 bg-red-50 p-3 rounded-lg text-center">
                    This will notify all assigned panelists. This action can be undone by editing the schedule status later.
                </p>
            </div>

            <div class="flex gap-3 p-6 border-t">
                <button type="button" onclick="toggleModal('cancelModal')" class="flex-1 bg-gray-200 text-gray-700 font-semibold py-3 rounded-lg hover:bg-gray-300 transition">
                    Nevermind
                </button>
                <button type="submit" class="flex-1 bg-red-600 text-white font-semibold py-3 rounded-lg hover:bg-red-700 transition flex items-center justify-center gap-2">
                    <i data-lucide="calendar-x"></i> Yes, Cancel Schedule
                </button>
            </div>
        </form>
    </div>
</div>


<script>
    $(document).ready(function() {
        // Initialize Select2
        $('#assign_panelist_ids').select2({
            placeholder: "Search for a panelist...",
            width: '100%'
        });
    });

    lucide.createIcons();

    function toggleModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.classList.toggle('hidden');
        if (!modal.classList.contains('hidden')) {
            setTimeout(() => lucide.createIcons(), 50);
        }
    }

    function editSchedule(schedule) {
        document.getElementById('edit_schedule_id').value = schedule.schedule_id;
        document.getElementById('edit_group_id').value = schedule.group_id;
        document.getElementById('edit_date').value = schedule.date;
        document.getElementById('edit_room_id').value = schedule.room;
        document.getElementById('edit_mode').value = schedule.mode;
        document.getElementById('edit_defense_type').value = schedule.defense_type;
        document.getElementById('edit_status').value = schedule.status;

        // Split the 'time' string (e.g., "08:00 - 09:00")
        const times = schedule.time.split(' - ');
        if (times.length === 2) {
            document.getElementById('edit_start_time').value = times[0];
            document.getElementById('edit_end_time').value = times[1];
        }

        toggleModal('editModal');
    }

    function assignPanel(scheduleId, assignedIds) {
        document.getElementById('assign_schedule_id').value = scheduleId;
        
        // Convert assignedIds to an array of strings
        let idArray = [];
        if (Array.isArray(assignedIds)) {
            idArray = assignedIds.map(String);
        } else if (assignedIds) {
            idArray = String(assignedIds).split(',').map(String);
        }

        // Use jQuery to set the values for Select2
        $('#assign_panelist_ids').val(idArray).trigger('change');
        
        toggleModal('assignModal');
    }

    function confirmCancel(scheduleId, groupName, scheduleDate) {
        document.getElementById('cancel_schedule_id').value = scheduleId;
        document.getElementById('cancel_group_name').textContent = groupName;
        document.getElementById('cancel_schedule_date').textContent = scheduleDate;
        toggleModal('cancelModal');
    }

    // Pre-select group in create modal if group_id is in URL
    <?php if ($group_id_filter): ?>
        // Disable the group dropdown so the user can't change it
        document.querySelector('#createModal select[name="group_id"]').setAttribute('disabled', 'disabled');
        
        // Create a hidden input to carry the locked-in group_id
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'group_id';
        hiddenInput.value = '<?= $group_id_filter ?>';
        document.querySelector('#createModal form').appendChild(hiddenInput);
    <?php endif; ?>

</script>
</body>
</html>






