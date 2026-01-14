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

// Handle join request deletion
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $request_id = $_POST['request_id'];
    
    // Check if the request belongs to the current user and is pending
    $stmt = $pdo->prepare("
        SELECT jr.*, t.name as team_name 
        FROM join_requests jr 
        JOIN teams t ON jr.team_id = t.id 
        WHERE jr.id = ? AND jr.user_id = ? AND jr.status = 'pending'
    ");
    $stmt->execute([$request_id, $user['id']]);
    $request = $stmt->fetch();
    
    if (!$request) {
        $error = 'Join request not found or cannot be deleted.';
    } else {
        try {
            // Delete the join request
            $stmt = $pdo->prepare("DELETE FROM join_requests WHERE id = ? AND user_id = ?");
            $stmt->execute([$request_id, $user['id']]);
            
            if ($stmt->rowCount() > 0) {
                $message = 'Join request to "' . htmlspecialchars($request['team_name']) . '" has been deleted successfully.';
            } else {
                $error = 'Failed to delete join request.';
            }
        } catch (Exception $e) {
            $error = 'Error deleting join request: ' . $e->getMessage();
        }
    }
}

// Get all join requests for current user
$stmt = $pdo->prepare("
    SELECT jr.*, t.name as team_name, t.idea, t.problem_statement,
           u.name as leader_name, u.email as leader_email,
           (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as current_members,
           CASE 
               WHEN jr.status = 'pending' THEN 'Pending'
               WHEN jr.status = 'approved' THEN 'Approved'
               WHEN jr.status = 'rejected' THEN 'Rejected'
               WHEN jr.status = 'expired' THEN 'Expired'
               ELSE 'Unknown'
           END as status_text
    FROM join_requests jr
    JOIN teams t ON jr.team_id = t.id
    LEFT JOIN users u ON t.leader_id = u.id
    WHERE jr.user_id = ?
    ORDER BY jr.created_at DESC
");
$stmt->execute([$user['id']]);
$join_requests = $stmt->fetchAll();

// Separate requests by status
$pending_requests = array_filter($join_requests, function($req) { return $req['status'] === 'pending'; });
$processed_requests = array_filter($join_requests, function($req) { return $req['status'] !== 'pending'; });
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Join Requests - Hackathon Management</title>
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
                        <i class="fas fa-paper-plane text-blue-600"></i>
                        My Join Requests
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
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4">
                <i class="fas fa-info-circle mr-2"></i>
                You are already part of a team or have your own team. All your pending join requests have been automatically cancelled.
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow mb-6 p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <i class="fas fa-bolt text-yellow-600 mr-2"></i>
                Quick Actions
            </h3>
            <div class="flex flex-wrap gap-3">
                <a href="join_team.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-plus mr-2"></i>
                    Send New Join Request
                </a>
                <a href="team_invitations.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-envelope mr-2"></i>
                    View Team Invitations
                </a>
                <a href="dashboard.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-home mr-2"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Pending Join Requests -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold flex items-center">
                    <i class="fas fa-clock text-orange-600 mr-2"></i>
                    Pending Requests (<?php echo count($pending_requests); ?>)
                </h3>
                <p class="text-sm text-gray-600 mt-1">These requests are waiting for team leader approval. You can send requests to multiple teams.</p>
            </div>
            
            <?php if (empty($pending_requests)): ?>
                <div class="p-6 text-center">
                    <i class="fas fa-inbox text-gray-300 text-4xl mb-4"></i>
                    <p class="text-gray-500">No pending join requests.</p>
                    <p class="text-gray-400 text-sm mt-2">
                        <a href="join_team.php" class="text-blue-600 hover:text-blue-800">Send a join request</a> 
                        to join a team.
                    </p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($pending_requests as $request): ?>
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-3">
                                        <div class="flex-shrink-0">
                                            <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-users text-orange-600"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <h4 class="text-lg font-medium text-gray-900">
                                                <?php echo htmlspecialchars($request['team_name']); ?>
                                            </h4>
                                            <p class="text-sm text-gray-600">
                                                <i class="fas fa-user mr-1"></i>
                                                Team Leader: <?php echo htmlspecialchars($request['leader_name'] ?: 'Unknown'); ?>
                                            </p>
                                            <?php if (!empty($request['leader_email'])): ?>
                                                <p class="text-sm text-gray-500">
                                                    <i class="fas fa-envelope mr-1"></i>
                                                    <?php echo htmlspecialchars($request['leader_email']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="ml-16">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                            <div>
                                                <p class="text-sm font-medium text-gray-600">Team Size:</p>
                                                <p class="text-sm text-gray-800">
                                                    <?php echo $request['current_members']; ?>/4 members
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-600">Request Status:</p>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    Pending Review
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($request['idea'])): ?>
                                            <div class="mb-3">
                                                <p class="text-sm font-medium text-gray-600">Project Idea:</p>
                                                <p class="text-sm text-gray-700 bg-gray-50 p-3 rounded">
                                                    <?php echo htmlspecialchars($request['idea']); ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($request['problem_statement'])): ?>
                                            <div class="mb-3">
                                                <p class="text-sm font-medium text-gray-600">Problem Statement:</p>
                                                <p class="text-sm text-gray-700 bg-gray-50 p-3 rounded">
                                                    <?php echo htmlspecialchars($request['problem_statement']); ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($request['message'])): ?>
                                            <div class="mb-4">
                                                <p class="text-sm font-medium text-gray-600">Your Message:</p>
                                                <p class="text-sm text-gray-700 bg-blue-50 p-3 rounded border-l-4 border-blue-400">
                                                    <?php echo htmlspecialchars($request['message']); ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="flex items-center text-sm text-gray-500 mb-4">
                                            <i class="fas fa-calendar mr-1"></i>
                                            Sent: <?php echo formatDateTime($request['created_at']); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="ml-4">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" 
                                                onclick="return confirm('Are you sure you want to delete your join request to team: <?php echo addslashes($request['team_name']); ?>?')"
                                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                                            <i class="fas fa-trash mr-2"></i>
                                            Delete Request
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Request History -->
        <?php if (!empty($processed_requests)): ?>
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-history text-gray-600 mr-2"></i>
                        Request History (<?php echo count($processed_requests); ?>)
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">Your previous join requests and their outcomes</p>
                </div>
                
                <div class="divide-y divide-gray-200">
                    <?php foreach ($processed_requests as $request): ?>
                        <div class="p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <div class="flex-shrink-0">
                                            <div class="w-10 h-10 <?php echo $request['status'] === 'approved' ? 'bg-green-100' : 'bg-red-100'; ?> rounded-full flex items-center justify-center">
                                                <i class="fas fa-<?php echo $request['status'] === 'approved' ? 'check' : 'times'; ?> <?php echo $request['status'] === 'approved' ? 'text-green-600' : 'text-red-600'; ?>"></i>
                                            </div>
                                        </div>
                                        <div class="ml-3">
                                            <p class="font-medium text-gray-900">
                                                <?php echo htmlspecialchars($request['team_name']); ?>
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                Leader: <?php echo htmlspecialchars($request['leader_name'] ?: 'Unknown'); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="ml-13">
                                        <div class="flex items-center text-xs text-gray-500 space-x-4">
                                            <span>
                                                <i class="fas fa-calendar mr-1"></i>
                                                Sent: <?php echo formatDateTime($request['created_at']); ?>
                                            </span>
                                            <?php if ($request['responded_at']): ?>
                                                <span>
                                                    <i class="fas fa-reply mr-1"></i>
                                                    Responded: <?php echo formatDateTime($request['responded_at']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if (!empty($request['message'])): ?>
                                            <div class="mt-2">
                                                <p class="text-xs text-gray-600">Your message:</p>
                                                <p class="text-sm text-gray-700 bg-gray-50 p-2 rounded text-xs">
                                                    <?php echo htmlspecialchars($request['message']); ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="ml-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php 
                                        if ($request['status'] === 'approved') {
                                            echo 'bg-green-100 text-green-800';
                                        } elseif ($request['status'] === 'expired') {
                                            echo 'bg-gray-100 text-gray-800';
                                        } else {
                                            echo 'bg-red-100 text-red-800';
                                        }
                                        ?>">
                                        <i class="fas fa-<?php 
                                        if ($request['status'] === 'approved') {
                                            echo 'check';
                                        } elseif ($request['status'] === 'expired') {
                                            echo 'clock';
                                        } else {
                                            echo 'times';
                                        }
                                        ?> mr-1"></i>
                                        <?php echo $request['status_text']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Empty State -->
        <?php if (empty($join_requests)): ?>
            <div class="bg-white rounded-lg shadow p-8 text-center">
                <i class="fas fa-paper-plane text-gray-300 text-6xl mb-4"></i>
                <h3 class="text-xl font-medium text-gray-900 mb-2">No Join Requests Yet</h3>
                <p class="text-gray-600 mb-6">You haven't sent any join requests to teams yet.</p>
                <a href="join_team.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-md font-medium transition-colors">
                    <i class="fas fa-plus mr-2"></i>
                    Send Your First Join Request
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-hide success messages after 5 seconds
        setTimeout(function() {
            const successAlert = document.querySelector('.bg-green-100');
            if (successAlert) {
                successAlert.style.transition = 'opacity 0.5s';
                successAlert.style.opacity = '0';
                setTimeout(() => successAlert.remove(), 500);
            }
        }, 5000);
    </script>
</body>
</html>