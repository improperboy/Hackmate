<?php
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

// Handle Assign Volunteer
if ($_POST && isset($_POST['assign_volunteer'])) {
    $volunteer_id = intval($_POST['volunteer_id']);
    $floor_id = intval($_POST['floor_id']);
    $room_id = intval($_POST['room_id']);

    if ($volunteer_id <= 0 || $floor_id <= 0 || $room_id <= 0) {
        $error = 'All assignment fields are required.';
    } else {
        try {
            // Check if volunteer is already assigned to any room (one volunteer = one room only)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM volunteer_assignments WHERE volunteer_id = ?");
            $stmt->execute([$volunteer_id]);

            if ($stmt->fetchColumn() > 0) {
                $error = 'This volunteer is already assigned to a room. Each volunteer can only be assigned to one room.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO volunteer_assignments (volunteer_id, floor_id, room_id) VALUES (?, ?, ?)");
                if ($stmt->execute([$volunteer_id, $floor_id, $room_id])) {
                    $message = 'Volunteer assigned successfully!';
                } else {
                    $error = 'Failed to assign volunteer.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle Remove Volunteer Assignment
if ($_POST && isset($_POST['remove_assignment'])) {
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

// Handle Bulk Assignment
if ($_POST && isset($_POST['bulk_assign'])) {
    $volunteer_id = intval($_POST['bulk_volunteer_id']);
    $selected_locations = $_POST['selected_locations'] ?? [];

    if ($volunteer_id <= 0 || empty($selected_locations)) {
        $error = 'Please select a volunteer and at least one location.';
    } else {
        // First check if volunteer is already assigned to any room
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM volunteer_assignments WHERE volunteer_id = ?");
        $stmt->execute([$volunteer_id]);

        if ($stmt->fetchColumn() > 0) {
            $error = 'This volunteer is already assigned to a room. Each volunteer can only be assigned to one room.';
        } else {
            $success_count = 0;
            $error_count = 0;

            // Get the selected location (only one since we're using radio buttons)
            $selected_location = $selected_locations[0];
            list($floor_id, $room_id) = explode('-', $selected_location);
            $floor_id = intval($floor_id);
            $room_id = intval($room_id);

            try {
                $stmt = $pdo->prepare("INSERT INTO volunteer_assignments (volunteer_id, floor_id, room_id) VALUES (?, ?, ?)");
                if ($stmt->execute([$volunteer_id, $floor_id, $room_id])) {
                    $success_count = 1;
                } else {
                    $error_count = 1;
                }
            } catch (PDOException $e) {
                $error_count = 1;
            }
        }

        if ($success_count > 0) {
            $message = "Successfully assigned volunteer to {$success_count} location(s).";
            if ($error_count > 0) {
                $message .= " {$error_count} assignment(s) failed or already existed.";
            }
        } else {
            $error = 'No new assignments were made. All selected locations may already be assigned to this volunteer.';
        }
    }
}

// Handle Schedule Assignment (Time-based) - Simplified for current DB structure
if ($_POST && isset($_POST['schedule_assignment'])) {
    $volunteer_id = intval($_POST['schedule_volunteer_id']);
    $floor_id = intval($_POST['schedule_floor_id']);
    $room_id = intval($_POST['schedule_room_id']);

    if ($volunteer_id <= 0 || $floor_id <= 0 || $room_id <= 0) {
        $error = 'All assignment fields are required.';
    } else {
        try {
            // Check if volunteer is already assigned to any room (one volunteer = one room only)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM volunteer_assignments WHERE volunteer_id = ?");
            $stmt->execute([$volunteer_id]);

            if ($stmt->fetchColumn() > 0) {
                $error = 'This volunteer is already assigned to a room. Each volunteer can only be assigned to one room.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO volunteer_assignments (volunteer_id, floor_id, room_id) VALUES (?, ?, ?)");
                if ($stmt->execute([$volunteer_id, $floor_id, $room_id])) {
                    $message = 'Volunteer assigned successfully!';
                } else {
                    $error = 'Failed to assign volunteer.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch data
$volunteers = $pdo->query("
    SELECT u.id, u.name, u.email, 
           CASE WHEN va.volunteer_id IS NOT NULL THEN 1 ELSE 0 END as is_assigned,
           CONCAT(f.floor_number, ' - ', r.room_number) as assigned_location
    FROM users u 
    LEFT JOIN volunteer_assignments va ON u.id = va.volunteer_id
    LEFT JOIN floors f ON va.floor_id = f.id
    LEFT JOIN rooms r ON va.room_id = r.id
    WHERE u.role = 'volunteer' 
    ORDER BY u.name
")->fetchAll(PDO::FETCH_ASSOC);

$floors_data = $pdo->query("SELECT * FROM floors ORDER BY floor_number")->fetchAll(PDO::FETCH_ASSOC);
foreach ($floors_data as &$floor) {
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE floor_id = ? ORDER BY room_number");
    $stmt->execute([$floor['id']]);
    $floor['rooms'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($floor);

// Fetch existing assignments with statistics
$stmt = $pdo->query("
    SELECT 
        va.volunteer_id, 
        va.floor_id, 
        va.room_id, 
        u.name as volunteer_name, 
        u.email as volunteer_email,
        f.floor_number, 
        r.room_number,
        r.capacity,
        COUNT(t.id) as assigned_teams
    FROM volunteer_assignments va
    JOIN users u ON va.volunteer_id = u.id
    JOIN floors f ON va.floor_id = f.id
    JOIN rooms r ON va.room_id = r.id
    LEFT JOIN teams t ON t.room_id = r.id
    GROUP BY va.volunteer_id, va.floor_id, va.room_id, u.name, u.email, f.floor_number, r.room_number, r.capacity
    ORDER BY u.name, f.floor_number, r.room_number
");
$existing_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get assignment statistics
$stats = [
    'total_volunteers' => count($volunteers),
    'assigned_volunteers' => $pdo->query("SELECT COUNT(DISTINCT volunteer_id) FROM volunteer_assignments")->fetchColumn(),
    'total_assignments' => $pdo->query("SELECT COUNT(*) FROM volunteer_assignments")->fetchColumn(),
    'total_rooms' => $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn()
];

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Assignments - HackMate</title>

    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        .volunteer-card {
            transition: transform 0.2s ease-in-out;
        }

        .volunteer-card:hover {
            transform: translateY(-2px);
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Custom scrollbar for the bulk assignment locations */
        .overflow-y-auto::-webkit-scrollbar {
            width: 6px;
        }

        .overflow-y-auto::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .overflow-y-auto::-webkit-scrollbar-thumb {
            background: #c5c5c5;
            border-radius: 10px;
        }

        .overflow-y-auto::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>
</head>

<body class="bg-gray-50">
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
                    <h1 class="text-lg font-semibold text-gray-900">Volunteers</h1>
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
                                <i class="fas fa-hands-helping text-blue-600 mr-3"></i>
                                Volunteer Assignments
                            </h1>
                            <p class="text-gray-600 mt-1">Assign volunteers to floors and rooms</p>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg shadow-sm animate-fade-in">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-500 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-green-800 font-medium"><?php echo $message; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg shadow-sm animate-fade-in">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-red-800 font-medium"><?php echo $error; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="volunteer-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-teal-100 text-teal-600">
                                <i class="fas fa-hands-helping text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total Volunteers</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_volunteers']; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="volunteer-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <i class="fas fa-user-check text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Assigned Volunteers</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['assigned_volunteers']; ?></p>
                                <p class="text-xs text-gray-500">One room per volunteer</p>
                            </div>
                        </div>
                    </div>

                    <div class="volunteer-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                <i class="fas fa-map-pin text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total Assignments</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_assignments']; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="volunteer-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                <i class="fas fa-door-open text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Available Rooms</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_rooms']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assignment Forms and Content -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Single Assignment Form -->
                    <div class="volunteer-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <h3 class="text-lg font-semibold mb-4 flex items-center">
                            <i class="fas fa-user-plus text-teal-600 mr-3"></i>
                            Assign Volunteer
                        </h3>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-6">
                            <p class="text-sm text-blue-800">
                                <i class="fas fa-info-circle mr-2"></i>
                                <strong>Assignment Rule:</strong> Each volunteer can only be assigned to one room, but multiple volunteers can be assigned to the same room.
                            </p>
                        </div>

                        <form method="POST" class="space-y-6">
                            <div>
                                <label for="volunteer_id" class="block text-sm font-medium text-gray-700 mb-2">Select Volunteer</label>
                                <select id="volunteer_id" name="volunteer_id" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
                                    <option value="">Choose a volunteer...</option>
                                    <?php foreach ($volunteers as $volunteer): ?>
                                        <option value="<?php echo $volunteer['id']; ?>" <?php echo $volunteer['is_assigned'] ? 'disabled' : ''; ?>>
                                            <?php echo htmlspecialchars($volunteer['name']); ?> (<?php echo htmlspecialchars($volunteer['email']); ?>)
                                            <?php if ($volunteer['is_assigned']): ?>
                                                - Already assigned to <?php echo htmlspecialchars($volunteer['assigned_location']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label for="floor_id" class="block text-sm font-medium text-gray-700 mb-2">Floor</label>
                                    <select id="floor_id" name="floor_id" required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
                                        <option value="">Select Floor</option>
                                        <?php foreach ($floors_data as $floor): ?>
                                            <option value="<?php echo $floor['id']; ?>"><?php echo htmlspecialchars($floor['floor_number']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label for="room_id" class="block text-sm font-medium text-gray-700 mb-2">Room</label>
                                    <select id="room_id" name="room_id" required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
                                        <option value="">Select Room</option>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" name="assign_volunteer"
                                class="w-full bg-teal-600 hover:bg-teal-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors flex items-center justify-center">
                                <i class="fas fa-plus mr-2"></i>
                                Assign Volunteer
                            </button>
                        </form>
                    </div>

                    <!-- Bulk Assignment Form -->
                    <div class="volunteer-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <h3 class="text-lg font-semibold mb-4 flex items-center">
                            <i class="fas fa-users-cog text-green-600 mr-3"></i>
                            Bulk Assignment
                        </h3>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-6">
                            <p class="text-sm text-green-800">
                                <i class="fas fa-info-circle mr-2"></i>
                                <strong>Quick Assignment:</strong> Select one room to assign the volunteer to. Already assigned volunteers are disabled in the dropdown.
                            </p>
                        </div>

                        <form method="POST" class="space-y-6">
                            <div>
                                <label for="bulk_volunteer_id" class="block text-sm font-medium text-gray-700 mb-2">Select Volunteer</label>
                                <select id="bulk_volunteer_id" name="bulk_volunteer_id" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                    <option value="">Choose a volunteer...</option>
                                    <?php foreach ($volunteers as $volunteer): ?>
                                        <option value="<?php echo $volunteer['id']; ?>" <?php echo $volunteer['is_assigned'] ? 'disabled' : ''; ?>>
                                            <?php echo htmlspecialchars($volunteer['name']); ?>
                                            <?php if ($volunteer['is_assigned']): ?>
                                                - Already assigned to <?php echo htmlspecialchars($volunteer['assigned_location']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-3">Select Room <span class="text-red-500">(Only one room per volunteer)</span></label>
                                <div class="max-h-64 overflow-y-auto border border-gray-200 rounded-lg p-4 space-y-3">
                                    <?php foreach ($floors_data as $floor): ?>
                                        <div class="border-b border-gray-100 pb-3 last:border-b-0">
                                            <h4 class="font-medium text-gray-800 mb-2">
                                                <i class="fas fa-layer-group mr-2 text-teal-600"></i>
                                                <?php echo htmlspecialchars($floor['floor_number']); ?>
                                            </h4>
                                            <div class="grid grid-cols-1 gap-2 ml-6">
                                                <?php foreach ($floor['rooms'] as $room): ?>
                                                    <label class="flex items-center space-x-2 text-sm">
                                                        <input type="radio" name="selected_locations[]"
                                                            value="<?php echo $floor['id'] . '-' . $room['id']; ?>"
                                                            class="border-gray-300 text-green-600 focus:ring-green-500">
                                                        <span><?php echo htmlspecialchars($room['room_number']); ?> (Capacity: <?php echo $room['capacity']; ?>)</span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Note: Each volunteer can only be assigned to one room, but multiple volunteers can be assigned to the same room.
                                </p>
                            </div>

                            <button type="submit" name="bulk_assign"
                                class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors flex items-center justify-center">
                                <i class="fas fa-layer-group mr-2"></i>
                                Bulk Assign
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Current Assignments -->
                <div class="mt-8 volunteer-card bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold flex items-center">
                            <i class="fas fa-list text-gray-600 mr-3"></i>
                            Current Volunteer Assignments
                        </h3>
                    </div>

                    <?php if (empty($existing_assignments)): ?>
                        <div class="p-8 text-center">
                            <i class="fas fa-clipboard-list text-gray-300 text-4xl mb-4"></i>
                            <p class="text-gray-500">No volunteer assignments yet.</p>
                            <p class="text-sm text-gray-400 mt-2">Use the forms above to assign volunteers to locations.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Volunteer</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room Info</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teams</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($existing_assignments as $assignment): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-10 h-10 bg-teal-100 rounded-full flex items-center justify-center">
                                                        <i class="fas fa-user text-teal-600"></i>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($assignment['volunteer_name']); ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            <?php echo htmlspecialchars($assignment['volunteer_email']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($assignment['floor_number']); ?> - <?php echo htmlspecialchars($assignment['room_number']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <i class="fas fa-users mr-1"></i>
                                                    Capacity: <?php echo $assignment['capacity']; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $assignment['assigned_teams'] > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                                    <?php echo $assignment['assigned_teams']; ?> teams
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <form method="POST" class="inline" onsubmit="return confirm('Remove this volunteer assignment?');">
                                                    <input type="hidden" name="volunteer_id" value="<?php echo $assignment['volunteer_id']; ?>">
                                                    <input type="hidden" name="floor_id" value="<?php echo $assignment['floor_id']; ?>">
                                                    <input type="hidden" name="room_id" value="<?php echo $assignment['room_id']; ?>">
                                                    <button type="submit" name="remove_assignment"
                                                        class="text-red-600 hover:text-red-900 flex items-center">
                                                        <i class="fas fa-trash mr-1"></i>
                                                        Remove
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
            </main>
        </div>
    </div>

    <script>
        // Dynamic room loading for single assignment
        document.getElementById('floor_id').addEventListener('change', function() {
            updateRoomOptions(this.value, 'room_id');
        });

        function updateRoomOptions(floorId, roomSelectId) {
            const roomSelect = document.getElementById(roomSelectId);

            // Clear existing options
            roomSelect.innerHTML = '<option value="">Select Room</option>';

            if (floorId) {
                const floors = <?php echo json_encode($floors_data); ?>;
                const selectedFloor = floors.find(floor => floor.id == floorId);

                if (selectedFloor && selectedFloor.rooms) {
                    selectedFloor.rooms.forEach(room => {
                        const option = document.createElement('option');
                        option.value = room.id;
                        option.textContent = room.room_number + ' (Capacity: ' + room.capacity + ')';
                        roomSelect.appendChild(option);
                    });
                }
            }
        }

        // Ensure only one room can be selected for bulk assignment (radio button behavior across floors)
        document.querySelectorAll('input[name="selected_locations[]"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.checked) {
                    // Uncheck all other radio buttons
                    document.querySelectorAll('input[name="selected_locations[]"]').forEach(otherRadio => {
                        if (otherRadio !== this) {
                            otherRadio.checked = false;
                        }
                    });
                }
            });
        });
    </script>
</body>

</html>