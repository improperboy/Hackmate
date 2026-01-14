<?php
// Mentor Sidebar Component
// This file contains the sidebar navigation for mentor pages

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Initialize variables with safe defaults
$has_assignments = false;
$support_count = 0;
$active_rounds_count = 0;

// Only run queries if we have the required variables and database connection
if (isset($user) && isset($pdo) && $user) {
    try {
        // Get mentor's assignment status
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as assignment_count
            FROM mentor_assignments ma
            WHERE ma.mentor_id = ?
        ");
        $stmt->execute([$user['id']]);
        $assignment_result = $stmt->fetch();
        $has_assignments = $assignment_result['assignment_count'] > 0;

        // Get support message count
        if ($has_assignments) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM support_messages sm 
                JOIN mentor_assignments ma ON (sm.floor_id = ma.floor_id AND sm.room_id = ma.room_id)
                WHERE ma.mentor_id = ? AND sm.to_role = 'mentor' AND sm.status = 'open'
            ");
            $stmt->execute([$user['id']]);
            $support_count = $stmt->fetchColumn();
        }

        // Check for active mentoring rounds
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM mentoring_rounds 
            WHERE NOW() BETWEEN start_time AND end_time
        ");
        $active_rounds_count = $stmt->fetchColumn();
    } catch (Exception $e) {
        // Silently handle errors and use defaults
        error_log("Sidebar query error: " . $e->getMessage());
    }
}
?>

<!-- Mobile sidebar overlay -->
<div id="sidebar-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-20 lg:hidden hidden backdrop-blur-sm"></div>

<!-- Sidebar -->
<div id="sidebar" class="fixed inset-y-0 left-0 z-30 w-64 bg-white shadow-xl transform -translate-x-full transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0 flex flex-col border-r border-gray-200">
    <!-- Logo Section -->
    <div class="flex items-center justify-between h-16 px-6 border-b border-gray-100 flex-shrink-0 bg-gradient-to-r from-green-600 to-emerald-600">
        <div class="flex items-center">
            <div class="w-8 h-8 bg-white bg-opacity-20 rounded-lg flex items-center justify-center backdrop-blur-sm">
                <i class="fas fa-chalkboard-teacher text-white text-sm"></i>
            </div>
            <span class="ml-3 text-xl font-bold text-white">HackMate</span>
        </div>
        <button id="sidebar-close" class="lg:hidden text-white hover:text-gray-200 transition-colors">
            <i class="fas fa-times text-lg"></i>
        </button>
    </div>

    <!-- User Info Section -->
    <div class="px-6 py-4 bg-gradient-to-r from-green-50 to-emerald-50 border-b border-gray-100">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-500 rounded-full flex items-center justify-center">
                <span class="text-white font-semibold text-sm">
                    <?php echo isset($user['name']) ? strtoupper(substr($user['name'], 0, 2)) : 'M'; ?>
                </span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900 truncate">
                    <?php echo isset($user['name']) ? htmlspecialchars($user['name']) : 'Mentor'; ?>
                </p>
                <p class="text-xs text-gray-500">Mentor</p>
            </div>
        </div>
    </div>

    <!-- Scrollable Navigation -->
    <div class="flex-1 overflow-y-auto">
        <nav class="px-4 py-6">
            <!-- Main Section -->
            <div class="mb-8">
                <div class="space-y-2">
                    <a href="dashboard.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'dashboard.php' ? 'text-white bg-gradient-to-r from-green-600 to-emerald-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                        <i class="fas fa-tachometer-alt w-5 h-5 mr-3 <?php echo $current_page == 'dashboard.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                        <span>Dashboard</span>
                    </a>

                    <?php if ($has_assignments): ?>
                        <a href="assigned_teams.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'assigned_teams.php' ? 'text-white bg-gradient-to-r from-green-600 to-emerald-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                            <i class="fas fa-users w-5 h-5 mr-3 <?php echo $current_page == 'assigned_teams.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                            <span>My Teams</span>
                        </a>

                        <a href="score_teams.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'score_teams.php' ? 'text-white bg-gradient-to-r from-green-600 to-emerald-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                            <i class="fas fa-star w-5 h-5 mr-3 <?php echo $current_page == 'score_teams.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                            <span>Score Teams</span>
                            <?php if ($active_rounds_count > 0): ?>
                                <span class="ml-auto bg-green-500 text-white text-xs px-2 py-1 rounded-full animate-pulse"><?php echo $active_rounds_count; ?></span>
                            <?php endif; ?>
                        </a>

                        <a href="support_messages.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'support_messages.php' ? 'text-white bg-gradient-to-r from-green-600 to-emerald-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                            <i class="fas fa-life-ring w-5 h-5 mr-3 <?php echo $current_page == 'support_messages.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                            <span>Support Messages</span>
                            <?php if ($support_count > 0): ?>
                                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full animate-pulse"><?php echo $support_count; ?></span>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <!-- Show message when no assignments -->
                        <div class="px-3 py-3 text-sm font-medium text-gray-500 bg-yellow-50 rounded-xl border border-yellow-200">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle w-5 h-5 mr-3 text-yellow-500"></i>
                                <span>No Assignments</span>
                            </div>
                            <p class="text-xs text-gray-600 mt-1 ml-8">Contact admin for team assignments</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mentoring Section -->
            <div class="mb-8">
                <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Mentoring</p>
                <div class="space-y-2">
                    <a href="schedule.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'schedule.php' ? 'text-white bg-gradient-to-r from-green-600 to-emerald-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                        <i class="fas fa-calendar w-5 h-5 mr-3 <?php echo $current_page == 'schedule.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                        <span>Schedule</span>
                    </a>

                    <a href="scoring_history.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'scoring_history.php' ? 'text-white bg-gradient-to-r from-green-600 to-emerald-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                        <i class="fas fa-history w-5 h-5 mr-3 <?php echo $current_page == 'scoring_history.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                        <span>Scoring History</span>
                    </a>

                    <a href="team_progress.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'team_progress.php' ? 'text-white bg-gradient-to-r from-green-600 to-emerald-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                        <i class="fas fa-chart-line w-5 h-5 mr-3 <?php echo $current_page == 'team_progress.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                        <span>Team Progress</span>
                    </a>
                </div>
            </div>

            <!-- Resources Section -->
            <div class="mb-8">
                <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Resources</p>
                <div class="space-y-2">
                    <a href="announcements.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'announcements.php' ? 'text-white bg-gradient-to-r from-green-600 to-emerald-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                        <i class="fas fa-bullhorn w-5 h-5 mr-3 <?php echo $current_page == 'announcements.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                        <span>Announcements</span>
                    </a>

                    <a href="mentor_guidelines.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'mentor_guidelines.php' ? 'text-white bg-gradient-to-r from-green-600 to-emerald-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                        <i class="fas fa-book w-5 h-5 mr-3 <?php echo $current_page == 'mentor_guidelines.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                        <span>Guidelines</span>
                    </a>

                    <a href="contact_admin.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'contact_admin.php' ? 'text-white bg-gradient-to-r from-green-600 to-emerald-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                        <i class="fas fa-phone w-5 h-5 mr-3 <?php echo $current_page == 'contact_admin.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                        <span>Contact Admin</span>
                    </a>
                </div>
            </div>
        </nav>
    </div>

    <!-- Bottom User Actions -->
    <div class="flex-shrink-0 p-4 border-t border-gray-100 bg-gray-50">
        <div class="space-y-2">
            <a href="../change_password.php" class="flex items-center px-3 py-2 text-sm text-gray-600 hover:text-gray-900 hover:bg-white rounded-lg transition-colors">
                <i class="fas fa-key w-4 h-4 mr-3 text-gray-400"></i>
                <span>Change Password</span>
            </a>
            <a href="../logout.php" class="flex items-center px-3 py-2 text-sm text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors">
                <i class="fas fa-sign-out-alt w-4 h-4 mr-3 text-red-400"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>

