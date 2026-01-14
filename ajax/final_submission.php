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
    checkAuth('participant');
    $user = getCurrentUser();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Authentication failed']);
    exit();
}

// Get user's team (must be leader)
$stmt = $pdo->prepare("SELECT * FROM teams WHERE leader_id = ? AND status = 'approved'");
$stmt->execute([$user['id']]);
$team = $stmt->fetch();

if (!$team) {
    echo json_encode(['success' => false, 'message' => 'You are not a team leader or team is not approved']);
    exit();
}

$github_link = sanitize($_POST['github_link'] ?? '');
$live_link = sanitize($_POST['live_link'] ?? '');
$tech_stack = sanitize($_POST['tech_stack'] ?? '');
$demo_video = sanitize($_POST['demo_video'] ?? '');

if (empty($github_link) || empty($tech_stack)) {
    echo json_encode(['success' => false, 'message' => 'GitHub link and tech stack are required']);
    exit();
}

// Validate URLs
if (!filter_var($github_link, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid GitHub URL']);
    exit();
}

if (!empty($live_link) && !filter_var($live_link, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid live demo URL']);
    exit();
}

if (!empty($demo_video) && !filter_var($demo_video, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid demo video URL']);
    exit();
}

try {
    // Check if submission already exists
    $stmt = $pdo->prepare("SELECT id FROM submissions WHERE team_id = ?");
    $stmt->execute([$team['id']]);
    
    if ($stmt->fetch()) {
        // Update existing submission
        $stmt = $pdo->prepare("UPDATE submissions SET github_link = ?, live_link = ?, tech_stack = ?, demo_video = ? WHERE team_id = ?");
        $success = $stmt->execute([$github_link, $live_link, $tech_stack, $demo_video, $team['id']]);
        $message = 'Project submission updated successfully!';
    } else {
        // Create new submission
        $stmt = $pdo->prepare("INSERT INTO submissions (team_id, github_link, live_link, tech_stack, demo_video) VALUES (?, ?, ?, ?, ?)");
        $success = $stmt->execute([$team['id'], $github_link, $live_link, $tech_stack, $demo_video]);
        $message = 'Project submitted successfully!';
    }
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit project']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
