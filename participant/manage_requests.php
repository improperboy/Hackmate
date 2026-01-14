<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('participant');
$user = getCurrentUser();

// Check if user is team leader
$stmt = $pdo->prepare("SELECT * FROM teams WHERE leader_id = ? AND status = 'approved'");
$stmt->execute([$user['id']]);
$team = $stmt->fetch();

if (!$team) {
    header('Location: dashboard.php');
    exit();
}

$message = '';
$error = '';

// Handle request response
if ($_POST && isset($_POST['action'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    
    $stmt = $pdo->prepare("SELECT * FROM join_requests WHERE id = ? AND team_id = ?");
    $stmt->execute([$request_id, $team['id']]);
    $request = $stmt->fetch();
    
    if ($request && $request['status'] == 'pending') {
        if ($action == 'approve') {
            // Check team capacity
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ?");
            $stmt->execute([$team['id']]);
            $member_count = $stmt->fetchColumn();
            
            if ($member_count >= 4) {
                $error = 'Team is full (maximum 4 members)';
            } else {
                $pdo->beginTransaction();
                try {
                    // Add user to team
                    $stmt = $pdo->prepare("INSERT INTO team_members (user_id, team_id) VALUES (?, ?)");
                    $stmt->execute([$request['user_id'], $team['id']]);
                    
                    // Update this request status to approved
                    $stmt = $pdo->prepare("UPDATE join_requests SET status = 'approved', responded_at = NOW() WHERE id = ?");
                    $stmt->execute([$request_id]);
                    
                    // Expire all other pending join requests from this user to different teams
                    $stmt = $pdo->prepare("UPDATE join_requests SET status = 'expired', responded_at = NOW() WHERE user_id = ? AND status = 'pending' AND id != ?");
                    $stmt->execute([$request['user_id'], $request_id]);
                    
                    $pdo->commit();
                    $message = 'Join request approved successfully! All other pending requests from this user have been automatically cancelled.';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Failed to approve request: ' . $e->getMessage();
                }
            }
        } elseif ($action == 'reject') {
            $stmt = $pdo->prepare("UPDATE join_requests SET status = 'rejected', responded_at = NOW() WHERE id = ?");
            if ($stmt->execute([$request_id])) {
                $message = 'Join request rejected.';
            } else {
                $error = 'Failed to reject request.';
            }
        }
    }
}

// Get pending join requests
$stmt = $pdo->prepare("
    SELECT jr.*, u.name, u.email 
    FROM join_requests jr 
    JOIN users u ON jr.user_id = u.id 
    WHERE jr.team_id = ? AND jr.status = 'pending'
    ORDER BY jr.created_at DESC
");
$stmt->execute([$team['id']]);
$pending_requests = $stmt->fetchAll();

// Get processed requests
$stmt = $pdo->prepare("
    SELECT jr.*, u.name, u.email 
    FROM join_requests jr 
    JOIN users u ON jr.user_id = u.id 
    WHERE jr.team_id = ? AND jr.status IN ('approved', 'rejected', 'expired')
    ORDER BY jr.responded_at DESC
    LIMIT 10
");
$stmt->execute([$team['id']]);
$processed_requests = $stmt->fetchAll();

// Get current team member count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ?");
$stmt->execute([$team['id']]);
$current_members = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Join Requests - Participant Panel</title>
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
                        <i class="fas fa-user-check text-indigo-600"></i>
                        Manage Requests
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

    <div class="max-w-6xl mx-auto py-6 px-4">
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

        <!-- Team Status -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        <strong>Team Status:</strong> <?php echo $current_members; ?>/4 members
                        <?php if ($current_members >= 4): ?>
                            - <span class="text-red-600 font-medium">Team is full</span>
                        <?php else: ?>
                            - <span class="text-green-600 font-medium"><?php echo 4 - $current_members; ?> spots available</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Pending Requests -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-yellow-600">
                    <i class="fas fa-clock"></i>
                    Pending Join Requests (<?php echo count($pending_requests); ?>)
                </h3>
            </div>
            
            <?php if (empty($pending_requests)): ?>
                <div class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-4"></i>
                    <p>No pending join requests.</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($pending_requests as $request): ?>
                        <div class="px-6 py-4">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <h4 class="font-medium text-gray-900 mr-4"><?php echo $request['name']; ?></h4>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Pending
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-500 mb-2"><?php echo $request['email']; ?></p>
                                    <?php if ($request['message']): ?>
                                        <div class="bg-gray-50 p-3 rounded mb-3">
                                            <p class="text-sm text-gray-700"><?php echo nl2br($request['message']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <p class="text-xs text-gray-500">
                                        <i class="fas fa-clock mr-1"></i>
                                        Requested: <?php echo formatDateTime($request['created_at']); ?>
                                    </p>
                                </div>
                                <div class="ml-4 flex space-x-2">
                                    <?php if ($current_members < 4): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" 
                                                    class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm transition-colors"
                                                    onclick="return confirm('Approve join request from <?php echo addslashes($request['name']); ?>?')">
                                                <i class="fas fa-check mr-1"></i>
                                                Approve
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs text-red-600 bg-red-50 px-2 py-1 rounded">Team Full</span>
                                    <?php endif; ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <button type="submit" 
                                                class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm transition-colors"
                                                onclick="return confirm('Reject join request from <?php echo addslashes($request['name']); ?>?')">
                                            <i class="fas fa-times mr-1"></i>
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Processed Requests -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-600">
                    <i class="fas fa-history"></i>
                    Recent Responses (<?php echo count($processed_requests); ?>)
                </h3>
            </div>
            
            <?php if (empty($processed_requests)): ?>
                <div class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-history text-4xl mb-4"></i>
                    <p>No processed requests yet.</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-200 max-h-96 overflow-y-auto">
                    <?php foreach ($processed_requests as $request): ?>
                        <div class="px-6 py-4 bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <h4 class="font-medium text-gray-900 mr-4"><?php echo $request['name']; ?></h4>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php 
                                        if ($request['status'] == 'approved') {
                                            echo 'bg-green-100 text-green-800';
                                        } elseif ($request['status'] == 'expired') {
                                            echo 'bg-gray-100 text-gray-800';
                                        } else {
                                            echo 'bg-red-100 text-red-800';
                                        }
                                        ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-500 mb-2"><?php echo $request['email']; ?></p>
                                    <?php if ($request['message']): ?>
                                        <div class="bg-white p-2 rounded mb-2">
                                            <p class="text-xs text-gray-600"><?php echo truncateText($request['message'], 100); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <p class="text-xs text-gray-500">
                                        <i class="fas fa-clock mr-1"></i>
                                        Responded: <?php echo formatDateTime($request['responded_at']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
