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

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$floor_filter = $_GET['floor'] ?? '';
$room_filter = $_GET['room'] ?? '';

// Fetch all submissions with team and leader details
$query = "
    SELECT s.*, t.name as team_name, t.idea, t.problem_statement,
           u.name as leader_name, u.email as leader_email,
           f.floor_number, r.room_number
    FROM submissions s
    JOIN teams t ON s.team_id = t.id
    JOIN users u ON t.leader_id = u.id
    LEFT JOIN floors f ON t.floor_id = f.id
    LEFT JOIN rooms r ON t.room_id = r.id
    WHERE 1=1
";

$params = [];
if ($search) {
    $query .= " AND (t.name LIKE ? OR u.name LIKE ? OR s.tech_stack LIKE ? OR s.github_link LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($floor_filter) {
    $query .= " AND f.floor_number = ?";
    $params[] = $floor_filter;
}
if ($room_filter) {
    $query .= " AND r.room_number = ?";
    $params[] = $room_filter;
}

$query .= " ORDER BY s.submitted_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $submissions = [];
    error_log("Database error in view_submissions.php: " . $e->getMessage());
}

// Get floors and rooms for filters
try {
    $stmt = $pdo->query("SELECT DISTINCT floor_number FROM floors ORDER BY floor_number");
    $floors = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT DISTINCT room_number FROM rooms ORDER BY room_number");
    $rooms = $stmt->fetchAll();
} catch (PDOException $e) {
    $floors = [];
    $rooms = [];
    error_log("Database error getting floors/rooms in view_submissions.php: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Submissions - HackMate</title>

    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        .submission-card {
            transition: all 0.3s ease-in-out;
        }

        .submission-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease-in-out;
        }

        .stats-card:hover {
            transform: translateY(-2px);
        }

        .tech-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            margin: 0.125rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .link-button {
            transition: all 0.2s ease-in-out;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .link-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .github-btn {
            background: linear-gradient(135deg, #24292e 0%, #1a1e22 100%);
            color: white;
        }

        .live-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .demo-btn {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
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

        .submission-row {
            transition: all 0.2s ease-in-out;
        }

        .submission-row:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            transform: scale(1.01);
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
                    <h1 class="text-lg font-semibold text-gray-900">Submissions</h1>
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
                                <i class="fas fa-file-upload text-blue-600 mr-3"></i>
                                Project Submissions
                            </h1>
                            <p class="text-gray-600 mt-1">View and manage all team project submissions</p>
                        </div>

                        <!-- Quick Actions -->
                        <div class="flex items-center space-x-3">
                            <a href="export.php" class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 shadow-md hover:shadow-lg">
                                <i class="fas fa-download mr-2"></i>
                                Export Data
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="stats-card rounded-xl p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white/80 text-sm font-medium">Total Submissions</p>
                                <p class="text-3xl font-bold mt-2"><?php echo count($submissions); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-file-upload text-2xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="submission-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium">Total Teams</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $total_teams; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-users text-green-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="submission-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium">Submission Rate</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $total_teams > 0 ? round((count($submissions) / $total_teams) * 100) : 0; ?>%</p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-chart-line text-blue-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="submission-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium">Pending Teams</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $pending_teams; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-clock text-orange-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="submission-card bg-white rounded-xl shadow-sm p-6 border border-gray-100 mb-6 animate-fade-in">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-search text-blue-600 mr-3"></i>
                            Search & Filter Submissions
                        </h3>
                        <span class="bg-blue-100 text-blue-800 text-xs font-medium px-3 py-1 rounded-full">
                            Advanced Filters
                        </span>
                    </div>
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                <i class="fas fa-search mr-2 text-gray-500"></i>
                                Search Submissions
                            </label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                placeholder="Team name, leader, tech stack..."
                                class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                <i class="fas fa-layer-group mr-2 text-gray-500"></i>
                                Floor
                            </label>
                            <select name="floor" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                <option value="">All Floors</option>
                                <?php foreach ($floors as $floor): ?>
                                    <option value="<?php echo $floor['floor_number']; ?>" <?php echo $floor_filter == $floor['floor_number'] ? 'selected' : ''; ?>>
                                        Floor <?php echo $floor['floor_number']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                <i class="fas fa-door-open mr-2 text-gray-500"></i>
                                Room
                            </label>
                            <select name="room" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                <option value="">All Rooms</option>
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?php echo $room['room_number']; ?>" <?php echo $room_filter == $room['room_number'] ? 'selected' : ''; ?>>
                                        Room <?php echo $room['room_number']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-end space-x-3">
                            <button type="submit" class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 shadow-md hover:shadow-lg">
                                <i class="fas fa-search mr-2"></i>Search
                            </button>
                            <a href="view_submissions.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-4 rounded-xl transition-all duration-300">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Submissions List -->
                <div class="submission-card bg-white rounded-xl shadow-sm border border-gray-100 animate-fade-in">
                    <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-list-check text-gray-600 mr-3"></i>
                                Project Submissions
                            </h3>
                            <div class="flex items-center space-x-3">
                                <span class="bg-blue-100 text-blue-800 text-sm font-medium px-3 py-1 rounded-full">
                                    <?php echo count($submissions); ?> Total
                                </span>
                                <?php if (count($submissions) > 0): ?>
                                    <span class="bg-green-100 text-green-800 text-sm font-medium px-3 py-1 rounded-full">
                                        <?php echo $total_teams > 0 ? round((count($submissions) / $total_teams) * 100) : 0; ?>% Completion
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (empty($submissions)): ?>
                        <div class="px-6 py-12 text-center">
                            <div class="mx-auto w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-6">
                                <i class="fas fa-file-upload text-gray-400 text-3xl"></i>
                            </div>
                            <h4 class="text-lg font-medium text-gray-900 mb-2">No Submissions Found</h4>
                            <p class="text-gray-500 max-w-sm mx-auto">No project submissions match your current search criteria. Try adjusting your filters or check back later.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Team Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leader</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tech Stack</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted At</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Links</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($submissions as $index => $submission): ?>
                                        <tr class="submission-row" style="animation-delay: <?php echo $index * 0.1; ?>s">
                                            <td class="px-6 py-5">
                                                <div class="flex items-center">
                                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center text-white font-bold text-sm mr-4">
                                                        <?php echo strtoupper(substr($submission['team_name'], 0, 2)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($submission['team_name']); ?></div>
                                                        <div class="text-sm text-gray-500 mt-1"><?php echo truncateText($submission['idea'] ?: 'No idea provided', 60); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-5 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center mr-3">
                                                        <i class="fas fa-user text-gray-600 text-sm"></i>
                                                    </div>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($submission['leader_name']); ?></div>
                                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($submission['leader_email']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-5 whitespace-nowrap">
                                                <?php if ($submission['floor_number']): ?>
                                                    <div class="flex items-center">
                                                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                                            <i class="fas fa-map-marker-alt text-blue-600 text-sm"></i>
                                                        </div>
                                                        <div>
                                                            <div class="text-sm font-medium text-gray-900">Floor <?php echo htmlspecialchars($submission['floor_number']); ?></div>
                                                            <div class="text-sm text-gray-500">Room <?php echo htmlspecialchars($submission['room_number']); ?></div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                        <i class="fas fa-question-circle mr-1"></i>
                                                        Not Assigned
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-5">
                                                <div class="max-w-xs">
                                                    <?php 
                                                    $tech_stack = $submission['tech_stack'];
                                                    $technologies = array_map('trim', explode(',', $tech_stack));
                                                    $display_count = 3;
                                                    ?>
                                                    <div class="flex flex-wrap gap-1">
                                                        <?php for ($i = 0; $i < min(count($technologies), $display_count); $i++): ?>
                                                            <span class="tech-badge"><?php echo htmlspecialchars($technologies[$i]); ?></span>
                                                        <?php endfor; ?>
                                                        <?php if (count($technologies) > $display_count): ?>
                                                            <span class="tech-badge bg-gray-500">+<?php echo count($technologies) - $display_count; ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-5 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                                        <i class="fas fa-clock text-green-600 text-sm"></i>
                                                    </div>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900"><?php echo date('M j, Y', strtotime($submission['submitted_at'])); ?></div>
                                                        <div class="text-sm text-gray-500"><?php echo date('g:i A', strtotime($submission['submitted_at'])); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-5 whitespace-nowrap">
                                                <div class="flex flex-col space-y-2">
                                                    <a href="<?php echo htmlspecialchars($submission['github_link']); ?>" target="_blank" class="link-button github-btn text-sm">
                                                        <i class="fab fa-github"></i>
                                                        GitHub
                                                    </a>
                                                    <?php if ($submission['live_link']): ?>
                                                        <a href="<?php echo htmlspecialchars($submission['live_link']); ?>" target="_blank" class="link-button live-btn text-sm">
                                                            <i class="fas fa-external-link-alt"></i>
                                                            Live Demo
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($submission['demo_video']): ?>
                                                        <a href="<?php echo htmlspecialchars($submission['demo_video']); ?>" target="_blank" class="link-button demo-btn text-sm">
                                                            <i class="fas fa-video"></i>
                                                            Video
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>

</html>