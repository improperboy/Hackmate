<?php
// Prevent any duplicate output or warnings
error_reporting(E_ALL & ~E_WARNING);
ini_set('display_errors', 0);

// Output buffering to prevent header issues
if (!ob_get_level()) {
    ob_start();
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('admin');
$user = getCurrentUser();

// Get counts for sidebar notifications
$pending_teams = $pdo->query("SELECT COUNT(*) FROM teams WHERE status = 'pending'")->fetchColumn();
$open_support_requests = $pdo->query("SELECT COUNT(*) FROM support_messages WHERE status = 'open'")->fetchColumn();
$total_submissions = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();

$message = '';
$error = '';

// Handle Add Floor
if ($_POST && isset($_POST['add_floor'])) {
    $floor_number = sanitize($_POST['floor_number']);
    $description = sanitize($_POST['description']);

    if (empty($floor_number)) {
        $error = 'Floor number is required.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO floors (floor_number, description) VALUES (?, ?)");
            if ($stmt->execute([$floor_number, $description])) {
                $message = 'Floor added successfully!';
            } else {
                $error = 'Failed to add floor. It might already exist.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle Add Room
if ($_POST && isset($_POST['add_room'])) {
    $floor_id = intval($_POST['floor_id']);
    $room_number = sanitize($_POST['room_number']);
    $capacity = intval($_POST['capacity']);

    if (empty($room_number) || $capacity <= 0) {
        $error = 'Room number and valid capacity are required.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO rooms (floor_id, room_number, capacity) VALUES (?, ?, ?)");
            if ($stmt->execute([$floor_id, $room_number, $capacity])) {
                $message = 'Room added successfully!';
            } else {
                $error = 'Failed to add room. It might already exist on this floor.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle Delete Floor
if ($_POST && isset($_POST['delete_floor'])) {
    $floor_id = intval($_POST['floor_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM floors WHERE id = ?");
        if ($stmt->execute([$floor_id])) {
            $message = 'Floor and its rooms deleted successfully!';
        } else {
            $error = 'Failed to delete floor.';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Handle Check Room Dependencies
if ($_POST && isset($_POST['check_room_dependencies'])) {
    $room_id = intval($_POST['room_id']);
    
    // Get affected entities
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as team_count 
        FROM teams 
        WHERE room_id = ?
    ");
    $stmt->execute([$room_id]);
    $affected_teams = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as mentor_count 
        FROM mentor_assignments 
        WHERE room_id = ?
    ");
    $stmt->execute([$room_id]);
    $affected_mentors = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as volunteer_count 
        FROM volunteer_assignments 
        WHERE room_id = ?
    ");
    $stmt->execute([$room_id]);
    $affected_volunteers = $stmt->fetchColumn();
    
    // Get room details
    $stmt = $pdo->prepare("
        SELECT r.room_number, f.floor_number 
        FROM rooms r 
        JOIN floors f ON r.floor_id = f.id 
        WHERE r.id = ?
    ");
    $stmt->execute([$room_id]);
    $room_details = $stmt->fetch();
    
    // Store in session for modal display
    $_SESSION['room_deletion_check'] = [
        'room_id' => $room_id,
        'room_number' => $room_details['room_number'],
        'floor_number' => $room_details['floor_number'],
        'affected_teams' => $affected_teams,
        'affected_mentors' => $affected_mentors,
        'affected_volunteers' => $affected_volunteers
    ];
}

// Handle Bulk Reassignment Before Room Deletion
if ($_POST && isset($_POST['bulk_reassign_and_delete'])) {
    $room_id = intval($_POST['room_id']);
    $new_room_id = intval($_POST['new_room_id']);
    
    if ($new_room_id <= 0) {
        $error = 'Please select a valid room for reassignment.';
    } else {
        $pdo->beginTransaction();
        try {
            // Get new room's floor_id
            $stmt = $pdo->prepare("SELECT floor_id FROM rooms WHERE id = ?");
            $stmt->execute([$new_room_id]);
            $new_floor_id = $stmt->fetchColumn();
            
            // Reassign all teams
            $stmt = $pdo->prepare("UPDATE teams SET floor_id = ?, room_id = ? WHERE room_id = ?");
            $stmt->execute([$new_floor_id, $new_room_id, $room_id]);
            $reassigned_teams = $stmt->rowCount();
            
            // Reassign all mentor assignments
            $stmt = $pdo->prepare("UPDATE mentor_assignments SET floor_id = ?, room_id = ? WHERE room_id = ?");
            $stmt->execute([$new_floor_id, $new_room_id, $room_id]);
            $reassigned_mentors = $stmt->rowCount();
            
            // Reassign all volunteer assignments
            $stmt = $pdo->prepare("UPDATE volunteer_assignments SET floor_id = ?, room_id = ? WHERE room_id = ?");
            $stmt->execute([$new_floor_id, $new_room_id, $room_id]);
            $reassigned_volunteers = $stmt->rowCount();
            
            // Now delete the room
            $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
            $stmt->execute([$room_id]);
            
            $pdo->commit();
            $message = "Room deleted successfully! Reassigned {$reassigned_teams} teams, {$reassigned_mentors} mentor assignments, and {$reassigned_volunteers} volunteer assignments.";
            
            // Clear session data
            unset($_SESSION['room_deletion_check']);
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to reassign and delete room: ' . $e->getMessage();
        }
    }
}

// Handle Cancel Room Deletion
if ($_POST && isset($_POST['cancel_room_deletion'])) {
    unset($_SESSION['room_deletion_check']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Handle Delete Room (without dependencies)
if ($_POST && isset($_POST['delete_room_confirmed'])) {
    $room_id = intval($_POST['room_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
        if ($stmt->execute([$room_id])) {
            $message = 'Room deleted successfully!';
            unset($_SESSION['room_deletion_check']);
        } else {
            $error = 'Failed to delete room.';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Fetch all floors and their rooms
$floors_data = $pdo->query("SELECT * FROM floors ORDER BY floor_number")->fetchAll(PDO::FETCH_ASSOC);
foreach ($floors_data as &$floor) {
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE floor_id = ? ORDER BY room_number");
    $stmt->execute([$floor['id']]);
    $floor['rooms'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($floor); // Break the reference

// Fetch all mentors and volunteers for assignment
$mentors = $pdo->query("SELECT id, name, email FROM users WHERE role = 'mentor' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$volunteers = $pdo->query("SELECT id, name, email FROM users WHERE role = 'volunteer' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing mentor assignments
$mentor_assignments = [];
$stmt = $pdo->query("
    SELECT ma.mentor_id, ma.floor_id, ma.room_id, u.name as mentor_name, f.floor_number, r.room_number
    FROM mentor_assignments ma
    JOIN users u ON ma.mentor_id = u.id
    JOIN floors f ON ma.floor_id = f.id
    JOIN rooms r ON ma.room_id = r.id
    ORDER BY u.name, f.floor_number, r.room_number
");
$existing_mentor_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing volunteer assignments
$volunteer_assignments = [];
$stmt = $pdo->query("
    SELECT va.volunteer_id, va.floor_id, va.room_id, u.name as volunteer_name, f.floor_number, r.room_number
    FROM volunteer_assignments va
    JOIN users u ON va.volunteer_id = u.id
    JOIN floors f ON va.floor_id = f.id
    JOIN rooms r ON va.room_id = r.id
    ORDER BY u.name, f.floor_number, r.room_number
");
$existing_volunteer_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Assign Mentor
if ($_POST && isset($_POST['assign_mentor'])) {
    $mentor_id = intval($_POST['mentor_id']);
    $floor_id = intval($_POST['assign_mentor_floor_id']);
    $room_id = intval($_POST['assign_mentor_room_id']);

    if ($mentor_id <= 0 || $floor_id <= 0 || $room_id <= 0) {
        $error = 'All assignment fields are required.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO mentor_assignments (mentor_id, floor_id, room_id) VALUES (?, ?, ?)");
            if ($stmt->execute([$mentor_id, $floor_id, $room_id])) {
                $message = 'Mentor assigned successfully!';
            } else {
                $error = 'Failed to assign mentor. This assignment might already exist.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle Remove Mentor Assignment
if ($_POST && isset($_POST['remove_mentor_assignment'])) {
    $mentor_id = intval($_POST['mentor_id']);
    $floor_id = intval($_POST['floor_id']);
    $room_id = intval($_POST['room_id']);

    try {
        $stmt = $pdo->prepare("DELETE FROM mentor_assignments WHERE mentor_id = ? AND floor_id = ? AND room_id = ?");
        if ($stmt->execute([$mentor_id, $floor_id, $room_id])) {
            $message = 'Mentor assignment removed successfully!';
        } else {
            $error = 'Failed to remove mentor assignment.';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Handle Assign Volunteer
if ($_POST && isset($_POST['assign_volunteer'])) {
    $volunteer_id = intval($_POST['volunteer_id']);
    $floor_id = intval($_POST['assign_volunteer_floor_id']);
    $room_id = intval($_POST['assign_volunteer_room_id']);

    if ($volunteer_id <= 0 || $floor_id <= 0 || $room_id <= 0) {
        $error = 'All assignment fields are required.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO volunteer_assignments (volunteer_id, floor_id, room_id) VALUES (?, ?, ?)");
            if ($stmt->execute([$volunteer_id, $floor_id, $room_id])) {
                $message = 'Volunteer assigned successfully!';
            } else {
                $error = 'Failed to assign volunteer. This assignment might already exist.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle Remove Volunteer Assignment
if ($_POST && isset($_POST['remove_volunteer_assignment'])) {
    $volunteer_id = intval($_POST['volunteer_id']);
    $floor_id = intval($_POST['floor_id']);
    $room_id = intval($_POST['room_id']);

    try {
        $stmt = $pdo->prepare("DELETE FROM volunteer_assignments WHERE volunteer_id = ? AND floor_id = ? AND room_id = ?");
        if ($stmt->execute([$volunteer_id, $floor_id, $room_id])) {
            $message = 'Volunteer assignment removed successfully!';
        } else {
            $error = 'Failed to remove volunteer assignment.';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Re-fetch data after any POST operation to ensure fresh display
$floors_data = $pdo->query("SELECT * FROM floors ORDER BY floor_number")->fetchAll(PDO::FETCH_ASSOC);
foreach ($floors_data as &$floor) {
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE floor_id = ? ORDER BY room_number");
    $stmt->execute([$floor['id']]);
    $floor['rooms'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($floor);

$mentors = $pdo->query("SELECT id, name, email FROM users WHERE role = 'mentor' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$volunteers = $pdo->query("SELECT id, name, email FROM users WHERE role = 'volunteer' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("
    SELECT ma.mentor_id, ma.floor_id, ma.room_id, u.name as mentor_name, f.floor_number, r.room_number
    FROM mentor_assignments ma
    JOIN users u ON ma.mentor_id = u.id
    JOIN floors f ON ma.floor_id = f.id
    JOIN rooms r ON ma.room_id = r.id
    ORDER BY u.name, f.floor_number, r.room_number
");
$existing_mentor_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("
    SELECT va.volunteer_id, va.floor_id, va.room_id, u.name as volunteer_name, f.floor_number, r.room_number
    FROM volunteer_assignments va
    JOIN users u ON va.volunteer_id = u.id
    JOIN floors f ON va.floor_id = f.id
    JOIN rooms r ON va.room_id = r.id
    ORDER BY u.name, f.floor_number, r.room_number
");
$existing_volunteer_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Locations - <?php echo htmlspecialchars(getSystemSetting('hackathon_name', 'HackMate')); ?></title>
    
    <!-- Primary Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- PWA Configuration -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#4F46E5">
    
    <style>
        .location-card {
            transition: all 0.2s ease-in-out;
        }
        
        .location-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .room-card {
            transition: all 0.2s ease-in-out;
        }
        
        .room-card:hover {
            transform: scale(1.02);
        }
        
        .modal {
            backdrop-filter: blur(4px);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="flex h-screen">
        <!-- Include Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden lg:ml-0">
            <!-- Top Header -->
            <header class="bg-white shadow-sm border-b border-gray-200 lg:hidden">
                <div class="flex items-center justify-between px-4 py-3">
                    <button onclick="toggleSidebar()" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-semibold text-gray-900">Locations</h1>
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
                                <i class="fas fa-map-marker-alt text-blue-600 mr-3"></i>
                                Locations Management
                            </h1>
                            <p class="text-gray-600 mt-1">Manage floors, rooms, and team assignments</p>
                        </div>
                    </div>
                </div>
                    <!-- Messages -->
                    <?php if ($message): ?>
                        <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                            <div class="flex">
                                <i class="fas fa-check-circle text-green-400 mt-0.5"></i>
                                <p class="ml-3 text-sm text-green-700"><?php echo htmlspecialchars($message); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                            <div class="flex">
                                <i class="fas fa-exclamation-triangle text-red-400 mt-0.5"></i>
                                <p class="ml-3 text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <!-- Add Floor Form -->
                        <div class="location-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                            <h3 class="text-lg font-semibold mb-4">
                                <i class="fas fa-plus-square text-blue-600 mr-2"></i>
                                Add New Floor
                            </h3>
                <form method="POST" class="space-y-4">
                    <div>
                        <label for="floor_number" class="block text-sm font-medium text-gray-700 mb-2">Floor Number *</label>
                        <input type="text" id="floor_number" name="floor_number" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g., F1, Ground Floor">
                    </div>
                    <div>
                        <label for="floor_description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea id="floor_description" name="description" rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Optional description for the floor"></textarea>
                    </div>
                            <button type="submit" name="add_floor" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-md transition-colors">
                                <i class="fas fa-plus mr-2"></i>Add Floor
                            </button>
                        </form>
                        </div>

                        <!-- Add Room Form -->
                        <div class="location-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                            <h3 class="text-lg font-semibold mb-4">
                                <i class="fas fa-plus-circle text-green-600 mr-2"></i>
                                Add New Room
                            </h3>
                <form method="POST" class="space-y-4">
                    <div>
                        <label for="room_floor_id" class="block text-sm font-medium text-gray-700 mb-2">Select Floor *</label>
                        <select id="room_floor_id" name="floor_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="">Select a Floor</option>
                            <?php foreach ($floors_data as $floor): ?>
                                <option value="<?php echo $floor['id']; ?>"><?php echo $floor['floor_number']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="room_number" class="block text-sm font-medium text-gray-700 mb-2">Room Number *</label>
                        <input type="text" id="room_number" name="room_number" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                               placeholder="e.g., R101, Auditorium">
                    </div>
                    <div>
                        <label for="capacity" class="block text-sm font-medium text-gray-700 mb-2">Capacity *</label>
                        <input type="number" id="capacity" name="capacity" required min="1" value="4"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                            <button type="submit" name="add_room" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-md transition-colors">
                                <i class="fas fa-plus mr-2"></i>Add Room
                            </button>
                        </form>
                        </div>
                    </div>

                    <!-- Floors and Rooms List -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 mb-8">
                        <h3 class="text-lg font-semibold mb-4">
                            <i class="fas fa-building text-gray-600 mr-2"></i>
                            Existing Floors & Rooms
                        </h3>
            <?php if (empty($floors_data)): ?>
                <p class="text-gray-500 text-center py-4">No floors or rooms added yet.</p>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($floors_data as $floor): ?>
                        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                            <div class="flex justify-between items-center mb-3">
                                <h4 class="text-lg font-bold text-gray-800">
                                    <i class="fas fa-layer-group mr-2 text-teal-600"></i>
                                    Floor: <?php echo htmlspecialchars($floor['floor_number']); ?>
                                </h4>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this floor and all its rooms? This action cannot be undone.');">
                                    <input type="hidden" name="floor_id" value="<?php echo $floor['id']; ?>">
                                    <button type="submit" name="delete_floor" class="text-red-600 hover:text-red-800 text-sm">
                                        <i class="fas fa-trash mr-1"></i>Delete Floor
                                    </button>
                                </form>
                            </div>
                            <?php if ($floor['description']): ?>
                                <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($floor['description']); ?></p>
                            <?php endif; ?>

                            <?php if (empty($floor['rooms'])): ?>
                                <p class="text-gray-500 text-sm mt-2">No rooms on this floor yet.</p>
                            <?php else: ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                                    <?php foreach ($floor['rooms'] as $room): ?>
                                        <div class="bg-white border border-gray-200 rounded-lg p-3 shadow-sm">
                                            <div class="flex justify-between items-center mb-2">
                                                <h5 class="font-semibold text-gray-900">
                                                    <i class="fas fa-door-open mr-2 text-green-600"></i>
                                                    Room: <?php echo htmlspecialchars($room['room_number']); ?>
                                                </h5>
                                                <form method="POST">
                                                    <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                                                    <button type="submit" name="check_room_dependencies" class="text-red-500 hover:text-red-700 text-xs">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                            <p class="text-sm text-gray-700">Capacity: <?php echo htmlspecialchars($room['capacity']); ?></p>
                                            <?php if ($room['description']): ?>
                                                <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($room['description']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Mentor Assignments -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h3 class="text-lg font-semibold mb-4">
                <i class="fas fa-chalkboard-teacher text-indigo-600"></i>
                Assign Mentors to Areas
            </h3>
            <form method="POST" class="space-y-4 mb-6">
                <div>
                    <label for="mentor_id" class="block text-sm font-medium text-gray-700 mb-2">Select Mentor *</label>
                    <select id="mentor_id" name="mentor_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Select Mentor</option>
                        <?php foreach ($mentors as $mentor): ?>
                            <option value="<?php echo $mentor['id']; ?>"><?php echo htmlspecialchars($mentor['name']); ?> (<?php echo htmlspecialchars($mentor['email']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="assign_mentor_floor_id" class="block text-sm font-medium text-gray-700 mb-2">Assign Floor *</label>
                        <select id="assign_mentor_floor_id" name="assign_mentor_floor_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">Select Floor</option>
                            <?php foreach ($floors_data as $floor): ?>
                                <option value="<?php echo $floor['id']; ?>"><?php echo htmlspecialchars($floor['floor_number']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="assign_mentor_room_id" class="block text-sm font-medium text-gray-700 mb-2">Assign Room *</label>
                        <select id="assign_mentor_room_id" name="assign_mentor_room_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">Select Room</option>
                            <?php 
                            foreach ($floors_data as $floor) {
                                foreach ($floor['rooms'] as $room) {
                                    echo '<option value="' . $room['id'] . '" data-floor-id="' . $floor['id'] . '">' . htmlspecialchars($floor['floor_number'] . ' - ' . $room['room_number']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="assign_mentor" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-6 rounded-md transition-colors">
                    <i class="fas fa-user-plus mr-2"></i>Assign Mentor
                </button>
            </form>

            <h4 class="text-md font-semibold mb-3">Existing Mentor Assignments:</h4>
            <?php if (empty($existing_mentor_assignments)): ?>
                <p class="text-gray-500 text-sm">No mentor assignments yet.</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($existing_mentor_assignments as $assignment): ?>
                        <div class="flex justify-between items-center bg-gray-50 p-3 rounded-lg border border-gray-200">
                            <p class="text-sm text-gray-800">
                                <strong><?php echo htmlspecialchars($assignment['mentor_name']); ?></strong> assigned to 
                                <strong><?php echo htmlspecialchars($assignment['floor_number'] . ' - ' . $assignment['room_number']); ?></strong>
                            </p>
                            <form method="POST" onsubmit="return confirm('Remove this mentor assignment?');">
                                <input type="hidden" name="mentor_id" value="<?php echo $assignment['mentor_id']; ?>">
                                <input type="hidden" name="floor_id" value="<?php echo $assignment['floor_id']; ?>">
                                <input type="hidden" name="room_id" value="<?php echo $assignment['room_id']; ?>">
                                <button type="submit" name="remove_mentor_assignment" class="text-red-600 hover:text-red-800 text-sm">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Volunteer Assignments -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">
                <i class="fas fa-hands-helping text-orange-600"></i>
                Assign Volunteers to Areas
            </h3>
            <form method="POST" class="space-y-4 mb-6">
                <div>
                    <label for="volunteer_id" class="block text-sm font-medium text-gray-700 mb-2">Select Volunteer *</label>
                    <select id="volunteer_id" name="volunteer_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="">Select Volunteer</option>
                        <?php foreach ($volunteers as $volunteer): ?>
                            <option value="<?php echo $volunteer['id']; ?>"><?php echo htmlspecialchars($volunteer['name']); ?> (<?php echo htmlspecialchars($volunteer['email']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="assign_volunteer_floor_id" class="block text-sm font-medium text-gray-700 mb-2">Assign Floor *</label>
                        <select id="assign_volunteer_floor_id" name="assign_volunteer_floor_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="">Select Floor</option>
                            <?php foreach ($floors_data as $floor): ?>
                                <option value="<?php echo $floor['id']; ?>"><?php echo htmlspecialchars($floor['floor_number']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="assign_volunteer_room_id" class="block text-sm font-medium text-gray-700 mb-2">Assign Room *</label>
                        <select id="assign_volunteer_room_id" name="assign_volunteer_room_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="">Select Room</option>
                            <?php 
                            foreach ($floors_data as $floor) {
                                foreach ($floor['rooms'] as $room) {
                                    echo '<option value="' . $room['id'] . '" data-floor-id="' . $floor['id'] . '">' . htmlspecialchars($floor['floor_number'] . ' - ' . $room['room_number']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="assign_volunteer" class="bg-orange-600 hover:bg-orange-700 text-white font-semibold py-2 px-6 rounded-md transition-colors">
                    <i class="fas fa-user-plus mr-2"></i>Assign Volunteer
                </button>
            </form>

            <h4 class="text-md font-semibold mb-3">Existing Volunteer Assignments:</h4>
            <?php if (empty($existing_volunteer_assignments)): ?>
                <p class="text-gray-500 text-sm">No volunteer assignments yet.</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($existing_volunteer_assignments as $assignment): ?>
                        <div class="flex justify-between items-center bg-gray-50 p-3 rounded-lg border border-gray-200">
                            <p class="text-sm text-gray-800">
                                <strong><?php echo htmlspecialchars($assignment['volunteer_name']); ?></strong> assigned to 
                                <strong><?php echo htmlspecialchars($assignment['floor_number'] . ' - ' . $assignment['room_number']); ?></strong>
                            </p>
                            <form method="POST" onsubmit="return confirm('Remove this volunteer assignment?');">
                                <input type="hidden" name="volunteer_id" value="<?php echo $assignment['volunteer_id']; ?>">
                                <input type="hidden" name="floor_id" value="<?php echo $assignment['floor_id']; ?>">
                                <input type="hidden" name="room_id" value="<?php echo $assignment['room_id']; ?>">
                                <button type="submit" name="remove_volunteer_assignment" class="text-red-600 hover:text-red-800 text-sm">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Room Deletion Dependencies Modal -->
    <?php if (isset($_SESSION['room_deletion_check'])): ?>
    <div id="roomDeletionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 mt-4">Room Deletion Warning</h3>
                <div class="mt-4 px-7 py-3">
                    <p class="text-sm text-gray-500 mb-4">
                        You are about to delete room <strong><?php echo $_SESSION['room_deletion_check']['floor_number'] . ' - ' . $_SESSION['room_deletion_check']['room_number']; ?></strong>
                    </p>
                    
                    <?php 
                    $total_affected = $_SESSION['room_deletion_check']['affected_teams'] + $_SESSION['room_deletion_check']['affected_mentors'] + $_SESSION['room_deletion_check']['affected_volunteers'];
                    if ($total_affected > 0): 
                    ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-400 text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium text-yellow-800">The following entities are assigned to this room:</h4>
                                <div class="mt-2 text-sm text-yellow-700">
                                    <ul class="list-disc list-inside space-y-1">
                                        <?php if ($_SESSION['room_deletion_check']['affected_teams'] > 0): ?>
                                            <li><strong><?php echo $_SESSION['room_deletion_check']['affected_teams']; ?></strong> team(s)</li>
                                        <?php endif; ?>
                                        <?php if ($_SESSION['room_deletion_check']['affected_mentors'] > 0): ?>
                                            <li><strong><?php echo $_SESSION['room_deletion_check']['affected_mentors']; ?></strong> mentor assignment(s)</li>
                                        <?php endif; ?>
                                        <?php if ($_SESSION['room_deletion_check']['affected_volunteers'] > 0): ?>
                                            <li><strong><?php echo $_SESSION['room_deletion_check']['affected_volunteers']; ?></strong> volunteer assignment(s)</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <p class="text-sm text-gray-600 mb-4">
                        Choose an option:
                    </p>
                    
                    <!-- Reassign Option -->
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="room_id" value="<?php echo $_SESSION['room_deletion_check']['room_id']; ?>">
                        <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <h4 class="text-sm font-medium text-blue-800 mb-3">
                                <i class="fas fa-exchange-alt mr-2"></i>Option 1: Reassign to another room
                            </h4>
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-blue-700 mb-2">Select Room for Reassignment:</label>
                                <select name="new_room_id" required class="w-full px-3 py-2 border border-blue-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Room</option>
                                    <?php 
                                    foreach ($floors_data as $floor) {
                                        foreach ($floor['rooms'] as $room) {
                                            if ($room['id'] != $_SESSION['room_deletion_check']['room_id']) {
                                                echo '<option value="' . $room['id'] . '">' . htmlspecialchars($floor['floor_number'] . ' - ' . $room['room_number'] . ' (Capacity: ' . $room['capacity'] . ')') . '</option>';
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <button type="submit" name="bulk_reassign_and_delete" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md transition-colors">
                                <i class="fas fa-exchange-alt mr-2"></i>Reassign & Delete Room
                            </button>
                        </div>
                    </form>
                    
                    <!-- Force Delete Option -->
                    <form method="POST" onsubmit="return confirm('Are you sure? This will orphan all teams and remove all mentor/volunteer assignments from this room. This action cannot be undone!');">
                        <input type="hidden" name="room_id" value="<?php echo $_SESSION['room_deletion_check']['room_id']; ?>">
                        <div class="bg-red-50 border border-red-200 rounded-md p-4">
                            <h4 class="text-sm font-medium text-red-800 mb-2">
                                <i class="fas fa-exclamation-triangle mr-2"></i>Option 2: Force delete (not recommended)
                            </h4>
                            <p class="text-xs text-red-600 mb-3">
                                This will orphan teams (set their location to N/A) and remove all mentor/volunteer assignments.
                            </p>
                            <button type="submit" name="delete_room_confirmed" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-md transition-colors">
                                <i class="fas fa-trash mr-2"></i>Force Delete
                            </button>
                        </div>
                    </form>
                    
                    <?php else: ?>
                    <div class="bg-green-50 border border-green-200 rounded-md p-4 mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400 text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-800">
                                    No teams, mentors, or volunteers are assigned to this room. It can be safely deleted.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="room_id" value="<?php echo $_SESSION['room_deletion_check']['room_id']; ?>">
                        <button type="submit" name="delete_room_confirmed" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-md transition-colors mr-3">
                            <i class="fas fa-trash mr-2"></i>Delete Room
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="items-center px-4 py-3">
                    <form method="POST" class="inline">
                        <button type="submit" name="cancel_room_deletion" class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md shadow-sm hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-300">
                            Cancel
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Function to filter rooms based on selected floor for mentor assignment
        const assignMentorFloorSelect = document.getElementById('assign_mentor_floor_id');
        const assignMentorRoomSelect = document.getElementById('assign_mentor_room_id');
        const allMentorRooms = Array.from(assignMentorRoomSelect.options).slice(1); // Exclude "Select Room" option

        assignMentorFloorSelect.addEventListener('change', filterMentorRooms);

        function filterMentorRooms() {
            const selectedFloorId = assignMentorFloorSelect.value;
            assignMentorRoomSelect.innerHTML = '<option value="">Select Room</option>'; // Reset rooms
            
            allMentorRooms.forEach(roomOption => {
                if (selectedFloorId === '' || roomOption.dataset.floorId === selectedFloorId) {
                    assignMentorRoomSelect.appendChild(roomOption.cloneNode(true));
                }
            });
        }
        filterMentorRooms(); // Initial call

        // Function to filter rooms based on selected floor for volunteer assignment
        const assignVolunteerFloorSelect = document.getElementById('assign_volunteer_floor_id');
        const assignVolunteerRoomSelect = document.getElementById('assign_volunteer_room_id');
        const allVolunteerRooms = Array.from(assignVolunteerRoomSelect.options).slice(1); // Exclude "Select Room" option

        assignVolunteerFloorSelect.addEventListener('change', filterVolunteerRooms);

        function filterVolunteerRooms() {
            const selectedFloorId = assignVolunteerFloorSelect.value;
            assignVolunteerRoomSelect.innerHTML = '<option value="">Select Room</option>'; // Reset rooms
            
            allVolunteerRooms.forEach(roomOption => {
                if (selectedFloorId === '' || roomOption.dataset.floorId === selectedFloorId) {
                    assignVolunteerRoomSelect.appendChild(roomOption.cloneNode(true));
                }
            });
        }
        filterVolunteerRooms(); // Initial call
    </script>
</body>
</html>
                </div>
            </main>
        </div>
    </div>

    <!-- Room Deletion Confirmation Modal -->
    <?php if (isset($_SESSION['room_deletion_check'])): ?>
        <div class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl shadow-xl p-6 max-w-md w-full mx-4">
                <div class="flex items-center mb-4">
                    <i class="fas fa-exclamation-triangle text-yellow-500 text-2xl mr-3"></i>
                    <h3 class="text-lg font-semibold text-gray-900">Confirm Room Deletion</h3>
                </div>
                
                <p class="text-gray-600 mb-4">
                    You are about to delete Room <?php echo htmlspecialchars($_SESSION['room_deletion_check']['room_number']); ?> 
                    on Floor <?php echo htmlspecialchars($_SESSION['room_deletion_check']['floor_number']); ?>.
                </p>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                    <h4 class="font-medium text-yellow-800 mb-2">This will affect:</h4>
                    <ul class="text-sm text-yellow-700 space-y-1">
                        <li>• <?php echo $_SESSION['room_deletion_check']['affected_teams']; ?> teams</li>
                        <li>• <?php echo $_SESSION['room_deletion_check']['affected_mentors']; ?> mentor assignments</li>
                        <li>• <?php echo $_SESSION['room_deletion_check']['affected_volunteers']; ?> volunteer assignments</li>
                    </ul>
                </div>
                
                <?php if ($_SESSION['room_deletion_check']['affected_teams'] > 0 || 
                         $_SESSION['room_deletion_check']['affected_mentors'] > 0 || 
                         $_SESSION['room_deletion_check']['affected_volunteers'] > 0): ?>
                    <form method="POST" class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reassign to Room:</label>
                        <select name="new_room_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="">Select a room for reassignment</option>
                            <?php foreach ($floors_data as $floor): ?>
                                <?php foreach ($floor['rooms'] as $room): ?>
                                    <?php if ($room['id'] != $_SESSION['room_deletion_check']['room_id']): ?>
                                        <option value="<?php echo $room['id']; ?>">
                                            <?php echo htmlspecialchars($floor['floor_number'] . ' - ' . $room['room_number']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="room_id" value="<?php echo $_SESSION['room_deletion_check']['room_id']; ?>">
                        <div class="flex space-x-3 mt-4">
                            <button type="submit" name="bulk_reassign_and_delete" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md">
                                Reassign & Delete
                            </button>
                            <button type="submit" name="cancel_room_deletion" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md">
                                Cancel
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="flex space-x-3">
                        <form method="POST" class="inline">
                            <input type="hidden" name="room_id" value="<?php echo $_SESSION['room_deletion_check']['room_id']; ?>">
                            <button type="submit" name="delete_room_confirmed" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md">
                                Delete Room
                            </button>
                        </form>
                        <form method="POST" class="inline">
                            <button type="submit" name="cancel_room_deletion" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md">
                                Cancel
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <script>

        // Dynamic room selection based on floor
        document.getElementById('assign_mentor_floor_id')?.addEventListener('change', function() {
            const floorId = this.value;
            const roomSelect = document.getElementById('assign_mentor_room_id');
            const options = roomSelect.querySelectorAll('option[data-floor-id]');
            
            // Hide all room options first
            options.forEach(option => {
                option.style.display = 'none';
            });
            
            // Show only rooms for selected floor
            if (floorId) {
                options.forEach(option => {
                    if (option.getAttribute('data-floor-id') === floorId) {
                        option.style.display = 'block';
                    }
                });
            }
            
            roomSelect.value = ''; // Reset room selection
        });

        document.getElementById('assign_volunteer_floor_id')?.addEventListener('change', function() {
            const floorId = this.value;
            const roomSelect = document.getElementById('assign_volunteer_room_id');
            const options = roomSelect.querySelectorAll('option[data-floor-id]');
            
            // Hide all room options first
            options.forEach(option => {
                option.style.display = 'none';
            });
            
            // Show only rooms for selected floor
            if (floorId) {
                options.forEach(option => {
                    if (option.getAttribute('data-floor-id') === floorId) {
                        option.style.display = 'block';
                    }
                });
            }
            
            roomSelect.value = ''; // Reset room selection
        });

        // Auto-refresh page every 30 seconds to show updated assignments
        setInterval(function() {
            // Only refresh if no forms are being filled
            const forms = document.querySelectorAll('form');
            let hasActiveInput = false;
            
            forms.forEach(form => {
                const inputs = form.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    if (input === document.activeElement || input.value !== input.defaultValue) {
                        hasActiveInput = true;
                    }
                });
            });
            
            if (!hasActiveInput) {
                console.log('Auto-refreshing location data...');
                // You can add AJAX refresh here instead of full page reload
            }
        }, 30000);
    </script>
</body>
</html>