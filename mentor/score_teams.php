<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('mentor');
$user = getCurrentUser();

$message = '';
$error = '';

// Handle score submission
if ($_POST) {
    $team_id = $_POST['team_id'];
    $round_id = $_POST['round_id'];
    $score = intval($_POST['score']);
    $comment = sanitize($_POST['comment']);
    
    // Validate score against round max score
    $stmt = $pdo->prepare("SELECT max_score FROM mentoring_rounds WHERE id = ?");
    $stmt->execute([$round_id]);
    $round = $stmt->fetch();
    
    if (!$round) {
        $error = 'Invalid mentoring round selected';
    } elseif ($score < 0 || $score > $round['max_score']) {
        $error = 'Score must be between 0 and ' . $round['max_score'];
    } else {
        // Check if already scored
        $stmt = $pdo->prepare("SELECT id FROM scores WHERE mentor_id = ? AND team_id = ? AND round_id = ?");
        $stmt->execute([$user['id'], $team_id, $round_id]);
        
        if ($stmt->fetch()) {
            // Update existing score
            $stmt = $pdo->prepare("UPDATE scores SET score = ?, comment = ? WHERE mentor_id = ? AND team_id = ? AND round_id = ?");
            if ($stmt->execute([$score, $comment, $user['id'], $team_id, $round_id])) {
                $message = 'Score updated successfully!';
            } else {
                $error = 'Failed to update score. Please try again.';
            }
        } else {
            // Insert new score
            $stmt = $pdo->prepare("INSERT INTO scores (mentor_id, team_id, round_id, score, comment) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$user['id'], $team_id, $round_id, $score, $comment])) {
                $message = 'Score submitted successfully!';
            } else {
                $error = 'Failed to submit score. Please try again.';
            }
        }
    }
}

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

$teams = [];
if (!empty($assignments)) {
    $floor_room_conditions = [];
    $params = [];
    
    foreach ($assignments as $assignment) {
        $floor_room_conditions[] = "(t.floor_id = ? AND t.room_id = ?)";
        $params[] = $assignment['floor_id'];
        $params[] = $assignment['room_id'];
    }
    
    // Only proceed if we have valid floor/room conditions
    if (!empty($floor_room_conditions)) {
        $teams_query = "
            SELECT t.*, u.name as leader_name, u.email as leader_email,
                   f.floor_number, r.room_number,
                   (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id) as member_count
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
        $teams = $stmt->fetchAll();
    }
}

