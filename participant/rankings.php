<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('participant');
$user = getCurrentUser();

// Check if rankings are visible to participants
$stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'rankings_visible'");
$stmt->execute();
$rankings_visible = $stmt->fetchColumn() ?: '0';

if ($rankings_visible != '1') {
    // Rankings are not visible, show message
    $rankings_hidden = true;
    $team_rankings = [];
} else {
    $rankings_hidden = false;
    
    // Get team rankings with proper calculation
    $team_rankings = [];

    // First get all approved teams with their basic info
    $teams_query = "
        SELECT 
            t.id,
            t.name as team_name,
            t.idea,
            u.name as leader_name,
            f.floor_number,
            r.room_number
        FROM teams t
        LEFT JOIN users u ON t.leader_id = u.id
        LEFT JOIN floors f ON t.floor_id = f.id
        LEFT JOIN rooms r ON t.room_id = r.id
        WHERE t.status = 'approved'
        ORDER BY t.id
    ";

    $stmt = $pdo->query($teams_query);
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate scores for each team
    foreach ($teams as $team) {
        $team_id = $team['id'];
        
        // Get scores for this team
        $scores_query = "
            SELECT 
                s.score,
                s.round_id,
                mr.round_name,
                mr.max_score
            FROM scores s
            LEFT JOIN mentoring_rounds mr ON s.round_id = mr.id
            WHERE s.team_id = ?
            ORDER BY mr.start_time
        ";
        
        $stmt = $pdo->prepare($scores_query);
        $stmt->execute([$team_id]);
        $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($scores)) {
            // Calculate statistics
            $total_score = array_sum(array_column($scores, 'score'));
            $average_score = round($total_score / count($scores), 2);
            $rounds_participated = count(array_unique(array_column($scores, 'round_id')));
            $total_scores_received = count($scores);
            
            // Get unique round names
            $round_names = array_unique(array_column($scores, 'round_name'));
            $participated_rounds = implode(', ', $round_names);
            
            // Add to rankings array
            $team_rankings[] = [
                'id' => $team['id'],
                'team_name' => $team['team_name'],
                'idea' => $team['idea'],
                'leader_name' => $team['leader_name'],
                'floor_number' => $team['floor_number'],
                'room_number' => $team['room_number'],
                'rounds_participated' => $rounds_participated,
                'total_scores_received' => $total_scores_received,
                'average_score' => $average_score,
                'total_score' => $total_score,
                'participated_rounds' => $participated_rounds
            ];
        }
    }

    // Sort by average score (descending), then by total score (descending), then by team name
    usort($team_rankings, function($a, $b) {
        if ($a['average_score'] == $b['average_score']) {
            if ($a['total_score'] == $b['total_score']) {
                return strcmp($a['team_name'], $b['team_name']);
            }
            return $b['total_score'] <=> $a['total_score'];
        }
        return $b['average_score'] <=> $a['average_score'];
    });

    // Add rank numbers with proper tie handling
    $ranked_teams = [];
    $current_rank = 1;
    $previous_avg_score = null;
    $previous_total_score = null;

    foreach ($team_rankings as $index => $team) {
        // Check if this team has the same scores as the previous team
        if ($previous_avg_score !== null && 
            $team['average_score'] == $previous_avg_score && 
            $team['total_score'] == $previous_total_score) {
            // Same rank as previous team (tie)
            $team['rank'] = $current_rank;
        } else {
            // New rank - skip positions for tied teams
            $current_rank = $index + 1;
            $team['rank'] = $current_rank;
        }
        
        $previous_avg_score = $team['average_score'];
        $previous_total_score = $team['total_score'];
        $ranked_teams[] = $team;
    }
    $team_rankings = $ranked_teams;
}

