<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('participant');
$user = getCurrentUser();

// Check if user is already in a team
$stmt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE user_id = ?");
$stmt->execute([$user['id']]);
if ($stmt->fetchColumn() > 0) {
    header('Location: dashboard.php');
    exit();
}

$message = '';
$error = '';

// Handle join request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['team_id'])) {
    $team_id = $_POST['team_id'];
    $request_message = sanitize($_POST['message'] ?? '');
    
    // Verify team exists and is approved
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ? AND status = 'approved'");
    $stmt->execute([$team_id]);
    $team = $stmt->fetch();
    
    if (!$team) {
        $error = 'Invalid team selected';
    } else {
        // Check if team has space (max 4 members)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ?");
        $stmt->execute([$team_id]);
        $member_count = $stmt->fetchColumn();
        
        if ($member_count >= 4) {
            $error = 'Team is full (maximum 4 members)';
        } else {
            // Check if there's already a pending request to this specific team
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM join_requests WHERE user_id = ? AND team_id = ? AND status = 'pending'");
            $stmt->execute([$user['id'], $team_id]);
            $pending_request = $stmt->fetchColumn();
            
            if ($pending_request > 0) {
                $error = 'You already have a pending join request for this team. Wait for the team leader to respond.';
            } else {
                // Check for existing join requests to this team (max 3 allowed per team)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM join_requests WHERE user_id = ? AND team_id = ?");
                $stmt->execute([$user['id'], $team_id]);
                $request_count = $stmt->fetchColumn();
                
                if ($request_count >= 3) {
                    $error = 'You have reached the maximum limit of 3 join requests for this team.';
                } else {
                    // Create join request
                    try {
                        // Always create a new request since unique constraint has been removed
                        $stmt = $pdo->prepare("INSERT INTO join_requests (user_id, team_id, message) VALUES (?, ?, ?)");
                        if ($stmt->execute([$user['id'], $team_id, $request_message])) {
                            $message = 'Join request sent successfully to team leader: ' . htmlspecialchars($team['name']) . '. You can send requests to multiple teams.';
                            // Redirect after 3 seconds to give user time to read the message
                            echo "<script>setTimeout(function(){ window.location.href='dashboard.php'; }, 3000);</script>";
                        } else {
                            $error = 'Failed to send join request. Please try again.';
                            // Get detailed error info
                            $errorInfo = $stmt->errorInfo();
                            if (isset($errorInfo[2])) {
                                $error .= ' Error: ' . $errorInfo[2];
                            }
                        }
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$floor_filter = $_GET['floor'] ?? '';

// Get available teams (approved teams with less than 4 members)
$query = "
    SELECT t.*, u.name as leader_name, u.email as leader_email,
           f.floor_number, r.room_number,
           (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id) as member_count,
           (SELECT GROUP_CONCAT(u2.name SEPARATOR ', ') 
            FROM team_members tm2 
            JOIN users u2 ON tm2.user_id = u2.id 
            WHERE tm2.team_id = t.id) as members,
           (SELECT COUNT(*) FROM join_requests jr 
            WHERE jr.user_id = ? AND jr.team_id = t.id AND jr.status = 'pending') as has_pending_request
    FROM teams t 
    JOIN users u ON t.leader_id = u.id 
    LEFT JOIN floors f ON t.floor_id = f.id
    LEFT JOIN rooms r ON t.room_id = r.id
    WHERE t.status = 'approved'
";

$params = [$user['id']]; // Add user_id as first parameter for the subquery
if ($search) {
    $query .= " AND (t.name LIKE ? OR t.idea LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($floor_filter) {
    $query .= " AND f.floor_number = ?";
    $params[] = $floor_filter;
}

$query .= " HAVING member_count < 4 ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$available_teams = $stmt->fetchAll();

// Get floors for filter
$stmt = $pdo->query("SELECT DISTINCT floor_number FROM floors ORDER BY floor_number");
$floors = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Team - Participant Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden lg:ml-0">
            <!-- Mobile Header -->
            <header class="lg:hidden bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-4 py-3">
                    <button onclick="toggleSidebar()" class="text-gray-600 hover:text-gray-900 transition-colors">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-semibold text-gray-800">Join Team</h1>
                    <div class="w-6"></div> <!-- Spacer for centering -->
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto">
                <div class="max-w-7xl mx-auto py-6 px-4 lg:px-8">
                    <!-- Page Header -->
                    <div class="mb-6">
                        <div class="flex items-center space-x-3 mb-2">
                            <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-blue-500 rounded-xl flex items-center justify-center">
                                <i class="fas fa-user-plus text-white text-lg"></i>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Join Team</h1>
                                <p class="text-gray-600">Find and join existing teams that match your interests</p>
                            </div>
                        </div>
                    </div>
                    <!-- Messages -->
                    <?php if ($message): ?>
                        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl mb-6 flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            <span><?php echo $message; ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl mb-6 flex items-center">
                            <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                            <span><?php echo $error; ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Search and Filter -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <div class="flex items-center space-x-3 mb-6">
                            <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-search text-white text-sm"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900">Search & Filter Teams</h3>
                        </div>
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-3">
                                    <i class="fas fa-search mr-2 text-blue-500"></i>
                                    Search Teams
                                </label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by team name, idea, or leader..."
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-3">
                                    <i class="fas fa-building mr-2 text-green-500"></i>
                                    Floor
                                </label>
                                <select name="floor" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200 bg-white">
                                    <option value="">All Floors</option>
                                    <?php foreach ($floors as $floor): ?>
                                        <option value="<?php echo $floor['floor_number']; ?>" <?php echo $floor_filter == $floor['floor_number'] ? 'selected' : ''; ?>>
                                            Floor <?php echo $floor['floor_number']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex items-end gap-3">
                                <button type="submit" class="flex-1 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white px-6 py-3 rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl font-semibold">
                                    <i class="fas fa-search mr-2"></i>Search
                                </button>
                                <a href="join_team.php" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-xl transition-all duration-200 text-center border border-gray-300 font-semibold">
                                    <i class="fas fa-times mr-2"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Available Teams -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="px-6 py-6 border-b border-gray-200">
                            <div class="flex items-center space-x-3 mb-3">
                                <div class="w-8 h-8 bg-gradient-to-br from-green-500 to-blue-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-users text-white text-sm"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-gray-900">
                                    Available Teams (<?php echo count($available_teams); ?>)
                                </h3>
                            </div>
                            <p class="text-sm text-gray-600 leading-relaxed">Send join requests to multiple team leaders. You can send up to 3 requests per team. Teams can have maximum 4 members. If any team accepts your request, all other pending requests will be automatically cancelled.</p>
                        </div>
            
                        <?php if (empty($available_teams)): ?>
                            <div class="px-6 py-12 text-center text-gray-500">
                                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                    <i class="fas fa-users-slash text-3xl text-gray-400"></i>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-700 mb-2">No Teams Available</h4>
                                <p class="text-gray-600 mb-6">No teams are available to join at the moment.</p>
                                <a href="create_team.php" class="bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl">
                                    <i class="fas fa-plus mr-2"></i>
                                    Create Your Own Team
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 p-6">
                                <?php foreach ($available_teams as $team): ?>
                                    <div class="border border-gray-200 rounded-xl p-6 hover:shadow-lg hover:border-purple-200 transition-all duration-200 bg-gradient-to-br from-white to-gray-50">
                                        <div class="flex justify-between items-start mb-4">
                                            <h4 class="text-lg font-bold text-gray-900"><?php echo $team['name']; ?></h4>
                                            <span class="inline-flex px-3 py-1 text-xs font-bold rounded-full bg-gradient-to-r from-green-500 to-blue-500 text-white shadow-sm">
                                                <?php echo $team['member_count']; ?>/4 members
                                            </span>
                                        </div>
                            
                                        <!-- Team Leader -->
                                        <div class="mb-4 bg-white rounded-lg p-4 border border-gray-100">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-blue-500 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-crown text-white text-xs"></i>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-semibold text-gray-600">Team Leader</p>
                                                    <p class="text-gray-900 font-medium"><?php echo $team['leader_name']; ?></p>
                                                    <p class="text-sm text-gray-500"><?php echo $team['leader_email']; ?></p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Current Members -->
                                        <div class="mb-4 bg-white rounded-lg p-4 border border-gray-100">
                                            <div class="flex items-start space-x-3">
                                                <div class="w-8 h-8 bg-gradient-to-br from-green-500 to-blue-500 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                                    <i class="fas fa-users text-white text-xs"></i>
                                                </div>
                                                <div class="flex-1">
                                                    <p class="text-sm font-semibold text-gray-600 mb-1">Current Members</p>
                                                    <p class="text-gray-900 text-sm leading-relaxed"><?php echo $team['members']; ?></p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Location -->
                                        <?php if ($team['floor_number'] && $team['room_number']): ?>
                                            <div class="mb-4 bg-white rounded-lg p-4 border border-gray-100">
                                                <div class="flex items-center space-x-3">
                                                    <div class="w-8 h-8 bg-gradient-to-br from-orange-500 to-red-500 rounded-full flex items-center justify-center">
                                                        <i class="fas fa-map-marker-alt text-white text-xs"></i>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-semibold text-gray-600">Location</p>
                                                        <p class="text-gray-900 font-medium">Floor <?php echo $team['floor_number']; ?> - Room <?php echo $team['room_number']; ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                            
                                        <!-- Project Idea -->
                                        <?php if ($team['idea']): ?>
                                            <div class="mb-4">
                                                <div class="flex items-start space-x-3">
                                                    <div class="w-8 h-8 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                                        <i class="fas fa-lightbulb text-white text-xs"></i>
                                                    </div>
                                                    <div class="flex-1">
                                                        <p class="text-sm font-semibold text-gray-600 mb-2">Project Idea</p>
                                                        <p class="text-gray-700 text-sm bg-gradient-to-r from-yellow-50 to-orange-50 p-4 rounded-lg border border-yellow-200 leading-relaxed"><?php echo substr($team['idea'], 0, 150) . (strlen($team['idea']) > 150 ? '...' : ''); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Problem Statement -->
                                        <?php if ($team['problem_statement']): ?>
                                            <div class="mb-6">
                                                <div class="flex items-start space-x-3">
                                                    <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                                        <i class="fas fa-question-circle text-white text-xs"></i>
                                                    </div>
                                                    <div class="flex-1">
                                                        <p class="text-sm font-semibold text-gray-600 mb-2">Problem Statement</p>
                                                        <p class="text-gray-700 text-sm bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-lg border border-blue-200 leading-relaxed"><?php echo substr($team['problem_statement'], 0, 150) . (strlen($team['problem_statement']) > 150 ? '...' : ''); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                            
                                        <!-- Join Request Form or Status -->
                                        <?php if ($team['has_pending_request'] > 0): ?>
                                            <!-- Show Request Already Sent -->
                                            <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border border-yellow-200 rounded-xl p-4">
                                                <div class="flex items-center space-x-3">
                                                    <div class="w-8 h-8 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-full flex items-center justify-center">
                                                        <i class="fas fa-clock text-white text-xs"></i>
                                                    </div>
                                                    <div>
                                                        <span class="text-yellow-800 font-semibold">Request Already Sent</span>
                                                        <p class="text-yellow-700 text-sm mt-1">
                                                            You have a pending join request for this team. Please wait for the team leader to respond.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <!-- Show Join Request Form -->
                                            <form method="POST" class="space-y-4">
                                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                                
                                                <div>
                                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                                        <i class="fas fa-comment mr-2 text-purple-500"></i>
                                                        Message to Team Leader (Optional)
                                                    </label>
                                                    <textarea name="message" rows="3" 
                                                              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200 resize-none"
                                                              placeholder="Introduce yourself and explain why you want to join this team..."></textarea>
                                                </div>
                                                
                                                <button type="submit" 
                                                        class="w-full bg-gradient-to-r from-green-600 to-blue-600 hover:from-green-700 hover:to-blue-700 text-white font-semibold py-3 px-4 rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl"
                                                        onclick="return confirm('Send join request to team: <?php echo addslashes($team['name']); ?>?')">
                                                    <i class="fas fa-paper-plane mr-2"></i>
                                                    Send Join Request
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Info Box -->
                    <div class="bg-gradient-to-r from-blue-50 to-purple-50 border border-blue-200 rounded-xl p-6 mt-6">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-500 rounded-xl flex items-center justify-center">
                                    <i class="fas fa-info-circle text-white text-lg"></i>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-sm font-semibold text-gray-900 mb-2">Important Information</h4>
                                <p class="text-sm text-gray-700 leading-relaxed">
                                    <strong>Note:</strong> You can send join requests to multiple teams simultaneously. 
                                    You can send up to 3 requests per team if your initial request gets rejected.
                                    When any team leader accepts your request, all other pending requests will be automatically cancelled.
                                    If you can't find a suitable team, you can 
                                    <a href="create_team.php" class="underline font-semibold text-purple-600 hover:text-purple-800 transition-colors">create your own team</a>.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
