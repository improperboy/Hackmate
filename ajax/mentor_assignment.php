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
    checkAuth('admin');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Authentication failed']);
    exit();
}

$mentor_id = (int)($_POST['mentor_id'] ?? 0);
$floor = sanitize($_POST['floor'] ?? '');
$room = sanitize($_POST['room'] ?? '');

if (empty($mentor_id) || empty($floor) || empty($room)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

try {
    // Check if mentor exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'mentor'");
    $stmt->execute([$mentor_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Invalid mentor selected']);
        exit();
    }
    
    // Check if assignment already exists
    $stmt = $pdo->prepare("SELECT id FROM mentor_assignments WHERE mentor_id = ?");
    $stmt->execute([$mentor_id]);
    
    if ($stmt->fetch()) {
        // Update existing assignment
        $stmt = $pdo->prepare("UPDATE mentor_assignments SET floor = ?, room = ? WHERE mentor_id = ?");
        $success = $stmt->execute([$floor, $room, $mentor_id]);
        $message = 'Mentor assignment updated successfully!';
    } else {
        // Create new assignment
        $stmt = $pdo->prepare("INSERT INTO mentor_assignments (mentor_id, floor, room) VALUES (?, ?, ?)");
        $success = $stmt->execute([$mentor_id, $floor, $room]);
        $message = 'Mentor assigned successfully!';
    }
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to assign mentor']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
