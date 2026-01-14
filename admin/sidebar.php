<?php
// Admin Sidebar Component
// This file contains the sidebar navigation for admin pages

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Mobile sidebar overlay -->
<div id="sidebar-overlay" class="fixed inset-0 bg-gray-600 bg-opacity-75 z-20 lg:hidden hidden"></div>

<!-- Sidebar -->
<div id="sidebar" class="fixed inset-y-0 left-0 z-30 w-64 bg-white shadow-lg transform -translate-x-full transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0 flex flex-col">
    <!-- Logo Section -->
    <div class="flex items-center justify-between h-16 px-6 border-b border-gray-200 flex-shrink-0">
        <div class="flex items-center">
            <div class="w-8 h-8 bg-gradient-to-br from-blue-600 to-purple-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-code text-white text-sm"></i>
            </div>
            <span class="ml-3 text-xl font-bold text-gray-800">HackMate</span>
        </div>
        <button id="sidebar-close" class="lg:hidden text-gray-500 hover:text-gray-700">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <!-- Scrollable Navigation -->
    <div class="flex-1 overflow-y-auto">
        <nav class="mt-6 px-3 pb-20">
        <!-- Main Menu Section -->
        <div class="mb-8">
            <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">MAIN MENU</p>
            <div class="space-y-1">
                <a href="dashboard.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'dashboard.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-tachometer-alt w-5 h-5 mr-3"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="analytics.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'analytics.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-chart-line w-5 h-5 mr-3"></i>
                    <span>Analytics</span>
                </a>
                
                <a href="recent_activity.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'recent_activity.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-clock w-5 h-5 mr-3"></i>
                    <span>Recent Activity</span>
                </a>
                
                <a href="teams.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'teams.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-users w-5 h-5 mr-3"></i>
                    <span>Teams</span>
                    <?php if ($pending_teams > 0): ?>
                        <span class="ml-auto bg-orange-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $pending_teams; ?></span>
                    <?php endif; ?>
                </a>
                
                <a href="view_submissions.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'view_submissions.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-file-upload w-5 h-5 mr-3"></i>
                    <span>Submissions</span>
                    <?php if ($total_submissions > 0): ?>
                        <span class="ml-auto bg-green-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $total_submissions; ?></span>
                    <?php endif; ?>
                </a>
                
                <a href="manage_users.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'manage_users.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-users-cog w-5 h-5 mr-3"></i>
                    <span>Users</span>
                </a>
            </div>
        </div>
        
        <!-- Management Section -->
        <div class="mb-8">
            <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">MANAGEMENT</p>
            <div class="space-y-1">
                <a href="posts.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'posts.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-bullhorn w-5 h-5 mr-3"></i>
                    <span>Announcements</span>
                </a>
                
                <a href="support_messages.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'support_messages.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-life-ring w-5 h-5 mr-3"></i>
                    <span>Support</span>
                    <?php if ($open_support_requests > 0): ?>
                        <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full animate-pulse"><?php echo $open_support_requests; ?></span>
                    <?php endif; ?>
                </a>
                
                <a href="mentoring_rounds.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'mentoring_rounds.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-chalkboard-teacher w-5 h-5 mr-3"></i>
                    <span>Mentoring</span>
                </a>
                
                <a href="mentor_assignments.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'mentor_assignments.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-user-tie w-5 h-5 mr-3"></i>
                    <span>Mentor Assign</span>
                </a>
                
                <a href="mentor_recommendations.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'mentor_recommendations.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-brain w-5 h-5 mr-3"></i>
                    <span>Smart Recommendations</span>
                </a>
                 <a href="submission_settings.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'submission_settings.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-user-tie w-5 h-5 mr-3"></i>
                    <span>Submission Settings</span>
                </a>
                
                <a href="volunteer_assignments.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'volunteer_assignments.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-hands-helping w-5 h-5 mr-3"></i>
                    <span>Volunteers</span>
                </a>
            </div>
        </div>
        
        <!-- Certificates Section -->
        <div class="mb-8">
            <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">CERTIFICATES</p>
            <div class="space-y-1">
                <a href="certificate_templates.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'certificate_templates.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-file-pdf w-5 h-5 mr-3"></i>
                    <span>Templates</span>
                </a>
                
                <a href="blockchain_certificates.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'blockchain_certificates.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-certificate w-5 h-5 mr-3"></i>
                    <span>All Certificates</span>
                </a>
                
                <a href="certificate_settings.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'certificate_settings.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-cog w-5 h-5 mr-3"></i>
                    <span>Certificate Settings</span>
                </a>
            </div>
        </div>
        
        <!-- System Section -->
        <div class="mb-8">
            <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">SYSTEM</p>
            <div class="space-y-1">
                <a href="floors_rooms.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'floors_rooms.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-map-marker-alt w-5 h-5 mr-3"></i>
                    <span>Locations</span>
                </a>
                
                <a href="themes.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'themes.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-palette w-5 h-5 mr-3"></i>
                    <span>Themes</span>
                </a>
                
                <a href="team_rankings.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'team_rankings.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-trophy w-5 h-5 mr-3"></i>
                    <span>Rankings</span>
                </a>
                
                <a href="export.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'export.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-download w-5 h-5 mr-3"></i>
                    <span>Export Data</span>
                </a>
                
                <a href="system_settings.php" class="sidebar-item flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page == 'system_settings.php' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                    <i class="fas fa-cogs w-5 h-5 mr-3"></i>
                    <span>Settings</span>
                </a>
            </div>
            </div>
        </nav>
    </div>
    
    <!-- Bottom User Section -->
    <div class="flex-shrink-0 p-4 border-t border-gray-200 bg-white">
        <div class="relative">
            <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100 transition-colors" onclick="toggleUserMenu()">
                <div class="w-8 h-8 bg-gradient-to-br from-green-400 to-blue-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-user text-white text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate"><?php echo htmlspecialchars($user['name']); ?></p>
                    <p class="text-xs text-gray-500">Admin</p>
                </div>
                <button class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
            </div>
            
            <!-- User Menu Dropdown -->
            <div id="userMenu" class="hidden absolute bottom-full left-0 right-0 mb-2 bg-white rounded-lg shadow-lg border border-gray-200 py-2">
                <a href="system_settings.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                    <i class="fas fa-cogs w-4 h-4 mr-3 text-gray-400"></i>
                    <span>System Settings</span>
                </a>
                <a href="../change_password.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                    <i class="fas fa-key w-4 h-4 mr-3 text-gray-400"></i>
                    <span>Change Password</span>
                </a>
                <div class="border-t border-gray-100 my-1"></div>
                <a href="../logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                    <i class="fas fa-sign-out-alt w-4 h-4 mr-3 text-red-400"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* Ensure proper scrolling for sidebar */