<style>
    /* Sidebar Styles */
    #sidebar {
        height: 100vh;
    }

    #sidebar .overflow-y-auto {
        scrollbar-width: thin;
        scrollbar-color: #e2e8f0 transparent;
    }

    #sidebar .overflow-y-auto::-webkit-scrollbar {
        width: 4px;
    }

    #sidebar .overflow-y-auto::-webkit-scrollbar-track {
        background: transparent;
    }

    #sidebar .overflow-y-auto::-webkit-scrollbar-thumb {
        background-color: #e2e8f0;
        border-radius: 2px;
    }

    #sidebar .overflow-y-auto::-webkit-scrollbar-thumb:hover {
        background-color: #cbd5e0;
    }

    /* Sidebar item hover effects */
    .sidebar-item {
        position: relative;
        overflow: hidden;
    }

    .sidebar-item:hover {
        transform: translateX(2px);
    }

    .sidebar-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(16, 185, 129, 0.1));
        opacity: 0;
        transition: opacity 0.2s ease-in-out;
        border-radius: 0.75rem;
    }

    .sidebar-item:hover::before {
        opacity: 1;
    }

    /* Mobile responsiveness */
    @media (max-width: 1024px) {
        #sidebar {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
    }
</style>

<script>
    // Sidebar functionality
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }

    function closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    }

    // Event listeners
    document.getElementById('sidebar-close')?.addEventListener('click', closeSidebar);
    document.getElementById('sidebar-overlay')?.addEventListener('click', closeSidebar);

    // Close sidebar on escape key (mobile)
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    // Auto-close sidebar on mobile when clicking nav items
    document.querySelectorAll('.sidebar-item').forEach(item => {
        item.addEventListener('click', function() {
            if (window.innerWidth < 1024) {
                setTimeout(closeSidebar, 150);
            }
        });
    });
</script>