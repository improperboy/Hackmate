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

if ($_POST && isset($_POST['export_data'])) {
    $data_type = sanitize($_POST['data_type']);
    $filename = $data_type . '_export_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    try {
        switch ($data_type) {
            case 'users':
                fputcsv($output, ['ID', 'Name', 'Email', 'Role', 'Created At']);
                $stmt = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at ASC");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, $row);
                }
                break;
            case 'teams':
                fputcsv($output, ['ID', 'Name', 'Idea', 'Problem Statement', 'Leader Name', 'Leader Email', 'Floor', 'Room', 'Status', 'Created At', 'Members']);
                $stmt = $pdo->query("
                    SELECT t.id, t.name, t.idea, t.problem_statement, 
                           u.name as leader_name, u.email as leader_email,
                           f.floor_number, r.room_number, t.status, t.created_at,
                           (SELECT GROUP_CONCAT(u2.name SEPARATOR '; ') FROM team_members tm JOIN users u2 ON tm.user_id = u2.id WHERE tm.team_id = t.id) as members
                    FROM teams t
                    LEFT JOIN users u ON t.leader_id = u.id
                    LEFT JOIN floors f ON t.floor_id = f.id
                    LEFT JOIN rooms r ON t.room_id = r.id
                    ORDER BY t.created_at ASC
                ");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, $row);
                }
                break;
            case 'submissions':
                fputcsv($output, ['ID', 'Team Name', 'GitHub Link', 'Live Link', 'Tech Stack', 'Demo Video', 'Submitted At']);
                $stmt = $pdo->query("
                    SELECT s.id, t.name as team_name, s.github_link, s.live_link, s.tech_stack, s.demo_video, s.submitted_at
                    FROM submissions s
                    JOIN teams t ON s.team_id = t.id
                    ORDER BY s.submitted_at ASC
                ");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, $row);
                }
                break;
            case 'scores':
                fputcsv($output, ['ID', 'Team Name', 'Mentor Name', 'Round Name', 'Score', 'Max Score', 'Comment', 'Scored At']);
                $stmt = $pdo->query("
                    SELECT sc.id, t.name as team_name, m.name as mentor_name, mr.round_name, sc.score, mr.max_score, sc.comment, sc.created_at
                    FROM scores sc
                    JOIN teams t ON sc.team_id = t.id
                    JOIN users m ON sc.mentor_id = m.id
                    JOIN mentoring_rounds mr ON sc.round_id = mr.id
                    ORDER BY sc.created_at ASC
                ");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, $row);
                }
                break;
            case 'support_messages':
                fputcsv($output, ['ID', 'From Name', 'From Email', 'From Role', 'To Role', 'Message', 'Floor', 'Room', 'Status', 'Received At', 'Resolved At', 'Resolved By']);
                $stmt = $pdo->query("
                    SELECT sm.id, u.name as from_name, u.email as from_email, sm.from_role, sm.to_role, sm.message,
                           f.floor_number, r.room_number, sm.status, sm.created_at, sm.resolved_at, res_u.name as resolved_by_name
                    FROM support_messages sm
                    JOIN users u ON sm.from_id = u.id
                    LEFT JOIN floors f ON sm.floor_id = f.id
                    LEFT JOIN rooms r ON sm.room_id = r.id
                    LEFT JOIN users res_u ON sm.resolved_by = res_u.id
                    ORDER BY sm.created_at ASC
                ");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, $row);
                }
                break;
            default:
                // If an invalid type is requested, close the output and show an error
                fclose($output);
                die('Invalid data type specified for export.');
        }
    } catch (PDOException $e) {
        fclose($output);
        die('Database error during export: ' . $e->getMessage());
    }

    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Data - HackMate</title>
    
    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .export-card {
            transition: transform 0.2s ease-in-out;
        }
        .export-card:hover {
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
                    <h1 class="text-lg font-semibold text-gray-900">Export Data</h1>
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
                                <i class="fas fa-download text-blue-600 mr-3"></i>
                                Export Data
                            </h1>
                            <p class="text-gray-600 mt-1">Export hackathon data in various formats</p>
                        </div>
                    </div>
                </div>

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

                <div class="max-w-4xl mx-auto">

                <!-- Export Form -->
                <div class="export-card bg-white rounded-xl shadow-sm p-6 border border-gray-100 mb-6">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-file-csv text-blue-600 mr-2"></i>
                        Export to CSV
                    </h3>
            <form method="POST" class="space-y-4">
                <div>
                    <label for="data_type" class="block text-sm font-medium text-gray-700 mb-2">Data Type *</label>
                    <select id="data_type" name="data_type" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Data Type</option>
                        <option value="users">Users</option>
                        <option value="teams">Teams</option>
                        <option value="submissions">Submissions</option>
                        <option value="scores">Scores</option>
                        <option value="support_messages">Support Messages</option>
                    </select>
                </div>
                <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700">
                                Select the type of data you wish to export. The data will be downloaded as a CSV file.
                            </p>
                        </div>
                    </div>
                </div>
                    <button type="submit" name="export_data" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors">
                        <i class="fas fa-file-csv mr-2"></i>Export to CSV
                    </button>
                </form>
                </div>

                <div class="export-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-file-pdf text-red-600 mr-2"></i>
                        Export Team Report (PDF)
                    </h3>
                    <p class="text-gray-700 mb-4">
                        You can export a detailed report for any team as a PDF. Go to <a href="teams.php" class="text-blue-600 hover:underline">Manage Teams</a>, 
                        view a team's details, and click the "Export PDF Report" button.
                    </p>
                    <a href="teams.php" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors">
                        <i class="fas fa-users mr-2"></i>Go to Manage Teams
                    </a>
                </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