// Get user's team information
$user_team = null;
$user_team_rank = null;
$stmt = $pdo->prepare("
    SELECT t.*, u.name as leader_name 
    FROM teams t 
    JOIN team_members tm ON t.id = tm.team_id 
    JOIN users u ON t.leader_id = u.id
    WHERE tm.user_id = ? AND t.status = 'approved'
");
$stmt->execute([$user['id']]);
$user_team = $stmt->fetch();

if ($user_team && !$rankings_hidden) {
    // Find user's team rank
    foreach ($team_rankings as $team) {
        if ($team['id'] == $user_team['id']) {
            $user_team_rank = $team['rank'];
            break;
        }
    }
}

// Get total number of mentoring rounds for context
$total_rounds = $pdo->query("SELECT COUNT(*) FROM mentoring_rounds")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Rankings - HackMate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
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
                    <h1 class="text-lg font-semibold text-gray-800">Team Rankings</h1>
                    <div class="w-6"></div> <!-- Spacer for centering -->
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto">
                <div class="max-w-7xl mx-auto py-6 px-4 lg:px-8">
                    <!-- Page Header -->
                    <div class="mb-6">
                        <div class="flex items-center space-x-3 mb-2">
                            <div class="w-10 h-10 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-xl flex items-center justify-center">
                                <i class="fas fa-trophy text-white text-lg"></i>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Team Rankings</h1>
                                <p class="text-gray-600">See how teams are performing in the hackathon</p>
                            </div>
                        </div>
                    </div>
                    <?php if ($rankings_hidden): ?>
                        <!-- Rankings Hidden Message -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-eye-slash text-3xl text-gray-400"></i>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900 mb-4">Rankings Not Available</h2>
                            <p class="text-gray-600 mb-4 max-w-md mx-auto">
                                Team rankings are currently not visible. The admin will make them available when ready.
                            </p>
                            <p class="text-sm text-gray-500 mb-8">
                                Check back later or contact the organizers for more information.
                            </p>
                            <a href="dashboard.php" class="bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl">
                                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Your Team Status -->
                        <?php if ($user_team): ?>
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                                <div class="flex items-center space-x-3 mb-6">
                                    <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-500 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-users text-white text-sm"></i>
                                    </div>
                                    <h3 class="text-xl font-semibold text-gray-900">Your Team Status</h3>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-6 rounded-xl text-center border border-blue-200">
                                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center mx-auto mb-3">
                                            <i class="fas fa-users text-white text-sm"></i>
                                        </div>
                                        <div class="text-lg font-bold text-blue-700 mb-1"><?php echo htmlspecialchars($user_team['name']); ?></div>
                                        <div class="text-sm text-blue-600">Your Team</div>
                                    </div>
                                    <div class="bg-gradient-to-br from-green-50 to-green-100 p-6 rounded-xl text-center border border-green-200">
                                        <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-green-600 rounded-lg flex items-center justify-center mx-auto mb-3">
                                            <i class="fas fa-crown text-white text-sm"></i>
                                        </div>
                                        <div class="text-lg font-bold text-green-700 mb-1"><?php echo htmlspecialchars($user_team['leader_name']); ?></div>
                                        <div class="text-sm text-green-600">Team Leader</div>
                                    </div>
                                    <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 p-6 rounded-xl text-center border border-yellow-200">
                                        <div class="w-10 h-10 bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-lg flex items-center justify-center mx-auto mb-3">
                                            <i class="fas fa-trophy text-white text-sm"></i>
                                        </div>
                                        <div class="text-lg font-bold text-yellow-700 mb-1">
                                            <?php echo $user_team_rank ? "#$user_team_rank" : 'Not Ranked'; ?>
                                        </div>
                                        <div class="text-sm text-yellow-600">Current Rank</div>
                                    </div>
                                    <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-6 rounded-xl text-center border border-purple-200">
                                        <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg flex items-center justify-center mx-auto mb-3">
                                            <i class="fas fa-chart-bar text-white text-sm"></i>
                                        </div>
                                        <div class="text-lg font-bold text-purple-700 mb-1"><?php echo count($team_rankings); ?></div>
                                        <div class="text-sm text-purple-600">Total Teams</div>
                                    </div>
                                </div>
                                
                                <?php if (!$user_team_rank): ?>
                                    <div class="mt-6 bg-gradient-to-r from-yellow-50 to-orange-50 border border-yellow-200 rounded-xl p-4">
                                        <div class="flex items-start space-x-3">
                                            <div class="w-8 h-8 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                                <i class="fas fa-info-circle text-white text-sm"></i>
                                            </div>
                                            <p class="text-sm text-yellow-800 leading-relaxed">
                                                Your team hasn't received any scores yet. Rankings will appear once mentors start scoring your team.
                                            </p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border border-yellow-200 rounded-xl p-6 mb-6">
                                <div class="flex items-start space-x-4">
                                    <div class="w-10 h-10 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-xl flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-exclamation-triangle text-white text-lg"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-semibold text-yellow-800 mb-1">No Team Found</h4>
                                        <p class="text-yellow-700 text-sm">
                                            You are not currently part of any team. Join a team to see your ranking!
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Rankings Overview -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                            <div class="flex items-center space-x-3 mb-6">
                                <div class="w-8 h-8 bg-gradient-to-br from-green-500 to-blue-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-chart-bar text-white text-sm"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-gray-900">Rankings Overview</h3>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-6 rounded-xl text-center border border-blue-200">
                                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-users text-white text-lg"></i>
                                    </div>
                                    <div class="text-2xl font-bold text-blue-700 mb-2"><?php echo count($team_rankings); ?></div>
                                    <div class="text-sm text-blue-600 font-medium">Teams Ranked</div>
                                </div>
                                <div class="bg-gradient-to-br from-green-50 to-green-100 p-6 rounded-xl text-center border border-green-200">
                                    <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 rounded-xl flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-clock text-white text-lg"></i>
                                    </div>
                                    <div class="text-2xl font-bold text-green-700 mb-2"><?php echo $total_rounds; ?></div>
                                    <div class="text-sm text-green-600 font-medium">Mentoring Rounds</div>
                                </div>
                                <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 p-6 rounded-xl text-center border border-yellow-200">
                                    <div class="w-12 h-12 bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-star text-white text-lg"></i>
                                    </div>
                                    <div class="text-2xl font-bold text-yellow-700 mb-2">
                                        <?php echo !empty($team_rankings) ? number_format($team_rankings[0]['average_score'], 2) : '0'; ?>
                                    </div>
                                    <div class="text-sm text-yellow-600 font-medium">Highest Average</div>
                                </div>
                            </div>
                        </div>

                        <!-- Team Rankings -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                            <div class="px-6 py-6 border-b border-gray-200">
                                <div class="flex items-center space-x-3 mb-3">
                                    <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-pink-500 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-list-ol text-white text-sm"></i>
                                    </div>
                                    <h3 class="text-xl font-semibold text-gray-900">Team Rankings</h3>
                                </div>
                                <p class="text-sm text-gray-600 leading-relaxed">
                                    Rankings based on average scores from all mentoring rounds
                                </p>
                            </div>
                
                            <?php if (empty($team_rankings)): ?>
                                <div class="px-6 py-12 text-center text-gray-500">
                                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                        <i class="fas fa-trophy text-3xl text-gray-400"></i>
                                    </div>
                                    <h4 class="text-lg font-semibold text-gray-700 mb-2">No Rankings Available</h4>
                                    <p class="text-gray-600 mb-2">No team rankings available yet.</p>
                                    <p class="text-sm text-gray-500">Rankings will appear once teams start receiving scores from mentors.</p>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                                            <tr>
                                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">
                                                    <i class="fas fa-trophy mr-2 text-yellow-500"></i>Rank
                                                </th>
                                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">
                                                    <i class="fas fa-users mr-2 text-blue-500"></i>Team
                                                </th>
                                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">
                                                    <i class="fas fa-crown mr-2 text-purple-500"></i>Leader
                                                </th>
                                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">
                                                    <i class="fas fa-map-marker-alt mr-2 text-red-500"></i>Location
                                                </th>
                                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">
                                                    <i class="fas fa-clock mr-2 text-green-500"></i>Rounds
                                                </th>
                                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">
                                                    <i class="fas fa-star mr-2 text-orange-500"></i>Average Score
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-100">
                                            <?php foreach ($team_rankings as $team): ?>
                                                <tr class="<?php echo ($user_team && $team['id'] == $user_team['id']) ? 'bg-gradient-to-r from-blue-50 to-purple-50 border-l-4 border-blue-500' : 'hover:bg-gray-50'; ?> transition-all duration-200">
                                                    <td class="px-6 py-5 whitespace-nowrap">
                                                        <div class="flex items-center space-x-3">
                                                            <?php if ($team['rank'] <= 3): ?>
                                                                <div class="relative">
                                                                    <div class="w-10 h-10 rounded-full flex items-center justify-center <?php echo $team['rank'] == 1 ? 'bg-gradient-to-br from-yellow-400 to-yellow-600' : ($team['rank'] == 2 ? 'bg-gradient-to-br from-gray-300 to-gray-500' : 'bg-gradient-to-br from-yellow-600 to-orange-600'); ?>">
                                                                        <i class="fas fa-medal text-white text-sm"></i>
                                                                    </div>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="w-10 h-10 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center">
                                                                    <span class="text-sm font-bold text-gray-600"><?php echo $team['rank']; ?></span>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <span class="text-lg font-bold text-gray-900">#<?php echo $team['rank']; ?></span>
                                                                <?php if ($user_team && $team['id'] == $user_team['id']): ?>
                                                                    <span class="ml-2 bg-gradient-to-r from-blue-500 to-purple-500 text-white text-xs font-bold px-3 py-1 rounded-full">Your Team</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-5">
                                                        <div class="flex items-start space-x-3">
                                                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                                                <i class="fas fa-users text-white text-sm"></i>
                                                            </div>
                                                            <div>
                                                                <div class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($team['team_name']); ?></div>
                                                                <?php if ($team['idea']): ?>
                                                                    <div class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars(truncateText($team['idea'], 50)); ?></div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-5 whitespace-nowrap">
                                                        <div class="flex items-center space-x-2">
                                                            <div class="w-8 h-8 bg-gradient-to-br from-green-500 to-blue-500 rounded-full flex items-center justify-center">
                                                                <i class="fas fa-crown text-white text-xs"></i>
                                                            </div>
                                                            <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($team['leader_name']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-5 whitespace-nowrap">
                                                        <div class="flex items-center space-x-2">
                                                            <div class="w-8 h-8 bg-gradient-to-br from-red-500 to-pink-500 rounded-full flex items-center justify-center">
                                                                <i class="fas fa-map-marker-alt text-white text-xs"></i>
                                                            </div>
                                                            <span class="text-sm text-gray-700">Floor <?php echo $team['floor_number']; ?> - Room <?php echo $team['room_number']; ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-5 whitespace-nowrap">
                                                        <div class="text-center">
                                                            <div class="text-sm font-bold text-gray-900"><?php echo $team['rounds_participated']; ?>/<?php echo $total_rounds; ?></div>
                                                            <div class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full mt-1"><?php echo $team['total_scores_received']; ?> scores</div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-5 whitespace-nowrap text-center">
                                                        <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-orange-400 to-red-500 rounded-xl">
                                                            <span class="text-lg font-bold text-white"><?php echo $team['average_score']; ?></span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Information Section -->
                        <div class="mt-6 bg-gradient-to-r from-blue-50 to-purple-50 border border-blue-200 rounded-xl p-6">
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-500 rounded-xl flex items-center justify-center">
                                        <i class="fas fa-info-circle text-white text-lg"></i>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-sm font-semibold text-gray-900 mb-3">How Rankings Work</h3>
                                    <div class="text-sm text-gray-700 space-y-2">
                                        <div class="flex items-start space-x-2">
                                            <i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0"></i>
                                            <span>Rankings are based on the average score across all mentoring rounds</span>
                                        </div>
                                        <div class="flex items-start space-x-2">
                                            <i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0"></i>
                                            <span>Each mentor gives scores during different mentoring rounds</span>
                                        </div>
                                        <div class="flex items-start space-x-2">
                                            <i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0"></i>
                                            <span>Teams with higher average scores rank higher</span>
                                        </div>
                                        <div class="flex items-start space-x-2">
                                            <i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0"></i>
                                            <span>In case of ties, total score is used as a tiebreaker</span>
                                        </div>
                                        <div class="flex items-start space-x-2">
                                            <i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0"></i>
                                            <span>Only teams that have received at least one score are ranked</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>