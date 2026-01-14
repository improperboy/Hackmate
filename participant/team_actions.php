<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('participant');
$user = getCurrentUser();

// Get user's team
$stmt = $pdo->prepare("
    SELECT t.*, tm.user_id as is_member
    FROM teams t 
    JOIN team_members tm ON t.id = tm.team_id 
    WHERE tm.user_id = ? AND t.status = 'approved'
");
$stmt->execute([$user['id']]);
$team = $stmt->fetch();

if (!$team) {
    header('Location: dashboard.php?error=no_team');
    exit();
}

$action = $_GET['action'] ?? '';
$message = '';
$error = '';

try {
    switch ($action) {
        case 'remove_member':
            // Only team leader can remove members
            if ($team['leader_id'] != $user['id']) {
                throw new Exception('Access denied: Only team leader can remove members.');
            }
            
            $member_id = $_GET['member_id'] ?? 0;
            if (!$member_id) {
                throw new Exception('Invalid member ID.');
            }
            
            // Can't remove team leader
            if ($member_id == $team['leader_id']) {
                throw new Exception('Cannot remove team leader from the team. If you want to remove yourself as leader, delete the entire team instead.');
            }
            
            // Check if member exists in team
            $stmt = $pdo->prepare("SELECT u.name FROM team_members tm JOIN users u ON tm.user_id = u.id WHERE tm.team_id = ? AND tm.user_id = ?");
            $stmt->execute([$team['id'], $member_id]);
            $member = $stmt->fetch();
            
            if (!$member) {
                throw new Exception('Member not found in team.');
            }
            
            // Remove member from team
            $stmt = $pdo->prepare("DELETE FROM team_members WHERE team_id = ? AND user_id = ?");
            $stmt->execute([$team['id'], $member_id]);
            
            // Log the action for audit
            error_log("Member {$member_id} ({$member['name']}) removed from team {$team['id']} ({$team['name']}) by leader {$user['id']} ({$user['name']})");
            
            $message = "Member '{$member['name']}' has been removed from the team.";
            header("Location: team_details.php?message=" . urlencode($message));
            exit();
            
        case 'leave_team':
            // Team member leaves team (but not leader)
            if ($team['leader_id'] == $user['id']) {
                throw new Exception('Team leader cannot leave the team. You must delete the entire team or transfer leadership first.');
            }
            
            // Confirm user is actually a member
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ? AND user_id = ?");
            $stmt->execute([$team['id'], $user['id']]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception('You are not a member of this team.');
            }
            
            // Remove user from team
            $stmt = $pdo->prepare("DELETE FROM team_members WHERE team_id = ? AND user_id = ?");
            $stmt->execute([$team['id'], $user['id']]);
            
            // Log the action for audit
            error_log("User {$user['id']} ({$user['name']}) left team {$team['id']} ({$team['name']})");
            
            $message = "You have successfully left the team '{$team['name']}'";
            header("Location: dashboard.php?message=" . urlencode($message));
            exit();
            
        case 'delete_team':
            // Only team leader can delete team
            if ($team['leader_id'] != $user['id']) {
                throw new Exception('Access denied: Only team leader can delete the team.');
            }
            
            // Start transaction for data consistency
            $pdo->beginTransaction();
            
            try {
                // Get all team members for notification
                $stmt = $pdo->prepare("SELECT u.name, u.email FROM team_members tm JOIN users u ON tm.user_id = u.id WHERE tm.team_id = ?");
                $stmt->execute([$team['id']]);
                $team_members = $stmt->fetchAll();
                
                // Delete related records in correct order
                // 1. Delete scores
                $stmt = $pdo->prepare("DELETE FROM scores WHERE team_id = ?");
                $stmt->execute([$team['id']]);
                
                // 2. Delete submissions
                $stmt = $pdo->prepare("DELETE FROM submissions WHERE team_id = ?");
                $stmt->execute([$team['id']]);
                
                // 3. Delete join requests
                $stmt = $pdo->prepare("DELETE FROM join_requests WHERE team_id = ?");
                $stmt->execute([$team['id']]);
                
                // 4. Delete team members
                $stmt = $pdo->prepare("DELETE FROM team_members WHERE team_id = ?");
                $stmt->execute([$team['id']]);
                
                // 5. Delete the team itself
                $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
                $stmt->execute([$team['id']]);
                
                // Commit transaction
                $pdo->commit();
                
                // Log the action for audit
                error_log("Team {$team['id']} ({$team['name']}) deleted by leader {$user['id']} ({$user['name']}). Removed " . count($team_members) . " members.");
                
                $message = "Team '{$team['name']}' has been deleted successfully. All " . count($team_members) . " team members have been removed.";
                header("Location: dashboard.php?message=" . urlencode($message));
                exit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            
        default:
            throw new Exception('Invalid action.');
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
    header("Location: team_details.php?error=" . urlencode($error));
    exit();
}
?>
