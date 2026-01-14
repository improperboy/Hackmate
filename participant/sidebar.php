<?php
// Participant Sidebar Component
// This file contains the sidebar navigation for participant pages

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Get user's team status for conditional navigation
$user = getCurrentUser();
$stmt = $pdo->prepare("
    SELECT t.*, tm.user_id as is_member, t.leader_id = ? as is_leader
    FROM teams t 
    JOIN team_members tm ON t.id = tm.team_id 
    WHERE tm.user_id = ? AND t.status = 'approved'
");
$stmt->execute([$user['id'], $user['id']]);
$user_team = $stmt->fetch();

// Check for pending invitations from approved teams only
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM team_invitations ti 
    JOIN teams t ON ti.team_id = t.id 
    WHERE ti.to_user_id = ? AND ti.status = 'pending' AND t.status = 'approved'
");
$stmt->execute([$user['id']]);
$pending_invitations = $stmt->fetchColumn();

// Check for pending join requests (if user is team leader)
$pending_requests = 0;
if ($user_team && $user_team['is_leader']) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM join_requests WHERE team_id = ? AND status = 'pending'");
    $stmt->execute([$user_team['id']]);
    $pending_requests = $stmt->fetchColumn();
}

// Check for pending join requests sent by user (can be multiple to different teams)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM join_requests WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user['id']]);
$user_pending_requests = $stmt->fetchColumn();

// Check for pending team created by user
$stmt = $pdo->prepare("SELECT * FROM teams WHERE leader_id = ? AND status = 'pending'");
$stmt->execute([$user['id']]);
$pending_team = $stmt->fetch();

// Check for rejected team created by user
$stmt = $pdo->prepare("SELECT * FROM teams WHERE leader_id = ? AND status = 'rejected'");
$stmt->execute([$user['id']]);
$rejected_team = $stmt->fetch();
?>

<!-- Mobile sidebar overlay -->
<div id="sidebar-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-20 lg:hidden hidden backdrop-blur-sm"></div>

