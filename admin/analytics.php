<?php
// Prevent any duplicate output or warnings
error_reporting(E_ALL & ~E_WARNING);
ini_set('display_errors', 0);

// Output buffering to prevent header issues
if (!ob_get_level()) {
    ob_start();
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('admin');
$user = getCurrentUser();

// Get counts for sidebar notifications
$pending_teams = $pdo->query("SELECT COUNT(*) FROM teams WHERE status = 'pending'")->fetchColumn();
$open_support_requests = $pdo->query("SELECT COUNT(*) FROM support_messages WHERE status = 'open'")->fetchColumn();
$total_submissions = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();

// --- Overall Statistics ---
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_teams = $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
$approved_teams = $pdo->query("SELECT COUNT(*) FROM teams WHERE status = 'approved'")->fetchColumn();
$pending_teams = $pdo->query("SELECT COUNT(*) FROM teams WHERE status = 'pending'")->fetchColumn();
$total_submissions = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
$total_mentors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mentor'")->fetchColumn();
$total_volunteers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'volunteer'")->fetchColumn();
$total_participants = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'participant'")->fetchColumn();
$open_support_messages = $pdo->query("SELECT COUNT(*) FROM support_messages WHERE status = 'open'")->fetchColumn();

// --- User Role Distribution ---
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$user_roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
$user_roles_chart_data = [];
foreach ($user_roles as $row) {
    $user_roles_chart_data[] = ['role' => ucfirst($row['role']), 'count' => $row['count']];
}

// --- Team Status Distribution ---
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM teams GROUP BY status");
$team_statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
$team_statuses_chart_data = [];
foreach ($team_statuses as $row) {
    $team_statuses_chart_data[] = ['status' => ucfirst($row['status']), 'count' => $row['count']];
}

// --- Submissions Over Time (example: daily count) ---
$stmt = $pdo->query("
    SELECT DATE(submitted_at) as submission_date, COUNT(*) as count 
    FROM submissions 
    GROUP BY submission_date 
    ORDER BY submission_date ASC
    LIMIT 30
");
$submissions_over_time = $stmt->fetchAll(PDO::FETCH_ASSOC);
$submissions_chart_data = [];
foreach ($submissions_over_time as $row) {
    $submissions_chart_data[] = ['date' => $row['submission_date'], 'count' => $row['count']];
}

// --- Average Score per Round ---
$stmt = $pdo->query("
    SELECT mr.round_name, AVG(s.score) as avg_score, mr.max_score
    FROM scores s
    JOIN mentoring_rounds mr ON s.round_id = mr.id
    GROUP BY mr.round_name, mr.max_score
    ORDER BY mr.start_time ASC
");
$avg_scores_per_round = $stmt->fetchAll(PDO::FETCH_ASSOC);
$avg_scores_chart_data = [];
foreach ($avg_scores_per_round as $row) {
    $avg_scores_chart_data[] = ['round' => $row['round_name'], 'avg_score' => round($row['avg_score'], 2), 'max_score' => $row['max_score']];
}

// --- Teams per Floor/Room ---
$stmt = $pdo->query("
    SELECT f.floor_number, r.room_number, COUNT(t.id) as team_count
    FROM teams t
    JOIN floors f ON t.floor_id = f.id
    JOIN rooms r ON t.room_id = r.id
    WHERE t.status = 'approved'
    GROUP BY f.floor_number, r.room_number
    ORDER BY f.floor_number, r.room_number
");
$teams_per_location = $stmt->fetchAll(PDO::FETCH_ASSOC);
$teams_location_chart_data = [];
foreach ($teams_per_location as $row) {
    $teams_location_chart_data[] = ['location' => $row['floor_number'] . '-' . $row['room_number'], 'count' => $row['team_count']];
}

// --- Top 5 Tech Stacks ---
$stmt = $pdo->query("
    SELECT tech_stack, COUNT(*) as count 
    FROM submissions 
    GROUP BY tech_stack 
    ORDER BY count DESC 
    LIMIT 5
");
$top_tech_stacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
$top_tech_stacks_chart_data = [];
foreach ($top_tech_stacks as $row) {
    $top_tech_stacks_chart_data[] = ['tech_stack' => $row['tech_stack'], 'count' => $row['count']];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - <?php echo htmlspecialchars(getSystemSetting('hackathon_name', 'HackMate')); ?></title>
    
    <!-- Primary Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- PWA Configuration -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#4F46E5">
    
    <style>
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .metric-card {
            transition: transform 0.2s ease-in-out;
        }
        
        .metric-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="flex h-screen">
        <!-- Include Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden lg:ml-0">
            <!-- Top Header -->
            <header class="bg-white shadow-sm border-b border-gray-200 lg:hidden">
                <div class="flex items-center justify-between px-4 py-3">
                    <button onclick="toggleSidebar()" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-semibold text-gray-900">Analytics</h1>
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
                                <i class="fas fa-chart-line text-blue-600 mr-3"></i>
                                Analytics Dashboard
                            </h1>
                            <p class="text-gray-600 mt-1">Comprehensive hackathon analytics and insights</p>
                        </div>
                    </div>
                </div>
                    <!-- Overview Statistics -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="metric-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">TOTAL USERS</p>
                                    <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $total_users; ?></p>
                                </div>
                                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-users text-blue-600"></i>
                                </div>
                            </div>
                            <div class="flex items-center text-sm">
                                <span class="text-green-600 font-medium">+<?php echo round(($total_users / max($total_users, 1)) * 100); ?>%</span>
                                <span class="text-gray-500 ml-2">from start</span>
                            </div>
                        </div>

                        <div class="metric-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">APPROVED TEAMS</p>
                                    <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $approved_teams; ?></p>
                                </div>
                                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-check-circle text-green-600"></i>
                                </div>
                            </div>
                            <div class="flex items-center text-sm">
                                <span class="text-blue-600 font-medium"><?php echo $total_teams > 0 ? round(($approved_teams / $total_teams) * 100) : 0; ?>%</span>
                                <span class="text-gray-500 ml-2">approval rate</span>
                            </div>
                        </div>

                        <div class="metric-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">SUBMISSIONS</p>
                                    <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $total_submissions; ?></p>
                                </div>
                                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-file-code text-purple-600"></i>
                                </div>
                            </div>
                            <div class="flex items-center text-sm">
                                <span class="text-purple-600 font-medium"><?php echo $approved_teams > 0 ? round(($total_submissions / $approved_teams) * 100) : 0; ?>%</span>
                                <span class="text-gray-500 ml-2">completion rate</span>
                            </div>
                        </div>

                        <div class="metric-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">SUPPORT STAFF</p>
                                    <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $total_mentors + $total_volunteers; ?></p>
                                </div>
                                <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-hands-helping text-indigo-600"></i>
                                </div>
                            </div>
                            <div class="flex items-center text-sm">
                                <span class="text-indigo-600 font-medium"><?php echo $total_mentors; ?> mentors</span>
                                <span class="text-gray-500 ml-2">• <?php echo $total_volunteers; ?> volunteers</span>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Section -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <!-- User Role Distribution Chart -->
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    <i class="fas fa-users text-blue-600 mr-2"></i>
                                    User Role Distribution
                                </h3>
                                <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">Live Data</span>
                            </div>
                            <div class="chart-container">
                                <canvas id="userRolesChart"></canvas>
                            </div>
                        </div>

                        <!-- Team Status Distribution Chart -->
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    <i class="fas fa-chart-pie text-green-600 mr-2"></i>
                                    Team Status Distribution
                                </h3>
                                <span class="px-3 py-1 bg-green-100 text-green-800 text-sm rounded-full">Real Time</span>
                            </div>
                            <div class="chart-container">
                                <canvas id="teamStatusChart"></canvas>
                            </div>
                        </div>

                        <!-- Submissions Over Time Chart -->
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    <i class="fas fa-chart-line text-purple-600 mr-2"></i>
                                    Submissions Timeline
                                </h3>
                                <span class="px-3 py-1 bg-purple-100 text-purple-800 text-sm rounded-full">Trending</span>
                            </div>
                            <div class="chart-container">
                                <canvas id="submissionsChart"></canvas>
                            </div>
                        </div>

                        <!-- Average Score per Round Chart -->
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    <i class="fas fa-trophy text-yellow-600 mr-2"></i>
                                    Average Scores by Round
                                </h3>
                                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 text-sm rounded-full">Performance</span>
                            </div>
                            <div class="chart-container">
                                <canvas id="avgScoresChart"></canvas>
                            </div>
                        </div>

                        <!-- Teams per Location Chart -->
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    <i class="fas fa-map-marker-alt text-teal-600 mr-2"></i>
                                    Teams by Location
                                </h3>
                                <span class="px-3 py-1 bg-teal-100 text-teal-800 text-sm rounded-full">Distribution</span>
                            </div>
                            <div class="chart-container">
                                <canvas id="teamsLocationChart"></canvas>
                            </div>
                        </div>

                        <!-- Top Tech Stacks Chart -->
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    <i class="fas fa-code text-pink-600 mr-2"></i>
                                    Popular Tech Stacks
                                </h3>
                                <span class="px-3 py-1 bg-pink-100 text-pink-800 text-sm rounded-full">Top 5</span>
                            </div>
                            <div class="chart-container">
                                <canvas id="topTechStacksChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script>
        // Data for charts
        const userRolesData = <?php echo json_encode($user_roles_chart_data); ?>;
        const teamStatusData = <?php echo json_encode($team_statuses_chart_data); ?>;
        const submissionsData = <?php echo json_encode($submissions_chart_data); ?>;
        const avgScoresData = <?php echo json_encode($avg_scores_chart_data); ?>;
        const teamsLocationData = <?php echo json_encode($teams_location_chart_data); ?>;
        const topTechStacksData = <?php echo json_encode($top_tech_stacks_chart_data); ?>;

        // User Roles Chart
        new Chart(document.getElementById('userRolesChart'), {
            type: 'pie',
            data: {
                labels: userRolesData.map(row => row.role),
                datasets: [{
                    data: userRolesData.map(row => row.count),
                    backgroundColor: ['#3B82F6', '#10B981', '#6366F1', '#F97316'],
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: false }
                }
            }
        });

        // Team Status Chart
        new Chart(document.getElementById('teamStatusChart'), {
            type: 'doughnut',
            data: {
                labels: teamStatusData.map(row => row.status),
                datasets: [{
                    data: teamStatusData.map(row => row.count),
                    backgroundColor: ['#22C55E', '#F59E0B', '#EF4444'],
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: false }
                }
            }
        });

        // Submissions Over Time Chart
        new Chart(document.getElementById('submissionsChart'), {
            type: 'line',
            data: {
                labels: submissionsData.map(row => row.date),
                datasets: [{
                    label: 'Submissions',
                    data: submissionsData.map(row => row.count),
                    borderColor: '#8B5CF6',
                    backgroundColor: 'rgba(139, 92, 246, 0.2)',
                    fill: true,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    title: { display: false }
                },
                scales: {
                    x: { title: { display: true, text: 'Date' } },
                    y: { title: { display: true, text: 'Count' }, beginAtZero: true }
                }
            }
        });

        // Average Score per Round Chart
        new Chart(document.getElementById('avgScoresChart'), {
            type: 'bar',
            data: {
                labels: avgScoresData.map(row => row.round),
                datasets: [{
                    label: 'Average Score',
                    data: avgScoresData.map(row => row.avg_score),
                    backgroundColor: '#F97316',
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    title: { display: false }
                },
                scales: {
                    x: { title: { display: true, text: 'Mentoring Round' } },
                    y: { title: { display: true, text: 'Average Score' }, beginAtZero: true }
                }
            }
        });

        // Teams per Location Chart
        new Chart(document.getElementById('teamsLocationChart'), {
            type: 'bar',
            data: {
                labels: teamsLocationData.map(row => row.location),
                datasets: [{
                    label: 'Number of Teams',
                    data: teamsLocationData.map(row => row.count),
                    backgroundColor: '#06B6D4',
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    title: { display: false }
                },
                scales: {
                    x: { title: { display: true, text: 'Floor - Room' } },
                    y: { title: { display: true, text: 'Number of Teams' }, beginAtZero: true }
                }
            }
        });

        // Top Tech Stacks Chart
        new Chart(document.getElementById('topTechStacksChart'), {
            type: 'bar',
            data: {
                labels: topTechStacksData.map(row => row.tech_stack),
                datasets: [{
                    label: 'Number of Submissions',
                    data: topTechStacksData.map(row => row.count),
                    backgroundColor: '#EC4899',
                }]
            },
            options: {
                indexAxis: 'y', // Horizontal bar chart
                responsive: true,
                plugins: {
                    legend: { display: false },
                    title: { display: false }
                },
                scales: {
                    x: { title: { display: true, text: 'Number of Submissions' }, beginAtZero: true },
                    y: { title: { display: true, text: 'Tech Stack' } }
                }
            }
        });
    </script>
</body>
</html>
