<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

checkAuth('participant');
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$invite_user_id = $input['user_id'] ?? null;
$invite_message = trim($input['message'] ?? '');

if (!$invite_user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID is required.']);
    exit();
}

// Check if user is a team leader with approved team
$stmt = $pdo->prepare("
    SELECT t.* FROM teams t 
    WHERE t.leader_id = ? AND t.status = 'approved'
");
$stmt->execute([$user['id']]);
$user_team = $stmt->fetch();

if (!$user_team) {
    echo json_encode(['success' => false, 'message' => 'You are not a team leader with an approved team.']);
    exit();
}

// Check current team size
$stmt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ?");
$stmt->execute([$user_team['id']]);
$current_team_size = $stmt->fetchColumn();

require_once '../includes/system_settings.php';
$team_limits = getTeamSizeLimits();
$max_team_size = $team_limits['max'];
if ($current_team_size >= $max_team_size) {
    echo json_encode(['success' => false, 'message' => 'Your team is already full.']);
    exit();
}

// Check if target user exists and is available
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
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit();
}

if ($target_user['in_team'] > 0 || $target_user['is_leader'] > 0) {
    echo json_encode(['success' => false, 'message' => 'This user is already part of a team or has their own team.']);
    exit();
}

// Check if invitation already exists
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM team_invitations 
    WHERE team_id = ? AND to_user_id = ? AND status = 'pending'
");
$stmt->execute([$user_team['id'], $invite_user_id]);

if ($stmt->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'You have already sent an invitation to this user.']);
    exit();
}

// Send invitation
try {
    $stmt = $pdo->prepare("
        INSERT INTO team_invitations (team_id, from_user_id, to_user_id, message) 
        VALUES (?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$user_team['id'], $user['id'], $invite_user_id, $invite_message])) {
        echo json_encode([
            'success' => true, 
            'message' => 'Invitation sent successfully to ' . htmlspecialchars($target_user['name']) . '!'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send invitation. Please try again.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>