<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('admin');
$user = getCurrentUser();

// Get dashboard statistics
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_teams = $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
$pending_teams = $pdo->query("SELECT COUNT(*) FROM teams WHERE status = 'pending'")->fetchColumn();
$approved_teams = $pdo->query("SELECT COUNT(*) FROM teams WHERE status = 'approved'")->fetchColumn();
$total_submissions = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
$open_support_requests = $pdo->query("SELECT COUNT(*) FROM support_messages WHERE status = 'open'")->fetchColumn();
$total_mentors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mentor'")->fetchColumn();
$total_volunteers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'volunteer'")->fetchColumn();
$assigned_mentors = $pdo->query("SELECT COUNT(DISTINCT mentor_id) FROM mentor_assignments")->fetchColumn();
$assigned_volunteers = $pdo->query("SELECT COUNT(DISTINCT volunteer_id) FROM volunteer_assignments")->fetchColumn();

// Get day-wise data for charts (last 7 days)
$daily_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $day_label = date('M j', strtotime("-$i days"));
    
    // Get daily registrations
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $daily_users = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $daily_teams = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE DATE(submitted_at) = ?");
    $stmt->execute([$date]);
    $daily_submissions = $stmt->fetchColumn();
    
    $daily_data[] = [
        'date' => $date,
        'label' => $day_label,
        'users' => $daily_users,
        'teams' => $daily_teams,
        'submissions' => $daily_submissions
    ];
}

// Get recent activities
$recent_activities = [];

// Recent teams
$stmt = $pdo->query("SELECT id, name, created_at FROM teams ORDER BY created_at DESC LIMIT 5");
$recent_teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($recent_teams as $team) {
    $recent_activities[] = [
        'type' => 'New Team',
        'description' => 'Team "' . $team['name'] . '" registered',
        'time' => $team['created_at'],
        'icon' => 'fas fa-users',
        'color' => 'blue'
    ];
}

// Recent submissions
$stmt = $pdo->query("SELECT s.id, t.name as team_name, s.submitted_at FROM submissions s JOIN teams t ON s.team_id = t.id ORDER BY s.submitted_at DESC LIMIT 5");
$recent_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($recent_submissions as $submission) {
    $recent_activities[] = [
        'type' => 'New Submission',
        'description' => 'Team "' . $submission['team_name'] . '" submitted project',
        'time' => $submission['submitted_at'],
        'icon' => 'fas fa-file-upload',
        'color' => 'green'
    ];
}

// Sort activities by time
usort($recent_activities, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});
$recent_activities = array_slice($recent_activities, 0, 8);

