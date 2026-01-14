<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

header('Content-Type: application/json');

// Check authentication
try {
    checkAuth('participant');
    $user = getCurrentUser();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Check if user is already in a team
$stmt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE user_id = ?");
$stmt->execute([$user['id']]);
if ($stmt->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'You are already in a team']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$team_id = $_POST['team_id'] ?? null;
$request_message = sanitize($_POST['message'] ?? '');

if (!$team_id) {
    echo json_encode(['success' => false, 'message' => 'Team ID is required']);
    exit;
}

try {
    // Verify team exists and is approved
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ? AND status = 'approved'");
    $stmt->execute([$team_id]);
    $team = $stmt->fetch();
    
    if (!$team) {
        echo json_encode(['success' => false, 'message' => 'Invalid team selected']);
        exit;
    }
    
    // Check if team has space (max 4 members)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ?");
    $stmt->execute([$team_id]);
    $member_count = $stmt->fetchColumn();
    
    if ($member_count >= 4) {
        echo json_encode(['success' => false, 'message' => 'Team is full (maximum 4 members)']);
        exit;
    }
    
    // Check for existing join requests to this team (max 3 allowed)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM join_requests WHERE user_id = ? AND team_id = ?");
    $stmt->execute([$user['id'], $team_id]);
    $request_count = $stmt->fetchColumn();
    
    if ($request_count >= 3) {
        echo json_encode(['success' => false, 'message' => 'You have reached the maximum limit of 3 join requests for this team']);
        exit;
    }
    
    // Check if there's already a pending request
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM join_requests WHERE user_id = ? AND team_id = ? AND status = 'pending'");
    $stmt->execute([$user['id'], $team_id]);
    $pending_request = $stmt->fetchColumn();
    
    if ($pending_request > 0) {
        echo json_encode(['success' => false, 'message' => 'You already have a pending join request for this team']);
        exit;
    }
    
    // Create join request
    $stmt = $pdo->prepare("INSERT INTO join_requests (user_id, team_id, message) VALUES (?, ?, ?)");
    if ($stmt->execute([$user['id'], $team_id, $request_message])) {
        echo json_encode([
            'success' => true, 
            'message' => 'Join request sent successfully! Your request to team "' . htmlspecialchars($team['name']) . '" is now pending.'
        ]);
    } else {
        $errorInfo = $stmt->errorInfo();
        echo json_encode(['success' => false, 'message' => 'Failed to send join request: ' . ($errorInfo[2] ?? 'Unknown error')]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>