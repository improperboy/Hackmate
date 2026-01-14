<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';

checkAuth('mentor');
$user = getCurrentUser();

echo "<h2>Debug: Mentor Teams Assignment</h2>";

// Get mentor's assignments
$stmt = $pdo->prepare("
    SELECT ma.*, f.floor_number, r.room_number 
    FROM mentor_assignments ma
    JOIN floors f ON ma.floor_id = f.id
    JOIN rooms r ON ma.room_id = r.id
    WHERE ma.mentor_id = ?
");
$stmt->execute([$user['id']]);
$assignments = $stmt->fetchAll();

echo "<h3>Mentor Assignments:</h3>";
echo "<pre>" . print_r($assignments, true) . "</pre>";

// Check all teams
$stmt = $pdo->query("SELECT t.*, f.floor_number, r.room_number FROM teams t LEFT JOIN floors f ON t.floor_id = f.id LEFT JOIN rooms r ON t.room_id = r.id");
$all_teams = $stmt->fetchAll();

echo "<h3>All Teams in Database:</h3>";
foreach ($all_teams as $team) {
    echo "Team: {$team['name']}, Status: {$team['status']}, Floor: {$team['floor_number']}, Room: {$team['room_number']}<br>";
}

// Check teams in mentor's assigned areas
if (!empty($assignments)) {
    $floor_room_conditions = [];
    $params = [];

    foreach ($assignments as $assignment) {
        $floor_room_conditions[] = "(t.floor_id = ? AND t.room_id = ?)";
        $params[] = $assignment['floor_id'];
        $params[] = $assignment['room_id'];
    }

    $teams_query = "
        SELECT t.*, f.floor_number, r.room_number
        FROM teams t 
        LEFT JOIN floors f ON t.floor_id = f.id
        LEFT JOIN rooms r ON t.room_id = r.id
        WHERE (" . implode(' OR ', $floor_room_conditions) . ")
    ";

    $stmt = $pdo->prepare($teams_query);
    $stmt->execute($params);
    $assigned_teams = $stmt->fetchAll();

    echo "<h3>Teams in Assigned Areas (All Status):</h3>";
    foreach ($assigned_teams as $team) {
        echo "Team: {$team['name']}, Status: {$team['status']}, Floor: {$team['floor_number']}, Room: {$team['room_number']}<br>";
    }

    // Now check only approved teams
    $teams_query_approved = "
        SELECT t.*, f.floor_number, r.room_number
        FROM teams t 
        LEFT JOIN floors f ON t.floor_id = f.id
        LEFT JOIN rooms r ON t.room_id = r.id
        WHERE t.status = 'approved' AND (" . implode(' OR ', $floor_room_conditions) . ")
    ";

    $stmt = $pdo->prepare($teams_query_approved);
    $stmt->execute($params);
    $approved_teams = $stmt->fetchAll();

    echo "<h3>Approved Teams in Assigned Areas:</h3>";
    foreach ($approved_teams as $team) {
        echo "Team: {$team['name']}, Status: {$team['status']}, Floor: {$team['floor_number']}, Room: {$team['room_number']}<br>";
    }
    
    echo "<p><strong>Total approved teams in assigned areas: " . count($approved_teams) . "</strong></p>";
}
?>