<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    checkAuth(['participant', 'volunteer']);
    $user = getCurrentUser();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Authentication failed']);
    exit();
}

$message = sanitize($_POST['message'] ?? '');
$to_role = sanitize($_POST['to_role'] ?? 'mentor');

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message is required']);
    exit();
}

try {
    if ($user['role'] === 'participant') {
        // For participants, check if they are team leaders and get team location
        $stmt = $pdo->prepare("
            SELECT t.floor_id, t.room_id 
            FROM teams t 
            WHERE t.leader_id = ? AND t.status = 'approved'
        ");
        $stmt->execute([$user['id']]);
        $team = $stmt->fetch();
        
        if (!$team) {
            echo json_encode(['success' => false, 'message' => 'Only team leaders can raise support requests']);
            exit();
        }
        
        if (!$team['floor_id'] || !$team['room_id']) {
            echo json_encode(['success' => false, 'message' => 'Your team has not been assigned a floor and room yet']);
            exit();
        }
        
        $floor_id = $team['floor_id'];
        $room_id = $team['room_id'];
    } else {
        // For volunteers, use their personal floor/room assignments
        $floor_stmt = $pdo->prepare("SELECT id FROM floors WHERE floor_number = ?");
        $floor_stmt->execute([$user['floor']]);
        $floor_id = $floor_stmt->fetchColumn();
        
        $room_stmt = $pdo->prepare("SELECT id FROM rooms WHERE room_number = ? AND floor_id = ?");
        $room_stmt->execute([$user['room'], $floor_id]);
        $room_id = $room_stmt->fetchColumn();
    }
    
    $stmt = $pdo->prepare("INSERT INTO support_messages (from_id, from_role, to_role, message, floor_id, room_id) VALUES (?, ?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$user['id'], $user['role'], $to_role, $message, $floor_id, $room_id])) {
        echo json_encode(['success' => true, 'message' => 'Support message sent successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
