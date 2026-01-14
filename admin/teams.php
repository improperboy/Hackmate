<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('admin');
$user = getCurrentUser();

// Get counts for sidebar notifications
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_teams = $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
$pending_teams = $pdo->query("SELECT COUNT(*) FROM teams WHERE status = 'pending'")->fetchColumn();
$total_submissions = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
$open_support_requests = $pdo->query("SELECT COUNT(*) FROM support_messages WHERE status = 'open'")->fetchColumn();

$message = '';
$error = '';

// Handle team approval/rejection
if ($_POST && isset($_POST['action'])) {
    $team_id = $_POST['team_id'];
    $action = $_POST['action'];
    
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->execute([$team_id]);
    $team = $stmt->fetch();
    
    if ($team) {
        if ($action == 'approve') {
            $floor_id = $_POST['floor_id'];
            $room_id = $_POST['room_id'];
            
            if (empty($floor_id) || empty($room_id)) {
                $error = 'Floor and Room must be selected for approval.';
            } else {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("UPDATE teams SET status = 'approved', floor_id = ?, room_id = ? WHERE id = ?");
                    $stmt->execute([$floor_id, $room_id, $team_id]);
                    
                    // If it's a newly created team, add the leader as a member
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ? AND user_id = ?");
                    $stmt->execute([$team_id, $team['leader_id']]);
                    if ($stmt->fetchColumn() == 0) {
                        $stmt = $pdo->prepare("INSERT INTO team_members (team_id, user_id) VALUES (?, ?)");
                        $stmt->execute([$team_id, $team['leader_id']]);
                    }

                    $pdo->commit();
                    $message = 'Team "' . $team['name'] . '" approved and assigned to ' . getFloorRoomName($pdo, $floor_id, $room_id) . '.';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Failed to approve team: ' . $e->getMessage();
                }
            }
        } elseif ($action == 'reject') {
            $stmt = $pdo->prepare("UPDATE teams SET status = 'rejected' WHERE id = ?");
            if ($stmt->execute([$team_id])) {
                $message = 'Team "' . $team['name'] . '" rejected.';
            } else {
                $error = 'Failed to reject team.';
            }
        } elseif ($action == 'delete') {
            $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
            if ($stmt->execute([$team_id])) {
                $message = 'Team "' . $team['name'] . '" deleted.';
            } else {
                $error = 'Failed to delete team.';
            }
        }
    } else {
        $error = 'Team not found.';
    }
}

// Handle team member removal
if ($_POST && isset($_POST['remove_member'])) {
    $team_id = $_POST['team_id'];
    $user_id_to_remove = $_POST['user_id_to_remove'];

    $stmt = $pdo->prepare("SELECT leader_id FROM teams WHERE id = ?");
    $stmt->execute([$team_id]);
    $team_leader_id = $stmt->fetchColumn();

    if ($user_id_to_remove == $team_leader_id) {
        $error = "Cannot remove team leader. Assign a new leader first or delete the team.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM team_members WHERE team_id = ? AND user_id = ?");
        if ($stmt->execute([$team_id, $user_id_to_remove])) {
            $message = "Team member removed successfully.";
        } else {
            $error = "Failed to remove team member.";
        }
    }
}

