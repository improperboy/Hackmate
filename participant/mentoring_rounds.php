<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('participant');
$user = getCurrentUser();

// Check if scores should be visible to participants
$show_scores_to_participants = getSystemSetting('show_mentoring_scores_to_participants', false);

// Get user's team
$stmt = $pdo->prepare("
    SELECT t.*, u.name as leader_name, th.name as theme_name, th.color_code as theme_color, f.floor_number, r.room_number
    FROM teams t 
    JOIN team_members tm ON t.id = tm.team_id 
    LEFT JOIN users u ON t.leader_id = u.id
    LEFT JOIN themes th ON t.theme_id = th.id
    LEFT JOIN floors f ON t.floor_id = f.id
    LEFT JOIN rooms r ON t.room_id = r.id
    WHERE tm.user_id = ? AND t.status = 'approved'
");
$stmt->execute([$user['id']]);
$user_team = $stmt->fetch();

// Redirect if user is not part of a team
if (!$user_team) {
    header('Location: dashboard.php');
    exit();
}

// Get all mentoring rounds
$stmt = $pdo->query("
    SELECT * FROM mentoring_rounds 
    ORDER BY start_time ASC
");
$mentoring_rounds = $stmt->fetchAll();

// Get mentors assigned to team's location
$assigned_mentors = [];
if ($user_team['floor_id'] && $user_team['room_id']) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.tech_stack
        FROM mentor_assignments ma 
        JOIN users u ON ma.mentor_id = u.id 
        WHERE ma.floor_id = ? AND ma.room_id = ? AND u.role = 'mentor'
        ORDER BY u.name ASC
    ");
    $stmt->execute([$user_team['floor_id'], $user_team['room_id']]);
    $assigned_mentors = $stmt->fetchAll();
}

// Get scores for each round and mentor
$round_scores = [];
foreach ($mentoring_rounds as $round) {
    $stmt = $pdo->prepare("
        SELECT s.*, u.name as mentor_name, u.email as mentor_email
        FROM scores s
        JOIN users u ON s.mentor_id = u.id
        WHERE s.team_id = ? AND s.round_id = ?
        ORDER BY u.name ASC
    ");
    $stmt->execute([$user_team['id'], $round['id']]);
    $round_scores[$round['id']] = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentoring Rounds - <?php echo htmlspecialchars(getSystemSetting('hackathon_name', 'HackMate')); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8'
                        }
                    }
                }
            }
        }
    </script>
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
                    <h1 class="text-lg font-semibold text-gray-900">Mentoring Rounds</h1>
                    <div class="w-8"></div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto">
                <div class="p-6">
                    <!-- Header -->
                    <div class="mb-8">
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Mentoring Rounds</h1>
                                <p class="text-gray-600 mt-1">Track your team's progress through mentoring sessions</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500">Team: <?php echo htmlspecialchars($user_team['name']); ?></p>
                                <?php if ($user_team['floor_number'] && $user_team['room_number']): ?>
                                    <p class="text-sm text-gray-500">
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        Floor <?php echo $user_team['floor_number']; ?>, Room <?php echo $user_team['room_number']; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Assigned Mentors Section -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
                        <h3 class="text-xl font-bold text-gray-900 mb-6">
                            <i class="fas fa-user-tie text-blue-500 mr-2"></i>
                            Assigned Mentors
                        </h3>
                        
                        <?php if (empty($assigned_mentors)): ?>
                            <div class="text-center py-8">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-user-slash text-gray-400 text-xl"></i>
                                </div>
                                <h4 class="text-lg font-medium text-gray-900 mb-2">No Mentors Assigned</h4>
                                <p class="text-gray-600">No mentors have been assigned to your team's location yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php foreach ($assigned_mentors as $mentor): ?>
                                    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-4 border border-blue-200">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center">
                                                <span class="text-white font-semibold text-sm"><?php echo strtoupper(substr($mentor['name'], 0, 2)); ?></span>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($mentor['name']); ?></h4>
                                                <p class="text-sm text-gray-600 truncate"><?php echo htmlspecialchars($mentor['email']); ?></p>
                                            </div>
                                        </div>
                                        <?php if ($mentor['tech_stack']): ?>
                                            <div class="mt-3">
                                                <p class="text-xs text-gray-500 mb-1">Tech Stack:</p>
                                                <div class="flex flex-wrap gap-1">
                                                    <?php 
                                                    $tech_items = array_slice(explode(',', $mentor['tech_stack']), 0, 3);
                                                    foreach ($tech_items as $tech): 
                                                    ?>
                                                        <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                                                            <?php echo htmlspecialchars(trim($tech)); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                    <?php if (count(explode(',', $mentor['tech_stack'])) > 3): ?>
                                                        <span class="inline-block text-blue-600 text-xs px-2 py-1">
                                                            +<?php echo count(explode(',', $mentor['tech_stack'])) - 3; ?> more
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Mentoring Rounds -->
                    <?php if (empty($mentoring_rounds)): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-calendar-times text-gray-400 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No Mentoring Rounds</h3>
                            <p class="text-gray-600">No mentoring rounds have been scheduled yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-6">
                            <?php foreach ($mentoring_rounds as $round): ?>
                                <?php 
                                $round_start = new DateTime($round['start_time']);
                                $round_end = new DateTime($round['end_time']);
                                $now = new DateTime();
                                $is_active = $now >= $round_start && $now <= $round_end;
                                $is_upcoming = $now < $round_start;
                                $is_completed = $now > $round_end;
                                $scores = $round_scores[$round['id']] ?? [];
                                ?>
                                
                                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                                    <!-- Round Header -->
                                    <div class="p-6 border-b border-gray-100 <?php echo $is_active ? 'bg-gradient-to-r from-green-50 to-emerald-50' : ($is_upcoming ? 'bg-gradient-to-r from-blue-50 to-indigo-50' : 'bg-gray-50'); ?>">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-4">
                                                <div class="w-12 h-12 <?php echo $is_active ? 'bg-green-500' : ($is_upcoming ? 'bg-blue-500' : 'bg-gray-400'); ?> rounded-xl flex items-center justify-center">
                                                    <i class="fas <?php echo $is_active ? 'fa-play' : ($is_upcoming ? 'fa-clock' : 'fa-check'); ?> text-white"></i>
                                                </div>
                                                <div>
                                                    <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($round['round_name']); ?></h3>
                                                    <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($round['description']); ?></p>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $is_active ? 'bg-green-100 text-green-800' : ($is_upcoming ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'); ?>">
                                                    <?php echo $is_active ? 'Active' : ($is_upcoming ? 'Upcoming' : 'Completed'); ?>
                                                </div>
                                                <p class="text-sm text-gray-500 mt-1">Max Score: <?php echo $round['max_score']; ?></p>
                                            </div>
                                        </div>
                                        
                                        <!-- Time Information -->
                                        <div class="mt-4 flex items-center space-x-6 text-sm text-gray-600">
                                            <div class="flex items-center">
                                                <i class="fas fa-calendar-alt mr-2"></i>
                                                <span><?php echo $round_start->format('M j, Y'); ?></span>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-clock mr-2"></i>
                                                <span><?php echo $round_start->format('g:i A') . ' - ' . $round_end->format('g:i A'); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Scores Section -->
                                    <div class="p-6">
                                        <h4 class="text-lg font-semibold text-gray-900 mb-4">
                                            <i class="fas <?php echo $show_scores_to_participants ? 'fa-star' : 'fa-comment-alt'; ?> text-<?php echo $show_scores_to_participants ? 'yellow' : 'blue'; ?>-500 mr-2"></i>
                                            <?php echo $show_scores_to_participants ? 'Mentor Feedback & Scores' : 'Mentor Feedback & Status'; ?>
                                        </h4>

                                        <?php if (empty($scores)): ?>
                                            <div class="text-center py-8">
                                                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                                    <i class="fas fa-star-half-alt text-gray-400"></i>
                                                </div>
                                                <p class="text-gray-600">
                                                    <?php if ($is_upcoming): ?>
                                                        This round hasn't started yet.
                                                    <?php elseif ($is_active): ?>
                                                        No evaluations have been submitted yet for this round.
                                                    <?php else: ?>
                                                        No mentors evaluated your team for this round.
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        <?php else: ?>
                                            <!-- Show scored mentors -->
                                            <div class="space-y-4">
                                                <?php foreach ($scores as $score): ?>
                                                    <div class="bg-gradient-to-r from-gray-50 to-blue-50 rounded-xl p-4 border border-gray-200">
                                                        <div class="flex items-start justify-between">
                                                            <div class="flex items-center space-x-3">
                                                                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center">
                                                                    <span class="text-white font-semibold text-sm"><?php echo strtoupper(substr($score['mentor_name'], 0, 2)); ?></span>
                                                                </div>
                                                                <div>
                                                                    <h5 class="font-semibold text-gray-900"><?php echo htmlspecialchars($score['mentor_name']); ?></h5>
                                                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($score['mentor_email']); ?></p>
                                                                </div>
                                                            </div>
                                                            <div class="text-right">
                                                                <?php if ($show_scores_to_participants): ?>
                                                                    <div class="text-2xl font-bold text-blue-600">
                                                                        <?php echo $score['score']; ?><span class="text-sm text-gray-500">/ <?php echo $round['max_score']; ?></span>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                                                        <i class="fas fa-check mr-1"></i>
                                                                        Scored
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div class="text-sm text-gray-500">
                                                                    <?php echo date('M j, g:i A', strtotime($score['created_at'])); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if ($score['comment']): ?>
                                                            <div class="mt-4 pt-4 border-t border-gray-200">
                                                                <h6 class="text-sm font-medium text-gray-700 mb-2">
                                                                    <i class="fas fa-comment-alt mr-1"></i>
                                                                    Feedback:
                                                                </h6>
                                                                <p class="text-gray-700 bg-white rounded-lg p-3 border border-gray-200">
                                                                    <?php echo nl2br(htmlspecialchars($score['comment'])); ?>
                                                                </p>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                                
                                                <!-- Round Summary -->
                                                <?php if (count($scores) > 1): ?>
                                                    <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl p-4 border border-indigo-200 mt-4">
                                                        <div class="flex items-center justify-between">
                                                            <div>
                                                                <h6 class="font-semibold text-gray-900">Round Summary</h6>
                                                                <p class="text-sm text-gray-600">Based on <?php echo count($scores); ?> mentor evaluations</p>
                                                            </div>
                                                            <div class="text-right">
                                                                <?php if ($show_scores_to_participants): ?>
                                                                    <?php 
                                                                    $total_score = array_sum(array_column($scores, 'score'));
                                                                    $avg_score = round($total_score / count($scores), 1);
                                                                    $max_possible = $round['max_score'] * count($scores);
                                                                    ?>
                                                                    <div class="text-xl font-bold text-indigo-600">
                                                                        <?php echo $total_score; ?><span class="text-sm text-gray-500">/ <?php echo $max_possible; ?></span>
                                                                    </div>
                                                                    <div class="text-sm text-gray-600">
                                                                        Avg: <?php echo $avg_score; ?>/<?php echo $round['max_score']; ?>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                                                        <i class="fas fa-check-circle mr-1"></i>
                                                                        <?php echo count($scores); ?> Evaluations Complete
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Show mentors who haven't scored yet -->
                                            <?php if (!empty($assigned_mentors) && !$is_upcoming): ?>
                                                <?php 
                                                $scored_mentor_ids = array_column($scores, 'mentor_id');
                                                $unscored_mentors = array_filter($assigned_mentors, function($mentor) use ($scored_mentor_ids) {
                                                    return !in_array($mentor['id'], $scored_mentor_ids);
                                                });
                                                ?>
                                                
                                                <?php if (!empty($unscored_mentors)): ?>
                                                    <div class="mt-6 pt-6 border-t border-gray-200">
                                                        <h5 class="text-md font-medium text-gray-700 mb-3">
                                                            <i class="fas fa-clock text-orange-500 mr-1"></i>
                                                            Pending Evaluations
                                                        </h5>
                                                        <div class="space-y-2">
                                                            <?php foreach ($unscored_mentors as $mentor): ?>
                                                                <div class="bg-gradient-to-r from-orange-50 to-yellow-50 rounded-lg p-3 border border-orange-200">
                                                                    <div class="flex items-center space-x-3">
                                                                        <div class="w-8 h-8 bg-gradient-to-br from-orange-400 to-yellow-500 rounded-full flex items-center justify-center">
                                                                            <span class="text-white font-semibold text-xs"><?php echo strtoupper(substr($mentor['name'], 0, 2)); ?></span>
                                                                        </div>
                                                                        <div class="flex-1">
                                                                            <h6 class="font-medium text-gray-900"><?php echo htmlspecialchars($mentor['name']); ?></h6>
                                                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($mentor['email']); ?></p>
                                                                        </div>
                                                                        <div class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                                            <i class="fas fa-hourglass-half mr-1"></i>
                                                                            Not Scored
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Sidebar functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
    </script>
</body>

</html>