<?php
if (!isset($_SESSION)) session_start();
?>
<aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-900 text-white flex flex-col fixed h-full shadow-2xl">
    <div class="flex items-center justify-center py-8 border-b border-blue-600">
        <i data-lucide="shield" class="w-8 h-8 text-white mr-2"></i>
        <h1 class="text-xl font-bold">Admin Panel</h1>
    </div>

    <nav class="flex-1 p-4 space-y-2">

        <!-- Dashboard -->
        <a href="adminDashboard.php" class="sidebar-link">
            <i data-lucide="home" class="w-5 h-5"></i> Dashboard
        </a>

        <!-- Step 1: Manage Faculty -->
        <a href="addFaculty.php" class="sidebar-link">
            <i data-lucide="user-plus" class="w-5 h-5"></i> Add Faculty
        </a>
        <a href="viewFaculty.php" class="sidebar-link">
            <i data-lucide="users" class="w-5 h-5"></i> View Faculty
        </a>

        <!-- Step 2: Manage Thesis Groups -->
        <a href="manageGroups.php" class="sidebar-link">
            <i data-lucide="book-open" class="w-5 h-5"></i> Thesis Groups
        </a>

        <!-- Step 3: Manage Schedules -->
        <a href="manageSchedules.php" class="sidebar-link">
            <i data-lucide="calendar" class="w-5 h-5"></i> Schedules
        </a>

        <!-- Step 4: Assign Panel -->
        <a href="assignPanel.php" class="sidebar-link">
            <i data-lucide="user-check" class="w-5 h-5"></i> Assign Panel
        </a>

        <!-- Step 5: Panelist Evaluations -->
        <a href="evaluations.php" class="sidebar-link">
            <i data-lucide="clipboard-check" class="w-5 h-5"></i> Evaluations
        </a>

        <!-- Step 6: Reports -->
        <a href="reports.php" class="sidebar-link">
            <i data-lucide="file-text" class="w-5 h-5"></i> Reports
        </a>

        <!-- Step 7: Settings -->
        <a href="settings.php" class="sidebar-link">
            <i data-lucide="settings" class="w-5 h-5"></i> Settings
        </a>

    </nav>

    <div class="p-4 border-t border-blue-600">
        <div class="mb-3 px-4 py-2 bg-blue-800 rounded-lg">
            <p class="text-xs text-blue-200">Logged in as</p>
            <p class="font-semibold truncate"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin User') ?></p>
        </div>
        
        <!-- Notification Bell -->
        <div class="flex items-center justify-center mb-3">
            <?php include __DIR__ . '/../classes/includes/notification_bell.php'; ?>
        </div>
        
        <a href="logout.php" class="flex items-center gap-3 px-4 py-2 rounded-lg hover:bg-red-600 transition bg-red-500">
            <i data-lucide="log-out" class="w-5 h-5"></i> Logout
        </a>
    </div>
</aside>

<style>
    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        transition: background 0.2s;
        text-decoration: none;
        color: white;
    }
    .sidebar-link:hover {
        background-color: #2563eb;
    }
</style>