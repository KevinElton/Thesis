<?php
session_start();
require_once __DIR__ . '/../classes/database.php';
require_once __DIR__ . '/../classes/email.php';
require_once __DIR__ . '/../classes/audit.php';
require_once __DIR__ . '/../classes/Notification.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();
$message = '';

// Get selected group
$group_id = $_GET['group_id'] ?? null;
$group_info = null;

if ($group_id) {
    $stmt = $conn->prepare("
        SELECT 
            tg.*, 
            COALESCE(t.title, '') AS thesis_title, 
            t.adviser_id,
            CONCAT(f.first_name, ' ', f.last_name) AS adviser_name
        FROM thesis_group tg
        LEFT JOIN thesis t ON tg.group_id = t.group_id
        LEFT JOIN faculty f ON t.adviser_id = f.panelist_id
        WHERE tg.group_id = ?
    ");
    $stmt->execute([$group_id]);
    $group_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch assigned panel for this group
$assigned_panel = [];
if ($group_id) {
    $stmt = $conn->prepare("
        SELECT
            a.assignment_id,
            a.group_id,
            a.panelist_id,
            a.role,
            CONCAT(f.first_name, ' ', f.last_name) AS panelist_name,
            f.expertise,
            f.email
        FROM assignment a
        INNER JOIN faculty f ON a.panelist_id = f.panelist_id
        WHERE a.group_id = ?
    ");
    $stmt->execute([$group_id]);
    $assigned_panel = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submission
$selected_day = $_POST['selected_day'] ?? null;
$schedule_date = $_POST['schedule_date'] ?? null;
$schedule_time = $_POST['schedule_time'] ?? null;
$schedule_end_time = $_POST['schedule_end_time'] ?? null;
$room = $_POST['room'] ?? 'TBA';
$available_panelists = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fetch available panelists based on selected day
    if ($selected_day) {
        $stmt = $conn->prepare("
            SELECT DISTINCT 
                p.panelist_id, 
                p.first_name, 
                p.last_name, 
                p.expertise,
                a.day,
                a.start_time, 
                a.end_time,
                TIME_FORMAT(a.start_time, '%h:%i %p') as formatted_start,
                TIME_FORMAT(a.end_time, '%h:%i %p') as formatted_end
            FROM panelist p
            INNER JOIN availability a ON p.panelist_id = a.panelist_id
            WHERE a.day = ?
              AND p.status = 'active'
            ORDER BY p.last_name, p.first_name
        ");
        $stmt->execute([$selected_day]);
        $available_panelists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Assign panel if requested
    if (isset($_POST['assign_panel'])) {
        $chair_id = $_POST['chair'] ?? null;
        $critic_id = $_POST['critic'] ?? null;
        $member_id = $_POST['member'] ?? null;

        $errors = [];
        $selected = array_filter([$chair_id, $critic_id, $member_id]);
        
        if (count($selected) !== count(array_unique($selected))) {
            $errors[] = "Cannot assign the same faculty to multiple roles.";
        }
        if ($group_info && !empty($group_info['adviser_id']) && in_array($group_info['adviser_id'], $selected)) {
            $errors[] = "Adviser cannot be assigned as panelist.";
        }
        if (!$schedule_date || !$schedule_time || !$schedule_end_time) {
            $errors[] = "Please set complete schedule (date, start time, and end time).";
        }
        if (!$selected_day) {
            $errors[] = "Please select a day of the week.";
        }

        // Validate that schedule date matches selected day
        if ($schedule_date && $selected_day) {
            $date_day = date('l', strtotime($schedule_date));
            if ($date_day !== $selected_day) {
                $errors[] = "Selected date ($schedule_date) is a $date_day, but you selected $selected_day. Please choose a date that matches the selected day.";
            }
        }

        // Validate time ranges for selected panelists
        if (empty($errors) && $schedule_time && $schedule_end_time) {
            $time_start = (new DateTime($schedule_time))->format('H:i:s');
            $time_end = (new DateTime($schedule_end_time))->format('H:i:s');
            
            if (strtotime($time_end) <= strtotime($time_start)) {
                $errors[] = "End time must be after start time.";
            } else {
                // Check if all selected panelists are available during the specified time
                foreach ($selected as $panelist_id) {
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) as available
                        FROM availability
                        WHERE panelist_id = ?
                          AND day = ?
                          AND ? >= start_time 
                          AND ? <= end_time
                    ");
                    $stmt->execute([$panelist_id, $selected_day, $time_start, $time_end]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result['available'] == 0) {
                        $stmt_name = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM panelist WHERE panelist_id = ?");
                        $stmt_name->execute([$panelist_id]);
                        $panelist_name = $stmt_name->fetch(PDO::FETCH_ASSOC)['name'];
                        $errors[] = "$panelist_name is not available during the selected time ($schedule_time - $schedule_end_time).";
                    }
                }
            }
        }

        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                
                // Check if schedule already exists for this group
                $stmt = $conn->prepare("SELECT schedule_id FROM schedule WHERE group_id = ? AND status != 'Cancelled'");
                $stmt->execute([$group_id]);
                $existing_schedule = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_schedule) {
                    $schedule_id = $existing_schedule['schedule_id'];
                    
                    // Update existing schedule
                    $stmt = $conn->prepare("
                        UPDATE schedule 
                        SET date = ?, time = ?, end_time = ?, room = ?, status = 'Confirmed'
                        WHERE schedule_id = ?
                    ");
                    $stmt->execute([$schedule_date, $schedule_time, $schedule_end_time, $room, $schedule_id]);
                    
                    // Delete old assignments for this schedule
                    $stmt = $conn->prepare("DELETE FROM assignment WHERE schedule_id = ?");
                    $stmt->execute([$schedule_id]);
                } else {
                    // Create new schedule entry
                    $stmt = $conn->prepare("
                        INSERT INTO schedule (group_id, date, time, end_time, room, defense_type, status)
                        VALUES (?, ?, ?, ?, ?, 'Proposal Defense', 'Confirmed')
                    ");
                    $stmt->execute([$group_id, $schedule_date, $schedule_time, $schedule_end_time, $room]);
                    $schedule_id = $conn->lastInsertId();
                }

                // Delete any existing assignments for this group
                $stmt = $conn->prepare("DELETE FROM assignment WHERE group_id = ? AND schedule_id IS NULL");
                $stmt->execute([$group_id]);

                // Insert new assignments
                $stmt = $conn->prepare("INSERT INTO assignment (schedule_id, group_id, panelist_id, role, assigned_date) VALUES (?, ?, ?, ?, NOW())");
                $roles = [['Chair', $chair_id], ['Critic', $critic_id], ['Member', $member_id]];
                foreach ($roles as [$role, $pid]) {
                    if ($pid) $stmt->execute([$schedule_id, $group_id, $pid, $role]);
                }

               
                $conn->commit();
                $message = '<div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-4 flex items-start gap-3"><i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0 mt-0.5"></i><div><strong>Success!</strong> Panel assigned and schedule created for ' . htmlspecialchars($group_info['leader_name']) . '\'s group.</div></div>';

                // Refresh assigned panel
                $stmt = $conn->prepare("
                    SELECT a.assignment_id, a.group_id, a.panelist_id, a.role, 
                           CONCAT(p.first_name, ' ', p.last_name) AS panelist_name, 
                           p.expertise, p.email
                    FROM assignment a
                    INNER JOIN panelist p ON a.panelist_id = p.panelist_id
                    WHERE a.group_id = ?
                ");
                $stmt->execute([$group_id]);
                $assigned_panel = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // ✅ SEND NOTIFICATIONS TO PANELISTS (In-System + Email)
                try {
                    $notification = new Notification();
                    
                    // Send notification to each panelist
                    foreach ($assigned_panel as $member) {
                        $notification->notifyPanelistAssignment(
                            $member['panelist_id'],
                            $member['email'],
                            $group_info['leader_name'],
                            $group_info['thesis_title'],
                            $schedule_date,
                            $schedule_time,
                            $schedule_end_time,
                            $room,
                            $member['role'],
                            $group_id
                            
                        );
                    }
                } catch (Exception $e) {
                    error_log("Notification error: " . $e->getMessage());
                }

                // Log audit action
                $audit = new Audit();
                $audit->logAction($_SESSION['user_id'] ?? 1, 'Admin', 'Panel Assignment', 'assignment', $group_id, 'Assigned panel members and created schedule for group ' . $group_id);

            } catch (PDOException $e) {
                $conn->rollBack();
                $message = '<div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-4 flex items-start gap-3"><i data-lucide="alert-triangle" class="w-5 h-5 flex-shrink-0 mt-0.5"></i><div><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
            }
        } else {
            $message = '<div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-4"><ul class="list-disc ml-5 space-y-1">';
            foreach ($errors as $e) $message .= '<li>' . htmlspecialchars($e) . '</li>';
            $message .= '</ul></div>';
        }
    }
}

// Fetch all groups
$groups = $conn->query("
    SELECT tg.group_id, tg.leader_name, tg.course, tg.status, COALESCE(t.title, '') AS thesis_title,
           COUNT(a.assignment_id) AS panel_count
    FROM thesis_group tg
    LEFT JOIN thesis t ON tg.group_id = t.group_id
    LEFT JOIN assignment a ON tg.group_id = a.group_id
    GROUP BY tg.group_id
    ORDER BY panel_count ASC, tg.group_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Days of the week
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Get available panelists count per day
$panelist_count_per_day = [];
foreach ($days_of_week as $day) {
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT p.panelist_id) as count
        FROM panelist p
        INNER JOIN availability a ON p.panelist_id = a.panelist_id
        WHERE a.day = ? AND p.status = 'active'
    ");
    $stmt->execute([$day]);
    $panelist_count_per_day[$day] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assign Panel Members - Admin Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<style>
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.85; }
}
.highlight-available {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    border-left: 4px solid #10b981;
    animation: pulse 2s ease-in-out infinite;
}
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-slide-in {
    animation: slideIn 0.3s ease-out;
}
.group-card {
    transition: all 0.2s ease;
}
.group-card:hover {
    transform: translateX(4px);
}
</style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-blue-50 to-purple-50 min-h-screen flex">
<?php include 'sidebar.php'; ?>

<main class="flex-1 ml-64 p-8">
<div class="max-w-7xl mx-auto">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-4xl font-bold mb-2 flex items-center gap-3 text-gray-800">
            <div class="p-3 bg-gradient-to-br from-purple-500 to-blue-600 rounded-2xl shadow-lg">
                <i data-lucide="user-check" class="w-8 h-8 text-white"></i>
            </div>
            Assign Panel Members
        </h1>
        <p class="text-gray-600 ml-16">Select a day to view available panelists and assign them to thesis groups</p>
    </div>

    <?= $message ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left: Thesis Groups -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-2xl shadow-lg p-6 sticky top-4">
                <h2 class="text-xl font-bold mb-4 flex items-center gap-2 text-gray-800">
                    <i data-lucide="users" class="w-5 h-5 text-purple-600"></i>
                    Thesis Groups
                </h2>
                <div class="space-y-2 max-h-[calc(100vh-200px)] overflow-y-auto pr-2">
                    <?php foreach ($groups as $g): ?>
                        <a href="?group_id=<?= $g['group_id'] ?>" class="group-card block p-4 rounded-xl border-2 transition <?= ($g['group_id']==$group_id)?'border-purple-600 bg-gradient-to-r from-purple-50 to-blue-50 shadow-md':'border-gray-200 hover:border-purple-300 hover:bg-gray-50' ?>">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($g['leader_name']) ?></p>
                                    <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($g['course']) ?></p>
                                </div>
                                <span class="px-2.5 py-1 text-xs font-bold rounded-full <?= ($g['panel_count']>=3)?'bg-green-100 text-green-700':'bg-yellow-100 text-yellow-700' ?>">
                                    <?= $g['panel_count'] ?>/3
                                </span>
                            </div>
                            <?php if ($g['thesis_title']): ?>
                                <p class="text-xs text-gray-600 mt-2 line-clamp-2 italic"><?= htmlspecialchars($g['thesis_title']) ?></p>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Center: Assignment Form -->
        <div class="lg:col-span-6">
            <?php if ($group_info): ?>
            <div class="bg-white rounded-2xl shadow-lg p-6 animate-slide-in">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($group_info['leader_name']) ?>'s Group</h2>
                    <?php if ($group_info['thesis_title']): ?>
                        <p class="text-sm text-gray-600 italic">"<?= htmlspecialchars($group_info['thesis_title']) ?>"</p>
                    <?php endif; ?>
                    <?php if ($group_info['adviser_name']): ?>
                        <p class="text-sm text-gray-600 mt-2">
                            <i data-lucide="user" class="inline w-4 h-4"></i>
                            <strong>Adviser:</strong> <?= htmlspecialchars($group_info['adviser_name']) ?>
                        </p>
                    <?php endif; ?>
                </div>

                <form method="POST" class="space-y-6">
                    <input type="hidden" name="group_id" value="<?= $group_id ?>">

                    <!-- STEP 1: Select Day of Week -->
                    <div class="p-5 bg-gradient-to-r from-blue-50 to-purple-50 border-2 border-blue-200 rounded-xl">
                        <label class="block mb-3 font-bold text-blue-900 flex items-center gap-2">
                            <i data-lucide="calendar-days" class="w-5 h-5"></i>
                            Step 1: Select Day of Week
                        </label>
                        <select name="selected_day" id="selected_day" required class="w-full border-2 border-blue-300 rounded-lg p-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 bg-white font-semibold">
                            <option value="">-- Select a Day --</option>
                            <?php foreach ($days_of_week as $day): ?>
                                <option value="<?= $day ?>" <?= ($selected_day == $day) ? 'selected' : '' ?>>
                                    <?= $day ?> (<?= $panelist_count_per_day[$day] ?> panelist<?= $panelist_count_per_day[$day] != 1 ? 's' : '' ?> available)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="mt-4 bg-gradient-to-r from-blue-600 to-purple-600 text-white px-5 py-2.5 rounded-lg hover:from-blue-700 hover:to-purple-700 text-sm w-full font-semibold shadow-md hover:shadow-lg transition flex items-center justify-center gap-2">
                            <i data-lucide="search" class="w-4 h-4"></i> View Available Panelists
                        </button>
                    </div>

                    <?php if($selected_day && !empty($available_panelists)): ?>
                        <!-- STEP 2: Schedule Details -->
                        <div class="p-5 bg-gradient-to-r from-purple-50 to-pink-50 border-2 border-purple-200 rounded-xl">
                            <label class="block mb-3 font-bold text-purple-900 flex items-center gap-2">
                                <i data-lucide="calendar-clock" class="w-5 h-5"></i>
                                Step 2: Set Defense Schedule
                            </label>
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label class="text-xs text-gray-600 font-semibold block mb-1">Date (must be a <?= $selected_day ?>)</label>
                                    <input type="date" name="schedule_date" value="<?= htmlspecialchars($schedule_date ?? '') ?>" required class="w-full border-2 border-gray-300 rounded-lg p-2.5 focus:border-purple-500 focus:ring-2 focus:ring-purple-200" min="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-xs text-gray-600 font-semibold block mb-1">Start Time</label>
                                        <input type="time" name="schedule_time" value="<?= htmlspecialchars($schedule_time ?? '') ?>" required class="w-full border-2 border-gray-300 rounded-lg p-2.5 focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-600 font-semibold block mb-1">End Time</label>
                                        <input type="time" name="schedule_end_time" value="<?= htmlspecialchars($schedule_end_time ?? '') ?>" required class="w-full border-2 border-gray-300 rounded-lg p-2.5 focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-600 font-semibold block mb-1">Room/Venue</label>
                                    <input type="text" name="room" value="<?= htmlspecialchars($room ?? 'TBA') ?>" placeholder="e.g., Room 101, Conference Hall" class="w-full border-2 border-gray-300 rounded-lg p-2.5 focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                                </div>
                            </div>
                        </div>

                        <!-- STEP 3: Assign Panelists -->
                        <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg mb-4">
                            <p class="text-green-800 font-semibold flex items-center gap-2">
                                <i data-lucide="check-circle" class="w-5 h-5"></i>
                                <?= count($available_panelists) ?> panelist<?= count($available_panelists) > 1 ? 's' : '' ?> available on <?= $selected_day ?>
                            </p>
                        </div>

                        <?php
                        $roles = [
                            'Chair' => ['color' => 'blue', 'icon' => 'crown'],
                            'Critic' => ['color' => 'green', 'icon' => 'message-square'],
                            'Member' => ['color' => 'yellow', 'icon' => 'user']
                        ];
                        foreach ($roles as $role => $config):
                            $color = $config['color'];
                            $icon = $config['icon'];
                            $selected_id = null;
                            foreach ($assigned_panel as $ap) {
                                if($ap['role']==$role) $selected_id=$ap['panelist_id'];
                            }
                        ?>
                        <div class="p-5 bg-<?= $color ?>-50 border-2 border-<?= $color ?>-200 rounded-xl">
                            <label class="block mb-3 font-bold text-<?= $color ?>-900 flex items-center gap-2">
                                <i data-lucide="<?= $icon ?>" class="w-5 h-5"></i>
                                <?= $role ?>
                            </label>
                            <select name="<?= strtolower($role) ?>" required class="w-full border-2 border-<?= $color ?>-300 rounded-lg p-3 focus:border-<?= $color ?>-500 focus:ring-2 focus:ring-<?= $color ?>-200 bg-white">
                                <option value="">-- Select <?= $role ?> --</option>
                                <?php foreach ($available_panelists as $f):
                                    if (!empty($group_info['adviser_id']) && $group_info['adviser_id']==$f['panelist_id']) continue;
                                ?>
                                    <option value="<?= $f['panelist_id'] ?>" <?= ($selected_id==$f['panelist_id'])?'selected':'' ?>>
                                        <?= htmlspecialchars($f['last_name'].', '.$f['first_name'].' - '.$f['expertise']) ?>
                                        (<?= $f['formatted_start'] ?> - <?= $f['formatted_end'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endforeach; ?>
                        
                        <input type="hidden" name="assign_panel" value="1">
                        <button type="submit" class="bg-gradient-to-r from-purple-600 to-blue-600 text-white py-3 px-6 rounded-xl hover:from-purple-700 hover:to-blue-700 w-full font-bold text-lg shadow-lg hover:shadow-xl transition flex items-center justify-center gap-2">
                            <i data-lucide="save" class="w-5 h-5"></i> Assign Panel & Set Schedule
                        </button>
                    <?php elseif($selected_day && empty($available_panelists)): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 p-5 rounded-lg">
                            <p class="text-red-700 font-bold mb-3 flex items-center gap-2">
                                <i data-lucide="alert-circle" class="w-6 h-6"></i>
                                No Panelists Available on <?= $selected_day ?>
                            </p>
                            <p class="text-red-600 text-sm mb-3">
                                No panelists have set their availability for <?= $selected_day ?>s yet.
                            </p>
                            <div class="mt-4 p-3 bg-blue-50 rounded border border-blue-200">
                                <p class="text-sm text-blue-800 flex items-start gap-2">
                                    <i data-lucide="lightbulb" class="w-5 h-5 flex-shrink-0 mt-0.5"></i>
                                    <span><strong>Solution:</strong> Ask panelists to add their availability for <?= $selected_day ?>s, or select a different day.</span>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                </form>

                <!-- Current Panel Assignment Display -->
                <?php if (!empty($assigned_panel)): ?>
                <div class="mt-6 p-5 bg-gray-50 border-2 border-gray-200 rounded-xl">
                    <h3 class="font-bold text-gray-800 mb-3 flex items-center gap-2">
                        <i data-lucide="users-round" class="w-5 h-5 text-purple-600"></i>
                        Current Panel Members
                    </h3>
                    <div class="space-y-2">
                        <?php foreach ($assigned_panel as $member): ?>
                            <div class="bg-white p-3 rounded-lg border border-gray-200 flex items-center justify-between">
                                <div>
                                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($member['panelist_name']) ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($member['expertise']) ?></p>
                                </div>
                                <span class="px-3 py-1 bg-purple-100 text-purple-700 text-xs font-bold rounded-full">
                                    <?= htmlspecialchars($member['role']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                <div class="max-w-md mx-auto">
                    <i data-lucide="arrow-left" class="w-20 h-20 mx-auto mb-6 text-gray-300"></i>
                    <h3 class="text-xl font-bold text-gray-700 mb-3">No Group Selected</h3>
                    <p class="text-gray-600">Select a thesis group from the left sidebar to start assigning panel members.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Right: Available Panelists -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-2xl shadow-lg p-6 sticky top-4">
                <h2 class="text-lg font-bold mb-4 text-green-700 flex items-center gap-2">
                    <i data-lucide="users-round" class="w-5 h-5"></i>
                    <div>
                        Available Panelists
                        <?php if ($selected_day): ?>
                            <span class="block text-xs font-normal text-gray-600 mt-0.5">
                                <?= $selected_day ?>s
                            </span>
                        <?php endif; ?>
                    </div>
                </h2>

                <div class="overflow-y-auto max-h-[calc(100vh-200px)] space-y-3">
                <?php if (!$selected_day): ?>
                    <div class="text-center text-gray-600 text-sm p-6 bg-gray-50 rounded-lg">
                        <i data-lucide="info" class="w-10 h-10 mx-auto mb-3 text-gray-400"></i>
                        <p class="font-semibold mb-1">No Day Selected</p>
                        <p class="text-xs">Select a day from Step 1 to view available panelists.</p>
                    </div>
                <?php elseif (!empty($available_panelists)): ?>
                    <?php foreach ($available_panelists as $panelist): ?>
                    <div class="border-2 rounded-xl p-4 shadow-sm bg-green-50 border-green-300">
                        <h3 class="font-semibold text-gray-800 text-sm mb-2 flex items-center gap-2">
                            <i data-lucide="user-check" class="w-4 h-4 text-green-600"></i>
                            <span class="flex-1"><?= htmlspecialchars($panelist['first_name'] . ' ' . $panelist['last_name']) ?></span>
                        </h3>
                        
                        <div class="space-y-1 mb-2">
                            <div class="text-xs p-2 rounded bg-white border border-green-200">
                                <i data-lucide="clock" class="inline w-3 h-3"></i>
                                <?= $panelist['formatted_start'] ?> - <?= $panelist['formatted_end'] ?>
                            </div>
                        </div>
                        
                        <div class="text-xs text-gray-600 bg-white p-2 rounded border border-gray-200">
                            <i data-lucide="briefcase" class="inline w-3 h-3"></i>
                            <strong>Expertise:</strong> <?= htmlspecialchars($panelist['expertise']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-gray-600 text-sm p-6 bg-yellow-50 rounded-lg border-2 border-yellow-200">
                        <i data-lucide="calendar-x" class="w-10 h-10 mx-auto mb-3 text-yellow-600"></i>
                        <p class="font-bold text-yellow-800 mb-2">No Availability</p>
                        <p class="text-xs mb-3">No panelists have set availability for <?= $selected_day ?>s.</p>
                        <p class="text-xs text-gray-600">Please select a different day or ask panelists to update their schedules.</p>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</main>

<script>
document.addEventListener("DOMContentLoaded", function(){ 
    if(window.lucide) lucide.createIcons();
    
    // Auto-submit form when day is selected (optional - remove if you prefer manual submission)
    const daySelect = document.getElementById('selected_day');
    if (daySelect) {
        daySelect.addEventListener('change', function() {
            // Optional: auto-submit when day changes
            // Uncomment the line below to enable auto-submission
            // this.form.submit();
        });
    }
    
    // Success message auto-hide
    const successMessages = document.querySelectorAll('.bg-green-50');
    successMessages.forEach(msg => {
        if (msg.textContent.includes('Success!')) {
            setTimeout(() => {
                msg.style.transition = 'opacity 0.5s';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            }, 5000);   
        }
    });
    
    // Date validation - ensure selected date matches the selected day
    const dateInput = document.querySelector('input[name="schedule_date"]');
    const daySelectInput = document.querySelector('select[name="selected_day"]');
    
    if (dateInput && daySelectInput) {
        dateInput.addEventListener('change', function() {
            const selectedDay = daySelectInput.value;
            if (selectedDay && this.value) {
                const date = new Date(this.value);
                const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                const dayOfWeek = days[date.getDay()];
                
                if (dayOfWeek !== selectedDay) {
                    alert(`Warning: The selected date (${this.value}) is a ${dayOfWeek}, but you selected ${selectedDay}. Please choose a date that falls on a ${selectedDay}.`);
                    this.value = '';
                }
            }
        });
    }
});
</script>
</body>
</html>