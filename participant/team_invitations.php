<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('participant');
$user = getCurrentUser();

// Check if user is already in a team
$stmt = $pdo->prepare("
    SELECT t.*, u.name as leader_name 
    FROM teams t 
    JOIN team_members tm ON t.id = tm.team_id 
    LEFT JOIN users u ON t.leader_id = u.id
    WHERE tm.user_id = ? AND t.status = 'approved'
");
$stmt->execute([$user['id']]);
$user_team = $stmt->fetch();

// Check if user is a team leader
$stmt = $pdo->prepare("SELECT * FROM teams WHERE leader_id = ? AND status IN ('pending', 'approved')");
$stmt->execute([$user['id']]);
$is_team_leader = $stmt->fetch();

$message = '';
$error = '';

// Handle invitation response
if ($_POST && isset($_POST['action'])) {
    $invitation_id = $_POST['invitation_id'];
    $action = $_POST['action'];
    
    if (!in_array($action, ['accept', 'reject'])) {
        $error = 'Invalid action.';
    } else {
        // Get invitation details
        $stmt = $pdo->prepare("
            SELECT ti.*, t.name as team_name, u.name as from_user_name, t.leader_id
            FROM team_invitations ti
            JOIN teams t ON ti.team_id = t.id
            JOIN users u ON ti.from_user_id = u.id
            WHERE ti.id = ? AND ti.to_user_id = ? AND ti.status = 'pending'
        ");
        $stmt->execute([$invitation_id, $user['id']]);
        $invitation = $stmt->fetch();
        
        if (!$invitation) {
            $error = 'Invitation not found or already processed.';
        } elseif ($user_team || $is_team_leader) {
            $error = 'You are already part of a team or have your own team.';
        } else {
            try {
                $pdo->beginTransaction();
                
                if ($action === 'accept') {
                    // Check if team still has space
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ?");
                    $stmt->execute([$invitation['team_id']]);
                    $current_members = $stmt->fetchColumn();
                    
                    if ($current_members >= 4) {
                        $error = 'Team is now full. Cannot accept invitation.';
                        $pdo->rollBack();
                    } else {
                        // Add user to team
                        $stmt = $pdo->prepare("INSERT INTO team_members (user_id, team_id) VALUES (?, ?)");
                        $stmt->execute([$user['id'], $invitation['team_id']]);
                        
                        // Update invitation status
                        $stmt = $pdo->prepare("
                            UPDATE team_invitations 
                            SET status = 'accepted', responded_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$invitation_id]);
                        
                        // Reject all other pending invitations for this user
                        $stmt = $pdo->prepare("
                            UPDATE team_invitations 
                            SET status = 'rejected', responded_at = NOW() 
                            WHERE to_user_id = ? AND status = 'pending' AND id != ?
                        ");
                        $stmt->execute([$user['id'], $invitation_id]);
                        
                        $pdo->commit();
                        $message = "Successfully joined team: " . htmlspecialchars($invitation['team_name']);
                        
                        // Redirect to dashboard after 2 seconds
                        echo "<script>setTimeout(function(){ window.location.href='dashboard.php'; }, 2000);</script>";
                    }
                } else {
                    // Reject invitation
                    $stmt = $pdo->prepare("
                        UPDATE team_invitations 
                        SET status = 'rejected', responded_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$invitation_id]);
                    
                    $pdo->commit();
                    $message = "Invitation rejected.";
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error processing invitation: ' . $e->getMessage();
            }
        }
    }
}

// Get pending invitations for current user
$stmt = $pdo->prepare("
    SELECT ti.*, t.name as team_name, t.idea, t.problem_statement,
           u.name as from_user_name, u.email as from_user_email,
           (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as current_members,
           (SELECT GROUP_CONCAT(u2.name SEPARATOR ', ') 
            FROM team_members tm 
            JOIN users u2 ON tm.user_id = u2.id 
            WHERE tm.team_id = t.id) as team_members
    FROM team_invitations ti
    JOIN teams t ON ti.team_id = t.id
    JOIN users u ON ti.from_user_id = u.id
    WHERE ti.to_user_id = ? AND ti.status = 'pending' AND t.status = 'approved'
    ORDER BY ti.created_at DESC
");
$stmt->execute([$user['id']]);
$pending_invitations = $stmt->fetchAll();

// Get invitation history
$stmt = $pdo->prepare("
    SELECT ti.*, t.name as team_name, u.name as from_user_name,
           CASE 
               WHEN ti.status = 'accepted' THEN 'Accepted'
               WHEN ti.status = 'rejected' THEN 'Rejected'
               ELSE 'Pending'
           END as status_text
    FROM team_invitations ti
    JOIN teams t ON ti.team_id = t.id
    JOIN users u ON ti.from_user_id = u.id
    WHERE ti.to_user_id = ? AND ti.status != 'pending'
    ORDER BY ti.responded_at DESC
    LIMIT 10
");
$stmt->execute([$user['id']]);
$invitation_history = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Invitations - Hackathon Management</title>
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
                        <i class="fas fa-envelope text-blue-600"></i>
                        Team Invitations
                    </h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600 text-sm">Welcome, <?php echo htmlspecialchars($user['name']); ?></span>
                    <a href="../logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="hidden md:inline ml-1">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4">
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($user_team || $is_team_leader): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4">
                <i class="fas fa-info-circle mr-2"></i>
                You are already part of a team or have your own team. You cannot accept new invitations.
            </div>
        <?php endif; ?>

        <!-- Pending Invitations -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold flex items-center">
                    <i class="fas fa-clock text-orange-600 mr-2"></i>
                    Pending Invitations (<?php echo count($pending_invitations); ?>)
                </h3>
            </div>
            
            <?php if (empty($pending_invitations)): ?>
                <div class="p-6 text-center">
                    <i class="fas fa-inbox text-gray-300 text-4xl mb-4"></i>
                    <p class="text-gray-500">No pending team invitations.</p>
                    <p class="text-gray-400 text-sm mt-2">Team leaders can invite you to join their teams.</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($pending_invitations as $invitation): ?>
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-3">
                                        <div class="flex-shrink-0">
                                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-users text-blue-600"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <h4 class="text-lg font-medium text-gray-900">
                                                <?php echo htmlspecialchars($invitation['team_name']); ?>
                                            </h4>
                                            <p class="text-sm text-gray-600">
                                                <i class="fas fa-user mr-1"></i>
                                                Invited by: <?php echo htmlspecialchars($invitation['from_user_name']); ?>
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                <i class="fas fa-envelope mr-1"></i>
                                                <?php echo htmlspecialchars($invitation['from_user_email']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="ml-16">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                            <div>
                                                <p class="text-sm font-medium text-gray-600">Team Size:</p>
                                                <p class="text-sm text-gray-800">
                                                    <?php echo $invitation['current_members']; ?>/4 members
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-600">Current Members:</p>
                                                <p class="text-sm text-gray-800">
                                                    <?php echo htmlspecialchars($invitation['team_members'] ?: 'No members yet'); ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($invitation['idea'])): ?>
                                            <div class="mb-3">
                                                <p class="text-sm font-medium text-gray-600">Project Idea:</p>
                                                <p class="text-sm text-gray-700 bg-gray-50 p-3 rounded">
                                                    <?php echo htmlspecialchars($invitation['idea']); ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($invitation['problem_statement'])): ?>
                                            <div class="mb-3">
                                                <p class="text-sm font-medium text-gray-600">Problem Statement:</p>
                                                <p class="text-sm text-gray-700 bg-gray-50 p-3 rounded">
                                                    <?php echo htmlspecialchars($invitation['problem_statement']); ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($invitation['message'])): ?>
                                            <div class="mb-4">
                                                <p class="text-sm font-medium text-gray-600">Personal Message:</p>
                                                <p class="text-sm text-gray-700 bg-blue-50 p-3 rounded border-l-4 border-blue-400">
                                                    <?php echo htmlspecialchars($invitation['message']); ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="flex items-center text-sm text-gray-500 mb-4">
                                            <i class="fas fa-clock mr-1"></i>
                                            Received: <?php echo formatDateTime($invitation['created_at']); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (!$user_team && !$is_team_leader): ?>
                                    <div class="ml-4 flex flex-col space-y-2">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="invitation_id" value="<?php echo $invitation['id']; ?>">
                                            <input type="hidden" name="action" value="accept">
                                            <button type="submit" 
                                                    onclick="return confirm('Accept invitation to join team: <?php echo addslashes($invitation['team_name']); ?>?')"
                                                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                                                <i class="fas fa-check mr-2"></i>
                                                Accept
                                            </button>
                                        </form>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="invitation_id" value="<?php echo $invitation['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" 
                                                    onclick="return confirm('Reject invitation from team: <?php echo addslashes($invitation['team_name']); ?>?')"
                                                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                                                <i class="fas fa-times mr-2"></i>
                                                Reject
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Invitation History -->
        <?php if (!empty($invitation_history)): ?>
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-history text-gray-600 mr-2"></i>
                        Recent Invitation History
                    </h3>
                </div>
                
                <div class="divide-y divide-gray-200">
                    <?php foreach ($invitation_history as $history): ?>
                        <div class="p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium text-gray-900">
                                        <?php echo htmlspecialchars($history['team_name']); ?>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        From: <?php echo htmlspecialchars($history['from_user_name']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo formatDateTime($history['responded_at']); ?>
                                    </p>
                                </div>
                                <div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php echo $history['status'] === 'accepted' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <i class="fas fa-<?php echo $history['status'] === 'accepted' ? 'check' : 'times'; ?> mr-1"></i>
                                        <?php echo $history['status_text']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>