<!-- Sidebar -->
<div id="sidebar" class="fixed inset-y-0 left-0 z-30 w-64 bg-white shadow-xl transform -translate-x-full transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0 flex flex-col border-r border-gray-200">
    <!-- Logo Section -->
    <div class="flex items-center justify-between h-16 px-6 border-b border-gray-100 flex-shrink-0 bg-gradient-to-r from-purple-600 to-blue-600">
        <div class="flex items-center">
            <div class="w-8 h-8 bg-white bg-opacity-20 rounded-lg flex items-center justify-center backdrop-blur-sm">
                <i class="fas fa-laptop-code text-white text-sm"></i>
            </div>
            <span class="ml-3 text-xl font-bold text-white">HackMate</span>
        </div>
        <button id="sidebar-close" class="lg:hidden text-white hover:text-gray-200 transition-colors">
            <i class="fas fa-times text-lg"></i>
        </button>
    </div>

    <!-- User Info Section -->
    <div class="px-6 py-4 bg-gradient-to-r from-purple-50 to-blue-50 border-b border-gray-100">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-blue-500 rounded-full flex items-center justify-center">
                <span class="text-white font-semibold text-sm"><?php echo strtoupper(substr($user['name'], 0, 2)); ?></span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($user['name']); ?></p>
                <p class="text-xs text-gray-500">Participant</p>
            </div>
        </div>
    </div>

    <!-- Scrollable Navigation -->
    <div class="flex-1 overflow-y-auto">
        <nav class="px-4 py-6">
            <!-- Main Section -->
            <div class="mb-8">
                <div class="space-y-2">
                    <a href="dashboard.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'dashboard.php' ? 'text-white bg-gradient-to-r from-purple-600 to-blue-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                        <i class="fas fa-home w-5 h-5 mr-3 <?php echo $current_page == 'dashboard.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                        <span>Dashboard</span>
                    </a>

                    <?php if ($user_team): ?>
                        <a href="team_details.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'team_details.php' ? 'text-white bg-gradient-to-r from-purple-600 to-blue-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                            <i class="fas fa-users w-5 h-5 mr-3 <?php echo $current_page == 'team_details.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                            <span>My Team</span>
                        </a>

                        <a href="mentoring_rounds.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'mentoring_rounds.php' ? 'text-white bg-gradient-to-r from-purple-600 to-blue-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                            <i class="fas fa-user-tie w-5 h-5 mr-3 <?php echo $current_page == 'mentoring_rounds.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                            <span>Mentoring Rounds</span>
                        </a>

                        <?php if ($user_team['is_leader']): ?>
                            <a href="manage_requests.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'manage_requests.php' ? 'text-white bg-gradient-to-r from-purple-600 to-blue-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                                <i class="fas fa-user-check w-5 h-5 mr-3 <?php echo $current_page == 'manage_requests.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                                <span>Join Requests</span>
                                <?php if ($pending_requests > 0): ?>
                                    <span class="ml-auto bg-orange-500 text-white text-xs px-2 py-1 rounded-full animate-pulse"><?php echo $pending_requests; ?></span>
                                <?php endif; ?>
                            </a>

                            <a href="search_users.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'search_users.php' ? 'text-white bg-gradient-to-r from-purple-600 to-blue-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                                <i class="fas fa-search w-5 h-5 mr-3 <?php echo $current_page == 'search_users.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                                <span>Find Members</span>
                            </a>

                            <a href="submit_project.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'submit_project.php' ? 'text-white bg-gradient-to-r from-purple-600 to-blue-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                                <i class="fas fa-upload w-5 h-5 mr-3 <?php echo $current_page == 'submit_project.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                                <span>Submit Project</span>
                            </a>
                        <?php endif; ?>
                    <?php elseif ($rejected_team): ?>
                        <!-- Show rejected team status and allow new team creation -->
                        <div class="px-3 py-3 text-sm font-medium text-gray-500 bg-red-50 rounded-xl border border-red-200 mb-3">
                            <div class="flex items-center">
                                <i class="fas fa-times w-5 h-5 mr-3 text-red-500"></i>
                                <span>Team Rejected</span>
                            </div>
                            <p class="text-xs text-gray-600 mt-1 ml-8">Create a new team</p>
                        </div>
                        
                        <a href="create_team.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'create_team.php' ? 'text-white bg-gradient-to-r from-purple-600 to-blue-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                            <i class="fas fa-plus w-5 h-5 mr-3 <?php echo $current_page == 'create_team.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                            <span>Create New Team</span>
                        </a>

                        <a href="join_team.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'join_team.php' ? 'text-white bg-gradient-to-r from-purple-600 to-blue-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                            <i class="fas fa-user-plus w-5 h-5 mr-3 <?php echo $current_page == 'join_team.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                            <span>Join Teams</span>
                        </a>
                    <?php elseif (!$pending_team): ?>
                        <!-- Show Create Team and Join Team if no pending team creation -->
                        <a href="create_team.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'create_team.php' ? 'text-white bg-gradient-to-r from-purple-600 to-blue-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                            <i class="fas fa-plus w-5 h-5 mr-3 <?php echo $current_page == 'create_team.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                            <span>Create Team</span>
                        </a>

                        <a href="join_team.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'join_team.php' ? 'text-white bg-gradient-to-r from-purple-600 to-blue-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                            <i class="fas fa-user-plus w-5 h-5 mr-3 <?php echo $current_page == 'join_team.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                            <span>Join Teams</span>
                        </a>
                    <?php else: ?>
                        <!-- Show pending team creation status in sidebar -->
                        <div class="px-3 py-3 text-sm font-medium text-gray-500 bg-orange-50 rounded-xl border border-orange-200">
                            <div class="flex items-center">
                                <i class="fas fa-hourglass-half w-5 h-5 mr-3 text-orange-500"></i>
                                <span>Team Pending</span>
                            </div>
                            <p class="text-xs text-gray-600 mt-1 ml-8">Awaiting approval</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Activity Section -->
            <div class="mb-8">
                <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Activity</p>
                <div class="space-y-2">
                    <!-- Show invitations for all users to view their invitation history -->
                    <a href="team_invitations.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'team_invitations.php' ? 'text-white bg-gradient-to-r from-purple-600 to-blue-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                        <i class="fas fa-envelope w-5 h-5 mr-3 <?php echo $current_page == 'team_invitations.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                        <span>Invitations</span>
                        <?php if ($pending_invitations > 0 && !$user_team): ?>
                            <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full animate-pulse"><?php echo $pending_invitations; ?></span>
                        <?php endif; ?>
                    </a>

                    <!-- Show join requests history for all users -->
                    <?php if (!$user_team): ?>
                        <a href="my_join_requests.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'my_join_requests.php' ? 'text-white bg-gradient-to-r from-purple-600 to-blue-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                            <i class="fas fa-paper-plane w-5 h-5 mr-3 <?php echo $current_page == 'my_join_requests.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                            <span>Join Requests</span>
                            <?php if ($user_pending_requests > 0): ?>
                                <span class="ml-auto bg-blue-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $user_pending_requests; ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>

                    <a href="rankings.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'rankings.php' ? 'text-white bg-gradient-to-r from-purple-600 to-blue-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                        <i class="fas fa-trophy w-5 h-5 mr-3 <?php echo $current_page == 'rankings.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                        <span>Rankings</span>
                    </a>

                    <a href="announcements.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'announcements.php' ? 'text-white bg-gradient-to-r from-purple-600 to-blue-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                        <i class="fas fa-bullhorn w-5 h-5 mr-3 <?php echo $current_page == 'announcements.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                        <span>Announcements</span>
                    </a>

                    <a href="certificates.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'certificates.php' ? 'text-white bg-gradient-to-r from-purple-600 to-blue-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                        <i class="fas fa-certificate w-5 h-5 mr-3 <?php echo $current_page == 'certificates.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                        <span>My Certificates</span>
                    </a>
                </div>
            </div>

            <!-- Support Section -->
            <div class="mb-8">
                <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Support</p>
                <div class="space-y-2">
                    <a href="support.php" class="sidebar-item group flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 <?php echo $current_page == 'support.php' ? 'text-white bg-gradient-to-r from-purple-600 to-blue-600 shadow-lg' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
                        <i class="fas fa-life-ring w-5 h-5 mr-3 <?php echo $current_page == 'support.php' ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'; ?>"></i>
                        <span>Get Help</span>
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
        background: linear-gradient(135deg, rgba(147, 51, 234, 0.1), rgba(59, 130, 246, 0.1));
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