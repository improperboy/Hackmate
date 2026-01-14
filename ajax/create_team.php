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

$team_name = sanitize($_POST['team_name'] ?? '');
$idea = sanitize($_POST['idea'] ?? '');
$problem_statement = sanitize($_POST['problem_statement'] ?? '');
$theme_id = (int)($_POST['theme_id'] ?? 0);

if (empty($team_name) || empty($idea) || empty($problem_statement) || empty($theme_id)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Check if user is already in a team
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'You are already part of a team']);
        exit();
    }
    
    // Check if user has pending join requests
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM join_requests WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$user['id']]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'You have pending join requests. Please wait for response or cancel them first.']);
        exit();
    }
    
    // Check if user already has a team as leader (pending, approved, or rejected)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE leader_id = ?");
    $stmt->execute([$user['id']]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'You can only create one team. You already have a team.']);
        exit();
    }
    
    // Verify theme exists and is active
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM themes WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$theme_id]);
    if ($stmt->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid theme selected']);
        exit();
    }
    
    // Check if team name already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE name = ?");
    $stmt->execute([$team_name]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Team name already exists. Please choose a different name.']);
        exit();
    }
    
    // Create team with pending status
    $stmt = $pdo->prepare("INSERT INTO teams (name, idea, problem_statement, theme_id, leader_id, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$team_name, $idea, $problem_statement, $theme_id, $user['id']]);
    $team_id = $pdo->lastInsertId();
    
    // Add leader as team member
    $stmt = $pdo->prepare("INSERT INTO team_members (user_id, team_id) VALUES (?, ?)");
    $stmt->execute([$user['id'], $team_id]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Team created successfully! Waiting for admin approval.']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error creating team: ' . $e->getMessage()]);
}
?>
