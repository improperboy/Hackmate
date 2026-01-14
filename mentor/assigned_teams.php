<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('mentor');
$user = getCurrentUser();

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$floor_filter = $_GET['floor'] ?? '';
$room_filter = $_GET['room'] ?? '';

// Get mentor's assigned teams with search and filters
$stmt = $pdo->prepare("
    SELECT ma.*, f.floor_number, r.room_number 
    FROM mentor_assignments ma
    JOIN floors f ON ma.floor_id = f.id
    JOIN rooms r ON ma.room_id = r.id
    WHERE ma.mentor_id = ?
");
$stmt->execute([$user['id']]);
$assignments = $stmt->fetchAll();

$assigned_teams = [];
if (!empty($assignments)) {
    $floor_room_conditions = [];
    $params = [];
    
    foreach ($assignments as $assignment) {
        $floor_room_conditions[] = "(t.floor_id = ? AND t.room_id = ?)";
        $params[] = $assignment['floor_id'];
        $params[] = $assignment['room_id'];
    }
    
    $teams_query = "
        SELECT t.*, u.name as leader_name, u.email as leader_email,
               f.floor_number, r.room_number,
               (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id) as member_count,
               (SELECT GROUP_CONCAT(u2.name SEPARATOR ', ') 
                FROM team_members tm2 
                JOIN users u2 ON tm2.user_id = u2.id 
                WHERE tm2.team_id = t.id) as members
        FROM teams t 
        JOIN users u ON t.leader_id = u.id 
        JOIN floors f ON t.floor_id = f.id
        JOIN rooms r ON t.room_id = r.id
        WHERE t.status = 'approved' AND (" . implode(' OR ', $floor_room_conditions) . ")
    ";
    
    // Add search condition
    if ($search) {
        $teams_query .= " AND (t.name LIKE ? OR u.name LIKE ? OR t.idea LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Add floor filter
    if ($floor_filter) {
        $teams_query .= " AND f.floor_number = ?";
        $params[] = $floor_filter;
    }
    
    // Add room filter
    if ($room_filter) {
        $teams_query .= " AND r.room_number = ?";
        $params[] = $room_filter;
    }
    
    $teams_query .= " ORDER BY t.name ASC";
    
    $stmt = $pdo->prepare($teams_query);
    $stmt->execute($params);
    $assigned_teams = $stmt->fetchAll();
}

// Get team submissions
$team_submissions = [];
if (!empty($assigned_teams)) {
    $team_ids = array_column($assigned_teams, 'id');
    $placeholders = str_repeat('?,', count($team_ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT * FROM submissions WHERE team_id IN ($placeholders)");
    $stmt->execute($team_ids);
    $submissions = $stmt->fetchAll();
    
    foreach ($submissions as $submission) {
        $team_submissions[$submission['team_id']] = $submission;
    }
}

// Get floors and rooms for filters
$stmt = $pdo->query("SELECT DISTINCT floor_number FROM floors ORDER BY floor_number");
$floors = $stmt->fetchAll();

$stmt = $pdo->query("SELECT DISTINCT room_number FROM rooms ORDER BY room_number");
$rooms = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Teams - HackMate</title>
    
    <!-- Primary Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- PWA Configuration -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#10B981">
    <meta name="background-color" content="#10B981">
    
    <style>
        .mobile-menu-btn {
            display: none;
        }

        @media (max-width: 1024px) {
            .mobile-menu-btn {
                display: block;
            }
            
            .lg\:ml-64 {
                margin-left: 0 !important;
            }
        }
        
        /* Ensure sidebar is properly positioned */
        #sidebar {
            position: fixed !important;
            top: 0;
            left: 0;
            z-index: 40;
            width: 16rem;
            height: 100vh;
        }
        
        /* Main content positioning */
        .main-content {
            margin-left: 0;
            min-height: 100vh;
        }
        
        @media (min-width: 1024px) {
            .main-content {
                margin-left: 16rem !important;
            }
        }
        
        /* Ensure proper layout on mobile */
        @media (max-width: 1023px) {
            #sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
            }
            
            #sidebar.show {
                transform: translateX(0);
            }
        }
        
        .team-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .team-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .search-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content min-h-screen bg-gray-50">
        <!-- Top Navigation Bar -->
        <nav class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <!-- Mobile menu button -->
                        <button onclick="toggleSidebar()" class="mobile-menu-btn text-gray-600 hover:text-gray-900 focus:outline-none focus:text-gray-900 mr-4">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-indigo-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-users text-white text-sm"></i>
                            </div>
                            <div>
                                <h1 class="text-xl font-bold text-gray-900">My Teams</h1>
                                <p class="text-sm text-gray-500 hidden sm:block">Teams assigned to your areas</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <!-- Quick Actions Dropdown -->
                        <div class="relative">
                            <button onclick="toggleQuickActions()" class="flex items-center space-x-2 text-gray-600 hover:text-gray-900 focus:outline-none">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div id="quickActionsMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-20">
                                <a href="dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fas fa-tachometer-alt w-4 mr-2"></i>Dashboard
                                </a>
                                <a href="score_teams.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fas fa-star w-4 mr-2"></i>Score Teams
                                </a>
                                <a href="../logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <i class="fas fa-sign-out-alt w-4 mr-2"></i>Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="p-4 sm:p-6 lg:p-8">
            <!-- Page Header -->
            <div class="mb-8">
                <div class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-bold mb-2">My Teams</h2>
                            <p class="text-purple-100 mb-3">Manage and monitor your assigned teams</p>
                            
                            <?php if (!empty($assignments)): ?>
                                <div class="flex items-center text-purple-100">
                                    <i class="fas fa-map-marker-alt mr-2"></i>
                                    <span class="text-sm">
                                        Assigned to: 
                                        <?php 
                                        $assignment_list = [];
                                        foreach ($assignments as $assignment) {
                                            $assignment_list[] = $assignment['floor_number'] . '-' . $assignment['room_number'];
                                        }
                                        echo implode(', ', $assignment_list);
                                        ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="hidden md:block">
                            <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center backdrop-blur-sm">
                                <i class="fas fa-users text-3xl text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="search-card rounded-2xl shadow-sm border border-gray-200 p-6 mb-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-search text-blue-500 mr-2"></i>
                    Search & Filter Teams
                </h3>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search Teams</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by team name, leader, or idea..."
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Floor</label>
                        <select name="floor" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Floors</option>
                            <?php foreach ($floors as $floor): ?>
                                <option value="<?php echo $floor['floor_number']; ?>" <?php echo $floor_filter == $floor['floor_number'] ? 'selected' : ''; ?>>
                                    <?php echo $floor['floor_number']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Room</label>
                        <select name="room" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Rooms</option>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?php echo $room['room_number']; ?>" <?php echo $room_filter == $room['room_number'] ? 'selected' : ''; ?>>
                                    <?php echo $room['room_number']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors font-medium">
                            <i class="fas fa-search mr-2"></i>Search
                        </button>
                        <a href="assigned_teams.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
        </div>

            <!-- Teams Grid -->
            <?php if (empty($assigned_teams)): ?>
                <div class="team-card rounded-2xl shadow-sm border border-gray-200 p-12 text-center">
                    <i class="fas fa-users text-gray-300 text-6xl mb-6"></i>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">No Teams Found</h3>
                    <p class="text-gray-500 mb-4">No teams are currently assigned to your areas or match your search criteria.</p>
                    <a href="dashboard.php" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                        <i class="fas fa-tachometer-alt mr-2"></i>
                        Back to Dashboard
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <?php foreach ($assigned_teams as $team): ?>
                        <div class="team-card rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-purple-600">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-xl font-bold text-white"><?php echo htmlspecialchars($team['name']); ?></h3>
                                        <p class="text-blue-100">Team ID: #<?php echo $team['id']; ?></p>
                                    </div>
                                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                        <i class="fas fa-users text-white text-lg"></i>
                                    </div>
                                </div>
                            </div>
                        
                            <div class="p-6">
                                <!-- Team Info Grid -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                    <!-- Team Leader -->
                                    <div class="bg-gradient-to-br from-yellow-50 to-orange-50 rounded-xl p-4">
                                        <div class="flex items-center mb-2">
                                            <i class="fas fa-crown text-yellow-500 mr-2"></i>
                                            <h4 class="font-semibold text-gray-900">Team Leader</h4>
                                        </div>
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($team['leader_name']); ?></p>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($team['leader_email']); ?></p>
                                    </div>

                                    <!-- Location -->
                                    <div class="bg-gradient-to-br from-red-50 to-pink-50 rounded-xl p-4">
                                        <div class="flex items-center mb-2">
                                            <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>
                                            <h4 class="font-semibold text-gray-900">Location</h4>
                                        </div>
                                        <p class="font-medium text-gray-900"><?php echo $team['floor_number']; ?> - <?php echo $team['room_number']; ?></p>
                                        <p class="text-sm text-gray-600"><?php echo $team['member_count']; ?> team members</p>
                                    </div>
                                </div>

                                <!-- Team Members -->
                                <div class="mb-4">
                                    <div class="flex items-center mb-2">
                                        <i class="fas fa-users text-blue-500 mr-2"></i>
                                        <h4 class="font-semibold text-gray-900">Team Members (<?php echo $team['member_count']; ?>)</h4>
                                    </div>
                                    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-3">
                                        <p class="text-sm text-gray-700"><?php echo htmlspecialchars($team['members']); ?></p>
                                    </div>
                                </div>

                                <!-- Project Idea -->
                                <?php if ($team['idea']): ?>
                                    <div class="mb-4">
                                        <div class="flex items-center mb-2">
                                            <i class="fas fa-lightbulb text-yellow-500 mr-2"></i>
                                            <h4 class="font-semibold text-gray-900">Project Idea</h4>
                                        </div>
                                        <div class="bg-gradient-to-br from-yellow-50 to-orange-50 rounded-xl p-3">
                                            <p class="text-sm text-gray-700"><?php echo htmlspecialchars($team['idea']); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Problem Statement -->
                                <?php if ($team['problem_statement']): ?>
                                    <div class="mb-4">
                                        <div class="flex items-center mb-2">
                                            <i class="fas fa-question-circle text-purple-500 mr-2"></i>
                                            <h4 class="font-semibold text-gray-900">Problem Statement</h4>
                                        </div>
                                        <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-3">
                                            <p class="text-sm text-gray-700"><?php echo htmlspecialchars($team['problem_statement']); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Submission Status -->
                                <div class="mb-6">
                                    <div class="flex items-center mb-3">
                                        <i class="fas fa-upload text-green-500 mr-2"></i>
                                        <h4 class="font-semibold text-gray-900">Submission Status</h4>
                                    </div>
                                    <?php if (isset($team_submissions[$team['id']])): ?>
                                        <div class="bg-gradient-to-br from-green-50 to-emerald-50 border-l-4 border-green-500 rounded-xl p-4">
                                            <div class="flex items-center mb-2">
                                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                                <p class="text-green-800 font-semibold">Project Submitted</p>
                                            </div>
                                            <p class="text-sm text-green-700 mb-3">
                                                Submitted: <?php echo date('M j, Y g:i A', strtotime($team_submissions[$team['id']]['submitted_at'])); ?>
                                            </p>
                                            <div class="flex space-x-3">
                                                <a href="<?php echo htmlspecialchars($team_submissions[$team['id']]['github_link']); ?>" 
                                                   target="_blank" 
                                                   class="inline-flex items-center px-3 py-1 bg-gray-800 hover:bg-gray-900 text-white text-sm rounded-lg transition-colors">
                                                    <i class="fab fa-github mr-1"></i>
                                                    GitHub
                                                </a>
                                                <?php if ($team_submissions[$team['id']]['live_link']): ?>
                                                    <a href="<?php echo htmlspecialchars($team_submissions[$team['id']]['live_link']); ?>" 
                                                       target="_blank" 
                                                       class="inline-flex items-center px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg transition-colors">
                                                        <i class="fas fa-external-link-alt mr-1"></i>
                                                        Live Demo
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="bg-gradient-to-br from-yellow-50 to-orange-50 border-l-4 border-yellow-500 rounded-xl p-4">
                                            <div class="flex items-center mb-1">
                                                <i class="fas fa-clock text-yellow-500 mr-2"></i>
                                                <p class="text-yellow-800 font-semibold">Not Submitted Yet</p>
                                            </div>
                                            <p class="text-sm text-yellow-700">Waiting for project submission</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Actions -->
                                <div class="flex space-x-3">
                                    <a href="score_teams.php?team_id=<?php echo $team['id']; ?>" 
                                       class="flex-1 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white text-center py-3 px-4 rounded-lg font-medium transition-all duration-200 transform hover:scale-105">
                                        <i class="fas fa-star mr-2"></i>
                                        Score Team
                                    </a>
                                    <a href="support_messages.php?team_id=<?php echo $team['id']; ?>" 
                                       class="flex-1 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white text-center py-3 px-4 rounded-lg font-medium transition-all duration-200 transform hover:scale-105">
                                        <i class="fas fa-comments mr-2"></i>
                                        Messages
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Sidebar functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');

            if (sidebar) {
                sidebar.classList.toggle('-translate-x-full');
                sidebar.classList.toggle('show');
            }
            if (overlay) {
                overlay.classList.toggle('hidden');
            }
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');

            if (sidebar) {
                sidebar.classList.add('-translate-x-full');
                sidebar.classList.remove('show');
            }
            if (overlay) {
                overlay.classList.add('hidden');
            }
        }

        // Quick Actions Menu Toggle
        function toggleQuickActions() {
            const menu = document.getElementById('quickActionsMenu');
            menu.classList.toggle('hidden');
        }

        // Close quick actions menu when clicking outside
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('quickActionsMenu');
            const button = event.target.closest('button');
            
            if (!button || !button.onclick || button.onclick.toString().indexOf('toggleQuickActions') === -1) {
                menu.classList.add('hidden');
            }
        });

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
</body>
</html>
