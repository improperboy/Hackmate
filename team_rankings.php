<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('admin');
$user = getCurrentUser();

// Get counts for sidebar notifications
$pending_teams = $pdo->query("SELECT COUNT(*) FROM teams WHERE status = 'pending'")->fetchColumn();
$open_support_requests = $pdo->query("SELECT COUNT(*) FROM support_messages WHERE status = 'open'")->fetchColumn();
$total_submissions = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();

$message = '';
$error = '';

// Handle ranking visibility toggle
if ($_POST && isset($_POST['toggle_visibility'])) {
    $visible = isset($_POST['rankings_visible']) ? 1 : 0;
    
    try {
        // Check if setting exists
        $stmt = $pdo->prepare("SELECT id FROM system_settings WHERE setting_key = 'rankings_visible'");
        $stmt->execute();
        
        if ($stmt->fetch()) {
            // Update existing setting
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = 'rankings_visible'");
            $stmt->execute([$visible]);
        } else {
            // Insert new setting
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES ('rankings_visible', ?, 'boolean', 'Whether team rankings are visible to participants', true)");
            $stmt->execute([$visible]);
        }
        
        $message = $visible ? 'Team rankings are now visible to participants!' : 'Team rankings are now hidden from participants.';
    } catch (PDOException $e) {
        $error = 'Failed to update ranking visibility: ' . $e->getMessage();
    }
}

// Get current ranking visibility setting
$stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'rankings_visible'");
$stmt->execute();
$rankings_visible = $stmt->fetchColumn() ?: '0';

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
            'participated_rounds' => $participated_rounds,
            'scores_detail' => $scores
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

// Add rank numbers
$ranked_teams = [];
foreach ($team_rankings as $index => $team) {
    $team['rank'] = $index + 1;
    $ranked_teams[] = $team;
}
$team_rankings = $ranked_teams;