// Calculate progress metrics
$team_approval_rate = $total_teams > 0 ? round(($approved_teams / $total_teams) * 100) : 0;
$submission_rate = $total_teams > 0 ? round(($total_submissions / $total_teams) * 100) : 0;
$mentor_utilization = $total_mentors > 0 ? round(($assigned_mentors / $total_mentors) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - HackMate</title>
    
    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .metric-card {
            transition: transform 0.2s ease-in-out;
        }
        .metric-card:hover {
            transform: translateY(-2px);
        }
        .progress-ring {
            transition: stroke-dasharray 0.35s;
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
                    <h1 class="text-lg font-semibold text-gray-900">Dashboard</h1>
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
                                <i class="fas fa-tachometer-alt text-blue-600 mr-3"></i>
                                Dashboard
                            </h1>
                            <p class="text-gray-600 mt-1">Welcome back, <?php echo htmlspecialchars($user['name']); ?> • <?php echo date('l, F j, Y'); ?></p>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="flex items-center space-x-3">
                            <?php if ($pending_teams > 0): ?>
                                <a href="teams.php" class="bg-orange-100 text-orange-800 px-4 py-2 rounded-lg text-sm font-medium hover:bg-orange-200 transition-colors">
                                    <i class="fas fa-clock mr-2"></i>
                                    <?php echo $pending_teams; ?> Pending Teams
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($open_support_requests > 0): ?>
                                <a href="support_messages.php" class="bg-red-100 text-red-800 px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-200 transition-colors">
                                    <i class="fas fa-life-ring mr-2"></i>
                                    <?php echo $open_support_requests; ?> Support Requests
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Key Metrics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Participants -->
                    <div class="metric-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Participants</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $total_users; ?></p>
                                <p class="text-sm text-gray-500 mt-1">Total registered</p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-users text-blue-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo min(($total_users / 200) * 100, 100); ?>%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Target: 200 participants</p>
                        </div>
                    </div>
                    
                    <!-- Total Teams -->
                    <div class="metric-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Teams</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $total_teams; ?></p>
                                <p class="text-sm text-gray-500 mt-1"><?php echo $approved_teams; ?> approved</p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-users-cog text-green-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo min(($total_teams / 50) * 100, 100); ?>%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Max capacity: 50 teams</p>
                        </div>
                    </div>
                    
                    <!-- Project Submissions -->
                    <div class="metric-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Submissions</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $total_submissions; ?></p>
                                <p class="text-sm text-gray-500 mt-1"><?php echo $submission_rate; ?>% completion</p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-file-code text-purple-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-purple-500 h-2 rounded-full" style="width: <?php echo $submission_rate; ?>%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Projects submitted</p>
                        </div>
                    </div>
                    
                    <!-- Support Staff -->
                    <div class="metric-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Support Staff</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $total_mentors + $total_volunteers; ?></p>
                                <p class="text-sm text-gray-500 mt-1"><?php echo $assigned_mentors + $assigned_volunteers; ?> active</p>
                            </div>
                            <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-hands-helping text-indigo-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-indigo-500 h-2 rounded-full" style="width: <?php echo $total_mentors + $total_volunteers > 0 ? min((($assigned_mentors + $assigned_volunteers) / ($total_mentors + $total_volunteers)) * 100, 100) : 0; ?>%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1"><?php echo $total_mentors; ?> mentors, <?php echo $total_volunteers; ?> volunteers</p>
                        </div>
                    </div>
                </div>
                
                <!-- Charts and Analytics -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Daily Activity Chart -->
                    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                                Daily Activity (Last 7 Days)
                            </h3>
                            <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full font-medium">Live Data</span>
                        </div>
                        
                        <div class="h-64 mb-4">
                            <canvas id="activityChart"></canvas>
                        </div>
                        
                        <div class="flex items-center justify-center space-x-6 text-sm">
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                                <span class="text-gray-600">Teams</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                                <span class="text-gray-600">Submissions</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-purple-500 rounded-full mr-2"></div>
                                <span class="text-gray-600">Users</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Progress Overview -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6">
                            <i class="fas fa-trophy text-yellow-600 mr-2"></i>
                            Progress Overview
                        </h3>
                        
                        <!-- Team Approval Progress -->
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-600">Team Approvals</span>
                                <span class="text-sm font-bold text-gray-900"><?php echo $team_approval_rate; ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-500 h-2 rounded-full transition-all duration-300" style="width: <?php echo $team_approval_rate; ?>%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1"><?php echo $approved_teams; ?> of <?php echo $total_teams; ?> teams approved</p>
                        </div>
                        
                        <!-- Submission Progress -->
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-600">Project Submissions</span>
                                <span class="text-sm font-bold text-gray-900"><?php echo $submission_rate; ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full transition-all duration-300" style="width: <?php echo $submission_rate; ?>%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1"><?php echo $total_submissions; ?> projects submitted</p>
                        </div>
                        
                        <!-- Mentor Utilization -->
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-600">Mentor Utilization</span>
                                <span class="text-sm font-bold text-gray-900"><?php echo $mentor_utilization; ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-purple-500 h-2 rounded-full transition-all duration-300" style="width: <?php echo $mentor_utilization; ?>%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1"><?php echo $assigned_mentors; ?> of <?php echo $total_mentors; ?> mentors active</p>
                        </div>
                        
                        <!-- Support Requests -->
                        <?php if ($open_support_requests > 0): ?>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                                <span class="text-sm font-medium text-red-800"><?php echo $open_support_requests; ?> Open Support Requests</span>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                <span class="text-sm font-medium text-green-800">All support requests resolved</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Activity and Quick Stats -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Recent Activity -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-clock text-blue-600 mr-2"></i>
                                Recent Activity
                            </h3>
                            <a href="recent_activity.php" class="text-sm text-blue-600 hover:text-blue-800 font-medium">View All</a>
                        </div>
                        
                        <div class="space-y-4">
                            <?php if (empty($recent_activities)): ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-inbox text-gray-300 text-3xl mb-2"></i>
                                    <p class="text-gray-500">No recent activity</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_activities as $activity): ?>
                                    <div class="flex items-start space-x-3 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                                        <div class="w-8 h-8 bg-<?php echo $activity['color']; ?>-100 rounded-full flex items-center justify-center flex-shrink-0">
                                            <i class="<?php echo $activity['icon']; ?> text-<?php echo $activity['color']; ?>-600 text-sm"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900"><?php echo $activity['type']; ?></p>
                                            <p class="text-sm text-gray-600 truncate"><?php echo $activity['description']; ?></p>
                                            <p class="text-xs text-gray-400 mt-1"><?php echo timeAgo($activity['time']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6">
                            <i class="fas fa-chart-bar text-green-600 mr-2"></i>
                            Quick Stats
                        </h3>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="text-center p-4 bg-blue-50 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600"><?php echo $assigned_mentors; ?></div>
                                <div class="text-sm text-gray-600">Active Mentors</div>
                            </div>
                            
                            <div class="text-center p-4 bg-green-50 rounded-lg">
                                <div class="text-2xl font-bold text-green-600"><?php echo $assigned_volunteers; ?></div>
                                <div class="text-sm text-gray-600">Active Volunteers</div>
                            </div>
                            
                            <div class="text-center p-4 bg-orange-50 rounded-lg">
                                <div class="text-2xl font-bold text-orange-600"><?php echo $pending_teams; ?></div>
                                <div class="text-sm text-gray-600">Pending Teams</div>
                            </div>
                            
                            <div class="text-center p-4 bg-purple-50 rounded-lg">
                                <div class="text-2xl font-bold text-purple-600"><?php echo $total_submissions; ?></div>
                                <div class="text-sm text-gray-600">Submissions</div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="mt-6 space-y-2">
                            <a href="teams.php" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors flex items-center justify-center">
                                <i class="fas fa-users mr-2"></i>
                                Manage Teams
                            </a>
                            <a href="view_submissions.php" class="w-full bg-green-600 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors flex items-center justify-center">
                                <i class="fas fa-file-upload mr-2"></i>
                                View Submissions
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Include AI Chatbot -->
    <?php include '../includes/chatbot_component.php'; ?>
    
    <script>
        // Chart.js initialization
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('activityChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [<?php 
                        $labels = array_map(function($day) { return "'" . $day['label'] . "'"; }, $daily_data);
                        echo implode(',', $labels);
                    ?>],
                    datasets: [{
                        label: 'Teams',
                        data: [<?php 
                            $team_data = array_map(function($day) { return $day['teams']; }, $daily_data);
                            echo implode(',', $team_data);
                        ?>],
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#3B82F6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }, {
                        label: 'Submissions',
                        data: [<?php 
                            $submission_data = array_map(function($day) { return $day['submissions']; }, $daily_data);
                            echo implode(',', $submission_data);
                        ?>],
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#10B981',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }, {
                        label: 'Users',
                        data: [<?php 
                            $user_data = array_map(function($day) { return $day['users']; }, $daily_data);
                            echo implode(',', $user_data);
                        ?>],
                        borderColor: '#8B5CF6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#8B5CF6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff'
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#F3F4F6'
                            },
                            ticks: {
                                color: '#6B7280'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#6B7280'
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>