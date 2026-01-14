<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('admin');
$user = getCurrentUser();

// Get counts for sidebar notifications
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_teams = $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
$pending_teams = $pdo->query("SELECT COUNT(*) FROM teams WHERE status = 'pending'")->fetchColumn();
$total_submissions = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
$open_support_requests = $pdo->query("SELECT COUNT(*) FROM support_messages WHERE status = 'open'")->fetchColumn();

// Get filter parameters
$filter_type = $_GET['type'] ?? '';
$filter_days = $_GET['days'] ?? '7';

// Build date filter
$date_filter = '';
if ($filter_days !== 'all') {
    $days = intval($filter_days);
    $date_filter = " AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
}

// Get all recent activities
$recent_activities = [];

// Recent users
$query = "SELECT id, name, email, role, created_at FROM users WHERE 1=1" . str_replace('created_at', 'created_at', $date_filter) . " ORDER BY created_at DESC LIMIT 50";
$stmt = $pdo->query($query);
$recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($recent_users as $user_item) {
    if ($filter_type === '' || $filter_type === 'user') {
        $recent_activities[] = [
            'type' => 'New User',
            'description' => ucfirst($user_item['role']) . ' "' . $user_item['name'] . '" registered',
            'time' => $user_item['created_at'],
            'icon' => 'fas fa-user-plus',
            'color' => 'green',
            'details' => [
                'name' => $user_item['name'],
                'email' => $user_item['email'],
                'role' => $user_item['role']
            ]
        ];
    }
}

// Recent teams
$query = "SELECT id, name, leader_id, created_at FROM teams WHERE 1=1" . $date_filter . " ORDER BY created_at DESC LIMIT 50";
$stmt = $pdo->query($query);
$recent_teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($recent_teams as $team) {
    if ($filter_type === '' || $filter_type === 'team') {
        $recent_activities[] = [
            'type' => 'New Team',
            'description' => 'Team "' . $team['name'] . '" registered',
            'time' => $team['created_at'],
            'icon' => 'fas fa-users',
            'color' => 'blue',
            'details' => [
                'team_name' => $team['name'],
                'team_id' => $team['id']
            ]
        ];
    }
}

// Recent submissions
$query = "SELECT s.id, s.submitted_at, t.name as team_name, t.id as team_id FROM submissions s JOIN teams t ON s.team_id = t.id WHERE 1=1" . str_replace('created_at', 's.submitted_at', $date_filter) . " ORDER BY s.submitted_at DESC LIMIT 50";
$stmt = $pdo->query($query);
$recent_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($recent_submissions as $submission) {
    if ($filter_type === '' || $filter_type === 'submission') {
        $recent_activities[] = [
            'type' => 'New Submission',
            'description' => 'Team "' . $submission['team_name'] . '" submitted project',
            'time' => $submission['submitted_at'],
            'icon' => 'fas fa-file-upload',
            'color' => 'purple',
            'details' => [
                'team_name' => $submission['team_name'],
                'team_id' => $submission['team_id']
            ]
        ];
    }
}

// Recent support messages
$query = "SELECT sm.id, sm.created_at, u.name as from_name, sm.from_role, sm.to_role FROM support_messages sm JOIN users u ON sm.from_id = u.id WHERE 1=1" . str_replace('created_at', 'sm.created_at', $date_filter) . " ORDER BY sm.created_at DESC LIMIT 50";
$stmt = $pdo->query($query);
$recent_support = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($recent_support as $support) {
    if ($filter_type === '' || $filter_type === 'support') {
        $recent_activities[] = [
            'type' => 'Support Request',
            'description' => $support['from_name'] . ' sent a ' . $support['to_role'] . ' support request',
            'time' => $support['created_at'],
            'icon' => 'fas fa-life-ring',
            'color' => 'orange',
            'details' => [
                'from_name' => $support['from_name'],
                'from_role' => $support['from_role'],
                'to_role' => $support['to_role']
            ]
        ];
    }
}

// Sort activities by time
usort($recent_activities, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});

