<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('mentor');
$user = getCurrentUser();

// Check if rankings are visible to participants (mentors can see when participants can see)
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

// Get mentor's scoring statistics
$mentor_stats = [];
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT s.team_id) as teams_scored,
        COUNT(s.id) as total_scores_given,
        ROUND(AVG(s.score), 2) as avg_score_given,
        COUNT(DISTINCT s.round_id) as rounds_participated
    FROM scores s 
    WHERE s.mentor_id = ?
");
$stmt->execute([$user['id']]);
$mentor_stats = $stmt->fetch();

// Get total number of mentoring rounds for context
$total_rounds = $pdo->query("SELECT COUNT(*) FROM mentoring_rounds")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Rankings - HackMate Mentor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-arrow-left"></i>
                        <span class="hidden md:inline ml-1">Back</span>
                    </a>
                    <h1 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-trophy text-yellow-600"></i>
                        Team Rankings
                    </h1>
                    <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">Mentor View</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Welcome, <?php echo $user['name']; ?></span>
                    <a href="../logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4">
        <?php if ($rankings_hidden): ?>
            <!-- Rankings Hidden Message -->
            <div class="bg-white rounded-lg shadow p-8 text-center">
                <i class="fas fa-eye-slash text-6xl text-gray-400 mb-4"></i>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Rankings Not Available</h2>
                <p class="text-gray-600 mb-4">
                    Team rankings are currently not visible. The admin will make them available when ready.
                </p>
                <p class="text-sm text-gray-500">
                    As a mentor, you can view rankings when they are made visible to participants.
                </p>
                <div class="mt-6">
                    <a href="dashboard.php" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Mentor Statistics -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">
                    <i class="fas fa-user-tie text-green-600"></i>
                    Your Mentoring Statistics
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-blue-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-blue-600"><?php echo $mentor_stats['teams_scored'] ?: 0; ?></div>
                        <div class="text-sm text-gray-600">Teams Scored</div>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-green-600"><?php echo $mentor_stats['total_scores_given'] ?: 0; ?></div>
                        <div class="text-sm text-gray-600">Total Scores Given</div>
                    </div>
                    <div class="bg-yellow-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-yellow-600"><?php echo $mentor_stats['avg_score_given'] ?: '0.00'; ?></div>
                        <div class="text-sm text-gray-600">Average Score Given</div>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-purple-600"><?php echo $mentor_stats['rounds_participated'] ?: 0; ?>/<?php echo $total_rounds; ?></div>
                        <div class="text-sm text-gray-600">Rounds Participated</div>
                    </div>
                </div>
            </div>

            <!-- Rankings Overview -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">
                    <i class="fas fa-chart-bar text-green-600"></i>
                    Rankings Overview
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-blue-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-blue-600"><?php echo count($team_rankings); ?></div>
                        <div class="text-sm text-gray-600">Teams Ranked</div>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-green-600"><?php echo $total_rounds; ?></div>
                        <div class="text-sm text-gray-600">Mentoring Rounds</div>
                    </div>
                    <div class="bg-yellow-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-yellow-600">
                            <?php echo !empty($team_rankings) ? number_format($team_rankings[0]['average_score'], 2) : '0'; ?>
                        </div>
                        <div class="text-sm text-gray-600">Highest Average</div>
                    </div>
                </div>
            </div>

            <!-- Team Rankings -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold">
                        <i class="fas fa-list-ol text-gray-600"></i>
                        Team Rankings
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Rankings based on average scores from all mentoring rounds
                    </p>
                </div>
                
                <?php if (empty($team_rankings)): ?>
                    <div class="px-6 py-8 text-center text-gray-500">
                        <i class="fas fa-trophy text-4xl mb-4"></i>
                        <p>No team rankings available yet.</p>
                        <p class="text-sm mt-2">Rankings will appear once teams start receiving scores from mentors.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Team</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leader</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rounds</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average Score</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Score</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($team_rankings as $team): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <?php if ($team['rank'] <= 3): ?>
                                                    <i class="fas fa-medal text-2xl mr-2 
                                                        <?php echo $team['rank'] == 1 ? 'text-yellow-500' : ($team['rank'] == 2 ? 'text-gray-400' : 'text-yellow-600'); ?>"></i>
                                                <?php endif; ?>
                                                <span class="text-lg font-bold text-gray-900">#<?php echo $team['rank']; ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($team['team_name']); ?></div>
                                            <?php if ($team['idea']): ?>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars(truncateText($team['idea'], 50)); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($team['leader_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $team['floor_number']; ?> - <?php echo $team['room_number']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $team['rounds_participated']; ?>/<?php echo $total_rounds; ?>
                                            <div class="text-xs text-gray-400"><?php echo $team['total_scores_received']; ?> scores</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-lg font-bold text-blue-600"><?php echo $team['average_score']; ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo $team['total_score']; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Information Section -->
            <div class="mt-6 bg-green-50 border-l-4 border-green-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-green-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800">Mentor Information</h3>
                        <div class="mt-2 text-sm text-green-700">
                            <ul class="list-disc list-inside space-y-1">
                                <li>As a mentor, you can view team rankings when they are made visible by the admin</li>
                                <li>Rankings are based on the average score across all mentoring rounds</li>
                                <li>Teams with identical average and total scores receive the same rank</li>
                                <li>Your scoring statistics are shown above to track your mentoring activity</li>
                                <li>Continue scoring teams to help maintain accurate rankings</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>