<?php
if (!isset($_SESSION)) session_start();

// Calculate the base path to get to /Thesis/ root
$scriptPath = $_SERVER['SCRIPT_NAME'];
$adminPos = strpos($scriptPath, '/admin');
if ($adminPos !== false) {
    $base = substr($scriptPath, 0, $adminPos);
} else {
    $base = dirname(dirname($scriptPath));
}
$base = rtrim($base, '/');
?>

<!-- Premium Sidebar -->
<aside class="sidebar-container w-72 text-white flex flex-col fixed h-screen shadow-2xl z-40">
    
    <!-- Logo/Brand Header -->
    <div class="px-6 py-5 flex-shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center">
                <i data-lucide="graduation-cap" class="w-7 h-7 text-white"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold tracking-wide">Thesis Panel</h1>
                <p class="text-xs text-blue-200 opacity-80">Scheduling System</p>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 px-4 py-2 overflow-y-auto custom-scrollbar">
        
        <!-- Main Section -->
        <div class="mb-4">
            <p class="px-3 mb-2 text-xs font-semibold text-blue-300 uppercase tracking-wider">Main</p>
            <a href="<?= $base ?>/admin/dashboard.php" class="nav-link group">
                <div class="nav-icon bg-gradient-to-br from-blue-400 to-blue-600">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                </div>
                <span>Dashboard</span>
            </a>
        </div>

        <!-- Management Section -->
        <div class="mb-4">
            <p class="px-3 mb-2 text-xs font-semibold text-blue-300 uppercase tracking-wider">Management</p>
            
            <a href="<?= $base ?>/admin/faculty/add.php" class="nav-link group">
                <div class="nav-icon bg-gradient-to-br from-emerald-400 to-teal-600">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                </div>
                <span>Add Panelist</span>
            </a>
            
            <a href="<?= $base ?>/admin/faculty/view.php" class="nav-link group">
                <div class="nav-icon bg-gradient-to-br from-cyan-400 to-blue-600">
                    <i data-lucide="users" class="w-4 h-4"></i>
                </div>
                <span>View Panelists</span>
            </a>
            
            <a href="<?= $base ?>/admin/groups/manage.php" class="nav-link group">
                <div class="nav-icon bg-gradient-to-br from-violet-400 to-purple-600">
                    <i data-lucide="book-open" class="w-4 h-4"></i>
                </div>
                <span>Thesis Groups</span>
            </a>
        </div>

        <!-- Scheduling Section -->
        <div class="mb-4">
            <p class="px-3 mb-2 text-xs font-semibold text-blue-300 uppercase tracking-wider">Scheduling</p>
            
            <a href="<?= $base ?>/admin/schedules/manage.php" class="nav-link group">
                <div class="nav-icon bg-gradient-to-br from-orange-400 to-red-500">
                    <i data-lucide="calendar-days" class="w-4 h-4"></i>
                </div>
                <span>Manage Schedules</span>
            </a>
            
            <a href="<?= $base ?>/admin/schedules/assign.php" class="nav-link group">
                <div class="nav-icon bg-gradient-to-br from-pink-400 to-rose-600">
                    <i data-lucide="user-check" class="w-4 h-4"></i>
                </div>
                <span>Assign Panel</span>
            </a>
        </div>

        <!-- Reports Section -->
        <div class="mb-4">
            <p class="px-3 mb-2 text-xs font-semibold text-blue-300 uppercase tracking-wider">Analytics</p>
            
            <a href="<?= $base ?>/admin/evaluations.php" class="nav-link group">
                <div class="nav-icon bg-gradient-to-br from-amber-400 to-orange-600">
                    <i data-lucide="clipboard-check" class="w-4 h-4"></i>
                </div>
                <span>Evaluations</span>
            </a>
            
            <a href="<?= $base ?>/admin/reports.php" class="nav-link group">
                <div class="nav-icon bg-gradient-to-br from-lime-400 to-green-600">
                    <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
                </div>
                <span>Reports</span>
            </a>
            
            <a href="<?= $base ?>/admin/activity_monitor.php" class="nav-link group">
                <div class="nav-icon bg-gradient-to-br from-fuchsia-400 to-purple-600">
                    <i data-lucide="activity" class="w-4 h-4"></i>
                </div>
                <span>Activity Monitor</span>
            </a>
        </div>

        <!-- System Section -->
        <div class="mb-4">
            <p class="px-3 mb-2 text-xs font-semibold text-blue-300 uppercase tracking-wider">System</p>
            
            <a href="<?= $base ?>/admin/settings.php" class="nav-link group">
                <div class="nav-icon bg-gradient-to-br from-slate-400 to-gray-600">
                    <i data-lucide="settings" class="w-4 h-4"></i>
                </div>
                <span>Settings</span>
            </a>
        </div>
    </nav>

    <!-- User Footer Section -->
    <div class="px-4 py-4 flex-shrink-0 border-t border-white/10 bg-black/20 backdrop-blur-sm">
        
        <!-- User Info + Notification Row -->
        <div class="flex items-center gap-3 mb-3 p-3 rounded-xl bg-white/10 backdrop-blur-sm">
            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white font-bold text-sm">
                <?= strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold truncate"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin User') ?></p>
                <p class="text-xs text-blue-200">Administrator</p>
            </div>
            <!-- Notification Bell -->
            <?php include __DIR__ . '/../includes/notification_bell.php'; ?>
        </div>
        
        <!-- Logout Button -->
        <button onclick="showLogoutModal()" class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-gradient-to-r from-red-500 to-rose-600 hover:from-red-600 hover:to-rose-700 text-white font-semibold transition-all duration-300 shadow-lg hover:shadow-red-500/30">
            <i data-lucide="log-out" class="w-5 h-5"></i>
            <span>Logout</span>
        </button>
    </div>
