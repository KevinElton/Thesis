<?php
session_start();
require_once __DIR__ . '/../classes/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();

if (!isset($_GET['id'])) {
    header("Location: manageGroups.php");
    exit;
}

$group_id = $_GET['id'];
$message = '';

// Get group details
$stmt = $conn->prepare("
    SELECT tg.*, t.title as thesis_title, t.adviser_id, t.status as thesis_status
    FROM thesis_group tg
    LEFT JOIN thesis t ON tg.group_id = t.group_id
    WHERE tg.group_id = ?
");
$stmt->execute([$group_id]);
$group = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    header("Location: manageGroups.php");
    exit;
}

// Get faculty list for adviser dropdown
$stmt = $conn->query("SELECT panelist_id, first_name, last_name, department FROM faculty ORDER BY last_name ASC");
$faculty_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leader_name = trim($_POST['leader_name']);
    $course = trim($_POST['course']);
    $thesis_title = trim($_POST['thesis_title']);
    $adviser_id = $_POST['adviser_id'] ?? null;
    $status = $_POST['status'];

    try {
        $conn->beginTransaction();
        
        // Update thesis_group
        $stmt = $conn->prepare("UPDATE thesis_group SET leader_name = ?, course = ?, title = ?, status = ? WHERE group_id = ?");
        $stmt->execute([$leader_name, $course, $thesis_title, $status, $group_id]);
        
        // Update thesis table if exists
        try {
            $stmt2 = $conn->prepare("UPDATE thesis SET title = ?, leader_name = ?, course = ?, adviser_id = ?, status = ? WHERE group_id = ?");
            $stmt2->execute([$thesis_title, $leader_name, $course, $adviser_id, $status, $group_id]);
        } catch (PDOException $e) {
            // Thesis record might not exist, create it
            $stmt3 = $conn->prepare("INSERT INTO thesis (group_id, title, leader_name, course, adviser_id, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt3->execute([$group_id, $thesis_title, $leader_name, $course, $adviser_id, $status]);
        }
        
        $conn->commit();
        $message = '<div class="bg-green-100 text-green-700 border border-green-400 p-4 rounded-lg mb-4">
                    <i data-lucide="check-circle" class="inline w-5 h-5 mr-2"></i>
                    Thesis group updated successfully!
                    </div>';
        
        // Refresh group data
        $stmt = $conn->prepare("
            SELECT tg.*, t.title as thesis_title, t.adviser_id, t.status as thesis_status
            FROM thesis_group tg
            LEFT JOIN thesis t ON tg.group_id = t.group_id
            WHERE tg.group_id = ?
        ");
        $stmt->execute([$group_id]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $message = '<div class="bg-red-100 text-red-700 border border-red-400 p-4 rounded-lg mb-4">
                    <i data-lucide="alert-triangle" class="inline w-5 h-5 mr-2"></i>
                    Error: ' . htmlspecialchars($e->getMessage()) . '
                    </div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Thesis Group</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gray-100 min-h-screen">

<nav class="bg-gradient-to-r from-orange-600 to-orange-700 text-white p-4 shadow-lg">
    <div class="container mx-auto flex justify-between items-center">
        <h1 class="text-xl font-bold flex items-center gap-2">
            <i data-lucide="edit"></i> Edit Thesis Group
        </h1>
        <a href="viewGroup.php?id=<?= $group_id ?>" class="bg-white text-orange-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition flex items-center gap-2">
            <i data-lucide="arrow-left"></i> Back
        </a>
    </div>
</nav>

<div class="container mx-auto p-8 max-w-3xl">
    
    <?= $message ?>
    
    <div class="bg-white rounded-2xl shadow-lg p-8">
        <form method="POST" class="space-y-5">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Group Leader Name *</label>
                <input type="text" name="leader_name" value="<?= htmlspecialchars($group['leader_name']) ?>" required
                       class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-orange-500">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Course *</label>
                <select name="course" required class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-orange-500">
                    <option value="">Select Course</option>
                    <option value="BSCS" <?= $group['course'] == 'BSCS' ? 'selected' : '' ?>>BS Computer Science</option>
                    <option value="BSIT" <?= $group['course'] == 'BSIT' ? 'selected' : '' ?>>BS Information Technology</option>
                    <option value="BSCE" <?= $group['course'] == 'BSCE' ? 'selected' : '' ?>>BS App Development</option>
                    <option value="BSEE" <?= $group['course'] == 'BSEE' ? 'selected' : '' ?>>BS Networking</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Thesis Title *</label>
                <textarea name="thesis_title" required rows="3"
                          class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-orange-500"><?= htmlspecialchars($group['thesis_title'] ?? $group['title'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Thesis Adviser</label>
                <select name="adviser_id" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-orange-500">
                    <option value="">Select Adviser</option>
                    <?php foreach ($faculty_list as $fac): ?>
                        <option value="<?= $fac['panelist_id'] ?>" <?= $group['adviser_id'] == $fac['panelist_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($fac['first_name'] . ' ' . $fac['last_name'] . ' (' . $fac['department'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Status *</label>
                <select name="status" required class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-orange-500">
                    <option value="Active" <?= ($group['status'] ?? 'Active') == 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Proposal" <?= ($group['status'] ?? '') == 'Proposal' ? 'selected' : '' ?>>Proposal Stage</option>
                    <option value="For Revision" <?= ($group['status'] ?? '') == 'For Revision' ? 'selected' : '' ?>>For Revision</option>
                    <option value="Approved" <?= ($group['status'] ?? '') == 'Approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="Defended" <?= ($group['status'] ?? '') == 'Defended' ? 'selected' : '' ?>>Defended</option>
                    <option value="Completed" <?= ($group['status'] ?? '') == 'Completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>

            <div class="flex gap-3 pt-4 border-t">
                <button type="submit"
                        class="flex-1 bg-orange-600 text-white font-semibold py-3 rounded-lg hover:bg-orange-700 transition flex items-center justify-center gap-2">
                    <i data-lucide="save"></i> Update Group
                </button>
                <a href="viewGroup.php?id=<?= $group_id ?>"
                   class="px-6 bg-gray-200 text-gray-700 font-semibold py-3 rounded-lg hover:bg-gray-300 transition flex items-center justify-center gap-2">
                    <i data-lucide="x"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Info Box -->
    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mt-6 rounded-r-lg">
        <div class="flex items-center gap-2 mb-2">
            <i data-lucide="info" class="w-5 h-5 text-blue-600"></i>
            <h4 class="font-semibold text-blue-800">Important Notes</h4>
        </div>
        <ul class="text-sm text-blue-700 space-y-1 ml-7">
            <li>• Changes will affect all related schedules and assignments</li>
            <li>• The adviser cannot be a panelist in this group's defense</li>
            <li>• Make sure to update the status as the thesis progresses</li>
        </ul>
    </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>