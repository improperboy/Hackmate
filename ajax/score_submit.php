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
    checkAuth('mentor');
    $user = getCurrentUser();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Authentication failed']);
    exit();
}

$team_id = (int)($_POST['team_id'] ?? 0);
$round_id = (int)($_POST['round_id'] ?? 0);
$score = (int)($_POST['score'] ?? 0);
$comment = sanitize($_POST['comment'] ?? '');

if (empty($team_id) || empty($round_id)) {
    echo json_encode(['success' => false, 'message' => 'Team and round are required']);
    exit();
}

try {
    // Validate score against round max score
    $stmt = $pdo->prepare("SELECT max_score FROM mentoring_rounds WHERE id = ?");
    $stmt->execute([$round_id]);
    $round = $stmt->fetch();
    
    if (!$round) {
        echo json_encode(['success' => false, 'message' => 'Invalid mentoring round']);
        exit();
    }
    
    if ($score < 0 || $score > $round['max_score']) {
        echo json_encode(['success' => false, 'message' => 'Score must be between 0 and ' . $round['max_score']]);
        exit();
    }
    
    // Check if already scored
    $stmt = $pdo->prepare("SELECT id FROM scores WHERE mentor_id = ? AND team_id = ? AND round_id = ?");
    $stmt->execute([$user['id'], $team_id, $round_id]);
    
    if ($stmt->fetch()) {
        // Update existing score
        $stmt = $pdo->prepare("UPDATE scores SET score = ?, comment = ? WHERE mentor_id = ? AND team_id = ? AND round_id = ?");
        $success = $stmt->execute([$score, $comment, $user['id'], $team_id, $round_id]);
        $message = 'Score updated successfully!';
    } else {
        // Insert new score
        $stmt = $pdo->prepare("INSERT INTO scores (mentor_id, team_id, round_id, score, comment) VALUES (?, ?, ?, ?, ?)");
        $success = $stmt->execute([$user['id'], $team_id, $round_id, $score, $comment]);
        $message = 'Score submitted successfully!';
    }
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit score']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