#sidebar {
    height: 100vh;
}

#sidebar .overflow-y-auto {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e0 transparent;
}

#sidebar .overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

#sidebar .overflow-y-auto::-webkit-scrollbar-track {
    background: transparent;
}

#sidebar .overflow-y-auto::-webkit-scrollbar-thumb {
    background-color: #cbd5e0;
    border-radius: 3px;
}

#sidebar .overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background-color: #a0aec0;
}

/* Smooth transitions for user menu */
#userMenu {
    transition: all 0.2s ease-in-out;
    transform-origin: bottom;
}

#userMenu.hidden {
    opacity: 0;
    transform: translateY(10px) scale(0.95);
}

#userMenu:not(.hidden) {
    opacity: 1;
    transform: translateY(0) scale(1);
}

/* Ensure sidebar items are properly spaced */
.sidebar-item {
    transition: all 0.2s ease-in-out;
}

.sidebar-item:hover {
    transform: translateX(2px);
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

// User menu toggle
function toggleUserMenu() {
    const userMenu = document.getElementById('userMenu');
    userMenu.classList.toggle('hidden');
}

// Close user menu when clicking outside
document.addEventListener('click', function(event) {
    const userMenu = document.getElementById('userMenu');
    const userMenuButton = event.target.closest('[onclick="toggleUserMenu()"]');
    
    if (!userMenuButton && !userMenu.contains(event.target)) {
        userMenu.classList.add('hidden');
    }
});

// Close user menu on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        document.getElementById('userMenu').classList.add('hidden');
    }
});
</script>