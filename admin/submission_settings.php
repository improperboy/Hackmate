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

$message = '';
$error = '';

// Fetch current settings
$stmt = $pdo->query("SELECT * FROM submission_settings ORDER BY created_at DESC LIMIT 1");
$current_settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle Update Settings
if ($_POST && isset($_POST['update_settings'])) {
    $start_time = sanitize($_POST['start_time']);
    $end_time = sanitize($_POST['end_time']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($start_time) || empty($end_time)) {
        $error = 'Start and End times are required.';
    } elseif (new DateTime($start_time) >= new DateTime($end_time)) {
        $error = 'End time must be after start time.';
    } else {
        try {
            if ($current_settings) {
                // Update existing settings (always update the latest one)
                $stmt = $pdo->prepare("UPDATE submission_settings SET start_time = ?, end_time = ?, is_active = ? WHERE id = ?");
                if ($stmt->execute([$start_time, $end_time, $is_active, $current_settings['id']])) {
                    $message = 'Submission settings updated successfully!';
                    // Re-fetch to update current_settings variable
                    $stmt = $pdo->query("SELECT * FROM submission_settings ORDER BY created_at DESC LIMIT 1");
                    $current_settings = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = 'Failed to update submission settings.';
                }
            } else {
                // Insert new settings if none exist
                $stmt = $pdo->prepare("INSERT INTO submission_settings (start_time, end_time, is_active) VALUES (?, ?, ?)");
                if ($stmt->execute([$start_time, $end_time, $is_active])) {
                    $message = 'Submission settings created successfully!';
                    // Re-fetch to update current_settings variable
                    $stmt = $pdo->query("SELECT * FROM submission_settings ORDER BY created_at DESC LIMIT 1");
                    $current_settings = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = 'Failed to create submission settings.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submission Settings - HackMate</title>

    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        .settings-card {
            transition: all 0.3s ease-in-out;
        }

        .settings-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
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

        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
                    <h1 class="text-lg font-semibold text-gray-900">Submission Settings</h1>
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
                                <i class="fas fa-cogs text-blue-600 mr-3"></i>
                                Submission Settings
                            </h1>
                            <p class="text-gray-600 mt-1">Configure project submission periods and settings</p>
                        </div>

                        <!-- Status Badge -->
                        <div class="flex items-center space-x-3">
                            <?php if ($current_settings && $current_settings['is_active']): ?>
                                <span class="bg-green-100 text-green-800 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    Submissions Active
                                </span>
                            <?php else: ?>
                                <span class="bg-red-100 text-red-800 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-times-circle mr-2"></i>
                                    Submissions Disabled
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg shadow-sm animate-fade-in">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-500 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-green-800 font-medium"><?php echo $message; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg shadow-sm animate-fade-in">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-red-800 font-medium"><?php echo $error; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Submission Settings Form -->
                <div class="settings-card bg-white rounded-xl shadow-sm p-8 border border-gray-100 mb-8 animate-fade-in">
                    <div class="flex items-center justify-between mb-8">
                        <h3 class="text-2xl font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-calendar-alt text-blue-600 mr-3"></i>
                            Configure Submission Period
                        </h3>
                        <span class="bg-blue-100 text-blue-800 text-xs font-medium px-3 py-1 rounded-full">
                            Admin Settings
                        </span>
                    </div>
                    <form method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="start_time" class="block text-sm font-medium text-gray-700 mb-3">
                                    <i class="fas fa-play-circle mr-2 text-green-500"></i>
                                    Submission Start Time *
                                </label>
                                <input type="datetime-local" id="start_time" name="start_time" required
                                    value="<?php echo $current_settings ? str_replace(' ', 'T', $current_settings['start_time']) : ''; ?>"
                                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                            </div>
                            <div>
                                <label for="end_time" class="block text-sm font-medium text-gray-700 mb-3">
                                    <i class="fas fa-stop-circle mr-2 text-red-500"></i>
                                    Submission End Time *
                                </label>
                                <input type="datetime-local" id="end_time" name="end_time" required
                                    value="<?php echo $current_settings ? str_replace(' ', 'T', $current_settings['end_time']) : ''; ?>"
                                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                            </div>
                        </div>

                        <div class="bg-gray-50 rounded-xl p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <input type="checkbox" id="is_active" name="is_active"
                                        <?php echo $current_settings && $current_settings['is_active'] ? 'checked' : ''; ?>
                                        class="h-5 w-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500 transition-all">
                                    <label for="is_active" class="ml-3 block text-lg font-medium text-gray-900">
                                        Enable Project Submissions
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <?php if ($current_settings && $current_settings['is_active']): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-2"></i>
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                            <i class="fas fa-times-circle mr-2"></i>
                                            Inactive
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="text-sm text-gray-600 mt-3 ml-8">
                                When enabled, team leaders can submit and update their project details within the specified time period.
                            </p>
                        </div>

                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-6">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle text-blue-500 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <h4 class="text-lg font-medium text-blue-900 mb-2">Important Notes</h4>
                                    <ul class="text-sm text-blue-800 space-y-1">
                                        <li class="flex items-center">
                                            <i class="fas fa-check mr-2 text-blue-600"></i>
                                            Teams can only submit during the active period
                                        </li>
                                        <li class="flex items-center">
                                            <i class="fas fa-check mr-2 text-blue-600"></i>
                                            Settings take effect immediately after saving
                                        </li>
                                        <li class="flex items-center">
                                            <i class="fas fa-check mr-2 text-blue-600"></i>
                                            Teams can update submissions until the end time
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                            <div class="text-sm text-gray-600">
                                <i class="fas fa-clock mr-2"></i>
                                Changes will take effect immediately
                            </div>
                            <button type="submit" name="update_settings"
                                class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold py-3 px-8 rounded-xl transition-all duration-300 shadow-md hover:shadow-lg">
                                <i class="fas fa-save mr-2"></i>
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Current Settings Display -->
                <div class="settings-card bg-white rounded-xl shadow-sm p-8 border border-gray-100 animate-fade-in">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-2xl font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-clock text-indigo-600 mr-3"></i>
                            Current Submission Period
                        </h3>
                        <span class="bg-indigo-100 text-indigo-800 text-xs font-medium px-3 py-1 rounded-full">
                            Live Status
                        </span>
                    </div>

                    <?php if ($current_settings): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-6 border border-green-200">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-play text-green-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-green-700">Start Time</p>
                                        <p class="text-lg font-bold text-green-900"><?php echo date('M j, Y', strtotime($current_settings['start_time'])); ?></p>
                                        <p class="text-sm text-green-600"><?php echo date('g:i A', strtotime($current_settings['start_time'])); ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gradient-to-br from-red-50 to-rose-50 rounded-xl p-6 border border-red-200">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-stop text-red-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-red-700">End Time</p>
                                        <p class="text-lg font-bold text-red-900"><?php echo date('M j, Y', strtotime($current_settings['end_time'])); ?></p>
                                        <p class="text-sm text-red-600"><?php echo date('g:i A', strtotime($current_settings['end_time'])); ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-6 border border-blue-200">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-toggle-<?php echo $current_settings['is_active'] ? 'on' : 'off'; ?> text-blue-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-blue-700">Status</p>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                            <?php echo $current_settings['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <i class="fas fa-<?php echo $current_settings['is_active'] ? 'check' : 'times'; ?>-circle mr-2"></i>
                                            <?php echo $current_settings['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gradient-to-br from-purple-50 to-violet-50 rounded-xl p-6 border border-purple-200">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-history text-purple-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-purple-700">Last Updated</p>
                                        <p class="text-lg font-bold text-purple-900"><?php echo date('M j, Y', strtotime($current_settings['created_at'])); ?></p>
                                        <p class="text-sm text-purple-600"><?php echo date('g:i A', strtotime($current_settings['created_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Time Remaining Display -->
                        <?php if ($current_settings['is_active']): ?>
                            <?php
                            $now = new DateTime();
                            $start = new DateTime($current_settings['start_time']);
                            $end = new DateTime($current_settings['end_time']);
                            ?>
                            <div class="mt-6 p-6 bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl text-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="text-lg font-semibold mb-2">Submission Period Status</h4>
                                        <?php if ($now < $start): ?>
                                            <p class="text-blue-100">Submissions will open in <?php echo $start->diff($now)->format('%d days, %h hours'); ?></p>
                                        <?php elseif ($now >= $start && $now <= $end): ?>
                                            <p class="text-green-100">✅ Submissions are currently OPEN</p>
                                            <p class="text-blue-100 text-sm">Closes in <?php echo $end->diff($now)->format('%d days, %h hours'); ?></p>
                                        <?php else: ?>
                                            <p class="text-red-100">❌ Submission period has ended</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-right">
                                        <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center">
                                            <?php if ($now >= $start && $now <= $end): ?>
                                                <i class="fas fa-check text-2xl"></i>
                                            <?php elseif ($now < $start): ?>
                                                <i class="fas fa-clock text-2xl"></i>
                                            <?php else: ?>
                                                <i class="fas fa-times text-2xl"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="mx-auto w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-6">
                                <i class="fas fa-calendar-times text-gray-400 text-3xl"></i>
                            </div>
                            <h4 class="text-lg font-medium text-gray-900 mb-2">No Settings Configured</h4>
                            <p class="text-gray-500 max-w-sm mx-auto">Configure the submission period above to allow teams to submit their projects.</p>
                        </div>
                    <?php endif; ?>
                </div>
        </div>
</body>

</html>