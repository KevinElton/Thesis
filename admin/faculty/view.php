<?php
session_start();
// Use Faculty class instead of direct DB interaction
require_once __DIR__ . '/../../classes/faculty.php';

// Check if user is logged in and is an Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    // Redirect to login page if not admin - Adjust path as necessary
    header("Location: ../../auth/login.php");
    exit;
}

$faculty = new Faculty();

// Use current system date/time as filter (optional, based on your previous logic)
// If you want *all* faculty regardless of date, set $filterDate = null;
$filterDate = time(); // Current Unix timestamp

// Fetch faculty list using the updated method which includes counts
$facultyList = $faculty->viewFaculty($filterDate);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Added viewport -->
    <title>View Faculty</title>
    <script src="/Thesis/assets/js/tailwind.js"></script>
    <script src="/Thesis/assets/js/lucide.min.js"></script>
    <style>
        /* Add some basic table styling improvements */
        th, td {
            white-space: nowrap; /* Prevent text wrapping */
        }
        tbody tr:hover {
            background-color: #f3f4f6; /* Lighter gray on hover */
        }
    </style>
</head>
<body class="bg-gray-100 flex min-h-screen">

<!-- Sidebar -->
<?php include __DIR__ . '/../sidebar.php'; // Ensure sidebar.php exists and includes necessary navigation ?>

<!-- Main content -->
<main class="flex-1 ml-64 p-6 md:p-10"> <!-- Added responsive padding -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-2">
            <i data-lucide="users"></i> Faculty List
        </h1>
        <a href="add.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-1 shadow-md transition whitespace-nowrap">
            <i data-lucide="user-plus" class="w-4 h-4"></i> Add Faculty
        </a>
    </div>

    <!-- Date Filter Info - Optional, uncomment if using $filterDate -->
    <?php /*
    <div class="bg-white shadow-md rounded-xl p-4 mb-6 border border-gray-200">
        <div class="flex items-center gap-2 text-gray-700">
            <i data-lucide="filter"></i>
            <p class="font-semibold">Filter:</p>
            <span class="text-blue-600">Showing faculty added on or before <?= date('M d, Y', $filterDate) ?></span>
        </div>
    </div>
    */ ?>

    <div class="bg-white shadow-lg rounded-xl p-4 sm:p-6 overflow-x-auto border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expertise</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Added</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Schedules</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Groups</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (!empty($facultyList)): ?>
                    <?php foreach ($facultyList as $index => $row): ?>
                        <?php $pid = htmlspecialchars($row['panelist_id']); ?>
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-500"><?= $index + 1 ?></td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($row['email']) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($row['department']) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600 truncate max-w-xs" title="<?= htmlspecialchars($row['expertise']) ?>">
                                <?= htmlspecialchars($row['expertise']) ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                <?php
                                if (!empty($row['Date'])) {
                                    try {
                                        echo date('M d, Y', strtotime($row['Date']));
                                    } catch (Exception $e) {
                                        echo 'Invalid Date'; // Handle potential date parsing errors
                                    }
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-center text-gray-600">
                                <?= $row['assigned_panels_count'] ?? 0 ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-center text-gray-600">
                                <?= $row['assigned_groups_count'] ?? 0 ?>
                            </td>
                             <td class="px-4 py-3 text-center">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $row['status'] === 'active' ? 'bg-green-100 text-green-800' : ($row['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                    <?= htmlspecialchars(ucfirst($row['status'] ?? 'Unknown')) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-sm">
                                <div class="inline-flex items-center gap-3">
                                    <a href="edit.php?id=<?= $pid ?>" class="text-blue-600 hover:text-blue-900" title="Edit">
                                        <i data-lucide="edit-2" class="w-4 h-4"></i>
                                    </a>
                                    <a href="delete.php?id=<?= $pid ?>" class="text-red-600 hover:text-red-900"
                                       title="Delete"
                                       onclick="return confirm('Are you sure you want to delete <?= htmlspecialchars(addslashes($row['first_name'] . ' ' . $row['last_name'])) ?>? This will also remove their assignments and availability.');">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="text-center px-4 py-6 text-gray-500">
                            No faculty records found<?php if($filterDate) echo ' matching the criteria'; ?>.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
    lucide.createIcons();

    // Optional: Auto-hide success/error messages if needed
    // document.addEventListener('DOMContentLoaded', () => {
    //     const messageDiv = document.querySelector('.message-div'); // Add a class 'message-div' to your message divs
    //     if(messageDiv) {
    //         setTimeout(() => {
    //             messageDiv.style.transition = 'opacity 0.5s';
    //             messageDiv.style.opacity = '0';
    //             setTimeout(() => messageDiv.remove(), 500);
    //         }, 5000); // Hide after 5 seconds
    //     }
    // });
</script>
</body>
</html>

                    