// Get total number of mentoring rounds for context
$total_rounds = $pdo->query("SELECT COUNT(*) FROM mentoring_rounds")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Rankings - HackMate</title>
    
    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .ranking-card {
            transition: transform 0.2s ease-in-out;
        }
        .ranking-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden lg:ml-0">
            <!-- Top Header -->
            <header class="bg-white shadow-sm border-b border-gray-200 lg:hidden">
                <div class="flex items-center justify-between px-4 py-3">
                    <button onclick="toggleSidebar()" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-semibold text-gray-900">Rankings</h1>
                    <div class="w-6"></div>
                </div>
            </header>
            
            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- Page Header -->
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">
                                <i class="fas fa-trophy text-blue-600 mr-3"></i>
                                Team Rankings
                            </h1>
                            <p class="text-gray-600 mt-1">View and manage team performance rankings</p>
                        </div>
                    </div>
                </div>

                <div class="max-w-7xl mx-auto">
                    <!-- Messages -->
                    <?php if ($message): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Ranking Visibility Control -->
                    <div class="ranking-card bg-white rounded-xl shadow-sm p-6 border border-gray-100 mb-6">
                        <h3 class="text-lg font-semibold mb-4">
                            <i class="fas fa-eye text-blue-600 mr-2"></i>
                            Ranking Visibility Control
                        </h3>
                
                        <form method="POST" class="flex items-center space-x-4">
                            <div class="flex items-center">
                                <input type="checkbox" id="rankings_visible" name="rankings_visible" 
                                       <?php echo $rankings_visible == '1' ? 'checked' : ''; ?>
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="rankings_visible" class="ml-2 text-sm font-medium text-gray-700">
                                    Make rankings visible to participants
                                </label>
                            </div>
                            <button type="submit" name="toggle_visibility" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>Update Visibility
                            </button>
                        </form>
                        
                        <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600">
                                <i class="fas fa-info-circle mr-2"></i>
                                Current Status: Rankings are 
                                <span class="font-semibold <?php echo $rankings_visible == '1' ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo $rankings_visible == '1' ? 'VISIBLE' : 'HIDDEN'; ?>
                                </span> 
                                to participants.
                            </p>
                            <?php if ($rankings_visible == '1'): ?>
                                <p class="text-sm text-gray-600 mt-1">
                                    Participants can view rankings at: <a href="../participant/rankings.php" class="text-blue-600 hover:underline" target="_blank">../participant/rankings.php</a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Rankings Overview -->
                    <div class="ranking-card bg-white rounded-xl shadow-sm p-6 border border-gray-100 mb-6">
                        <h3 class="text-lg font-semibold mb-4">
                            <i class="fas fa-chart-bar text-green-600 mr-2"></i>
                            Rankings Overview
                        </h3>
                
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600"><?php echo count($team_rankings); ?></div>
                                <div class="text-sm text-gray-600">Teams with Scores</div>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-green-600"><?php echo $total_rounds; ?></div>
                                <div class="text-sm text-gray-600">Total Mentoring Rounds</div>
                            </div>
                            <div class="bg-yellow-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-yellow-600">
                                    <?php echo !empty($team_rankings) ? number_format($team_rankings[0]['average_score'], 2) : '0'; ?>
                                </div>
                                <div class="text-sm text-gray-600">Highest Average Score</div>
                            </div>
                            <div class="bg-purple-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-purple-600">
                                    <?php 
                                    $total_scores = 0;
                                    foreach ($team_rankings as $team) {
                                        $total_scores += $team['total_scores_received'];
                                    }
                                    echo $total_scores;
                                    ?>
                                </div>
                                <div class="text-sm text-gray-600">Total Scores Given</div>
                            </div>
                        </div>
                    </div>

                    <!-- Team Rankings Table -->
                    <div class="ranking-card bg-white rounded-xl shadow-sm border border-gray-100">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold">
                                <i class="fas fa-list-ol text-gray-600 mr-2"></i>
                                Team Rankings (<?php echo count($team_rankings); ?> teams)
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">
                                Rankings based on average scores across all mentoring rounds
                            </p>
                        </div>
                
                        <?php if (empty($team_rankings)): ?>
                            <div class="px-6 py-8 text-center text-gray-500">
                                <i class="fas fa-trophy text-4xl mb-4"></i>
                                <p>No team rankings available yet.</p>
                                <p class="text-sm mt-2">Teams will appear here once they receive scores from mentors.</p>
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
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Score</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Score</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
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
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <button onclick="showTeamDetails(<?php echo $team['id']; ?>)" 
                                                            class="text-blue-600 hover:text-blue-900">
                                                        <i class="fas fa-eye mr-1"></i>Details
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Team Details Modal -->
    <div id="teamDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900" id="modalTeamName">Team Details</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="modalContent" class="mt-2">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTeamDetails(teamId) {
            const team = <?php echo json_encode($team_rankings); ?>.find(t => t.id == teamId);
            
            if (!team) return;
            
            document.getElementById('modalTeamName').textContent = `${team.team_name} - Rank #${team.rank}`;
            
            let content = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Team Leader:</p>
                            <p class="text-gray-900">${team.leader_name}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Location:</p>
                            <p class="text-gray-900">${team.floor_number} - ${team.room_number}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Average Score:</p>
                            <p class="text-lg font-bold text-blue-600">${team.average_score}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Score:</p>
                            <p class="text-gray-900">${team.total_score}</p>
                        </div>
                    </div>
            `;
            
            if (team.idea) {
                content += `
                    <div>
                        <p class="text-sm font-medium text-gray-600">Project Idea:</p>
                        <p class="text-gray-900 bg-gray-50 p-3 rounded">${team.idea}</p>
                    </div>
                `;
            }
            
            if (team.scores_detail && team.scores_detail.length > 0) {
                content += `
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-3">Detailed Scores by Round:</p>
                        <div class="space-y-2">
                `;
                
                // Group scores by round
                const scoresByRound = {};
                team.scores_detail.forEach(score => {
                    if (!scoresByRound[score.round_name]) {
                        scoresByRound[score.round_name] = [];
                    }
                    scoresByRound[score.round_name].push(score);
                });
                
                Object.keys(scoresByRound).forEach(roundName => {
                    const roundScores = scoresByRound[roundName];
                    const roundAvg = (roundScores.reduce((sum, s) => sum + parseFloat(s.score), 0) / roundScores.length).toFixed(2);
                    const maxScore = roundScores[0].max_score;
                    
                    content += `
                        <div class="border-l-4 border-blue-400 bg-blue-50 p-3">
                            <div class="flex justify-between items-center mb-2">
                                <h4 class="font-medium text-gray-900">${roundName}</h4>
                                <span class="text-lg font-bold text-blue-600">${roundAvg}/${maxScore}</span>
                            </div>
                            <p class="text-sm text-gray-600">
                                <strong>Individual Scores:</strong> ${roundScores.map(s => s.score).join(', ')}
                            </p>
                        </div>
                    `;
                });
                
                content += `
                        </div>
                    </div>
                `;
            }
            
            content += `</div>`;
            
            document.getElementById('modalContent').innerHTML = content;
            document.getElementById('teamDetailsModal').classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('teamDetailsModal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        document.getElementById('teamDetailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>