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

// Get team progress data
$team_progress = [];
if ($mentor_assignment) {
    $stmt = $pdo->prepare("
        SELECT t.*, u.name as leader_name, th.name as theme_name, th.color_code as theme_color,
               COUNT(tm.user_id) as member_count,
               AVG(sc.score) as avg_score,
               COUNT(sc.id) as score_count,
               MAX(sc.created_at) as last_scored,
               s.id as has_submission
        FROM teams t 
        LEFT JOIN users u ON t.leader_id = u.id
        LEFT JOIN themes th ON t.theme_id = th.id
        LEFT JOIN team_members tm ON t.id = tm.team_id
        LEFT JOIN scores sc ON t.id = sc.team_id
        LEFT JOIN submissions s ON t.id = s.team_id
        WHERE t.floor_id = ? AND t.room_id = ? AND t.status = 'approved'
        GROUP BY t.id
        ORDER BY t.name ASC
    ");
    $stmt->execute([$mentor_assignment['floor_id'], $mentor_assignment['room_id']]);
    $team_progress = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Progress - <?php echo htmlspecialchars(getSystemSetting('hackathon_name', 'HackMate')); ?></title>
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
                    <h1 class="text-lg font-semibold text-gray-900">Team Progress</h1>
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
                                <h1 class="text-2xl font-bold text-gray-900">Team Progress</h1>
                                <p class="text-gray-600 mt-1">Monitor the progress and performance of your assigned teams</p>
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
                    <?php elseif (count($team_progress) == 0): ?>
                        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-6">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-blue-500 mr-3"></i>
                                <div>
                                    <h3 class="font-semibold text-blue-900">No Teams Yet</h3>
                                    <p class="text-blue-700">No teams have been assigned to your location yet.</p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Assignment Info -->
                        <div class="bg-green-50 border border-green-200 rounded-2xl p-6 mb-8">
                            <div class="flex items-center">
                                <i class="fas fa-map-marker-alt text-green-500 mr-3"></i>
                                <div>
                                    <h3 class="font-semibold text-green-900">Your Assignment</h3>
                                    <p class="text-green-700">Floor <?php echo $mentor_assignment['floor_number']; ?>, Room <?php echo $mentor_assignment['room_number']; ?> • <?php echo count($team_progress); ?> team<?php echo count($team_progress) != 1 ? 's' : ''; ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Team Progress Cards -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <?php foreach ($team_progress as $team): ?>
                                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                                    <!-- Team Header -->
                                    <div class="flex items-center justify-between mb-6">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background-color: <?php echo $team['theme_color'] ?? '#6b7280'; ?>20;">
                                                <i class="fas fa-users text-xl" style="color: <?php echo $team['theme_color'] ?? '#6b7280'; ?>"></i>
                                            </div>
                                            <div>
                                                <h3 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($team['name']); ?></h3>
                                                <p class="text-sm text-gray-600">Leader: <?php echo htmlspecialchars($team['leader_name']); ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <a href="score_teams.php?team_id=<?php echo $team['id']; ?>" class="text-green-600 hover:text-green-700 p-2 rounded-lg hover:bg-green-50 transition-colors" title="Score Team">
                                                <i class="fas fa-star"></i>
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Progress Metrics -->
                                    <div class="grid grid-cols-2 gap-4 mb-6">
                                        <div class="text-center p-3 bg-gray-50 rounded-lg">
                                            <div class="text-2xl font-bold text-gray-900"><?php echo $team['member_count']; ?></div>
                                            <div class="text-xs text-gray-600">Members</div>
                                        </div>
                                        <div class="text-center p-3 bg-gray-50 rounded-lg">
                                            <div class="text-2xl font-bold text-gray-900"><?php echo $team['score_count']; ?></div>
                                            <div class="text-xs text-gray-600">Scores</div>
                                        </div>
                                    </div>

                                    <!-- Average Score -->
                                    <?php if ($team['avg_score']): ?>
                                        <div class="mb-4">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-sm font-medium text-gray-700">Average Score</span>
                                                <span class="text-sm font-bold text-gray-900"><?php echo round($team['avg_score'], 1); ?>/100</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo round($team['avg_score']); ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Status Indicators -->
                                    <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                                        <div class="flex items-center space-x-4">
                                            <!-- Theme -->
                                            <?php if ($team['theme_name']): ?>
                                                <div class="flex items-center">
                                                    <span class="w-3 h-3 rounded-full mr-2" style="background-color: <?php echo $team['theme_color']; ?>"></span>
                                                    <span class="text-xs text-gray-600"><?php echo htmlspecialchars($team['theme_name']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="flex items-center space-x-2">
                                            <!-- Submission Status -->
                                            <?php if ($team['has_submission']): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <i class="fas fa-check mr-1"></i>
                                                    Submitted
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    Pending
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Last Activity -->
                                    <?php if ($team['last_scored']): ?>
                                        <div class="mt-3 text-xs text-gray-500">
                                            <i class="fas fa-clock mr-1"></i>
                                            Last scored: <?php echo timeAgo($team['last_scored']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Summary Statistics -->
                        <div class="mt-8 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">
                                <i class="fas fa-chart-bar text-blue-500 mr-2"></i>
                                Summary Statistics
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-blue-600"><?php echo count($team_progress); ?></div>
                                    <div class="text-sm text-gray-600">Total Teams</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-green-600">
                                        <?php echo array_sum(array_column($team_progress, 'member_count')); ?>
                                    </div>
                                    <div class="text-sm text-gray-600">Total Members</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-purple-600">
                                        <?php echo array_sum(array_column($team_progress, 'score_count')); ?>
                                    </div>
                                    <div class="text-sm text-gray-600">Total Scores</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-orange-600">
                                        <?php echo count(array_filter($team_progress, function($t) { return $t['has_submission']; })); ?>
                                    </div>
                                    <div class="text-sm text-gray-600">Submissions</div>
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