<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('admin');
$user = getCurrentUser();

// Get chatbot usage statistics
$total_conversations = $pdo->query("SELECT COUNT(*) FROM chatbot_logs")->fetchColumn();
$unique_users = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM chatbot_logs")->fetchColumn();
$today_conversations = $pdo->query("SELECT COUNT(*) FROM chatbot_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();

// Get conversations by role
$stmt = $pdo->query("
    SELECT u.role, COUNT(*) as conversation_count 
    FROM chatbot_logs cl 
    JOIN users u ON cl.user_id = u.id 
    GROUP BY u.role 
    ORDER BY conversation_count DESC
");
$conversations_by_role = $stmt->fetchAll();

// Get recent conversations
$stmt = $pdo->query("
    SELECT cl.*, u.name, u.role 
    FROM chatbot_logs cl 
    JOIN users u ON cl.user_id = u.id 
    ORDER BY cl.created_at DESC 
    LIMIT 20
");
$recent_conversations = $stmt->fetchAll();

// Get most common questions (simplified keyword analysis)
$stmt = $pdo->query("
    SELECT question, COUNT(*) as frequency 
    FROM chatbot_logs 
    GROUP BY question 
    ORDER BY frequency DESC 
    LIMIT 10
");
$common_questions = $stmt->fetchAll();

// Get daily conversation trends (last 7 days)
$daily_trends = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM chatbot_logs WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $count = $stmt->fetchColumn();
    
    $daily_trends[] = [
        'date' => $date,
        'label' => date('M j', strtotime($date)),
        'count' => $count
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helper Analytics - HackMate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <h1 class="text-lg font-semibold text-gray-900">Helper Analytics</h1>
                    <div class="w-6"></div>
                </div>
            </header>
            
            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- Page Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900">
                        <i class="fas fa-robot text-blue-600 mr-3"></i>
                        Helper Bot Analytics
                    </h1>
                    <p class="text-gray-600 mt-1">Monitor helper bot usage and user interactions</p>
                </div>
                
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Total Conversations</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $total_conversations; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-comments text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Unique Users</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $unique_users; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-users text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Today's Conversations</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $today_conversations; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-calendar-day text-purple-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Avg per User</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $unique_users > 0 ? round($total_conversations / $unique_users, 1) : 0; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-chart-line text-orange-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Daily Trends -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                            Daily Conversation Trends
                        </h3>
                        <div class="h-64">
                            <canvas id="trendsChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Usage by Role -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-pie-chart text-green-600 mr-2"></i>
                            Usage by Role
                        </h3>
                        <div class="h-64">
                            <canvas id="roleChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Tables -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Common Questions -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-question-circle text-purple-600 mr-2"></i>
                            Most Common Questions
                        </h3>
                        <div class="space-y-3 max-h-64 overflow-y-auto">
                            <?php if (empty($common_questions)): ?>
                                <p class="text-gray-500 text-center py-4">No questions yet</p>
                            <?php else: ?>
                                <?php foreach ($common_questions as $question): ?>
                                    <div class="p-3 bg-gray-50 rounded-lg">
                                        <p class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars(truncateText($question['question'], 80)); ?></p>
                                        <p class="text-xs text-gray-500 mt-1">Asked <?php echo $question['frequency']; ?> time<?php echo $question['frequency'] > 1 ? 's' : ''; ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Recent Conversations -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-clock text-orange-600 mr-2"></i>
                            Recent Conversations
                        </h3>
                        <div class="space-y-3 max-h-64 overflow-y-auto">
                            <?php if (empty($recent_conversations)): ?>
                                <p class="text-gray-500 text-center py-4">No conversations yet</p>
                            <?php else: ?>
                                <?php foreach ($recent_conversations as $conv): ?>
                                    <div class="p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($conv['name']); ?></span>
                                            <span class="text-xs px-2 py-1 rounded-full <?php echo getRoleBadgeClass($conv['role']); ?>">
                                                <?php echo ucfirst($conv['role']); ?>
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-600"><?php echo htmlspecialchars(truncateText($conv['question'], 60)); ?></p>
                                        <p class="text-xs text-gray-400 mt-1"><?php echo timeAgo($conv['created_at']); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        // Daily Trends Chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: [<?php echo implode(',', array_map(function($day) { return "'" . $day['label'] . "'"; }, $daily_trends)); ?>],
                datasets: [{
                    label: 'Conversations',
                    data: [<?php echo implode(',', array_map(function($day) { return $day['count']; }, $daily_trends)); ?>],
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        
        // Role Usage Chart
        const roleCtx = document.getElementById('roleChart').getContext('2d');
        new Chart(roleCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php echo implode(',', array_map(function($role) { return "'" . ucfirst($role['role']) . "'"; }, $conversations_by_role)); ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_map(function($role) { return $role['conversation_count']; }, $conversations_by_role)); ?>],
                    backgroundColor: [
                        '#EF4444', // Red for admin
                        '#3B82F6', // Blue for participant
                        '#10B981', // Green for mentor
                        '#8B5CF6', // Purple for volunteer
                        '#F59E0B'  // Yellow for others
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>