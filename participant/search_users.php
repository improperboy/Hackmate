<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('participant');
$user = getCurrentUser();

// Check if user is a team leader with approved team
$stmt = $pdo->prepare("
    SELECT t.* FROM teams t 
    WHERE t.leader_id = ? AND t.status = 'approved'
");
$stmt->execute([$user['id']]);
$user_team = $stmt->fetch();

if (!$user_team) {
    header('Location: dashboard.php');
    exit();
}

// Check current team size
$stmt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ?");
$stmt->execute([$user_team['id']]);
$current_team_size = $stmt->fetchColumn();

require_once '../includes/system_settings.php';
$team_limits = getTeamSizeLimits();
$max_team_size = $team_limits['max'];
$can_invite = $current_team_size < $max_team_size;

$message = '';
$error = '';

// Handle invitation sending
if ($_POST && isset($_POST['invite_user'])) {
    $invite_user_id = $_POST['invite_user_id'];
    $invite_message = trim($_POST['invite_message'] ?? '');
    
    if (!$can_invite) {
        $error = 'Your team is already full.';
    } else {
        // Check if user exists and is not already in a team
        $stmt = $pdo->prepare("
            SELECT u.*, 
                   (SELECT COUNT(*) FROM team_members tm 
                    JOIN teams t ON tm.team_id = t.id 
                    WHERE tm.user_id = u.id AND t.status = 'approved') as in_team,
                   (SELECT COUNT(*) FROM teams WHERE leader_id = u.id AND status IN ('pending', 'approved')) as is_leader
            FROM users u 
            WHERE u.id = ? AND u.role = 'participant'
        ");
        $stmt->execute([$invite_user_id]);
        $target_user = $stmt->fetch();
        
        if (!$target_user) {
            $error = 'User not found.';
        } elseif ($target_user['in_team'] > 0 || $target_user['is_leader'] > 0) {
            $error = 'This user is already part of a team or has their own team.';
        } else {
            // Check if invitation already exists
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM team_invitations 
                WHERE team_id = ? AND to_user_id = ? AND status = 'pending'
            ");
            $stmt->execute([$user_team['id'], $invite_user_id]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'You have already sent an invitation to this user.';
            } else {
                // Send invitation
                $stmt = $pdo->prepare("
                    INSERT INTO team_invitations (team_id, from_user_id, to_user_id, message) 
                    VALUES (?, ?, ?, ?)
                ");
                if ($stmt->execute([$user_team['id'], $user['id'], $invite_user_id, $invite_message])) {
                    $message = 'Invitation sent successfully!';
                } else {
                    $error = 'Failed to send invitation. Please try again.';
                }
            }
        }
    }
}

// Search functionality
$search_query = $_GET['search'] ?? '';
$tech_filter = $_GET['tech'] ?? '';

$where_conditions = ["u.role = 'participant'", "u.id != ?"];
$params = [$user['id']];

if (!empty($search_query)) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

if (!empty($tech_filter)) {
    $where_conditions[] = "u.tech_stack LIKE ?";
    $params[] = "%$tech_filter%";
}

$sql = "
    SELECT u.*, 
           (SELECT COUNT(*) FROM team_members tm 
            JOIN teams t ON tm.team_id = t.id 
            WHERE tm.user_id = u.id AND t.status = 'approved') as in_team,
           (SELECT COUNT(*) FROM teams WHERE leader_id = u.id AND status IN ('pending', 'approved')) as is_leader,
           (SELECT COUNT(*) FROM team_invitations 
            WHERE team_id = ? AND to_user_id = u.id AND status = 'pending') as has_pending_invite
    FROM users u 
    WHERE " . implode(' AND ', $where_conditions) . "
    ORDER BY u.name ASC
    LIMIT 20
";

array_unshift($params, $user_team['id']);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$available_users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Users - Hackathon Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="dashboard.php" class="text-gray-600 hover:text-gray-800 mr-4">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h1 class="text-lg md:text-xl font-bold text-gray-800">
                        <i class="fas fa-search text-blue-600"></i>
                        Search Users
                    </h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600 text-sm">Team: <?php echo htmlspecialchars($user_team['name']); ?></span>
                    <span class="text-gray-600 text-sm">(<?php echo $current_team_size; ?>/<?php echo $max_team_size; ?>)</span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4">
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!$can_invite): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                Your team is full (<?php echo $max_team_size; ?> members). You cannot invite more users.
            </div>
        <?php endif; ?>

        <!-- Search Form -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4">
                <i class="fas fa-filter text-blue-600"></i>
                Search & Filter Users
            </h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Name or Email</label>
                    <input type="text" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search_query); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Search by name or email...">
                </div>
                <div>
                    <label for="tech" class="block text-sm font-medium text-gray-700 mb-1">Tech Stack</label>
                    <input type="text" id="tech" name="tech" 
                           value="<?php echo htmlspecialchars($tech_filter); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                           placeholder="e.g., React, Python, Node.js...">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <i class="fas fa-search mr-2"></i>
                        Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Users List -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold">
                    <i class="fas fa-users text-green-600"></i>
                    Available Users (<?php echo count($available_users); ?>)
                </h3>
            </div>
            
            <?php if (empty($available_users)): ?>
                <div class="p-6 text-center">
                    <i class="fas fa-user-slash text-gray-300 text-4xl mb-4"></i>
                    <p class="text-gray-500">No users found matching your criteria.</p>
                    <p class="text-gray-400 text-sm mt-2">Try adjusting your search filters.</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($available_users as $available_user): ?>
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-user text-blue-600"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <h4 class="text-lg font-medium text-gray-900">
                                                <?php echo htmlspecialchars($available_user['name']); ?>
                                            </h4>
                                            <p class="text-sm text-gray-600">
                                                <i class="fas fa-envelope mr-1"></i>
                                                <?php echo htmlspecialchars($available_user['email']); ?>
                                            </p>
                                            <?php if (!empty($available_user['tech_stack'])): ?>
                                                <div class="mt-2">
                                                    <p class="text-sm text-gray-700">
                                                        <i class="fas fa-code mr-1"></i>
                                                        <strong>Tech Stack:</strong>
                                                    </p>
                                                    <div class="mt-1 flex flex-wrap gap-1">
                                                        <?php 
                                                        $techs = array_map('trim', explode(',', $available_user['tech_stack']));
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
                                        </div>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <?php if ($available_user['in_team'] > 0 || $available_user['is_leader'] > 0): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                            <i class="fas fa-users mr-1"></i>
                                            Already in team
                                        </span>
                                    <?php elseif ($available_user['has_pending_invite'] > 0): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                            <i class="fas fa-clock mr-1"></i>
                                            Invitation sent
                                        </span>
                                    <?php elseif ($can_invite): ?>
                                        <button onclick="openInviteModal(<?php echo $available_user['id']; ?>, '<?php echo htmlspecialchars($available_user['name']); ?>')"
                                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                                            <i class="fas fa-paper-plane mr-2"></i>
                                            Invite to Team
                                        </button>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-600">
                                            <i class="fas fa-ban mr-1"></i>
                                            Team full
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Invite Modal -->
    <div id="inviteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">
                        <i class="fas fa-paper-plane text-green-600 mr-2"></i>
                        Send Team Invitation
                    </h3>
                    <button onclick="closeInviteModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form id="inviteForm">
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 mb-2">
                            Inviting: <span id="invite_user_name" class="font-medium"></span>
                        </p>
                        <p class="text-sm text-gray-600 mb-4">
                            To team: <strong><?php echo htmlspecialchars($user_team['name']); ?></strong>
                        </p>
                    </div>
                    <div class="mb-4">
                        <label for="invite_message" class="block text-sm font-medium text-gray-700 mb-1">
                            Message (Optional)
                        </label>
                        <textarea id="invite_message" name="invite_message" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500"
                                  placeholder="Add a personal message to your invitation..."></textarea>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeInviteModal()"
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                            <i class="fas fa-paper-plane mr-2"></i>
                            Send Invitation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentUserId = null;
        let currentUserName = '';

        function openInviteModal(userId, userName) {
            currentUserId = userId;
            currentUserName = userName;
            document.getElementById('invite_user_name').textContent = userName;
            document.getElementById('invite_message').value = '';
            document.getElementById('inviteModal').classList.remove('hidden');
        }

        function closeInviteModal() {
            document.getElementById('inviteModal').classList.add('hidden');
            currentUserId = null;
            currentUserName = '';
        }

        // Close modal when clicking outside
        document.getElementById('inviteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeInviteModal();
            }
        });

        // Handle AJAX invitation sending
        function sendInvitation() {
            if (!currentUserId) return;

            const message = document.getElementById('invite_message').value.trim();
            const submitBtn = document.querySelector('#inviteModal button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';

            fetch('../ajax/send_invitation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: currentUserId,
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showMessage(data.message, 'success');
                    closeInviteModal();
                    
                    // Update the button for this user
                    const userButton = document.querySelector(`button[onclick*="${currentUserId}"]`);
                    if (userButton) {
                        userButton.outerHTML = `
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                <i class="fas fa-clock mr-1"></i>
                                Invitation sent
                            </span>
                        `;
                    }
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred while sending the invitation.', 'error');
            })
            .finally(() => {
                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        }

        function showMessage(message, type) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-md shadow-lg ${
                type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'
            }`;
            messageDiv.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} mr-2"></i>
                    ${message}
                </div>
            `;
            
            document.body.appendChild(messageDiv);
            
            // Remove after 5 seconds
            setTimeout(() => {
                messageDiv.remove();
            }, 5000);
        }

        // Update the form submission to use AJAX
        document.addEventListener('DOMContentLoaded', function() {
            const inviteForm = document.querySelector('#inviteModal form');
            if (inviteForm) {
                inviteForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    sendInvitation();
                });
            }
        });
    </script>
</body>
</html>