<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('admin');

$team_id = $_GET['team_id'] ?? 0;

// Get team details
$stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
$stmt->execute([$team_id]);
$team = $stmt->fetch();

if (!$team) {
    echo '<p class="text-red-600">Team not found.</p>';
    exit();
}

// Get current team members
$stmt = $pdo->prepare("
    SELECT tm.id as member_id, u.*, tm.joined_at 
    FROM team_members tm 
    JOIN users u ON tm.user_id = u.id 
    WHERE tm.team_id = ?
    ORDER BY tm.joined_at ASC
");
$stmt->execute([$team_id]);
$members = $stmt->fetchAll();

// Get available participants (not in any team)
$stmt = $pdo->query("
    SELECT u.* FROM users u 
    WHERE u.role = 'participant' 
    AND u.id NOT IN (SELECT user_id FROM team_members)
    ORDER BY u.name
");
$available_participants = $stmt->fetchAll();
?>

<div class="space-y-6">
    <div>
        <h4 class="font-semibold text-gray-900 mb-2">Team: <?php echo $team['name']; ?></h4>
        <p class="text-sm text-gray-600">Manage team members (Maximum 4 members allowed)</p>
    </div>

    <!-- Add Member Form -->
    <?php if (count($members) < 4 && !empty($available_participants)): ?>
        <div class="bg-green-50 border border-green-200 rounded p-4">
            <h5 class="font-medium text-gray-900 mb-3">Add New Member</h5>
            <form method="POST" action="../admin/teams.php" class="flex space-x-3">
                <input type="hidden" name="action" value="add_member">
                <input type="hidden" name="team_id" value="<?php echo $team_id; ?>">
                
                <select name="user_id" required class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Participant</option>
                    <?php foreach ($available_participants as $participant): ?>
                        <option value="<?php echo $participant['id']; ?>">
                            <?php echo $participant['name']; ?> (<?php echo $participant['email']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md transition-colors">
                    <i class="fas fa-plus mr-1"></i>Add
                </button>
            </form>
        </div>
    <?php elseif (count($members) >= 4): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded p-4">
            <p class="text-yellow-800">Team is full (4/4 members). Remove a member to add a new one.</p>
        </div>
    <?php elseif (empty($available_participants)): ?>
        <div class="bg-gray-50 border border-gray-200 rounded p-4">
            <p class="text-gray-600">No available participants to add.</p>
        </div>
    <?php endif; ?>

    <!-- Current Members -->
    <div>
        <h5 class="font-medium text-gray-900 mb-3">Current Members (<?php echo count($members); ?>/4)</h5>
        <div class="space-y-3">
            <?php foreach ($members as $member): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <?php if ($member['id'] == $team['leader_id']): ?>
                                <i class="fas fa-crown text-yellow-500 text-lg"></i>
                            <?php else: ?>
                                <i class="fas fa-user text-gray-500 text-lg"></i>
                            <?php endif; ?>
                        </div>
                        <div class="ml-3">
                            <p class="font-medium text-gray-900"><?php echo $member['name']; ?></p>
                            <p class="text-sm text-gray-500"><?php echo $member['email']; ?></p>
                            <p class="text-xs text-gray-400">
                                <?php if ($member['id'] == $team['leader_id']): ?>
                                    Team Leader
                                <?php else: ?>
                                    Joined: <?php echo formatDateTime($member['joined_at']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($member['id'] != $team['leader_id']): ?>
                        <form method="POST" action="../admin/teams.php" class="inline" 
                              onsubmit="return confirm('Remove <?php echo addslashes($member['name']); ?> from the team?')">
                            <input type="hidden" name="action" value="remove_member">
                            <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">
                            <button type="submit" class="text-red-600 hover:text-red-800">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                    <?php else: ?>
                        <span class="text-xs text-gray-400">Leader</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
