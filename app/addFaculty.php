<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../apps/login.php");
    exit;
}

// Go up one level from /app/ to /Thesis/ then into /classes/
require_once __DIR__ . '/../classes/database.php';

$db = new Database();
$conn = $db->connect();
$message = '';
$messageType = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $panelist_id = $_POST['panelist_id'] ?? 0;
    $action = $_POST['action'];
    
    try {
        if ($action === 'approve') {
            // Generate username from email or name
            $stmt = $conn->prepare("SELECT email, first_name, last_name FROM panelist WHERE panelist_id = ?");
            $stmt->execute([$panelist_id]);
            $panelist = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($panelist) {
                // Generate username from first name + last name (lowercase, no spaces)
                $username = strtolower($panelist['first_name'] . $panelist['last_name']);
                $username = preg_replace('/[^a-z0-9]/', '', $username); // Remove special characters
                
                // Check if username exists, if so, add number
                $check_stmt = $conn->prepare("SELECT COUNT(*) FROM panelist WHERE username = ?");
                $check_stmt->execute([$username]);
                if ($check_stmt->fetchColumn() > 0) {
                    $username = $username . rand(1, 999);
                }
                
                // Update status and username
                $stmt = $conn->prepare("UPDATE panelist SET status = 'active', username = ? WHERE panelist_id = ?");
                $stmt->execute([$username, $panelist_id]);
                $message = "Panelist approved successfully! Username: <strong>$username</strong>";
                $messageType = 'success';
            }
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("DELETE FROM panelist WHERE panelist_id = ?");
            $stmt->execute([$panelist_id]);
            $message = 'Panelist registration rejected and removed.';
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle direct admin add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_panelist'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $expertise = trim($_POST['expertise'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($first_name) || empty($email) || empty($username) || empty($password)) {
        $message = 'First name, email, username, and password are required.';
        $messageType = 'error';
    } else {
        try {
            // Check if email or username already exists
            $stmt = $conn->prepare("SELECT email FROM panelist WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            
            if ($stmt->fetch()) {
                $message = 'Email or username already exists.';
                $messageType = 'error';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    INSERT INTO panelist 
                    (first_name, last_name, email, username, password, role, designation, contact_number, status, department, expertise, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'Panel', 'Panelist', ?, 'active', ?, ?, NOW())
                ");
                
                if ($stmt->execute([$first_name, $last_name, $email, $username, $hashed_password, $contact_number, $department, $expertise])) {
                    $message = 'Panelist added successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to add panelist.';
                    $messageType = 'error';
                }
            }
        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Fetch pending registrations
$pending_stmt = $conn->query("
    SELECT * FROM panelist 
    WHERE status = 'pending' 
    ORDER BY created_at DESC
");
$pending_panelists = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active panelists
$active_stmt = $conn->query("
    SELECT * FROM panelist 
    WHERE status = 'active' 
    ORDER BY created_at DESC
");
$active_panelists = $active_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Panelists</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-4xl font-bold text-gray-800 mb-2">Manage Panelists</h1>
                <p class="text-gray-600">Approve pending registrations or add new panelists directly</p>
            </div>
            <a href="evaluations.php" class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-800">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Back to Dashboard
            </a>
        </div>

        <?php if ($message): ?>
            <div class="<?= $messageType === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700' ?> border px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
                <i data-lucide="<?= $messageType === 'success' ? 'check-circle' : 'alert-triangle' ?>" class="w-5 h-5"></i>
                <span><?= $message ?></span>
            </div>
        <?php endif; ?>

        <!-- Pending Registrations -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="flex items-center gap-3 mb-6">
                <div class="bg-yellow-100 p-3 rounded-lg">
                    <i data-lucide="clock" class="w-6 h-6 text-yellow-600"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Pending Approvals</h2>
                    <p class="text-gray-600 text-sm"><?= count($pending_panelists) ?> registration(s) waiting for approval</p>
                </div>
            </div>

            <?php if (empty($pending_panelists)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-3 text-gray-400"></i>
                    <p>No pending registrations</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($pending_panelists as $panelist): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <h3 class="text-lg font-semibold text-gray-800">
                                            <?= htmlspecialchars($panelist['first_name'] . ' ' . $panelist['last_name']) ?>
                                        </h3>
                                        <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full">Pending</span>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-gray-600">
                                        <div class="flex items-center gap-2">
                                            <i data-lucide="mail" class="w-4 h-4"></i>
                                            <?= htmlspecialchars($panelist['email']) ?>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <i data-lucide="building" class="w-4 h-4"></i>
                                            <?= htmlspecialchars($panelist['department'] ?? 'N/A') ?>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <i data-lucide="award" class="w-4 h-4"></i>
                                            <?= htmlspecialchars($panelist['expertise'] ?? 'N/A') ?>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <i data-lucide="calendar" class="w-4 h-4"></i>
                                            <?= $panelist['created_at'] ? date('M d, Y', strtotime($panelist['created_at'])) : 'N/A' ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="panelist_id" value="<?= $panelist['panelist_id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition">
                                            <i data-lucide="check" class="w-4 h-4"></i>
                                            Approve
                                        </button>
                                    </form>
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to reject this registration?');">
                                        <input type="hidden" name="panelist_id" value="<?= $panelist['panelist_id'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition">
                                            <i data-lucide="x" class="w-4 h-4"></i>
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Add New Panelist Directly -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="flex items-center gap-3 mb-6">
                <div class="bg-blue-100 p-3 rounded-lg">
                    <i data-lucide="user-plus" class="w-6 h-6 text-blue-600"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Add New Panelist</h2>
                    <p class="text-gray-600 text-sm">Directly add a panelist without approval process</p>
                </div>
            </div>

            <form method="POST" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="text" name="first_name" placeholder="First Name *" required
                        class="border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <input type="text" name="last_name" placeholder="Last Name *" required
                        class="border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="email" name="email" placeholder="Email *" required
                        class="border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <input type="text" name="username" placeholder="Username *" required
                        class="border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="text" name="department" placeholder="Department *" required
                        class="border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <input type="text" name="expertise" placeholder="Expertise *" required
                        class="border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="text" name="contact_number" placeholder="Contact Number (Optional)"
                        class="border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <input type="password" name="password" placeholder="Password (min 6 characters) *" required
                        class="border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <button type="submit" name="add_panelist" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg flex items-center gap-2 transition font-semibold">
                    <i data-lucide="plus" class="w-5 h-5"></i>
                    Add Panelist
                </button>
            </form>
        </div>

        <!-- Active Panelists -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="bg-green-100 p-3 rounded-lg">
                    <i data-lucide="users" class="w-6 h-6 text-green-600"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Active Panelists</h2>
                    <p class="text-gray-600 text-sm"><?= count($active_panelists) ?> active panelist(s)</p>
                </div>
            </div>

            <?php if (empty($active_panelists)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i data-lucide="user-x" class="w-12 h-12 mx-auto mb-3 text-gray-400"></i>
                    <p>No active panelists</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Name</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Username</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Email</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Department</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Expertise</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($active_panelists as $panelist): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-800">
                                        <?= htmlspecialchars($panelist['first_name'] . ' ' . $panelist['last_name']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= htmlspecialchars($panelist['username'] ?? 'N/A') ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= htmlspecialchars($panelist['email']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= htmlspecialchars($panelist['department'] ?? 'N/A') ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= htmlspecialchars($panelist['expertise'] ?? 'N/A') ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">Active</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>