// Pagination
$page = $_GET['page'] ?? 1;
$per_page = 20;
$total_activities = count($recent_activities);
$total_pages = ceil($total_activities / $per_page);
$offset = ($page - 1) * $per_page;
$activities_page = array_slice($recent_activities, $offset, $per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recent Activity - HackMate</title>
    
    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .activity-card {
            transition: all 0.3s ease-in-out;
        }
        .activity-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .activity-item {
            transition: all 0.2s ease-in-out;
        }
        .activity-item:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            transform: translateX(4px);
        }
        .animate-fade-in {
            animation: fadeIn 0.6s ease-in-out;
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .filter-badge {
            transition: all 0.2s ease-in-out;
        }
        .filter-badge:hover {
            transform: translateY(-1px);
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
                    <h1 class="text-lg font-semibold text-gray-900">Recent Activity</h1>
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
                                <i class="fas fa-clock text-blue-600 mr-3"></i>
                                Recent Activity
                            </h1>
                            <p class="text-gray-600 mt-1">Monitor all system activities and user interactions</p>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="flex items-center space-x-3">
                            <span class="bg-blue-100 text-blue-800 px-4 py-2 rounded-lg text-sm font-medium">
                                <i class="fas fa-list mr-2"></i>
                                <?php echo $total_activities; ?> Activities
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="activity-card bg-white rounded-xl shadow-sm p-6 border border-gray-100 mb-6 animate-fade-in">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-filter text-blue-600 mr-3"></i>
                            Filter Activities
                        </h3>
                        <span class="bg-blue-100 text-blue-800 text-xs font-medium px-3 py-1 rounded-full">
                            Advanced Filters
                        </span>
                    </div>
                    
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                <i class="fas fa-tag mr-2 text-gray-500"></i>
                                Activity Type
                            </label>
                            <select name="type" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                <option value="">All Types</option>
                                <option value="user" <?php echo $filter_type == 'user' ? 'selected' : ''; ?>>New Users</option>
                                <option value="team" <?php echo $filter_type == 'team' ? 'selected' : ''; ?>>Team Registration</option>
                                <option value="submission" <?php echo $filter_type == 'submission' ? 'selected' : ''; ?>>Project Submissions</option>
                                <option value="support" <?php echo $filter_type == 'support' ? 'selected' : ''; ?>>Support Requests</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                <i class="fas fa-calendar mr-2 text-gray-500"></i>
                                Time Period
                            </label>
                            <select name="days" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                <option value="1" <?php echo $filter_days == '1' ? 'selected' : ''; ?>>Last 24 Hours</option>
                                <option value="7" <?php echo $filter_days == '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="30" <?php echo $filter_days == '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="all" <?php echo $filter_days == 'all' ? 'selected' : ''; ?>>All Time</option>
                            </select>
                        </div>
                        <div class="flex items-end space-x-3 md:col-span-2">
                            <button type="submit" class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 shadow-md hover:shadow-lg">
                                <i class="fas fa-search mr-2"></i>Apply Filters
                            </button>
                            <a href="recent_activity.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-4 rounded-xl transition-all duration-300">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Activity List -->
                <div class="activity-card bg-white rounded-xl shadow-sm border border-gray-100 animate-fade-in">
                    <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-list-check text-gray-600 mr-3"></i>
                                Activity Timeline
                            </h3>
                            <div class="flex items-center space-x-3">
                                <span class="bg-blue-100 text-blue-800 text-sm font-medium px-3 py-1 rounded-full">
                                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (empty($activities_page)): ?>
                        <div class="px-6 py-12 text-center">
                            <div class="mx-auto w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-6">
                                <i class="fas fa-clock text-gray-400 text-3xl"></i>
                            </div>
                            <h4 class="text-lg font-medium text-gray-900 mb-2">No Activities Found</h4>
                            <p class="text-gray-500 max-w-sm mx-auto">No activities match your current filters. Try adjusting your search criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($activities_page as $index => $activity): ?>
                                <div class="activity-item p-6" style="animation-delay: <?php echo $index * 0.1; ?>s">
                                    <div class="flex items-start space-x-4">
                                        <div class="w-12 h-12 bg-<?php echo $activity['color']; ?>-100 rounded-full flex items-center justify-center flex-shrink-0">
                                            <i class="<?php echo $activity['icon']; ?> text-<?php echo $activity['color']; ?>-600 text-lg"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="text-sm font-semibold text-gray-900"><?php echo $activity['type']; ?></p>
                                                    <p class="text-sm text-gray-600 mt-1"><?php echo $activity['description']; ?></p>
                                                </div>
                                                <div class="text-right flex-shrink-0 ml-4">
                                                    <p class="text-sm font-medium text-gray-900"><?php echo timeAgo($activity['time']); ?></p>
                                                    <p class="text-xs text-gray-500"><?php echo date('M j, g:i A', strtotime($activity['time'])); ?></p>
                                                </div>
                                            </div>
                                            
                                            <!-- Activity Details -->
                                            <?php if (!empty($activity['details'])): ?>
                                                <div class="mt-3 p-3 bg-gray-50 rounded-lg">
                                                    <div class="grid grid-cols-2 gap-2 text-xs">
                                                        <?php foreach ($activity['details'] as $key => $value): ?>
                                                            <div>
                                                                <span class="font-medium text-gray-600"><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</span>
                                                                <span class="text-gray-900"><?php echo htmlspecialchars($value); ?></span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <div class="text-sm text-gray-700">
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_activities); ?> of <?php echo $total_activities; ?> activities
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <?php if ($page > 1): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                               class="px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                                <i class="fas fa-chevron-left mr-1"></i>Previous
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                               class="px-3 py-2 <?php echo $i == $page ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border border-gray-300 rounded-lg text-sm font-medium transition-colors">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                               class="px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                                Next<i class="fas fa-chevron-right ml-1"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>