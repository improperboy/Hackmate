<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('mentor');
$user = getCurrentUser();

// Get mentor's assignment information
$stmt = $pdo->prepare("
    SELECT ma.*, f.floor_number, r.room_number
    FROM mentor_assignments ma
    LEFT JOIN floors f ON ma.floor_id = f.id
    LEFT JOIN rooms r ON ma.room_id = r.id
    WHERE ma.mentor_id = ?
");
$stmt->execute([$user['id']]);
$mentor_assignment = $stmt->fetch();

// Get all scoring history for this mentor
$scoring_history = [];
if ($mentor_assignment) {
    $stmt = $pdo->prepare("
        SELECT s.*, t.name as team_name, mr.round_name, s.created_at as scored_at,
               s.score as total_score, s.comment
        FROM scores s
        JOIN teams t ON s.team_id = t.id
        JOIN mentoring_rounds mr ON s.round_id = mr.id
        WHERE s.mentor_id = ? AND t.floor_id = ? AND t.room_id = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$user['id'], $mentor_assignment['floor_id'], $mentor_assignment['room_id']]);
    $scoring_history = $stmt->fetchAll();
}

// Calculate statistics
$total_scores = count($scoring_history);
$average_total = $total_scores > 0 ? array_sum(array_column($scoring_history, 'total_score')) / $total_scores : 0;
$highest_score = $total_scores > 0 ? max(array_column($scoring_history, 'total_score')) : 0;
$lowest_score = $total_scores > 0 ? min(array_column($scoring_history, 'total_score')) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scoring History - <?php echo htmlspecialchars(getSystemSetting('hackathon_name', 'HackMate')); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-50 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm border-b border-gray-200 lg:hidden">
                <div class="flex items-center justify-between h-16 px-4">
                    <button onclick="toggleSidebar()" class="text-gray-600 hover:text-gray-900 focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-semibold text-gray-900">Scoring History</h1>
                    <div class="w-8"></div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto">
                <div class="p-6">
                    <!-- Page Header -->
                    <div class="mb-8">
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Scoring History</h1>
                                <p class="text-gray-600 mt-1">Review all your scoring activities and performance metrics</p>
                            </div>
                
                        </div>
                    </div>

                    <?php if (!$mentor_assignment): ?>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-6">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-yellow-500 mr-3"></i>
                                <div>
                                    <h3 class="font-semibold text-yellow-900">No Assignment</h3>
                                    <p class="text-yellow-700">You haven't been assigned to any teams yet.</p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Statistics Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                            <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-600">Total Scores</p>
                                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $total_scores; ?></p>
                                    </div>
                                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-star text-blue-600"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-600">Average Score</p>
                                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo round($average_total, 1); ?></p>
                                    </div>
                                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-chart-line text-green-600"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-600">Highest Score</p>
                                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $highest_score; ?></p>
                                    </div>
                                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-trophy text-yellow-600"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-600">Lowest Score</p>
                                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $lowest_score; ?></p>
                                    </div>
                                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-chart-bar text-red-600"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Scoring History Table -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900">All Scores</h3>
                            </div>
                            
                            <?php if (count($scoring_history) > 0): ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Team</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Round</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Innovation</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Technical</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Presentation</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teamwork</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($scoring_history as $score): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($score['team_name']); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($score['round_name']); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900">-</div>
                                                        <div class="text-xs text-gray-500">Not tracked</div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900">-</div>
                                                        <div class="text-xs text-gray-500">Not tracked</div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900">-</div>
                                                        <div class="text-xs text-gray-500">Not tracked</div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900">-</div>
                                                        <div class="text-xs text-gray-500">Not tracked</div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-lg font-bold text-green-600"><?php echo $score['total_score']; ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-500"><?php echo formatDateTime($score['scored_at']); ?></div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="px-6 py-12 text-center">
                                    <i class="fas fa-star text-gray-300 text-4xl mb-4"></i>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Scores Yet</h3>
                                    <p class="text-gray-500">You haven't scored any teams yet. Start scoring teams during active mentoring rounds.</p>
                                    <div class="mt-6">
                                        <a href="score_teams.php" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">
                                            Score Teams
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>