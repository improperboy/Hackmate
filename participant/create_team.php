<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('participant');
$user = getCurrentUser();

// Check if user is already in a team, has pending requests, or is already a team leader
$stmt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE user_id = ?");
$stmt->execute([$user['id']]);
if ($stmt->fetchColumn() > 0) {
    header('Location: dashboard.php');
    exit();
}

// Note: Users can create teams even if they have pending join requests
// All pending requests will be expired when the team is created

// Check if user already has a team as leader (pending or approved only)
// Users can create new teams if their previous team was rejected
$stmt = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE leader_id = ? AND status IN ('pending', 'approved')");
$stmt->execute([$user['id']]);
if ($stmt->fetchColumn() > 0) {
    header('Location: dashboard.php');
    exit();
}

$message = '';
$error = '';

// Get active themes for the form
$stmt = $pdo->prepare("SELECT id, name, description, color_code FROM themes WHERE is_active = TRUE ORDER BY name ASC");
$stmt->execute();
$themes = $stmt->fetchAll();

if ($_POST) {
    $team_name = sanitize($_POST['team_name']);
    $idea = sanitize($_POST['idea']);
    $problem_statement = sanitize($_POST['problem_statement']);
    $tech_skills = sanitize($_POST['tech_skills']);
    $theme_id = (int)$_POST['theme_id'];

    if (empty($team_name) || empty($idea) || empty($problem_statement) || empty($tech_skills) || empty($theme_id)) {
        $error = 'All fields are required.';
    } else {
        // Verify theme exists and is active
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM themes WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$theme_id]);
        if ($stmt->fetchColumn() == 0) {
            $error = 'Invalid theme selected.';
        } else {
            // Check if team name already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE name = ?");
            $stmt->execute([$team_name]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Team name already exists. Please choose a different name.';
            } else {
                // Create team with pending status, leader_id and theme_id set
                $pdo->beginTransaction();
                try {
                    // Clean up any previously rejected teams by this user
                    $stmt = $pdo->prepare("DELETE FROM teams WHERE leader_id = ? AND status = 'rejected'");
                    $stmt->execute([$user['id']]);

                    $stmt = $pdo->prepare("INSERT INTO teams (name, idea, problem_statement, tech_skills, theme_id, leader_id, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                    $stmt->execute([$team_name, $idea, $problem_statement, $tech_skills, $theme_id, $user['id']]);

                    // Expire all pending join requests from this user
                    $stmt = $pdo->prepare("UPDATE join_requests SET status = 'expired', responded_at = NOW() WHERE user_id = ? AND status = 'pending'");
                    $stmt->execute([$user['id']]);

                    $pdo->commit();
                    $message = 'Your team "' . $team_name . '" has been created and sent for admin approval. All your pending join requests have been automatically cancelled. You will be notified once your team is approved and assigned a location.';
                    // Redirect after a short delay to show message
                    header('refresh:3;url=dashboard.php');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Failed to create team. Please try again.';
                }
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
    <title>Create Team - Participant Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden lg:ml-0">
            <!-- Mobile Header -->
            <header class="lg:hidden bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-4 py-3">
                    <button onclick="toggleSidebar()" class="text-gray-600 hover:text-gray-900 transition-colors">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-semibold text-gray-800">Create Team</h1>
                    <div class="w-6"></div> <!-- Spacer for centering -->
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto">
                <div class="max-w-4xl mx-auto py-6 px-4 lg:px-8">
                    <!-- Page Header -->
                    <div class="mb-6">
                        <div class="flex items-center space-x-3 mb-2">
                            <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-blue-500 rounded-xl flex items-center justify-center">
                                <i class="fas fa-plus text-white text-lg"></i>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Create Team</h1>
                                <p class="text-gray-600">Start your hackathon journey by creating a new team</p>
                            </div>
                        </div>
                    </div>
                    <!-- Messages -->
                    <?php if ($message): ?>
                        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl mb-6 flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            <span><?php echo $message; ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl mb-6 flex items-center">
                            <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                            <span><?php echo $error; ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Create Team Form -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 lg:p-8">
                        <div class="flex items-center space-x-3 mb-8">
                            <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-blue-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-users text-white text-sm"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900">Team Details</h3>
                        </div>

                        <form method="POST" class="space-y-8">
                            <div>
                                <label for="team_name" class="block text-sm font-semibold text-gray-700 mb-3">
                                    <i class="fas fa-users mr-2 text-purple-500"></i>
                                    Team Name *
                                </label>
                                <input type="text" id="team_name" name="team_name" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200"
                                    placeholder="e.g., Innovators, CodeMasters">
                            </div>

                            <div>
                                <label for="idea" class="block text-sm font-semibold text-gray-700 mb-3">
                                    <i class="fas fa-lightbulb mr-2 text-yellow-500"></i>
                                    Project Idea *
                                </label>
                                <textarea id="idea" name="idea" required rows="4"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200 resize-none"
                                    placeholder="Briefly describe your project idea..."></textarea>
                            </div>

                            <div>
                                <label for="problem_statement" class="block text-sm font-semibold text-gray-700 mb-3">
                                    <i class="fas fa-question-circle mr-2 text-blue-500"></i>
                                    Problem Statement *
                                </label>
                                <textarea id="problem_statement" name="problem_statement" required rows="4"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200 resize-none"
                                    placeholder="What problem does your project aim to solve?"></textarea>
                            </div>

                            <div>
                                <label for="tech_skills" class="block text-sm font-semibold text-gray-700 mb-3">
                                    <i class="fas fa-code mr-2 text-green-500"></i>
                                    Required Tech Skills for Project *
                                </label>
                                <textarea id="tech_skills" name="tech_skills" required rows="3"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200 resize-none"
                                    placeholder="e.g., React, Node.js, Python, MongoDB, AWS, Machine Learning, UI/UX Design..."></textarea>
                                <p class="text-sm text-gray-600 mt-2 flex items-start">
                                    <i class="fas fa-info-circle mr-2 text-blue-400 mt-0.5 flex-shrink-0"></i>
                                    <span>List the technologies and skills needed for your project. This will help match you with the right mentor who has expertise in these areas.</span>
                                </p>
                            </div>

                            <div>
                                <label for="theme_id" class="block text-sm font-semibold text-gray-700 mb-3">
                                    <i class="fas fa-tag mr-2 text-indigo-500"></i>
                                    Theme Category *
                                </label>
                                <select id="theme_id" name="theme_id" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200 bg-white">
                                    <option value="">Select a theme for your project</option>
                                    <?php foreach ($themes as $theme): ?>
                                        <option value="<?php echo $theme['id']; ?>"
                                            data-color="<?php echo $theme['color_code']; ?>"
                                            title="<?php echo htmlspecialchars($theme['description']); ?>">
                                            <?php echo htmlspecialchars($theme['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-sm text-gray-600 mt-2 flex items-start" id="theme-description">
                                    <i class="fas fa-info-circle mr-2 text-blue-400 mt-0.5 flex-shrink-0"></i>
                                    <span>Choose the theme that best matches your project idea. This cannot be changed once your team is created.</span>
                                </p>
                            </div>

                            <div class="bg-gradient-to-r from-blue-50 to-purple-50 border border-blue-200 rounded-xl p-6">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-500 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-info-circle text-white text-sm"></i>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <h4 class="text-sm font-semibold text-gray-900 mb-2">Important Information</h4>
                                        <p class="text-sm text-gray-700 leading-relaxed">
                                            After creating your team, it will be sent to the admin for approval.
                                            Once approved, you will be assigned a floor and room number, and you can then invite other participants to join your team.
                                            <br><br><strong>Note:</strong> The theme selection cannot be changed once your team is created.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row gap-4 pt-6">
                                <button type="submit"
                                    class="flex-1 sm:flex-none bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white font-semibold py-3 px-8 rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl">
                                    <i class="fas fa-plus mr-2"></i>
                                    Create Team
                                </button>
                                <a href="dashboard.php"
                                    class="flex-1 sm:flex-none bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-8 rounded-xl transition-all duration-200 text-center border border-gray-300">
                                    <i class="fas fa-arrow-left mr-2"></i>
                                    Back to Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        const themeDescriptions = {
            <?php foreach ($themes as $theme): ?>
                <?php echo $theme['id']; ?>: "<?php echo addslashes($theme['description']); ?>",
            <?php endforeach; ?>};

        // Update theme description when selection changes
        document.getElementById('theme_id').addEventListener('change', function() {
            const selectedThemeId = this.value;
            const descriptionElement = document.getElementById('theme-description');

            if (selectedThemeId && themeDescriptions[selectedThemeId]) {
                descriptionElement.innerHTML = '<i class="fas fa-info-circle mr-2 text-blue-400 mt-0.5 flex-shrink-0"></i><span>' + themeDescriptions[selectedThemeId] + '</span>';
                descriptionElement.className = 'text-sm text-gray-700 mt-2 flex items-start font-medium';
            } else {
                descriptionElement.innerHTML = '<i class="fas fa-info-circle mr-2 text-blue-400 mt-0.5 flex-shrink-0"></i><span>Choose the theme that best matches your project idea. This cannot be changed once your team is created.</span>';
                descriptionElement.className = 'text-sm text-gray-600 mt-2 flex items-start';
            }
        });

        // Add visual feedback for theme selection
        document.getElementById('theme_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const color = selectedOption.getAttribute('data-color');

            if (color) {
                this.style.borderLeftColor = color;
                this.style.borderLeftWidth = '4px';
                this.style.boxShadow = `0 0 0 3px ${color}20`;
            } else {
                this.style.borderLeftColor = '';
                this.style.borderLeftWidth = '';
                this.style.boxShadow = '';
            }
        });
    </script>
</body>

</html>