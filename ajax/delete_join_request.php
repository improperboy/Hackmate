<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

try {
    checkAuth('participant');
    $user = getCurrentUser();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $request_id = $input['request_id'] ?? null;
    
    if (!$request_id) {
        throw new Exception('Request ID is required');
    }
    
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
        throw new Exception('Join request not found or cannot be deleted');
    }
    
    // Delete the join request
    $stmt = $pdo->prepare("DELETE FROM join_requests WHERE id = ? AND user_id = ?");
    $stmt->execute([$request_id, $user['id']]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Failed to delete join request');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Join request deleted successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>