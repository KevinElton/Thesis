<?php
session_start();
require_once __DIR__ . '/../classes/database.php';
require_once __DIR__ . '/../classes/audit.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();
$message = '';

// Handle group creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    $leader_name = trim($_POST['leader_name'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $status = $_POST['status'] ?? 'Pending';

    if (empty($leader_name) || empty($course)) {
        $message = '<div class="bg-red-100 text-red-700 border border-red-400 p-4 rounded-lg mb-4 flex items-center gap-2">
                      <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                      <span>Error: Leader name and course are required fields.</span>
                      </div>';
    } else {
        try {
            // Get the next available group_id
            $result = $conn->query("SELECT MAX(group_id) as max_id FROM thesis_group");
            $row = $result->fetch(PDO::FETCH_ASSOC);
            $next_id = ($row['max_id'] ?? 0) + 1;
            
            // Insert with explicit group_id and created_at
            $stmt = $conn->prepare("
                INSERT INTO thesis_group (group_id, leader_name, course, title, status, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([$next_id, $leader_name, $course, $title, $status]);
            
            $group_id = $next_id;

            // Log audit action
            $audit = new Audit();
            $audit->logAction(
                $_SESSION['user_id'] ?? 1,
                'Admin',
                'CREATE_GROUP',
                'thesis_group',
                $group_id,
                "Created thesis group: $leader_name ($course)"
            );

            $message = '<div class="bg-green-100 text-green-700 border border-green-400 p-4 rounded-lg mb-4 flex items-center gap-2">
                          <i data-lucide="check-circle" class="w-5 h-5"></i>
                          <span>Thesis group created successfully! (Group ID: ' . $group_id . ')</span>
                          </div>';
        } catch (PDOException $e) {
            $message = '<div class="bg-red-100 text-red-700 border border-red-400 p-4 rounded-lg mb-4 flex items-center gap-2">
                          <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                          <span>Database Error: ' . htmlspecialchars($e->getMessage()) . '</span>
                          </div>';
        } catch (Exception $e) {
            $message = '<div class="bg-red-100 text-red-700 border border-red-400 p-4 rounded-lg mb-4 flex items-center gap-2">
                          <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                          <span>Error: ' . htmlspecialchars($e->getMessage()) . '</span>
                          </div>';
        }
    }
}

// Handle group update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_group'])) {
    $group_id = $_POST['group_id'] ?? null;
    $leader_name = trim($_POST['leader_name'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $status = $_POST['status'] ?? 'Pending';

    if (!$group_id || empty($leader_name) || empty($course)) {
        $message = '<div class="bg-red-100 text-red-700 border border-red-400 p-4 rounded-lg mb-4 flex items-center gap-2">
                      <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                      <span>Error: All required fields must be filled.</span>
                      </div>';
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE thesis_group 
                SET leader_name = ?, course = ?, title = ?, status = ?
                WHERE group_id = ?
            ");
            $stmt->execute([$leader_name, $course, $title, $status, $group_id]);

            // Log audit action
            $audit = new Audit();
            $audit->logAction(
                $_SESSION['user_id'] ?? 1,
                'Admin',
                'UPDATE_GROUP',
                'thesis_group',
                $group_id,
                "Updated thesis group: $leader_name ($course)"
            );

            $message = '<div class="bg-green-100 text-green-700 border border-green-400 p-4 rounded-lg mb-4 flex items-center gap-2">
                          <i data-lucide="check-circle" class="w-5 h-5"></i>
                          <span>Thesis group updated successfully!</span>
                          </div>';
        } catch (PDOException $e) {
            $message = '<div class="bg-red-100 text-red-700 border border-red-400 p-4 rounded-lg mb-4 flex items-center gap-2">
                          <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                          <span>Error: ' . htmlspecialchars($e->getMessage()) . '</span>
                          </div>';
        }
    }
}

// Handle group deletion
// This logic is now triggered by the delete confirmation modal
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $group_id = $_GET['delete'];
    
    try {
        $conn->beginTransaction();

        // Check if group has schedules
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM schedule WHERE group_id = ?");
        $stmt->execute([$group_id]);
        $scheduleCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($scheduleCount > 0) {
            $message = '<div class="bg-red-100 text-red-700 border border-red-400 p-4 rounded-lg mb-4 flex items-center gap-2">
                          <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                          <span>Cannot delete group: There are ' . $scheduleCount . ' defense schedule(s) associated with this group. Please cancel those schedules first.</span>
                          </div>';
        } else {
            // Also check for assignments
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM assignment WHERE group_id = ?");
            $stmt->execute([$group_id]);
            $assignmentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($assignmentCount > 0) {
                // Delete assignments first
                $stmt = $conn->prepare("DELETE FROM assignment WHERE group_id = ?");
                $stmt->execute([$group_id]);
            }

            // Check for thesis records
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM thesis WHERE group_id = ?");
            $stmt->execute([$group_id]);
            $thesisCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($thesisCount > 0) {
                // Delete thesis records
                $stmt = $conn->prepare("DELETE FROM thesis WHERE group_id = ?");
                $stmt->execute([$group_id]);
            }

            // Now delete the group
            $stmt = $conn->prepare("DELETE FROM thesis_group WHERE group_id = ?");
            $stmt->execute([$group_id]);

            // Log audit action
            $audit = new Audit();
            $audit->logAction(
                $_SESSION['user_id'] ?? 1,
                'Admin',
                'DELETE_GROUP',
                'thesis_group',
                $group_id,
                "Deleted thesis group ID: $group_id"
            );

            $conn->commit();
            $message = '<div class="bg-green-100 text-green-700 border border-green-400 p-4 rounded-lg mb-4 flex items-center gap-2">
                          <i data-lucide="check-circle" class="w-5 h-5"></i>
                          <span>Thesis group deleted successfully!</span>
                          </div>';
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $message = '<div class="bg-red-100 text-red-700 border border-red-400 p-4 rounded-lg mb-4 flex items-center gap-2">
                      <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                      <span>Error: ' . htmlspecialchars($e->getMessage()) . '</span>
                      </div>';
    }
}

// Fetch all thesis groups
try {
    $stmt = $conn->query("
        SELECT 
            tg.*,
            t.title as thesis_title,
            t.adviser_id,
            CONCAT(f.first_name, ' ', f.last_name) as adviser_name,
            (SELECT COUNT(*) FROM schedule s WHERE s.group_id = tg.group_id AND s.status NOT IN ('Cancelled', 'Completed', 'Done')) as active_schedules
        FROM thesis_group tg
        LEFT JOIN thesis t ON tg.group_id = t.group_id
        LEFT JOIN faculty f ON t.adviser_id = f.panelist_id
        ORDER BY tg.group_id DESC
    ");
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $groups = [];
    $message .= '<div class="bg-red-100 text-red-700 border border-red-400 p-4 rounded-lg mb-4 flex items-center gap-2">
                 <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                 <span>Error fetching groups: ' . htmlspecialchars($e->getMessage()) . '</span>
                 </div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thesis Groups Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gray-100 flex min-h-screen">

<?php include 'sidebar.php'; ?>

<!-- Main Content -->
<main class="flex-1 ml-64 p-10">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-2">
            <i data-lucide="users"></i> Thesis Groups Management
        </h1>
        <button onclick="toggleModal('createModal')" class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2 shadow-md transition">
            <i data-lucide="plus-circle"></i> Create New Group
        </button>
    </div>

    <?= $message ?>

    <!-- Groups Table -->
    <div class="bg-white shadow-lg rounded-2xl p-6 overflow-x-auto">
        <h2 class="text-xl font-semibold mb-4 flex items-center gap-2 text-gray-800">
            <i data-lucide="list"></i> All Thesis Groups
        </h2>
        <table class="min-w-full border border-gray-200 text-sm">
            <thead class="bg-blue-600 text-white">
                <tr>
                    <th class="px-4 py-3 text-left">Group ID</th>
                    <th class="px-4 py-3 text-left">Leader Name</th>
                    <th class="px-4 py-3 text-left">Course</th>
                    <th class="px-4 py-3 text-left">Title</th>
                    <th class="px-4 py-3 text-left">Adviser</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Schedules</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($groups)): ?>
                    <?php foreach ($groups as $group): ?>
                        <tr class="border-b hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-semibold"><?= htmlspecialchars($group['group_id']) ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($group['leader_name']) ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($group['course']) ?></td>
                            <td class="px-4 py-3 max-w-xs truncate" title="<?= htmlspecialchars($group['thesis_title'] ?? $group['title'] ?? 'No title') ?>">
                                <?= htmlspecialchars(substr($group['thesis_title'] ?? $group['title'] ?? 'No title', 0, 50)) ?>
                            </td>
                            <td class="px-4 py-3">
                                <?= htmlspecialchars($group['adviser_name'] ?? 'Not assigned') ?>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold 
                                    <?php 
                                        if ($group['status'] === 'For Defense') echo 'bg-yellow-100 text-yellow-700';
                                        elseif ($group['status'] === 'Defended') echo 'bg-green-100 text-green-700';
                                        else echo 'bg-gray-100 text-gray-700';
                                    ?>">
                                    <?= htmlspecialchars($group['status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($group['active_schedules'] > 0): ?>
                                    <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-medium">
                                        <?= $group['active_schedules'] ?> active
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">None</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <!-- ============================================ -->
                                <!-- START: UPDATED ACTIONS BLOCK                 -->
                                <!-- ============================================ -->
                                <div class="inline-flex items-center gap-3">
                                    
                                    <!-- NEW: Link to Manage Schedules -->
                                    <a href="manageSchedules.php?group_id=<?= $group['group_id'] ?>" class="text-green-600 hover:text-green-800" title="Manage Schedules">
                                        <i data-lucide="calendar-days" class="w-4 h-4"></i>
                                    </a>
                                    
                                    <!-- Edit Button -->
                                    <button onclick='editGroup(<?= htmlspecialchars(json_encode($group), ENT_QUOTES, 'UTF-8') ?>)' class="text-blue-600 hover:text-blue-800" title="Edit">
                                        <i data-lucide="edit" class="w-4 h-4"></i>
                                    </button>

                                    <!-- Delete Button (triggers modal) -->
                                    <button onclick="confirmDelete(<?= $group['group_id'] ?>, '<?= htmlspecialchars(addslashes($group['leader_name']), ENT_QUOTES) ?>')" class="text-red-600 hover:text-red-800" title="Delete">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>

                                </div>
                                <!-- ============================================ -->
                                <!-- END: UPDATED ACTIONS BLOCK                   -->
                                <!-- ============================================ -->
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center py-6 text-gray-500">No thesis groups found. Create one to get started!</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Create Group Modal -->
<div id="createModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl">
        <div class="bg-blue-600 text-white p-6 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-2xl font-bold flex items-center gap-2">
                <i data-lucide="user-plus"></i> Create New Group
            </h3>
            <button onclick="toggleModal('createModal')" class="text-white hover:bg-blue-700 p-2 rounded-full transition">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="create_group" value="1">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Leader Name *</label>
                <input type="text" name="leader_name" required 
                       class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500"
                       placeholder="Enter group leader's name">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Course *</label>
                <input type="text" name="course" required 
                       class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500"
                       placeholder="e.g., BSCS, BSIT, BSCpE">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Thesis Title (Optional)</label>
                <textarea name="title" rows="3"
                          class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500"
                          placeholder="Enter thesis title (can be added later)"></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500">
                    <option value="Pending">Pending</option>
                    <option value="For Defense">For Defense</option>
                    <option value="Defended">Defended</option>
                </select>
            </div>

            <div class="flex gap-3 pt-4 border-t">
                <button type="submit"
                        class="flex-1 bg-blue-600 text-white font-semibold py-3 rounded-lg hover:bg-blue-700 transition flex items-center justify-center gap-2">
                    <i data-lucide="save"></i> Create Group
                </button>
                <button type="button" onclick="toggleModal('createModal')"
                        class="px-6 bg-gray-200 text-gray-700 font-semibold py-3 rounded-lg hover:bg-gray-300 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Group Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl">
        <div class="bg-green-600 text-white p-6 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-2xl font-bold flex items-center gap-2">
                <i data-lucide="edit"></i> Edit Group
            </h3>
            <button onclick="toggleModal('editModal')" class="text-white hover:bg-green-700 p-2 rounded-full transition">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="update_group" value="1">
            <input type="hidden" name="group_id" id="edit_group_id">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Leader Name *</label>
                <input type="text" name="leader_name" id="edit_leader_name" required 
                       class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-green-500">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Course *</label>
                <input type="text" name="course" id="edit_course" required 
                       class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-green-500">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Thesis Title</label>
                <textarea name="title" id="edit_title" rows="3"
                          class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-green-500"></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                <select name="status" id="edit_status" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-green-500">
                    <option value="Pending">Pending</option>
                    <option value="For Defense">For Defense</option>
                    <option value="Defended">Defended</option>
                </select>
            </div>

            <div class="flex gap-3 pt-4 border-t">
                <button type="submit"
                        class="flex-1 bg-green-600 text-white font-semibold py-3 rounded-lg hover:bg-green-700 transition flex items-center justify-center gap-2">
                    <i data-lucide="save"></i> Update Group
                </button>
                <button type="button" onclick="toggleModal('editModal')"
                        class="px-6 bg-gray-200 text-gray-700 font-semibold py-3 rounded-lg hover:bg-gray-300 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- START: NEW DELETE CONFIRMATION MODAL         -->
<!-- ============================================ -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="bg-red-600 text-white p-6 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-2xl font-bold flex items-center gap-2">
                <i data-lucide="alert-triangle"></i> Confirm Deletion
            </h3>
            <button onclick="toggleModal('deleteModal')" class="text-white hover:bg-red-700 p-2 rounded-full transition">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        
        <div class="p-6 space-y-4">
            <p class="text-gray-700 text-center">
                Are you sure you want to delete the group for:
                <br>
                <strong id="delete_group_name" class="font-bold text-lg"></strong>?
            </p>
            <p class="text-sm text-red-600 bg-red-50 p-3 rounded-lg text-center">
                This will also delete associated assignments and thesis records. 
                <br>
                <strong>This action cannot be undone.</strong>
            </p>
        </div>

        <div class="flex gap-3 p-6 border-t">
            <button type="button" onclick="toggleModal('deleteModal')"
                    class="flex-1 bg-gray-200 text-gray-700 font-semibold py-3 rounded-lg hover:bg-gray-300 transition">
                Cancel
            </button>
            <a id="delete_confirm_button" href="#"
                    class="flex-1 bg-red-600 text-white font-semibold py-3 rounded-lg hover:bg-red-700 transition flex items-center justify-center gap-2">
                <i data-lucide="trash-2"></i> Yes, Delete
            </a>
        </div>
    </div>
</div>
<!-- ============================================ -->
<!-- END: NEW DELETE CONFIRMATION MODAL           -->
<!-- ============================================ -->


<script>
    lucide.createIcons();

    function toggleModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.classList.toggle('hidden');
        // Re-initialize icons if we are showing a modal
        if (!modal.classList.contains('hidden')) {
            setTimeout(() => lucide.createIcons(), 50);
        }
    }

    function editGroup(group) {
        document.getElementById('edit_group_id').value = group.group_id;
        document.getElementById('edit_leader_name').value = group.leader_name;
        document.getElementById('edit_course').value = group.course;
        document.getElementById('edit_title').value = group.title || group.thesis_title || '';
        document.getElementById('edit_status').value = group.status;
        toggleModal('editModal');
    }

    // Updated confirmDelete function
    function confirmDelete(groupId, groupName) {
        // Set the group name in the modal
        document.getElementById('delete_group_name').textContent = groupName;
        
        // Set the correct deletion link on the confirm button
        document.getElementById('delete_confirm_button').href = '?delete=' + groupId;
        
        // Show the delete modal
        toggleModal('deleteModal');
    }
</script>
</body>
</html>