</aside>

<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="hideLogoutModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden transform transition-all">
        <div class="bg-gradient-to-r from-red-500 to-rose-600 p-6 text-center">
            <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3">
                <i data-lucide="log-out" class="w-8 h-8 text-white"></i>
            </div>
            <h3 class="text-xl font-bold text-white">Logout Confirmation</h3>
        </div>
        <div class="p-6 text-center">
            <p class="text-gray-600 mb-2">Are you sure you want to log out?</p>
            <p class="text-sm text-gray-400">You will need to login again to access the admin panel.</p>
        </div>
        <div class="flex gap-3 p-6 pt-0">
            <button onclick="hideLogoutModal()" class="flex-1 px-4 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition">
                Cancel
            </button>
            <a href="<?= $base ?>/auth/logout.php" class="flex-1 px-4 py-3 bg-gradient-to-r from-red-500 to-rose-600 hover:from-red-600 hover:to-rose-700 text-white font-semibold rounded-xl transition text-center">
                Yes, Logout
            </a>
        </div>
    </div>
</div>

<style>
/* Sidebar Container */
.sidebar-container {
    background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);
}

/* Navigation Links */
.nav-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 0.75rem;
    border-radius: 0.75rem;
    transition: all 0.2s ease;
    text-decoration: none;
    color: rgba(255, 255, 255, 0.85);
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    transform: translateX(4px);
}

.nav-link:hover .nav-icon {
    transform: scale(1.1);
}

/* Icon containers */
.nav-icon {
    width: 2rem;
    height: 2rem;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    transition: transform 0.2s ease;
    flex-shrink: 0;
}

/* Custom Scrollbar */
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.4);
}

/* Modal Animation */
#logoutModal.show .relative {
    animation: modalPop 0.3s ease-out;
}

@keyframes modalPop {
    0% { transform: scale(0.8); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}
</style>

<script>
function showLogoutModal() {
    const modal = document.getElementById('logoutModal');
    modal.classList.remove('hidden');
    modal.classList.add('show');
    setTimeout(() => lucide.createIcons(), 50);
}

function hideLogoutModal() {
    const modal = document.getElementById('logoutModal');
    modal.classList.add('hidden');
    modal.classList.remove('show');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') hideLogoutModal();
});
</script>


