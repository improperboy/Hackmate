<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('mentor');
$user = getCurrentUser();

// Get mentor's assignments
$stmt = $pdo->prepare("
    SELECT ma.*, f.floor_number, r.room_number 
    FROM mentor_assignments ma
    JOIN floors f ON ma.floor_id = f.id
    JOIN rooms r ON ma.room_id = r.id
    WHERE ma.mentor_id = ?
");
$stmt->execute([$user['id']]);
$assignments = $stmt->fetchAll();

// Get teams in mentor's assigned areas with theme information
$assigned_teams = [];
if (!empty($assignments)) {
    $floor_room_conditions = [];
    $params = [];

    foreach ($assignments as $assignment) {
        $floor_room_conditions[] = "(t.floor_id = ? AND t.room_id = ?)";
        $params[] = $assignment['floor_id'];
        $params[] = $assignment['room_id'];
    }

    // First, get the basic team information without the scores subquery
    $teams_query = "
        SELECT t.*, u.name as leader_name, u.email as leader_email,
               f.floor_number, r.room_number, th.name as theme_name, th.color_code as theme_color,
               (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id) as member_count
        FROM teams t 
        LEFT JOIN users u ON t.leader_id = u.id 
        LEFT JOIN floors f ON t.floor_id = f.id
        LEFT JOIN rooms r ON t.room_id = r.id
        LEFT JOIN themes th ON t.theme_id = th.id
        WHERE t.status = 'approved' AND (" . implode(' OR ', $floor_room_conditions) . ")
        ORDER BY t.created_at DESC
    ";

    $stmt = $pdo->prepare($teams_query);
    $stmt->execute($params);
    $assigned_teams = $stmt->fetchAll();
    
    // Now add the scores_given count for each team
    foreach ($assigned_teams as &$team) {
        $score_stmt = $pdo->prepare("SELECT COUNT(*) FROM scores WHERE team_id = ? AND mentor_id = ?");
        $score_stmt->execute([$team['id'], $user['id']]);
        $team['scores_given'] = $score_stmt->fetchColumn();
    }
    
    // Debug: Log the query and results
    error_log("Mentor ID: " . $user['id']);
    error_log("Assignments count: " . count($assignments));
    error_log("Teams query: " . $teams_query);
    error_log("Query params: " . print_r($params, true));
    error_log("Assigned teams count: " . count($assigned_teams));
}



// Get active mentoring rounds
$stmt = $pdo->query("
    SELECT * FROM mentoring_rounds 
    WHERE NOW() BETWEEN start_time AND end_time 
    ORDER BY start_time ASC
");
$active_rounds = $stmt->fetchAll();

// Get upcoming mentoring rounds
$stmt = $pdo->query("
    SELECT * FROM mentoring_rounds 
    WHERE start_time > NOW() 
    ORDER BY start_time ASC 
    LIMIT 3
");
$upcoming_rounds = $stmt->fetchAll();

// Get support messages for mentor's areas
$support_messages = [];
if (!empty($assignments)) {
    $floor_room_conditions = [];
    $params = [];

    foreach ($assignments as $assignment) {
        $floor_room_conditions[] = "(sm.floor_id = ? AND sm.room_id = ?)";
        $params[] = $assignment['floor_id'];
        $params[] = $assignment['room_id'];
    }

    $support_query = "
        SELECT sm.*, u.name as from_name, f.floor_number, r.room_number
        FROM support_messages sm 
        JOIN users u ON sm.from_id = u.id 
        LEFT JOIN floors f ON sm.floor_id = f.id
        LEFT JOIN rooms r ON sm.room_id = r.id
        WHERE sm.to_role = 'mentor' AND sm.status = 'open' AND (" . implode(' OR ', $floor_room_conditions) . ")
        ORDER BY sm.priority DESC, sm.created_at DESC
        LIMIT 5
    ";

    $stmt = $pdo->prepare($support_query);
    $stmt->execute($params);
    $support_messages = $stmt->fetchAll();
}

// Get mentor's scoring statistics
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_scores, 
           COALESCE(AVG(score), 0) as avg_score,
           COUNT(DISTINCT team_id) as teams_scored,
           COUNT(DISTINCT round_id) as rounds_participated
    FROM scores 
    WHERE mentor_id = ?
");
$stmt->execute([$user['id']]);
$scoring_stats = $stmt->fetch();

// Get recent announcements
$stmt = $pdo->query("
    SELECT p.*, u.name as author_name 
    FROM posts p 
    JOIN users u ON p.author_id = u.id 
    WHERE p.target_roles IS NULL OR JSON_CONTAINS(p.target_roles, '\"mentor\"')
    ORDER BY p.is_pinned DESC, p.created_at DESC 
    LIMIT 3
");
$recent_announcements = $stmt->fetchAll();

// Get team progress data for charts
$team_progress_data = [];
if (!empty($assigned_teams)) {
    foreach ($assigned_teams as $team) {
        $stmt = $pdo->prepare("
            SELECT mr.round_name, COALESCE(s.score, 0) as score, mr.max_score
            FROM mentoring_rounds mr
            LEFT JOIN scores s ON (mr.id = s.round_id AND s.team_id = ? AND s.mentor_id = ?)
            WHERE mr.end_time < NOW()
            ORDER BY mr.start_time ASC
        ");
        $stmt->execute([$team['id'], $user['id']]);
        $team_progress_data[$team['id']] = $stmt->fetchAll();
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentor Dashboard - HackMate</title>

    <!-- Primary Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- Chart.js for data visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- PWA Configuration -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#10B981">
    <meta name="background-color" content="#10B981">

    <!-- iOS PWA Support -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="HackMate">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">

    <!-- Android PWA Support -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="HackMate">

    <!-- Windows PWA Support -->
    <meta name="msapplication-TileImage" content="/assets/icons/icon-144x144.png">
    <meta name="msapplication-TileColor" content="#10B981">
    <meta name="msapplication-navbutton-color" content="#10B981">

    <!-- General Meta Tags -->
    <meta name="description" content="Mentor Dashboard for HackMate - Guide and score hackathon teams">
    <meta name="keywords" content="hackathon, mentor, dashboard, scoring, teams, guidance">
    <meta name="author" content="HackMate Team">

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/icon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/icon-72x72.png">

    <!-- Preload critical resources -->
    <link rel="preload" href="/assets/js/pwa.js" as="script">
    <link rel="preload" href="/sw.js" as="script">

    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
        }

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
        }

        .priority-high {
            border-left: 4px solid #ef4444;
            background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
        }

        .priority-medium {
            border-left: 4px solid #f59e0b;
            background: linear-gradient(135deg, #fffbeb 0%, #fed7aa 100%);
        }

        .priority-low {
            border-left: 4px solid #10b981;
            background: linear-gradient(135deg, #f0fdf4 0%, #bbf7d0 100%);
        }

        .team-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .team-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }

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
            position: fixed;
            top: 0;
            left: 0;
            z-index: 40;
        }
        
        /* Main content positioning */
        .main-content {
            margin-left: 0;
        }
        
        @media (min-width: 1024px) {
            .main-content {
                margin-left: 16rem; /* 64 * 0.25rem = 16rem */
            }
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
                            <div class="w-8 h-8 bg-gradient-to-br from-green-500 to-emerald-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-tachometer-alt text-white text-sm"></i>
                            </div>
                            <div>
                                <h1 class="text-xl font-bold text-gray-900">Dashboard</h1>
                                <p class="text-sm text-gray-500 hidden sm:block">Welcome back, <?php echo htmlspecialchars($user['name']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <!-- Notification Bell -->
                        <?php if (count($support_messages) > 0): ?>
                            <div class="relative">
                                <a href="support_messages.php" class="text-gray-600 hover:text-gray-900 transition-colors">
                                    <i class="fas fa-bell text-xl"></i>
                                    <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center animate-pulse">
                                        <?php echo count($support_messages); ?>
                                    </span>
                                </a>
                            </div>
                        <?php endif; ?>

                        <!-- Quick Actions Dropdown -->
                        <div class="relative">
                            <button onclick="toggleQuickActions()" class="flex items-center space-x-2 text-gray-600 hover:text-gray-900 focus:outline-none">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div id="quickActionsMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-20">
                                <a href="../change_password.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fas fa-key w-4 mr-2"></i>Change Password
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

        <!-- Main Dashboard Content -->
        <div class="p-4 sm:p-6 lg:p-8">
            <!-- Welcome Section -->
            <div class="mb-8">
                <div class="gradient-bg rounded-2xl p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-bold mb-2">Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 18 ? 'Afternoon' : 'Evening'); ?>!</h2>
                            <p class="text-green-100 mb-4">Ready to guide and mentor amazing teams today?</p>

                            <?php if (!empty($assignments)): ?>
                                <div class="flex items-center space-x-2 text-green-100">
                                    <i class="fas fa-map-marker-alt"></i>
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
                            <?php else: ?>
                                <div class="bg-yellow-500 bg-opacity-20 border border-yellow-300 rounded-lg p-3 mt-4">
                                    <div class="flex items-center">
                                        <i class="fas fa-exclamation-triangle text-yellow-200 mr-2"></i>
                                        <span class="text-sm text-yellow-100">No assignments yet. Contact admin for team assignments.</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="hidden md:block">
                            <div class="w-24 h-24 bg-white bg-opacity-20 rounded-full flex items-center justify-center backdrop-blur-sm">
                                <i class="fas fa-chalkboard-teacher text-4xl text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Debug Info (temporary) -->
            <?php if (isset($_GET['debug'])): ?>
                <div class="bg-yellow-100 border border-yellow-400 rounded-lg p-4 mb-6">
                    <h4 class="font-bold">Debug Information:</h4>
                    <p>Assignments: <?php echo count($assignments); ?></p>
                    <p>Assigned Teams: <?php echo count($assigned_teams); ?></p>
                    <p>Debug Teams: <?php echo count($debug_teams ?? []); ?></p>
                    <p>User ID: <?php echo $user['id']; ?></p>
                    <?php if (!empty($assignments)): ?>
                        <p>Assignment Details:</p>
                        <ul>
                            <?php foreach ($assignments as $assignment): ?>
                                <li>Floor ID: <?php echo $assignment['floor_id']; ?>, Room ID: <?php echo $assignment['room_id']; ?> (<?php echo $assignment['floor_number']; ?>-<?php echo $assignment['room_number']; ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if (!empty($assigned_teams)): ?>
                        <p>Assigned Teams Found:</p>
                        <ul>
                            <?php foreach ($assigned_teams as $team): ?>
                                <li><?php echo htmlspecialchars($team['name']); ?> - Status: <?php echo $team['status']; ?> - Location: <?php echo $team['floor_number']; ?>-<?php echo $team['room_number']; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <a href="debug_teams.php" class="text-blue-600 underline">View detailed debug</a>
                </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Assigned Teams -->
                <div class="stat-card rounded-xl p-6 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-1">Assigned Teams</p>
                            <p class="text-3xl font-bold text-gray-900"><?php echo count($assigned_teams); ?></p>
                            <p class="text-xs text-gray-500 mt-1">
                                <?php echo count($assignments); ?> location<?php echo count($assignments) != 1 ? 's' : ''; ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center">
                            <i class="fas fa-users text-white text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Teams Scored -->
                <div class="stat-card rounded-xl p-6 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-1">Teams Scored</p>
                            <p class="text-3xl font-bold text-gray-900"><?php echo $scoring_stats['teams_scored'] ?? 0; ?></p>
                            <p class="text-xs text-gray-500 mt-1">
                                Avg: <?php echo number_format($scoring_stats['avg_score'] ?? 0, 1); ?> pts
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 rounded-xl flex items-center justify-center">
                            <i class="fas fa-star text-white text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Active Rounds -->
                <div class="stat-card rounded-xl p-6 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-1">Active Rounds</p>
                            <p class="text-3xl font-bold text-gray-900"><?php echo count($active_rounds); ?></p>
                            <p class="text-xs text-gray-500 mt-1">
                                <?php echo count($upcoming_rounds); ?> upcoming
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center">
                            <i class="fas fa-clock text-white text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Support Requests -->
                <div class="stat-card rounded-xl p-6 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-1">Support Requests</p>
                            <p class="text-3xl font-bold text-gray-900"><?php echo count($support_messages); ?></p>
                            <p class="text-xs text-gray-500 mt-1">
                                <?php
                                $urgent_count = 0;
                                foreach ($support_messages as $msg) {
                                    if ($msg['priority'] == 'urgent' || $msg['priority'] == 'high') $urgent_count++;
                                }
                                echo $urgent_count; ?> urgent
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl flex items-center justify-center">
                            <i class="fas fa-life-ring text-white text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions & Active Rounds -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Quick Actions -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">
                            <i class="fas fa-bolt text-yellow-500 mr-2"></i>
                            Quick Actions
                        </h3>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <?php if (!empty($assignments)): ?>
                            <a href="score_teams.php" class="group bg-gradient-to-br from-blue-50 to-blue-100 hover:from-blue-100 hover:to-blue-200 p-4 rounded-xl text-center transition-all duration-200 card-hover">
                                <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                                    <i class="fas fa-star text-white text-xl"></i>
                                </div>
                                <p class="text-sm font-medium text-gray-900">Score Teams</p>
                                <?php if (count($active_rounds) > 0): ?>
                                    <span class="inline-block bg-green-500 text-white text-xs px-2 py-1 rounded-full mt-1">
                                        <?php echo count($active_rounds); ?> active
                                    </span>
                                <?php endif; ?>
                            </a>

                            <a href="assigned_teams.php" class="group bg-gradient-to-br from-purple-50 to-purple-100 hover:from-purple-100 hover:to-purple-200 p-4 rounded-xl text-center transition-all duration-200 card-hover">
                                <div class="w-12 h-12 bg-purple-500 rounded-lg flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                                    <i class="fas fa-users text-white text-xl"></i>
                                </div>
                                <p class="text-sm font-medium text-gray-900">My Teams</p>
                                <span class="text-xs text-gray-600"><?php echo count($assigned_teams); ?> teams</span>
                            </a>
                        <?php endif; ?>

                        <a href="support_messages.php" class="group bg-gradient-to-br from-green-50 to-green-100 hover:from-green-100 hover:to-green-200 p-4 rounded-xl text-center transition-all duration-200 card-hover">
                            <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                                <i class="fas fa-life-ring text-white text-xl"></i>
                            </div>
                            <p class="text-sm font-medium text-gray-900">Support</p>
                            <?php if (count($support_messages) > 0): ?>
                                <span class="inline-block bg-red-500 text-white text-xs px-2 py-1 rounded-full mt-1 animate-pulse">
                                    <?php echo count($support_messages); ?> new
                                </span>
                            <?php endif; ?>
                        </a>

                        <a href="schedule.php" class="group bg-gradient-to-br from-indigo-50 to-indigo-100 hover:from-indigo-100 hover:to-indigo-200 p-4 rounded-xl text-center transition-all duration-200 card-hover">
                            <div class="w-12 h-12 bg-indigo-500 rounded-lg flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                                <i class="fas fa-calendar text-white text-xl"></i>
                            </div>
                            <p class="text-sm font-medium text-gray-900">Schedule</p>
                            <span class="text-xs text-gray-600">View rounds</span>
                        </a>
                    </div>
                </div>

                <!-- Active & Upcoming Rounds -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">
                            <i class="fas fa-clock text-blue-500 mr-2"></i>
                            Mentoring Rounds
                        </h3>
                    </div>

                    <div class="space-y-4 max-h-80 overflow-y-auto">
                        <?php if (!empty($active_rounds)): ?>
                            <div class="mb-4">
                                <h4 class="text-sm font-medium text-gray-700 mb-2 flex items-center">
                                    <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                                    Active Now
                                </h4>
                                <?php foreach ($active_rounds as $round): ?>
                                    <div class="p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl border-l-4 border-green-500 mb-3">
                                        <h5 class="font-medium text-gray-900 text-sm"><?php echo htmlspecialchars($round['round_name']); ?></h5>
                                        <p class="text-xs text-gray-600 mt-1">
                                            <i class="fas fa-clock mr-1"></i>
                                            Ends: <?php echo date('M j, g:i A', strtotime($round['end_time'])); ?>
                                        </p>
                                        <p class="text-xs text-green-700 mt-1">
                                            <i class="fas fa-star mr-1"></i>
                                            Max Score: <?php echo $round['max_score']; ?> points
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($upcoming_rounds)): ?>
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2 flex items-center">
                                    <span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
                                    Upcoming
                                </h4>
                                <?php foreach ($upcoming_rounds as $round): ?>
                                    <div class="p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border-l-4 border-blue-500 mb-3">
                                        <h5 class="font-medium text-gray-900 text-sm"><?php echo htmlspecialchars($round['round_name']); ?></h5>
                                        <p class="text-xs text-gray-600 mt-1">
                                            <i class="fas fa-calendar mr-1"></i>
                                            Starts: <?php echo date('M j, g:i A', strtotime($round['start_time'])); ?>
                                        </p>
                                        <p class="text-xs text-blue-700 mt-1">
                                            <i class="fas fa-star mr-1"></i>
                                            Max Score: <?php echo $round['max_score']; ?> points
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($active_rounds) && empty($upcoming_rounds)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-calendar-times text-gray-300 text-3xl mb-3"></i>
                                <p class="text-gray-500 text-sm">No mentoring rounds scheduled</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Support Messages Alert -->
            <?php if (!empty($support_messages)): ?>
                <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border-l-4 border-yellow-400 rounded-xl p-6 mb-8">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-yellow-400 rounded-lg flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-white"></i>
                            </div>
                        </div>
                        <div class="ml-4 flex-1">
                            <h4 class="text-lg font-medium text-yellow-800 mb-2">Pending Support Requests</h4>
                            <p class="text-sm text-yellow-700 mb-3">
                                You have <strong><?php echo count($support_messages); ?></strong> pending support request(s) from participants in your assigned areas.
                            </p>
                            <a href="support_messages.php" class="inline-flex items-center px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-medium rounded-lg transition-colors">
                                <i class="fas fa-reply mr-2"></i>
                                View and Respond
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Recent Announcements -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">
                            <i class="fas fa-bullhorn text-blue-500 mr-2"></i>
                            Recent Announcements
                        </h3>
                        <a href="announcements.php" class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>

                    <div class="space-y-4 max-h-80 overflow-y-auto">
                        <?php if (empty($recent_announcements)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-bullhorn text-gray-300 text-3xl mb-3"></i>
                                <p class="text-gray-500 text-sm">No announcements yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_announcements as $announcement): ?>
                                <div class="p-4 bg-gray-50 rounded-xl border border-gray-100 hover:bg-gray-100 transition-colors">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <h4 class="font-medium text-gray-900 text-sm mb-1">
                                                <?php if ($announcement['is_pinned']): ?>
                                                    <i class="fas fa-thumbtack text-red-500 mr-1"></i>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($announcement['title']); ?>
                                            </h4>
                                            <p class="text-xs text-gray-600 mb-2">
                                                <?php echo substr(strip_tags($announcement['content']), 0, 100) . '...'; ?>
                                            </p>
                                            <div class="flex items-center text-xs text-gray-500">
                                                <span><?php echo htmlspecialchars($announcement['author_name']); ?></span>
                                                <span class="mx-2">•</span>
                                                <span><?php echo date('M j, g:i A', strtotime($announcement['created_at'])); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Assigned Teams Preview -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">
                            <i class="fas fa-users text-green-500 mr-2"></i>
                            Your Teams (<?php echo count($assigned_teams); ?>)
                        </h3>
                        <?php if (!empty($assigned_teams)): ?>
                            <a href="assigned_teams.php" class="text-sm text-green-600 hover:text-green-700 font-medium">
                                View All <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-4 max-h-80 overflow-y-auto">
                        <?php if (empty($assigned_teams)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-users text-gray-300 text-3xl mb-3"></i>
                                <p class="text-gray-500 text-sm">No teams assigned yet</p>
                                <p class="text-gray-400 text-xs mt-1">Contact admin for assignments</p>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_slice($assigned_teams, 0, 5) as $team): ?>
                                <div class="team-card rounded-xl p-4">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center mb-2">
                                                <h4 class="font-medium text-gray-900 text-sm"><?php echo htmlspecialchars($team['name']); ?></h4>
                                                <?php if ($team['theme_color']): ?>
                                                    <span class="ml-2 w-3 h-3 rounded-full" style="background-color: <?php echo $team['theme_color']; ?>"></span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="space-y-1">
                                                <p class="text-xs text-gray-600">
                                                    <i class="fas fa-user-tie mr-1"></i>
                                                    Leader: <?php echo htmlspecialchars($team['leader_name'] ?? 'Not assigned'); ?>
                                                </p>
                                                <p class="text-xs text-gray-600">
                                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                                    <?php echo $team['floor_number']; ?> - <?php echo $team['room_number']; ?>
                                                </p>
                                                <?php if ($team['theme_name']): ?>
                                                    <p class="text-xs text-gray-600">
                                                        <i class="fas fa-tag mr-1"></i>
                                                        <?php echo htmlspecialchars($team['theme_name']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="text-right">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800 mb-1">
                                                <?php echo $team['member_count']; ?> members
                                            </span>
                                            <?php if ($team['scores_given'] > 0): ?>
                                                <div class="text-xs text-green-600">
                                                    <i class="fas fa-check-circle mr-1"></i>
                                                    Scored
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Support Messages Preview -->
            <?php if (!empty($support_messages)): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">
                            <i class="fas fa-life-ring text-orange-500 mr-2"></i>
                            Recent Support Requests
                        </h3>
                        <a href="support_messages.php" class="text-sm text-orange-600 hover:text-orange-700 font-medium">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>

                    <div class="space-y-3">
                        <?php foreach (array_slice($support_messages, 0, 3) as $message): ?>
                            <div class="p-4 rounded-xl <?php
                                                        echo $message['priority'] == 'urgent' || $message['priority'] == 'high' ? 'priority-high' : ($message['priority'] == 'medium' ? 'priority-medium' : 'priority-low');
                                                        ?>">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-1">
                                            <h4 class="font-medium text-gray-900 text-sm">
                                                <?php echo htmlspecialchars($message['subject'] ?? 'Support Request'); ?>
                                            </h4>
                                            <span class="ml-2 px-2 py-1 text-xs rounded-full <?php
                                                                                                echo $message['priority'] == 'urgent' ? 'bg-red-100 text-red-800' : ($message['priority'] == 'high' ? 'bg-orange-100 text-orange-800' : ($message['priority'] == 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'));
                                                                                                ?>">
                                                <?php echo ucfirst($message['priority']); ?>
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-600 mb-2">
                                            <?php echo substr(strip_tags($message['message']), 0, 80) . '...'; ?>
                                        </p>
                                        <div class="flex items-center text-xs text-gray-500">
                                            <span><?php echo htmlspecialchars($message['from_name']); ?></span>
                                            <?php if ($message['floor_number'] && $message['room_number']): ?>
                                                <span class="mx-2">•</span>
                                                <span><?php echo $message['floor_number'] . '-' . $message['room_number']; ?></span>
                                            <?php endif; ?>
                                            <span class="mx-2">•</span>
                                            <span><?php echo date('M j, g:i A', strtotime($message['created_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Include AI Chatbot -->
    <?php include '../includes/chatbot_component.php'; ?>

    <!-- PWA Scripts -->
    <script src="/assets/js/pwa.js"></script>

    <script>
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

        // Dashboard initialization
        document.addEventListener('DOMContentLoaded', function() {
            // PWA initialization
            if ('serviceWorker' in navigator) {
                console.log('PWA features available on mentor dashboard');

                if (window.pwaManager) {
                    window.pwaManager.enableMentorNotifications = true;
                }
            }

            // Auto-refresh support messages count every 30 seconds
            setInterval(function() {
                fetch('dashboard.php?ajax=support_count')
                    .then(response => response.json())
                    .then(data => {
                        if (data.count > 0) {
                            // Update notification badge
                            const badge = document.querySelector('.notification-badge');
                            if (badge) {
                                badge.textContent = data.count;
                                badge.classList.remove('hidden');
                            }
                        }
                    })
                    .catch(error => console.log('Support count update failed:', error));
            }, 30000);

            // Add smooth scrolling to anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Initialize tooltips for truncated text
            const truncatedElements = document.querySelectorAll('[title]');
            truncatedElements.forEach(element => {
                element.addEventListener('mouseenter', function() {
                    // Could add custom tooltip implementation here
                });
            });

            // Add loading states to action buttons
            document.querySelectorAll('a[href*=".php"]').forEach(link => {
                link.addEventListener('click', function() {
                    const icon = this.querySelector('i');
                    if (icon && !icon.classList.contains('fa-spin')) {
                        const originalClass = icon.className;
                        icon.className = 'fas fa-spinner fa-spin';

                        // Restore original icon after a short delay
                        setTimeout(() => {
                            icon.className = originalClass;
                        }, 1000);
                    }
                });
            });
        });

        // Handle AJAX support count updates
        <?php if (isset($_GET['ajax']) && $_GET['ajax'] === 'support_count'): ?>
            <?php
            header('Content-Type: application/json');

            $support_count = 0;
            if (!empty($assignments)) {
                $floor_room_conditions = [];
                $params = [];

                foreach ($assignments as $assignment) {
                    $floor_room_conditions[] = "(sm.floor_id = ? AND sm.room_id = ?)";
                    $params[] = $assignment['floor_id'];
                    $params[] = $assignment['room_id'];
                }

                $support_query = "
                    SELECT COUNT(*) 
                    FROM support_messages sm 
                    WHERE sm.to_role = 'mentor' AND sm.status = 'open' AND (" . implode(' OR ', $floor_room_conditions) . ")
                ";

                $stmt = $pdo->prepare($support_query);
                $stmt->execute($params);
                $support_count = $stmt->fetchColumn();
            }

            echo json_encode(['count' => $support_count]);
            exit;
            ?>
        <?php endif; ?>
    </script>
</body>

</html>