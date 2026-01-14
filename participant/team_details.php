<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('participant');
$user = getCurrentUser();

// Get user's team
$stmt = $pdo->prepare("
    SELECT t.*, u.name as leader_name, th.name as theme_name, th.color_code as theme_color, f.floor_number, r.room_number
    FROM teams t 
    JOIN team_members tm ON t.id = tm.team_id 
    LEFT JOIN users u ON t.leader_id = u.id
    LEFT JOIN themes th ON t.theme_id = th.id
    LEFT JOIN floors f ON t.floor_id = f.id
    LEFT JOIN rooms r ON t.room_id = r.id
    WHERE tm.user_id = ? AND t.status = 'approved'
");
$stmt->execute([$user['id']]);
$team = $stmt->fetch();

if (!$team) {
    header('Location: dashboard.php');
    exit();
}

// Get team members
$stmt = $pdo->prepare("
    SELECT u.*, tm.joined_at 
    FROM team_members tm 
    JOIN users u ON tm.user_id = u.id 
    WHERE tm.team_id = ?
    ORDER BY tm.joined_at ASC
");
$stmt->execute([$team['id']]);
$team_members = $stmt->fetchAll();

// Get team submission
$stmt = $pdo->prepare("SELECT * FROM submissions WHERE team_id = ?");
$stmt->execute([$team['id']]);
$submission = $stmt->fetch();

// Get team scores (only comments, not scores)
$stmt = $pdo->prepare("
    SELECT s.comment, mr.round_name, u.name as mentor_name, s.created_at
    FROM scores s 
    JOIN mentoring_rounds mr ON s.round_id = mr.id 
    JOIN users u ON s.mentor_id = u.id
    WHERE s.team_id = ? AND s.comment IS NOT NULL AND s.comment != ''
    ORDER BY mr.start_time DESC
");
$stmt->execute([$team['id']]);
$team_feedback = $stmt->fetchAll();

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// Handle team update (only leader can update)
if ($_POST && $team['leader_id'] == $user['id']) {
    $idea = sanitize($_POST['idea']);
    $problem_statement = sanitize($_POST['problem_statement']);
    
    if (empty($idea) || empty($problem_statement)) {
        $error = 'All fields are required';
    } else {
        $stmt = $pdo->prepare("UPDATE teams SET idea = ?, problem_statement = ? WHERE id = ?");
        if ($stmt->execute([$idea, $problem_statement, $team['id']])) {
            $message = 'Team details updated successfully!';
            // Refresh team data
            $team['idea'] = $idea;
            $team['problem_statement'] = $problem_statement;
        } else {
            $error = 'Failed to update team details.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Details - Participant Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
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
                    <h1 class="text-lg font-semibold text-gray-800">Team Details</h1>
                    <div class="w-8"></div> <!-- Spacer for centering -->
                </div>
            </header>

            <!-- Desktop Header -->
            <header class="hidden lg:block bg-white shadow-sm border-b border-gray-200">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">
                                <i class="fas fa-users text-purple-600 mr-2"></i>
                                Team Details
                            </h1>
                            <p class="text-sm text-gray-600 mt-1">Manage your team information and members</p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <span class="text-sm text-gray-600">Team: <span class="font-semibold"><?php echo $team['name']; ?></span></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Scrollable Content -->
            <main class="flex-1 overflow-y-auto">
                <div class="max-w-6xl mx-auto py-6 px-4 lg:px-6">
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Team Information -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold">
                            <i class="fas fa-info-circle text-blue-600"></i>
                            Team Information
                        </h3>
                        <?php if ($team['leader_id'] == $user['id']): ?>
                            <button onclick="toggleEdit()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm transition-colors">
                                <i class="fas fa-edit mr-1"></i>
                                Edit Details
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- View Mode -->
                    <div id="view-mode">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Team Name:</p>
                                <p class="text-gray-900 font-semibold"><?php echo $team['name']; ?></p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600">Team Leader:</p>
                                <p class="text-gray-900"><?php echo $team['leader_name']; ?></p>
                            </div>
                            <?php if ($team['theme_name']): ?>
                            <div>
                                <p class="text-sm font-medium text-gray-600">Theme:</p>
                                <p class="text-gray-900 flex items-center">
                                    <span class="w-3 h-3 rounded-full mr-2" style="background-color: <?php echo $team['theme_color']; ?>"></span>
                                    <?php echo $team['theme_name']; ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            <?php if ($team['floor_number'] && $team['room_number']): ?>
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Location:</p>
                                    <p class="text-gray-900"><?php echo $team['floor_number']; ?> - <?php echo $team['room_number']; ?></p>
                                </div>
                            <?php endif; ?>
                            <div>
                                <p class="text-sm font-medium text-gray-600">Status:</p>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                    <?php echo ucfirst($team['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <p class="text-sm font-medium text-gray-600 mb-2">Project Idea:</p>
                            <p class="text-gray-900 bg-gray-50 p-3 rounded"><?php echo $team['idea'] ?: 'Not provided yet'; ?></p>
                        </div>
                        
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-2">Problem Statement:</p>
                            <p class="text-gray-900 bg-gray-50 p-3 rounded"><?php echo $team['problem_statement'] ?: 'Not provided yet'; ?></p>
                        </div>
                    </div>

                    <!-- Edit Mode (only for team leader) -->
                    <?php if ($team['leader_id'] == $user['id']): ?>
                        <div id="edit-mode" style="display: none;">
                            <form method="POST" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-lightbulb mr-1"></i>
                                        Project Idea *
                                    </label>
                                    <textarea name="idea" required rows="4"
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo $team['idea']; ?></textarea>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-question-circle mr-1"></i>
                                        Problem Statement *
                                    </label>
                                    <textarea name="problem_statement" required rows="4"
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo $team['problem_statement']; ?></textarea>
                                </div>

                                <div class="flex space-x-4">
                                    <button type="submit" 
                                            class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-md transition-colors">
                                        <i class="fas fa-save mr-2"></i>
                                        Save Changes
                                    </button>
                                    <button type="button" onclick="toggleEdit()" 
                                            class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-6 rounded-md transition-colors">
                                        <i class="fas fa-times mr-2"></i>
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Submission Status -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-upload text-orange-600"></i>
                        Project Submission
                    </h3>
                    
                    <?php if ($submission): ?>
                        <div class="bg-green-50 border border-green-200 rounded p-4">
                            <div class="flex items-center mb-3">
                                <i class="fas fa-check-circle text-green-600 mr-2"></i>
                                <h4 class="font-medium text-green-800">Project Submitted Successfully!</h4>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="font-medium text-gray-600">GitHub Repository:</p>
                                    <a href="<?php echo $submission['github_link']; ?>" target="_blank" 
                                       class="text-blue-600 hover:text-blue-800 break-all">
                                        <?php echo $submission['github_link']; ?>
                                    </a>
                                </div>
                                
                                <?php if ($submission['live_link']): ?>
                                    <div>
                                        <p class="font-medium text-gray-600">Live Demo:</p>
                                        <a href="<?php echo $submission['live_link']; ?>" target="_blank" 
                                           class="text-blue-600 hover:text-blue-800 break-all">
                                            <?php echo $submission['live_link']; ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <div>
                                    <p class="font-medium text-gray-600">Tech Stack:</p>
                                    <p class="text-gray-800"><?php echo $submission['tech_stack']; ?></p>
                                </div>
                                
                                <div>
                                    <p class="font-medium text-gray-600">Submitted:</p>
                                    <p class="text-gray-800"><?php echo formatDateTime($submission['submitted_at']); ?></p>
                                </div>
                            </div>
                            
                            <?php if ($team['leader_id'] == $user['id']): ?>
                                <div class="mt-4">
                                    <a href="submit_project.php" 
                                       class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-md text-sm transition-colors">
                                        <i class="fas fa-edit mr-1"></i>
                                        Update Submission
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-yellow-50 border border-yellow-200 rounded p-4">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                                <h4 class="font-medium text-yellow-800">No Submission Yet</h4>
                            </div>
                            <p class="text-yellow-700 text-sm mb-3">Your team hasn't submitted the project yet.</p>
                            
                            <?php if ($team['leader_id'] == $user['id']): ?>
                                <a href="submit_project.php" 
                                   class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-md text-sm transition-colors">
                                    <i class="fas fa-upload mr-1"></i>
                                    Submit Project
                                </a>
                            <?php else: ?>
                                <p class="text-yellow-600 text-sm">Only the team leader can submit the project.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Mentor Feedback -->
                <?php if (!empty($team_feedback)): ?>
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold mb-4">
                            <i class="fas fa-comments text-blue-600"></i>
                            Mentor Feedback (<?php echo count($team_feedback); ?>)
                        </h3>
                        
                        <div class="space-y-4">
                            <?php foreach ($team_feedback as $feedback): ?>
                                <div class="border-l-4 border-blue-400 bg-blue-50 p-4">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="font-medium text-gray-900"><?php echo $feedback['round_name']; ?></h4>
                                        <span class="text-sm text-gray-600">by <?php echo $feedback['mentor_name']; ?></span>
                                    </div>
                                    <p class="text-gray-700 text-sm"><?php echo nl2br($feedback['comment']); ?></p>
                                    <p class="text-xs text-gray-500 mt-2">
                                        <i class="fas fa-clock mr-1"></i>
                                        <?php echo formatDateTime($feedback['created_at']); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Team Members -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-users text-purple-600"></i>
                        Team Members (<?php echo count($team_members); ?>/4)
                    </h3>
                    
                    <div class="space-y-4">
                        <?php foreach ($team_members as $member): ?>
                            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                                <div class="flex-shrink-0">
                                    <?php if ($member['id'] == $team['leader_id']): ?>
                                        <i class="fas fa-crown text-yellow-500 text-xl"></i>
                                    <?php else: ?>
                                        <i class="fas fa-user text-gray-500 text-xl"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="ml-3 flex-1">
                                    <p class="font-medium text-gray-900"><?php echo $member['name']; ?></p>
                                    <p class="text-sm text-gray-500"><?php echo $member['email']; ?></p>
                                    <?php if (!empty($member['tech_stack'])): ?>
                                        <div class="mt-1">
                                            <p class="text-xs text-gray-600 mb-1">
                                                <i class="fas fa-code mr-1"></i>Tech Stack:
                                            </p>
                                            <div class="flex flex-wrap gap-1">
                                                <?php 
                                                $techs = array_map('trim', explode(',', $member['tech_stack']));
                                                foreach ($techs as $tech): 
                                                    if (!empty($tech)):
                                                ?>
                                                    <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                                                        <?php echo htmlspecialchars($tech); ?>
                                                    </span>
                                                <?php 
                                                    endif;
                                                endforeach; 
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <p class="text-xs text-gray-400 mt-1">
                                        <?php if ($member['id'] == $team['leader_id']): ?>
                                            Team Leader
                                        <?php else: ?>
                                            Joined: <?php echo formatDateTime($member['joined_at']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <!-- Team Leader can remove members (except themselves) -->
                                <?php if ($team['leader_id'] == $user['id'] && $member['id'] != $team['leader_id']): ?>
                                    <button onclick="confirmRemoveMember(<?php echo $member['id']; ?>, '<?php echo addslashes($member['name']); ?>')" 
                                            class="ml-2 text-red-600 hover:text-red-800 p-1 rounded" title="Remove member">
                                        <i class="fas fa-user-minus"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (count($team_members) < 4): ?>
                        <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                            <p class="text-blue-800 text-sm">
                                <i class="fas fa-info-circle mr-1"></i>
                                Your team can have up to <?php echo 4 - count($team_members); ?> more member(s).
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Team Actions -->
                    <div class="mt-6 pt-4 border-t">
                        <h4 class="text-md font-semibold mb-3 text-gray-700">
                            <i class="fas fa-cogs text-gray-600"></i>
                            Team Actions
                        </h4>
                        
                        <div class="flex flex-wrap gap-3">
                            <?php if ($team['leader_id'] == $user['id']): ?>
                                <!-- Team Leader Actions -->
                                <button onclick="confirmDeleteTeam()" 
                                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm transition-colors flex items-center">
                                    <i class="fas fa-trash mr-2"></i>
                                    Delete Team
                                </button>
                            <?php else: ?>
                                <!-- Team Member Actions -->
                                <button onclick="confirmLeaveTeam()" 
                                        class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-md text-sm transition-colors flex items-center">
                                    <i class="fas fa-sign-out-alt mr-2"></i>
                                    Leave Team
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($team['leader_id'] == $user['id']): ?>
                            <div class="mt-3">
                                <p class="text-xs text-gray-500">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    As team leader, deleting the team will remove all members and cannot be undone.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="mt-3">
                                <p class="text-xs text-gray-500">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Leaving the team will remove you from all team activities.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        function toggleEdit() {
            const viewMode = document.getElementById('view-mode');
            const editMode = document.getElementById('edit-mode');
            
            if (viewMode.style.display === 'none') {
                viewMode.style.display = 'block';
                editMode.style.display = 'none';
            } else {
                viewMode.style.display = 'none';
                editMode.style.display = 'block';
            }
        }
        
        function confirmRemoveMember(memberId, memberName) {
            if (confirm(`Are you sure you want to remove "${memberName}" from the team?\n\nThis action cannot be undone.`)) {
                window.location.href = `team_actions.php?action=remove_member&member_id=${memberId}`;
            }
        }
        
        function confirmLeaveTeam() {
            if (confirm('Are you sure you want to leave this team?\n\nYou will lose access to all team activities and submissions.\n\nThis action cannot be undone.')) {
                window.location.href = 'team_actions.php?action=leave_team';
            }
        }
        
        function confirmDeleteTeam() {
            const teamName = '<?php echo addslashes($team['name']); ?>';
            if (confirm(`Are you sure you want to delete the entire team "${teamName}"?\n\nThis will:\n- Remove ALL team members\n- Delete team submissions\n- Delete all team data\n\nThis action CANNOT be undone!`)) {
                if (confirm('FINAL WARNING: This will permanently delete the team and all associated data.\n\nAre you absolutely sure?')) {
                    window.location.href = 'team_actions.php?action=delete_team';
                }
            }
        }
    </script>
</body>
</html>
