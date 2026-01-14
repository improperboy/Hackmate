<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

checkAuth('participant');
$user = getCurrentUser();

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
$can_invite = $current_team_size < $max_team_size;

if (!$can_invite) {
    echo json_encode(['success' => false, 'message' => 'Your team is already full.']);
    exit();
}

// Get search parameters
$search_query = $_GET['search'] ?? '';
$tech_filter = $_GET['tech'] ?? '';

$where_conditions = ["u.role = 'participant'", "u.id != ?"];
$params = [$user['id']];

if (!empty($search_query)) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

if (!empty($tech_filter)) {
    $where_conditions[] = "u.tech_stack LIKE ?";
    $params[] = "%$tech_filter%";
}

$sql = "
    SELECT u.id, u.name, u.email, u.tech_stack, u.created_at,
           (SELECT COUNT(*) FROM team_members tm 
            JOIN teams t ON tm.team_id = t.id 
            WHERE tm.user_id = u.id AND t.status = 'approved') as in_team,
           (SELECT COUNT(*) FROM teams WHERE leader_id = u.id AND status IN ('pending', 'approved')) as is_leader,
           (SELECT COUNT(*) FROM team_invitations 
            WHERE team_id = ? AND to_user_id = u.id AND status = 'pending') as has_pending_invite
    FROM users u 
    WHERE " . implode(' AND ', $where_conditions) . "
    ORDER BY u.name ASC
    LIMIT 20
";

array_unshift($params, $user_team['id']);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format the results
$formatted_users = [];
foreach ($users as $user_data) {
    $tech_stack_array = [];
    if (!empty($user_data['tech_stack'])) {
        $tech_stack_array = array_map('trim', explode(',', $user_data['tech_stack']));
    }
    
    $formatted_users[] = [
        'id' => $user_data['id'],
        'name' => $user_data['name'],
        'email' => $user_data['email'],
        'tech_stack' => $user_data['tech_stack'],
        'tech_stack_array' => $tech_stack_array,
        'in_team' => $user_data['in_team'] > 0,
        'is_leader' => $user_data['is_leader'] > 0,
        'has_pending_invite' => $user_data['has_pending_invite'] > 0,
        'can_invite' => $user_data['in_team'] == 0 && $user_data['is_leader'] == 0 && $user_data['has_pending_invite'] == 0
    ];
}

echo json_encode([
    'success' => true,
    'users' => $formatted_users,
    'team_info' => [
        'name' => $user_team['name'],
        'current_size' => $current_team_size,
        'max_size' => $max_team_size,
        'can_invite' => $can_invite
    ]
]);
?>