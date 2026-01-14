<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

// This endpoint can be accessed by admin, mentor, volunteer to view team details
try {
    checkAuth(['admin', 'mentor', 'volunteer', 'participant']);
} catch (Exception $e) {
    $response['message'] = 'Authentication required';
    echo json_encode($response);
    exit();
}

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_GET['team_id'])) {
    $response['message'] = 'Team ID is required.';
    echo json_encode($response);
    exit();
}

$team_id = (int)$_GET['team_id'];
if ($team_id <= 0) {
    $response['message'] = 'Invalid team ID.';
    echo json_encode($response);
    exit();
}

try {
    // Get team details
    $stmt = $pdo->prepare("
        SELECT t.*, u.name as leader_name, u.email as leader_email,
               f.floor_number, r.room_number,
               (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id) as member_count
        FROM teams t 
        LEFT JOIN users u ON t.leader_id = u.id
        LEFT JOIN floors f ON t.floor_id = f.id
        LEFT JOIN rooms r ON t.room_id = r.id
        WHERE t.id = ?
    ");
    $stmt->execute([$team_id]);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$team) {
        $response['message'] = 'Team not found.';
        echo json_encode($response);
        exit();
    }

    // Get team members
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.role, tm.joined_at 
        FROM team_members tm 
        JOIN users u ON tm.user_id = u.id 
        WHERE tm.team_id = ?
        ORDER BY tm.joined_at ASC
    ");
    $stmt->execute([$team_id]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get team submission
    $stmt = $pdo->prepare("SELECT * FROM submissions WHERE team_id = ?");
    $stmt->execute([$team_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get team scores (all mentors)
    $stmt = $pdo->prepare("
        SELECT s.score, s.comment, mr.round_name, mr.max_score, u.name as mentor_name, s.created_at
        FROM scores s 
        JOIN mentoring_rounds mr ON s.round_id = mr.id 
        JOIN users u ON s.mentor_id = u.id
        WHERE s.team_id = ?
        ORDER BY mr.start_time DESC, u.name ASC
    ");
    $stmt->execute([$team_id]);
    $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['team'] = $team;
    $response['members'] = $members;
    $response['submission'] = $submission;
    $response['scores'] = $scores;

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>