// Get active mentoring rounds
$stmt = $pdo->query("
    SELECT * FROM mentoring_rounds 
    WHERE NOW() BETWEEN start_time AND end_time 
    ORDER BY start_time ASC
");
$active_rounds = $stmt->fetchAll();

// Get specific team if requested
$selected_team = null;
if (isset($_GET['team_id'])) {
    $team_id = $_GET['team_id'];
    foreach ($teams as $team) {
        if ($team['id'] == $team_id) {
            $selected_team = $team;
            break;
        }
    }
}

// Get existing scores for selected team
$existing_scores = [];
if ($selected_team) {
    $stmt = $pdo->prepare("
        SELECT s.*, mr.round_name, mr.max_score 
        FROM scores s 
        JOIN mentoring_rounds mr ON s.round_id = mr.id 
        WHERE s.mentor_id = ? AND s.team_id = ?
        ORDER BY mr.start_time DESC
    ");
    $stmt->execute([$user['id'], $selected_team['id']]);
    $existing_scores = $stmt->fetchAll();
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
    <title>Score Teams - HackMate</title>
    
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
                margin-left: 16rem !important; /* 64 * 0.25rem = 16rem */
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
        
        .score-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .score-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .team-item {
            transition: all 0.2s ease;
        }
        
        .team-item:hover {
            transform: translateX(4px);
        }
        
        .team-item.selected {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-color: #3b82f6;
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
                            <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-star text-white text-sm"></i>
                            </div>
                            <div>
                                <h1 class="text-xl font-bold text-gray-900">Score Teams</h1>
                                <p class="text-sm text-gray-500 hidden sm:block">Evaluate and score team performance</p>
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
                                <a href="schedule.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fas fa-calendar w-4 mr-2"></i>Schedule
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
                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-bold mb-2">Score Teams</h2>
                            <p class="text-blue-100">Evaluate team performance and provide feedback</p>
                            <?php if (!empty($active_rounds)): ?>
                                <div class="flex items-center mt-3 text-blue-100">
                                    <i class="fas fa-clock mr-2"></i>
                                    <span class="text-sm"><?php echo count($active_rounds); ?> active round<?php echo count($active_rounds) != 1 ? 's' : ''; ?> available</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="hidden md:block">
                            <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center backdrop-blur-sm">
                                <i class="fas fa-star text-3xl text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-400 rounded-xl p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                        <p class="text-green-700 font-medium"><?php echo $message; ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-400 rounded-xl p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                        <p class="text-red-700 font-medium"><?php echo $error; ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($active_rounds)): ?>
                <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border-l-4 border-yellow-400 rounded-xl p-6 mb-8">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-yellow-400 rounded-lg flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-white"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h4 class="text-lg font-medium text-yellow-800 mb-2">No Active Rounds</h4>
                            <p class="text-yellow-700 mb-3">No active mentoring rounds are available for scoring at this time.</p>
                            <a href="schedule.php" class="inline-flex items-center px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white font-medium rounded-lg transition-colors">
                                <i class="fas fa-calendar mr-2"></i>
                                View Schedule
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Search and Filter -->
            <div class="score-card rounded-2xl shadow-sm border border-gray-200 p-6 mb-8">
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
                        <a href="score_teams.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
        </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Team Selection -->
                <div class="lg:col-span-1">
                    <div class="score-card rounded-2xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-users text-blue-500 mr-2"></i>
                            Select Team (<?php echo count($teams); ?>)
                        </h3>
                    
                        <?php if (empty($teams)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-users text-gray-300 text-4xl mb-4"></i>
                                <h4 class="text-lg font-medium text-gray-900 mb-2">No Teams Found</h4>
                                <p class="text-gray-500 text-sm">No teams are assigned to your area or match your search criteria.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                <?php foreach ($teams as $team): ?>
                                    <a href="?team_id=<?php echo $team['id']; ?>&search=<?php echo urlencode($search); ?>&floor=<?php echo urlencode($floor_filter); ?>&room=<?php echo urlencode($room_filter); ?>" 
                                       class="team-item block p-4 border rounded-xl hover:bg-gray-50 transition-all duration-200 <?php echo ($selected_team && $selected_team['id'] == $team['id']) ? 'selected' : 'border-gray-200'; ?>">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <h4 class="font-semibold text-gray-900 mb-1"><?php echo htmlspecialchars($team['name']); ?></h4>
                                                <p class="text-sm text-gray-600 mb-1">
                                                    <i class="fas fa-user-tie mr-1"></i>
                                                    <?php echo htmlspecialchars($team['leader_name']); ?>
                                                </p>
                                                <div class="flex items-center justify-between text-xs text-gray-500">
                                                    <span>
                                                        <i class="fas fa-users mr-1"></i>
                                                        <?php echo $team['member_count']; ?> members
                                                    </span>
                                                    <span>
                                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                                        <?php echo $team['floor_number']; ?>-<?php echo $team['room_number']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <?php if ($selected_team && $selected_team['id'] == $team['id']): ?>
                                                <div class="ml-3">
                                                    <i class="fas fa-check-circle text-blue-500"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                </div>
            </div>

                <!-- Scoring Form -->
                <div class="lg:col-span-2">
                    <?php if ($selected_team): ?>
                        <div class="score-card rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                                <i class="fas fa-info-circle text-green-500 mr-2"></i>
                                Team Information: <?php echo htmlspecialchars($selected_team['name']); ?>
                            </h3>
                        
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-4">
                                    <div class="flex items-center mb-2">
                                        <i class="fas fa-user-tie text-blue-500 mr-2"></i>
                                        <p class="text-sm font-medium text-gray-700">Team Leader</p>
                                    </div>
                                    <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($selected_team['leader_name']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($selected_team['leader_email']); ?></p>
                                </div>
                                <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-4">
                                    <div class="flex items-center mb-2">
                                        <i class="fas fa-map-marker-alt text-green-500 mr-2"></i>
                                        <p class="text-sm font-medium text-gray-700">Location</p>
                                    </div>
                                    <p class="font-semibold text-gray-900"><?php echo $selected_team['floor_number']; ?> - <?php echo $selected_team['room_number']; ?></p>
                                    <p class="text-sm text-gray-600"><?php echo $selected_team['member_count']; ?> team members</p>
                                </div>
                            </div>
                            
                            <?php if ($selected_team['idea']): ?>
                                <div class="mb-4">
                                    <div class="flex items-center mb-2">
                                        <i class="fas fa-lightbulb text-yellow-500 mr-2"></i>
                                        <p class="text-sm font-medium text-gray-700">Project Idea</p>
                                    </div>
                                    <div class="bg-gradient-to-br from-yellow-50 to-orange-50 rounded-xl p-4">
                                        <p class="text-gray-900"><?php echo htmlspecialchars($selected_team['idea']); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($selected_team['problem_statement']): ?>
                                <div class="mb-4">
                                    <div class="flex items-center mb-2">
                                        <i class="fas fa-question-circle text-purple-500 mr-2"></i>
                                        <p class="text-sm font-medium text-gray-700">Problem Statement</p>
                                    </div>
                                    <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-4">
                                        <p class="text-gray-900"><?php echo htmlspecialchars($selected_team['problem_statement']); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                    </div>

                        <?php if (!empty($active_rounds)): ?>
                            <div class="score-card rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                                    <i class="fas fa-star text-orange-500 mr-2"></i>
                                    Submit Score
                                </h3>
                            
                                <form method="POST" class="space-y-6">
                                    <input type="hidden" name="team_id" value="<?php echo $selected_team['id']; ?>">
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fas fa-clock text-blue-500 mr-2"></i>
                                            Mentoring Round
                                        </label>
                                        <select name="round_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                            <option value="">Select a round</option>
                                            <?php foreach ($active_rounds as $round): ?>
                                                <option value="<?php echo $round['id']; ?>">
                                                    <?php echo htmlspecialchars($round['round_name']); ?> (Max: <?php echo $round['max_score']; ?> points)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fas fa-star text-yellow-500 mr-2"></i>
                                            Score
                                        </label>
                                        <input type="number" name="score" required min="0" 
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg font-semibold"
                                               placeholder="Enter score...">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fas fa-comment text-green-500 mr-2"></i>
                                            Comments & Feedback
                                        </label>
                                        <textarea name="comment" rows="4" 
                                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                  placeholder="Provide constructive feedback and comments for the team..."></textarea>
                                    </div>

                                    <button type="submit" 
                                            class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold py-3 px-6 rounded-lg transition-all duration-200 transform hover:scale-105">
                                        <i class="fas fa-save mr-2"></i>
                                        Submit Score
                                    </button>
                                </form>
                        </div>
                    <?php endif; ?>

                        <!-- Previous Scores -->
                        <?php if (!empty($existing_scores)): ?>
                            <div class="score-card rounded-2xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                                    <i class="fas fa-history text-gray-500 mr-2"></i>
                                    Your Previous Scores for This Team
                                </h3>
                            
                                <div class="space-y-4">
                                    <?php foreach ($existing_scores as $score): ?>
                                        <div class="border-l-4 border-blue-500 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-4">
                                            <div class="flex justify-between items-start mb-3">
                                                <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($score['round_name']); ?></h4>
                                                <div class="text-right">
                                                    <span class="text-2xl font-bold text-blue-600">
                                                        <?php echo $score['score']; ?>
                                                    </span>
                                                    <span class="text-sm text-gray-500">
                                                        /<?php echo $score['max_score']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <?php if ($score['comment']): ?>
                                                <div class="bg-white bg-opacity-50 rounded-lg p-3 mb-3">
                                                    <p class="text-gray-700 text-sm"><?php echo nl2br(htmlspecialchars($score['comment'])); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex items-center text-xs text-gray-500">
                                                <i class="fas fa-clock mr-1"></i>
                                                <span>Scored on <?php echo date('M j, Y g:i A', strtotime($score['created_at'])); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                        </div>
                    <?php endif; ?>
                    <?php else: ?>
                        <div class="score-card rounded-2xl shadow-sm border border-gray-200 p-6">
                            <div class="text-center py-12">
                                <i class="fas fa-arrow-left text-gray-300 text-5xl mb-4"></i>
                                <h3 class="text-xl font-semibold text-gray-900 mb-2">Select a Team</h3>
                                <p class="text-gray-500 mb-4">Choose a team from the left panel to start scoring</p>
                                <div class="text-sm text-gray-400">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Team information and scoring form will appear here
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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

        // Auto-update max score when round is selected
        document.addEventListener('DOMContentLoaded', function() {
            const roundSelect = document.querySelector('select[name="round_id"]');
            const scoreInput = document.querySelector('input[name="score"]');
            
            if (roundSelect && scoreInput) {
                roundSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption.value) {
                        const maxScore = selectedOption.text.match(/Max: (\d+)/);
                        if (maxScore) {
                            scoreInput.setAttribute('max', maxScore[1]);
                            scoreInput.placeholder = `Enter score (0-${maxScore[1]})...`;
                        }
                    } else {
                        scoreInput.removeAttribute('max');
                        scoreInput.placeholder = 'Enter score...';
                    }
                });
            }
        });
    </script>
</body>
</html>
