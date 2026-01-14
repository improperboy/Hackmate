<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('mentor');
$user = getCurrentUser();

// Get mentoring rounds
$stmt = $pdo->query("
    SELECT * FROM mentoring_rounds 
    ORDER BY start_time ASC
");
$mentoring_rounds = $stmt->fetchAll();

// Get mentor's scores
$stmt = $pdo->prepare("
    SELECT s.*, mr.round_name, t.name as team_name 
    FROM scores s 
    JOIN mentoring_rounds mr ON s.round_id = mr.id 
    JOIN teams t ON s.team_id = t.id 
    WHERE s.mentor_id = ?
    ORDER BY mr.start_time DESC
");
$stmt->execute([$user['id']]);
$mentor_scores = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule - HackMate</title>
    
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
        
        .schedule-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .schedule-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
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
                                <i class="fas fa-calendar text-white text-sm"></i>
                            </div>
                            <div>
                                <h1 class="text-xl font-bold text-gray-900">Schedule</h1>
                                <p class="text-sm text-gray-500 hidden sm:block">Mentoring rounds and timeline</p>
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
                            <h2 class="text-2xl font-bold mb-2">Mentoring Schedule</h2>
                            <p class="text-purple-100">Track all mentoring rounds and your scoring history</p>
                        </div>
                        <div class="hidden md:block">
                            <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center backdrop-blur-sm">
                                <i class="fas fa-calendar-alt text-3xl text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mentoring Rounds Schedule -->
            <div class="schedule-card rounded-2xl shadow-sm border border-gray-200 mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-clock text-blue-500 mr-2"></i>
                        Mentoring Rounds Schedule
                    </h3>
                </div>
            
                <?php if (empty($mentoring_rounds)): ?>
                    <div class="px-6 py-12 text-center">
                        <i class="fas fa-calendar-times text-gray-300 text-5xl mb-4"></i>
                        <h4 class="text-lg font-medium text-gray-900 mb-2">No Rounds Scheduled</h4>
                        <p class="text-gray-500">No mentoring rounds have been scheduled yet.</p>
                    </div>
                <?php else: ?>
                    <div class="p-6">
                        <div class="space-y-6">
                            <?php foreach ($mentoring_rounds as $round): ?>
                                <?php
                                $now = time();
                                $start = strtotime($round['start_time']);
                                $end = strtotime($round['end_time']);
                                
                                if ($now < $start) {
                                    $status = 'upcoming';
                                    $status_class = 'bg-blue-100 text-blue-800 border-blue-200';
                                    $status_icon = 'fas fa-clock';
                                    $card_class = 'border-l-4 border-blue-500 bg-gradient-to-r from-blue-50 to-indigo-50';
                                } elseif ($now >= $start && $now <= $end) {
                                    $status = 'active';
                                    $status_class = 'bg-green-100 text-green-800 border-green-200';
                                    $status_icon = 'fas fa-play-circle';
                                    $card_class = 'border-l-4 border-green-500 bg-gradient-to-r from-green-50 to-emerald-50';
                                } else {
                                    $status = 'completed';
                                    $status_class = 'bg-gray-100 text-gray-800 border-gray-200';
                                    $status_icon = 'fas fa-check-circle';
                                    $card_class = 'border-l-4 border-gray-400 bg-gradient-to-r from-gray-50 to-slate-50';
                                }
                                ?>
                                <div class="<?php echo $card_class; ?> rounded-xl p-6 transition-all duration-200 hover:shadow-md">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="flex items-center mb-3">
                                                <h4 class="text-lg font-semibold text-gray-900 mr-4"><?php echo htmlspecialchars($round['round_name']); ?></h4>
                                                <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full <?php echo $status_class; ?>">
                                                    <i class="<?php echo $status_icon; ?> mr-2"></i>
                                                    <?php echo ucfirst($status); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                                <div class="flex items-center text-sm text-gray-600">
                                                    <i class="fas fa-calendar text-blue-500 mr-2"></i>
                                                    <div>
                                                        <span class="font-medium">Start:</span><br>
                                                        <span><?php echo date('M j, Y g:i A', strtotime($round['start_time'])); ?></span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center text-sm text-gray-600">
                                                    <i class="fas fa-calendar-check text-red-500 mr-2"></i>
                                                    <div>
                                                        <span class="font-medium">End:</span><br>
                                                        <span><?php echo date('M j, Y g:i A', strtotime($round['end_time'])); ?></span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center text-sm text-gray-600">
                                                    <i class="fas fa-star text-yellow-500 mr-2"></i>
                                                    <div>
                                                        <span class="font-medium">Max Score:</span><br>
                                                        <span class="text-lg font-bold text-gray-900"><?php echo $round['max_score']; ?> points</span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($round['description']): ?>
                                                <div class="bg-white bg-opacity-50 rounded-lg p-3 mt-3">
                                                    <p class="text-gray-700 text-sm"><?php echo htmlspecialchars($round['description']); ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($status == 'active'): ?>
                                            <div class="ml-6">
                                                <a href="score_teams.php" 
                                                   class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors shadow-sm">
                                                    <i class="fas fa-star mr-2"></i>
                                                    Score Teams
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
        </div>

            <!-- Your Scoring History -->
            <div class="schedule-card rounded-2xl shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-history text-green-500 mr-2"></i>
                        Your Scoring History (<?php echo count($mentor_scores); ?>)
                    </h3>
                </div>
            
                <?php if (empty($mentor_scores)): ?>
                    <div class="px-6 py-12 text-center">
                        <i class="fas fa-star text-gray-300 text-5xl mb-4"></i>
                        <h4 class="text-lg font-medium text-gray-900 mb-2">No Scores Yet</h4>
                        <p class="text-gray-500 mb-4">You haven't submitted any scores yet.</p>
                        <a href="score_teams.php" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-star mr-2"></i>
                            Start Scoring Teams
                        </a>
                    </div>
                <?php else: ?>
                    <div class="overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Round</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Team</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comment</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($mentor_scores as $score): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($score['round_name']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($score['team_name']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full bg-gradient-to-r from-blue-100 to-indigo-100 text-blue-800">
                                                    <i class="fas fa-star mr-1"></i>
                                                    <?php echo $score['score']; ?> pts
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900">
                                                <div class="max-w-xs truncate" title="<?php echo htmlspecialchars($score['comment'] ?: 'No comment'); ?>">
                                                    <?php echo htmlspecialchars($score['comment'] ?: 'No comment'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M j, Y g:i A', strtotime($score['created_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
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
    </script>
</body>
</html>
