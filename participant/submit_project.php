<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('participant');
$user = getCurrentUser();

// Get user's team and verify they are the leader
$stmt = $pdo->prepare("
    SELECT t.* FROM teams t 
    JOIN team_members tm ON t.id = tm.team_id 
    WHERE tm.user_id = ? AND t.leader_id = ? AND t.status = 'approved'
");
$stmt->execute([$user['id'], $user['id']]);
$team = $stmt->fetch();

if (!$team) {
    header('Location: dashboard.php');
    exit();
}

// Check submission settings
$stmt = $pdo->query("SELECT * FROM submission_settings WHERE is_active = 1 LIMIT 1");
$submission_settings = $stmt->fetch();

if (!$submission_settings) {
    $error = 'Submissions are not yet started by admin.';
} else {
    $now = new DateTime();
    // Apply timezone adjustment if needed
    // $now->add(new DateInterval('PT3H30M')); // Adds 3 hours and 30 minutes

    $start_time = new DateTime($submission_settings['start_time']);
    $end_time = new DateTime($submission_settings['end_time']);
    
    if ($now < $start_time) {
        $error = 'Submissions have not started yet. Start time: ' . formatDateTime($submission_settings['start_time']);
    } elseif ($now > $end_time) {
        $error = 'Submission deadline has passed. Deadline was: ' . formatDateTime($submission_settings['end_time']);
    }
}

// Get existing submission
$stmt = $pdo->prepare("SELECT * FROM submissions WHERE team_id = ?");
$stmt->execute([$team['id']]);
$existing_submission = $stmt->fetch();

$message = '';
if (!isset($error)) {
    $error = '';
}

// Handle submission
if ($_POST && !$error) {
    $github_link = sanitize($_POST['github_link']);
    $live_link = sanitize($_POST['live_link']);
    $tech_stack = sanitize($_POST['tech_stack']);
    $demo_video = sanitize($_POST['demo_video']);
    
    if (empty($github_link) || empty($tech_stack)) {
        $error = 'GitHub link and tech stack are required';
    } elseif (!filter_var($github_link, FILTER_VALIDATE_URL)) {
        $error = 'Please provide a valid GitHub URL';
    } elseif ($live_link && !filter_var($live_link, FILTER_VALIDATE_URL)) {
        $error = 'Please provide a valid live demo URL';
    } elseif ($demo_video && !filter_var($demo_video, FILTER_VALIDATE_URL)) {
        $error = 'Please provide a valid demo video URL';
    } else {
        if ($existing_submission) {
            // Update existing submission
            $stmt = $pdo->prepare("UPDATE submissions SET github_link = ?, live_link = ?, tech_stack = ?, demo_video = ?, submitted_at = NOW() WHERE team_id = ?");
            if ($stmt->execute([$github_link, $live_link, $tech_stack, $demo_video, $team['id']])) {
                $message = 'Project submission updated successfully!';
            } else {
                $error = 'Failed to update submission. Please try again.';
            }
        } else {
            // Create new submission
            $stmt = $pdo->prepare("INSERT INTO submissions (team_id, github_link, live_link, tech_stack, demo_video) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$team['id'], $github_link, $live_link, $tech_stack, $demo_video])) {
                $message = 'Project submitted successfully!';
            } else {
                $error = 'Failed to submit project. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Project - Participant Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-arrow-left"></i>
                        Back
                    </a>
                    <h1 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-upload text-orange-600"></i>
                        <?php echo $existing_submission ? 'Update' : 'Submit'; ?> Project
                    </h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Team: <?php echo $team['name']; ?></span>
                    <a href="../logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto py-6 px-4">
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

        <!-- Submission Deadline Info -->
        <?php if ($submission_settings && !$error): ?>
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-clock text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>Submission Deadline:</strong> <?php echo formatDateTime($submission_settings['end_time']); ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$error): ?>
            <!-- Submission Form -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-6">
                    <i class="fas fa-file-upload text-green-600"></i>
                    Project Submission Details
                </h3>
                
                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fab fa-github mr-1"></i>
                            GitHub Repository URL *
                        </label>
                        <input type="url" name="github_link" required 
                               value="<?php echo $existing_submission['github_link'] ?? ''; ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="https://github.com/username/repository">
                        <p class="text-xs text-gray-500 mt-1">Your project's GitHub repository URL</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-external-link-alt mr-1"></i>
                            Live Demo URL (Optional)
                        </label>
                        <input type="url" name="live_link" 
                               value="<?php echo $existing_submission['live_link'] ?? ''; ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="https://your-project-demo.com">
                        <p class="text-xs text-gray-500 mt-1">URL where your project is hosted/deployed</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-code mr-1"></i>
                            Technology Stack *
                        </label>
                        <textarea name="tech_stack" required rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="List the technologies, frameworks, and tools used in your project..."><?php echo $existing_submission['tech_stack'] ?? ''; ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">e.g., React, Node.js, MongoDB, Express, etc.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-video mr-1"></i>
                            Demo Video URL (Optional)
                        </label>
                        <input type="url" name="demo_video" 
                               value="<?php echo $existing_submission['demo_video'] ?? ''; ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="https://youtube.com/watch?v=... or https://drive.google.com/...">
                        <p class="text-xs text-gray-500 mt-1">YouTube, Google Drive, or other video platform URL</p>
                    </div>

                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    <strong>Important:</strong> Make sure your GitHub repository is public and contains 
                                    a proper README file with setup instructions. You can update your submission 
                                    multiple times before the deadline.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex space-x-4">
                        <button type="submit" 
                                class="bg-orange-600 hover:bg-orange-700 text-white font-semibold py-2 px-6 rounded-md transition-colors">
                            <i class="fas fa-upload mr-2"></i>
                            <?php echo $existing_submission ? 'Update' : 'Submit'; ?> Project
                        </button>
                        <a href="dashboard.php" 
                           class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-6 rounded-md transition-colors">
                            <i class="fas fa-times mr-2"></i>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Existing Submission Info -->
            <?php if ($existing_submission): ?>
                <div class="bg-white rounded-lg shadow p-6 mt-6">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-info-circle text-blue-600"></i>
                        Current Submission
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-600">GitHub Repository:</p>
                            <a href="<?php echo $existing_submission['github_link']; ?>" target="_blank" 
                               class="text-blue-600 hover:text-blue-800 text-sm break-all">
                                <?php echo $existing_submission['github_link']; ?>
                            </a>
                        </div>
                        
                        <?php if ($existing_submission['live_link']): ?>
                            <div>
                                <p class="text-sm font-medium text-gray-600">Live Demo:</p>
                                <a href="<?php echo $existing_submission['live_link']; ?>" target="_blank" 
                                   class="text-blue-600 hover:text-blue-800 text-sm break-all">
                                    <?php echo $existing_submission['live_link']; ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <p class="text-sm font-medium text-gray-600">Last Updated:</p>
                            <p class="text-sm text-gray-800"><?php echo formatDateTime($existing_submission['submitted_at']); ?></p>
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