// Handle team location reassignment
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'reassign_location') {
    $team_id = $_POST['team_id'];
    $floor_id = $_POST['floor_id'];
    $room_id = $_POST['room_id'];
    
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->execute([$team_id]);
    $team = $stmt->fetch();
    
    if (!$team) {
        $error = 'Team not found.';
    } elseif (empty($floor_id) || empty($room_id)) {
        $error = 'Floor and Room must be selected for reassignment.';
    } else {
        $stmt = $pdo->prepare("UPDATE teams SET floor_id = ?, room_id = ? WHERE id = ?");
        if ($stmt->execute([$floor_id, $room_id, $team_id])) {
            $message = 'Team "' . $team['name'] . '" location reassigned successfully to ' . getFloorRoomName($pdo, $floor_id, $room_id) . '.';
        } else {
            $error = 'Failed to reassign team location.';
        }
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$teammate_search = $_GET['teammate_search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$floor_filter = $_GET['floor'] ?? '';
$room_filter = $_GET['room'] ?? '';

// Get all teams with leader name and member count
if ($teammate_search) {
    // If searching by teammate name, use a different query with JOINs to team_members
    $query = "
        SELECT DISTINCT t.*, u.name as leader_name, u.email as leader_email,
               th.name as theme_name, th.color_code as theme_color,
               f.floor_number, r.room_number,
               (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id) as member_count
        FROM teams t 
        LEFT JOIN users u ON t.leader_id = u.id
        LEFT JOIN themes th ON t.theme_id = th.id
        LEFT JOIN floors f ON t.floor_id = f.id
        LEFT JOIN rooms r ON t.room_id = r.id
        INNER JOIN team_members tm ON t.id = tm.team_id
        INNER JOIN users teammate ON tm.user_id = teammate.id
        WHERE teammate.name LIKE ?
    ";
    $params = ["%$teammate_search%"];
} else {
    // Regular query without teammate search
    $query = "
        SELECT t.*, u.name as leader_name, u.email as leader_email,
               th.name as theme_name, th.color_code as theme_color,
               f.floor_number, r.room_number,
               (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id) as member_count
        FROM teams t 
        LEFT JOIN users u ON t.leader_id = u.id
        LEFT JOIN themes th ON t.theme_id = th.id
        LEFT JOIN floors f ON t.floor_id = f.id
        LEFT JOIN rooms r ON t.room_id = r.id
        WHERE 1=1
    ";
    $params = [];
}

// Add other filters
if ($search) {
    $query .= " AND (t.name LIKE ? OR u.name LIKE ? OR t.idea LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status_filter) {
    $query .= " AND t.status = ?";
    $params[] = $status_filter;
}
if ($floor_filter) {
    $query .= " AND f.floor_number = ?";
    $params[] = $floor_filter;
}
if ($room_filter) {
    $query .= " AND r.room_number = ?";
    $params[] = $room_filter;
}

$query .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$teams = $stmt->fetchAll();

// Get floors and rooms for filters and assignment
$stmt = $pdo->query("SELECT * FROM floors ORDER BY floor_number");
$floors = $stmt->fetchAll();

$stmt = $pdo->query("SELECT r.*, f.floor_number FROM rooms r JOIN floors f ON r.floor_id = f.id ORDER BY f.floor_number, r.room_number");
$rooms = $stmt->fetchAll();

// Get team members for modal
$team_members_data = [];
if (isset($_GET['view_team_id'])) {
    $view_team_id = $_GET['view_team_id'];
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.role, tm.joined_at,
               (SELECT COUNT(*) FROM scores s WHERE s.team_id = ? AND s.mentor_id = ?) as mentor_scores_count
        FROM team_members tm 
        JOIN users u ON tm.user_id = u.id 
        WHERE tm.team_id = ?
    ");
    $stmt->execute([$view_team_id, $user['id'], $view_team_id]); // Pass mentor_id for score count
    $team_members_data = $stmt->fetchAll();

    // Get team's submission
    $stmt = $pdo->prepare("SELECT * FROM submissions WHERE team_id = ?");
    $stmt->execute([$view_team_id]);
    $team_submission = $stmt->fetch();

    // Get team's scores (all mentors)
    $stmt = $pdo->prepare("
        SELECT s.score, s.comment, mr.round_name, mr.max_score, u.name as mentor_name, s.created_at
        FROM scores s 
        JOIN mentoring_rounds mr ON s.round_id = mr.id 
        JOIN users u ON s.mentor_id = u.id
        WHERE s.team_id = ?
        ORDER BY mr.start_time DESC, u.name ASC
    ");
    $stmt->execute([$view_team_id]);
    $team_scores = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teams Management - <?php echo htmlspecialchars(getSystemSetting('hackathon_name', 'HackMate')); ?></title>
    
    <!-- Primary Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: relative;
        }
        .close-button {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close-button:hover,
        .close-button:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden lg:ml-0">
            <!-- Top Header -->
            <header class="bg-white shadow-sm border-b border-gray-200 lg:hidden">
                <div class="flex items-center justify-between px-4 py-3">
                    <button onclick="toggleSidebar()" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-semibold text-gray-900">Teams Management</h1>
                    <div class="w-6"></div>
                </div>
            </header>
            
            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- Page Header -->
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">
                                <i class="fas fa-users text-blue-600 mr-3"></i>
                                Teams Management
                            </h1>
                            <p class="text-gray-600 mt-1">Manage team registrations and approvals</p>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="flex items-center space-x-3">
                            <?php if ($pending_teams > 0): ?>
                                <span class="bg-orange-100 text-orange-800 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-clock mr-2"></i>
                                    <?php echo $pending_teams; ?> Pending
                                </span>
                            <?php endif; ?>
                            <span class="bg-blue-100 text-blue-800 px-4 py-2 rounded-lg text-sm font-medium">
                                <i class="fas fa-users mr-2"></i>
                                <?php echo $total_teams; ?> Total
                            </span>
                        </div>
                    </div>
                </div>
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Search and Filter -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4">
                <i class="fas fa-search text-blue-600"></i>
                Search & Filter Teams
            </h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search Teams</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by team name, leader, or idea..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search by Teammate</label>
                    <input type="text" name="teammate_search" value="<?php echo htmlspecialchars($teammate_search); ?>" 
                           placeholder="Search by teammate name..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Floor</label>
                    <select name="floor" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Floors</option>
                        <?php foreach ($floors as $floor): ?>
                            <option value="<?php echo $floor['floor_number']; ?>" <?php echo $floor_filter == $floor['floor_number'] ? 'selected' : ''; ?>>
                                <?php echo $floor['floor_number']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition-colors mr-2">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                    <a href="teams.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md transition-colors">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Teams List -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold">
                    <i class="fas fa-list text-gray-600"></i>
                    All Teams (<?php echo count($teams); ?>)
                </h3>
            </div>
            
            <?php if (empty($teams)): ?>
                <div class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-users-slash text-4xl mb-4"></i>
                    <p>No teams found matching your criteria.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Team Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Theme</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leader</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Members</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($teams as $team): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo $team['name']; ?></div>
                                        <div class="text-sm text-gray-500"><?php echo substr($team['idea'] ?: 'No idea provided', 0, 80) . '...'; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($team['theme_name']): ?>
                                            <div class="flex items-center">
                                                <div class="w-3 h-3 rounded-full mr-2" style="background-color: <?php echo $team['theme_color']; ?>"></div>
                                                <div class="text-sm font-medium text-gray-900"><?php echo $team['theme_name']; ?></div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-sm">No theme</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo $team['leader_name']; ?></div>
                                            <div class="text-sm text-gray-500"><?php echo $team['leader_email']; ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $team['member_count']; ?>/4
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php if ($team['floor_number']): ?>
                                            <?php echo $team['floor_number'] . ' - ' . $team['room_number']; ?>
                                        <?php else: ?>
                                            <span class="text-red-600 font-medium">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php 
                                                if ($team['status'] == 'approved') echo 'bg-green-100 text-green-800';
                                                elseif ($team['status'] == 'pending') echo 'bg-yellow-100 text-yellow-800';
                                                else echo 'bg-red-100 text-red-800';
                                            ?>">
                                            <?php echo ucfirst($team['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="openTeamDetailsModal(<?php echo $team['id']; ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <?php if (!$team['floor_number'] && $team['status'] == 'approved'): ?>
                                            <button onclick="openReassignLocationModal(<?php echo $team['id']; ?>, '<?php echo addslashes($team['name']); ?>')" class="text-orange-600 hover:text-orange-900 mr-3">
                                                <i class="fas fa-map-marker-alt"></i> Reassign
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($team['status'] == 'pending'): ?>
                                            <button onclick="openApproveModal(<?php echo $team['id']; ?>, '<?php echo addslashes($team['name']); ?>')" class="text-green-600 hover:text-green-900 mr-3">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to reject team <?php echo addslashes($team['name']); ?>?');">
                                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" class="inline-block ml-3" onsubmit="return confirm('Are you sure you want to delete team <?php echo addslashes($team['name']); ?>? This action cannot be undone.');">
                                            <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="text-gray-600 hover:text-gray-900">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Approve Team Modal -->
    <div id="approveTeamModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeApproveModal()">&times;</span>
            <h2 class="text-2xl font-bold mb-4">Approve Team <span id="approveTeamName" class="text-purple-600"></span></h2>
            <form method="POST" id="approveTeamForm">
                <input type="hidden" name="team_id" id="approveTeamId">
                <input type="hidden" name="action" value="approve">
                
                <div class="mb-4">
                    <label for="floor_id" class="block text-sm font-medium text-gray-700 mb-2">Assign Floor *</label>
                    <select id="floor_id" name="floor_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Floor</option>
                        <?php foreach ($floors as $floor): ?>
                            <option value="<?php echo $floor['id']; ?>"><?php echo $floor['floor_number']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-6">
                    <label for="room_id" class="block text-sm font-medium text-gray-700 mb-2">Assign Room *</label>
                    <select id="room_id" name="room_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Room</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo $room['id']; ?>" data-floor-id="<?php echo $room['floor_id']; ?>">
                                <?php echo $room['floor_number']; ?> - <?php echo $room['room_number']; ?> (Capacity: <?php echo $room['capacity']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-md transition-colors">
                    <i class="fas fa-check mr-2"></i>
                    Approve & Assign
                </button>
            </form>
        </div>
    </div>

    <!-- Reassign Location Modal -->
    <div id="reassignLocationModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeReassignLocationModal()">&times;</span>
            <h2 class="text-2xl font-bold mb-4">Reassign Location for <span id="reassignTeamName" class="text-orange-600"></span></h2>
            <form method="POST" id="reassignLocationForm">
                <input type="hidden" name="team_id" id="reassignTeamId">
                <input type="hidden" name="action" value="reassign_location">
                
                <div class="mb-4">
                    <label for="reassign_floor_id" class="block text-sm font-medium text-gray-700 mb-2">Select Floor *</label>
                    <select id="reassign_floor_id" name="floor_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="">Select Floor</option>
                        <?php foreach ($floors as $floor): ?>
                            <option value="<?php echo $floor['id']; ?>"><?php echo $floor['floor_number']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-6">
                    <label for="reassign_room_id" class="block text-sm font-medium text-gray-700 mb-2">Select Room *</label>
                    <select id="reassign_room_id" name="room_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="">Select Room</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo $room['id']; ?>" data-floor-id="<?php echo $room['floor_id']; ?>">
                                <?php echo $room['floor_number']; ?> - <?php echo $room['room_number']; ?> (Capacity: <?php echo $room['capacity']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white font-semibold py-2 px-6 rounded-md transition-colors">
                    <i class="fas fa-map-marker-alt mr-2"></i>
                    Reassign Location
                </button>
            </form>
        </div>
    </div>

    <!-- Team Details Modal -->
    <div id="teamDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeTeamDetailsModal()">&times;</span>
            <h2 class="text-2xl font-bold mb-4" id="modalTeamName"></h2>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Team Info -->
                <div>
                    <h3 class="text-lg font-semibold mb-3">
                        <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                        General Information
                    </h3>
                    <p class="mb-2"><span class="font-medium">Leader:</span> <span id="modalLeaderName"></span> (<span id="modalLeaderEmail"></span>)</p>
                    <p class="mb-2"><span class="font-medium">Members:</span> <span id="modalMemberCount"></span>/4</p>
                    <p class="mb-2"><span class="font-medium">Location:</span> <span id="modalLocation"></span></p>
                    <p class="mb-2"><span class="font-medium">Status:</span> <span id="modalStatus" class="px-2 py-1 text-xs font-semibold rounded-full"></span></p>
                    <p class="mb-2"><span class="font-medium">Created:</span> <span id="modalCreatedAt"></span></p>
                    
                    <h4 class="font-semibold mt-4 mb-2">Project Idea:</h4>
                    <p id="modalIdea" class="bg-gray-50 p-3 rounded text-sm"></p>
                    
                    <h4 class="font-semibold mt-4 mb-2">Problem Statement:</h4>
                    <p id="modalProblemStatement" class="bg-gray-50 p-3 rounded text-sm"></p>

                    <h4 class="font-semibold mt-4 mb-2">Submission:</h4>
                    <div id="modalSubmission" class="bg-gray-50 p-3 rounded text-sm">
                        <p id="submissionStatus"></p>
                        <p id="submissionGithub" class="mt-1"></p>
                        <p id="submissionLive" class="mt-1"></p>
                        <p id="submissionTechStack" class="mt-1"></p>
                        <p id="submissionSubmittedAt" class="mt-1"></p>
                    </div>
                </div>

                <!-- Members & Scores -->
                <div>
                    <h3 class="text-lg font-semibold mb-3">
                        <i class="fas fa-users text-purple-600 mr-2"></i>
                        Team Members
                    </h3>
                    <div id="modalTeamMembers" class="space-y-2 mb-6">
                        <!-- Members will be loaded here -->
                    </div>

                    <h3 class="text-lg font-semibold mb-3">
                        <i class="fas fa-star text-orange-600 mr-2"></i>
                        Mentor Scores
                    </h3>
                    <div id="modalTeamScores" class="space-y-2">
                        <!-- Scores will be loaded here -->
                    </div>
                </div>
            </div>
            <div class="mt-6 flex justify-end">
                <a id="exportPdfButton" href="#" target="_blank" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-6 rounded-md transition-colors">
                    <i class="fas fa-file-pdf mr-2"></i>
                    Export PDF Report
                </a>
            </div>
        </div>
    </div>

    <script>
        const approveTeamModal = document.getElementById('approveTeamModal');
        const approveTeamIdInput = document.getElementById('approveTeamId');
        const approveTeamNameSpan = document.getElementById('approveTeamName');
        const floorSelect = document.getElementById('floor_id');
        const roomSelect = document.getElementById('room_id');
        const allRooms = Array.from(roomSelect.options).slice(1); // Exclude "Select Room" option

        function openApproveModal(teamId, teamName) {
            approveTeamIdInput.value = teamId;
            approveTeamNameSpan.textContent = teamName;
            approveTeamModal.style.display = 'flex';
            floorSelect.value = ''; // Reset selections
            roomSelect.value = '';
            filterRooms(); // Show all rooms initially
        }

        function closeApproveModal() {
            approveTeamModal.style.display = 'none';
        }

        // Filter rooms based on selected floor
        floorSelect.addEventListener('change', filterRooms);

        function filterRooms() {
            const selectedFloorId = floorSelect.value;
            roomSelect.innerHTML = '<option value="">Select Room</option>'; // Reset rooms
            
            allRooms.forEach(roomOption => {
                if (selectedFloorId === '' || roomOption.dataset.floorId === selectedFloorId) {
                    roomSelect.appendChild(roomOption.cloneNode(true));
                }
            });
        }

        // Initial filter call to populate rooms if a floor is pre-selected (unlikely with current flow but good practice)
        filterRooms();

        // Team Details Modal
        const teamDetailsModal = document.getElementById('teamDetailsModal');
        const modalTeamName = document.getElementById('modalTeamName');
        const modalLeaderName = document.getElementById('modalLeaderName');
        const modalLeaderEmail = document.getElementById('modalLeaderEmail');
        const modalMemberCount = document.getElementById('modalMemberCount');
        const modalLocation = document.getElementById('modalLocation');
        const modalStatus = document.getElementById('modalStatus');
        const modalCreatedAt = document.getElementById('modalCreatedAt');
        const modalIdea = document.getElementById('modalIdea');
        const modalProblemStatement = document.getElementById('modalProblemStatement');
        const modalTeamMembers = document.getElementById('modalTeamMembers');
        const modalTeamScores = document.getElementById('modalTeamScores');
        const modalSubmission = document.getElementById('modalSubmission');
        const submissionStatus = document.getElementById('submissionStatus');
        const submissionGithub = document.getElementById('submissionGithub');
        const submissionLive = document.getElementById('submissionLive');
        const submissionTechStack = document.getElementById('submissionTechStack');
        const submissionSubmittedAt = document.getElementById('submissionSubmittedAt');
        const exportPdfButton = document.getElementById('exportPdfButton');

        async function openTeamDetailsModal(teamId) {
            try {
                const response = await fetch(`../ajax/get_team_details.php?team_id=${teamId}`);
                const data = await response.json();

                if (data.success) {
                    const team = data.team;
                    const members = data.members;
                    const submission = data.submission;
                    const scores = data.scores;

                    modalTeamName.textContent = team.name;
                    modalLeaderName.textContent = team.leader_name;
                    modalLeaderEmail.textContent = team.leader_email;
                    modalMemberCount.textContent = team.member_count;
                    modalLocation.textContent = team.floor_number ? `${team.floor_number} - ${team.room_number}` : 'N/A';
                    modalStatus.textContent = team.status.charAt(0).toUpperCase() + team.status.slice(1);
                    modalStatus.className = `px-2 py-1 text-xs font-semibold rounded-full ${
                        team.status === 'approved' ? 'bg-green-100 text-green-800' : 
                        team.status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                        'bg-red-100 text-red-800'
                    }`;
                    modalCreatedAt.textContent = new Date(team.created_at).toLocaleString();
                    modalIdea.textContent = team.idea || 'Not provided yet';
                    modalProblemStatement.textContent = team.problem_statement || 'Not provided yet';

                    // Populate members
                    modalTeamMembers.innerHTML = '';
                    if (members.length > 0) {
                        members.forEach(member => {
                            const memberDiv = document.createElement('div');
                            memberDiv.className = 'flex items-center p-2 bg-gray-50 rounded-lg';
                            memberDiv.innerHTML = `
                                <div class="flex-shrink-0">
                                    <i class="fas ${member.id == team.leader_id ? 'fa-crown text-yellow-500' : 'fa-user text-gray-500'} text-lg"></i>
                                </div>
                                <div class="ml-3 flex-1">
                                    <p class="font-medium text-gray-900">${member.name}</p>
                                    <p class="text-sm text-gray-500">${member.email}</p>
                                    <p class="text-xs text-gray-400">${member.id == team.leader_id ? 'Team Leader' : 'Joined: ' + new Date(member.joined_at).toLocaleString()}</p>
                                </div>
                                <form method="POST" class="ml-auto" onsubmit="return confirm('Remove ${member.name} from team?');">
                                    <input type="hidden" name="team_id" value="${team.id}">
                                    <input type="hidden" name="user_id_to_remove" value="${member.id}">
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm p-1 rounded">
                                        <i class="fas fa-user-minus"></i>
                                    </button>
                                </form>
                            `;
                            modalTeamMembers.appendChild(memberDiv);
                        });
                    } else {
                        modalTeamMembers.innerHTML = '<p class="text-gray-500">No members found.</p>';
                    }

                    // Populate submission
                    if (submission) {
                        submissionStatus.className = 'text-green-800 font-medium';
                        submissionStatus.textContent = '✓ Project Submitted';
                        submissionGithub.innerHTML = `<span class="font-medium">GitHub:</span> <a href="${submission.github_link}" target="_blank" class="text-blue-600 hover:text-blue-800 break-all">${submission.github_link}</a>`;
                        submissionLive.innerHTML = submission.live_link ? `<span class="font-medium">Live Demo:</span> <a href="${submission.live_link}" target="_blank" class="text-blue-600 hover:text-blue-800 break-all">${submission.live_link}</a>` : '';
                        submissionTechStack.innerHTML = `<span class="font-medium">Tech Stack:</span> ${submission.tech_stack}`;
                        submissionSubmittedAt.innerHTML = `<span class="font-medium">Submitted:</span> ${new Date(submission.submitted_at).toLocaleString()}`;
                    } else {
                        submissionStatus.className = 'text-yellow-800 font-medium';
                        submissionStatus.textContent = '⏳ Not Submitted Yet';
                        submissionGithub.textContent = '';
                        submissionLive.textContent = '';
                        submissionTechStack.textContent = '';
                        submissionSubmittedAt.textContent = '';
                    }

                    // Populate scores
                    modalTeamScores.innerHTML = '';
                    if (scores.length > 0) {
                        scores.forEach(score => {
                            const scoreDiv = document.createElement('div');
                            scoreDiv.className = 'border-l-4 border-blue-400 bg-blue-50 p-3 rounded';
                            scoreDiv.innerHTML = `
                                <div class="flex justify-between items-start mb-1">
                                    <h5 class="font-medium text-gray-900">${score.round_name} by ${score.mentor_name}</h5>
                                    <span class="text-sm font-bold text-blue-600">${score.score}/${score.max_score}</span>
                                </div>
                                <p class="text-gray-700 text-sm">${score.comment || 'No comment provided.'}</p>
                                <p class="text-xs text-gray-500 mt-1">Scored on ${new Date(score.created_at).toLocaleString()}</p>
                            `;
                            modalTeamScores.appendChild(scoreDiv);
                        });
                    } else {
                        modalTeamScores.innerHTML = '<p class="text-gray-500">No scores recorded yet.</p>';
                    }

                    exportPdfButton.href = `export_team_pdf.php?team_id=${teamId}`;
                    teamDetailsModal.style.display = 'flex';
                } else {
                    alert(data.message || 'Failed to load team details.');
                }
            } catch (error) {
                console.error('Error fetching team details:', error);
                alert('An error occurred while loading team details.');
            }
        }

        function closeTeamDetailsModal() {
            teamDetailsModal.style.display = 'none';
        }

        // Reassign Location Modal
        const reassignLocationModal = document.getElementById('reassignLocationModal');
        const reassignTeamIdInput = document.getElementById('reassignTeamId');
        const reassignTeamNameSpan = document.getElementById('reassignTeamName');
        const reassignFloorSelect = document.getElementById('reassign_floor_id');
        const reassignRoomSelect = document.getElementById('reassign_room_id');
        const allReassignRooms = Array.from(reassignRoomSelect.options).slice(1); // Exclude "Select Room" option

        function openReassignLocationModal(teamId, teamName) {
            reassignTeamIdInput.value = teamId;
            reassignTeamNameSpan.textContent = teamName;
            reassignLocationModal.style.display = 'flex';
            reassignFloorSelect.value = ''; // Reset selections
            reassignRoomSelect.value = '';
            filterReassignRooms(); // Show all rooms initially
        }

        function closeReassignLocationModal() {
            reassignLocationModal.style.display = 'none';
        }

        // Filter rooms based on selected floor for reassignment
        reassignFloorSelect.addEventListener('change', filterReassignRooms);

        function filterReassignRooms() {
            const selectedFloorId = reassignFloorSelect.value;
            reassignRoomSelect.innerHTML = '<option value="">Select Room</option>'; // Reset rooms
            
            allReassignRooms.forEach(roomOption => {
                if (selectedFloorId === '' || roomOption.dataset.floorId === selectedFloorId) {
                    reassignRoomSelect.appendChild(roomOption.cloneNode(true));
                }
            });
        }

        // Initial filter call to populate rooms
        filterReassignRooms();

        // Close modals if clicked outside
        window.onclick = function(event) {
            if (event.target == approveTeamModal) {
                closeApproveModal();
            }
            if (event.target == teamDetailsModal) {
                closeTeamDetailsModal();
            }
            if (event.target == reassignLocationModal) {
                closeReassignLocationModal();
            }
        }
    </script>
            </main>
        </div>
    </div>
</body>
</html>
