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

// Handle Assign Mentor
if ($_POST && isset($_POST['assign_mentor'])) {
    $mentor_id = intval($_POST['mentor_id']);
    $floor_id = intval($_POST['floor_id']);
    $room_id = intval($_POST['room_id']);

    if ($mentor_id <= 0 || $floor_id <= 0 || $room_id <= 0) {
        $error = 'All assignment fields are required.';
    } else {
        try {
            // Check if assignment already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM mentor_assignments WHERE mentor_id = ? AND floor_id = ? AND room_id = ?");
            $stmt->execute([$mentor_id, $floor_id, $room_id]);

            if ($stmt->fetchColumn() > 0) {
                $error = 'This mentor is already assigned to this location.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO mentor_assignments (mentor_id, floor_id, room_id) VALUES (?, ?, ?)");
                if ($stmt->execute([$mentor_id, $floor_id, $room_id])) {
                    $message = 'Mentor assigned successfully!';
                } else {
                    $error = 'Failed to assign mentor.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle Remove Mentor Assignment
if ($_POST && isset($_POST['remove_assignment'])) {
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

// Handle Bulk Assignment
if ($_POST && isset($_POST['bulk_assign'])) {
    $mentor_id = intval($_POST['bulk_mentor_id']);
    $selected_locations = $_POST['selected_locations'] ?? [];

    if ($mentor_id <= 0 || empty($selected_locations)) {
        $error = 'Please select a mentor and at least one location.';
    } else {
        $success_count = 0;
        $error_count = 0;

        foreach ($selected_locations as $location) {
            list($floor_id, $room_id) = explode('-', $location);
            $floor_id = intval($floor_id);
            $room_id = intval($room_id);

            try {
                // Check if assignment already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM mentor_assignments WHERE mentor_id = ? AND floor_id = ? AND room_id = ?");
                $stmt->execute([$mentor_id, $floor_id, $room_id]);

                if ($stmt->fetchColumn() == 0) {
                    $stmt = $pdo->prepare("INSERT INTO mentor_assignments (mentor_id, floor_id, room_id) VALUES (?, ?, ?)");
                    if ($stmt->execute([$mentor_id, $floor_id, $room_id])) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                }
            } catch (PDOException $e) {
                $error_count++;
            }
        }

        if ($success_count > 0) {
            $message = "Successfully assigned mentor to {$success_count} location(s).";
            if ($error_count > 0) {
                $message .= " {$error_count} assignment(s) failed or already existed.";
            }
        } else {
            $error = 'No new assignments were made. All selected locations may already be assigned to this mentor.';
        }
    }
}

// Fetch data
$mentors = $pdo->query("SELECT id, name, email FROM users WHERE role = 'mentor' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

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
        ma.mentor_id, 
        ma.floor_id, 
        ma.room_id, 
        u.name as mentor_name, 
        u.email as mentor_email,
        f.floor_number, 
        r.room_number,
        r.capacity,
        COUNT(t.id) as assigned_teams
    FROM mentor_assignments ma
    JOIN users u ON ma.mentor_id = u.id
    JOIN floors f ON ma.floor_id = f.id
    JOIN rooms r ON ma.room_id = r.id
    LEFT JOIN teams t ON t.room_id = r.id
    GROUP BY ma.mentor_id, ma.floor_id, ma.room_id, u.name, u.email, f.floor_number, r.room_number, r.capacity
    ORDER BY u.name, f.floor_number, r.room_number
");
$existing_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get assignment statistics
$stats = [
    'total_mentors' => count($mentors),
    'assigned_mentors' => $pdo->query("SELECT COUNT(DISTINCT mentor_id) FROM mentor_assignments")->fetchColumn(),
    'total_assignments' => $pdo->query("SELECT COUNT(*) FROM mentor_assignments")->fetchColumn(),
    'total_rooms' => $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn()
];

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentor Assignments - HackMate</title>

    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        .assignment-card {
            transition: transform 0.2s ease-in-out;
        }

        .assignment-card:hover {
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
                    <h1 class="text-lg font-semibold text-gray-900">Mentor Assign</h1>
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
                                <i class="fas fa-user-tie text-blue-600 mr-3"></i>
                                Mentor Assignments
                            </h1>
                            <p class="text-gray-600 mt-1">Assign mentors to floors and rooms</p>
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
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                    <div class="assignment-card bg-white rounded-xl shadow-sm p-5 border border-gray-100">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-indigo-50 text-indigo-600">
                                <i class="fas fa-chalkboard-teacher text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total Mentors</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_mentors']; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="assignment-card bg-white rounded-xl shadow-sm p-5 border border-gray-100">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-green-50 text-green-600">
                                <i class="fas fa-user-check text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Assigned Mentors</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['assigned_mentors']; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="assignment-card bg-white rounded-xl shadow-sm p-5 border border-gray-100">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-blue-50 text-blue-600">
                                <i class="fas fa-map-pin text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total Assignments</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_assignments']; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="assignment-card bg-white rounded-xl shadow-sm p-5 border border-gray-100">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-purple-50 text-purple-600">
                                <i class="fas fa-door-open text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Available Rooms</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_rooms']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
                    <!-- Single Assignment Form -->
                    <div class="assignment-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                <i class="fas fa-user-plus text-indigo-600 mr-3"></i>
                Assign Mentor to Location
            </h3>
            <span class="bg-indigo-100 text-indigo-800 text-xs font-medium px-2.5 py-0.5 rounded-full">Single</span>
        </div>

        <form method="POST" class="space-y-5">
            <div>
                <label for="mentor_id" class="block text-sm font-medium text-gray-700 mb-2">Select Mentor</label>
                <select id="mentor_id" name="mentor_id" required
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                    <option value="">Choose a mentor...</option>
                    <?php foreach ($mentors as $mentor): ?>
                        <option value="<?php echo $mentor['id']; ?>">
                            <?php echo htmlspecialchars($mentor['name']); ?> (<?php echo htmlspecialchars($mentor['email']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="floor_id" class="block text-sm font-medium text-gray-700 mb-2">Floor</label>
                    <select id="floor_id" name="floor_id" required
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                        <option value="">Select Floor</option>
                        <?php foreach ($floors_data as $floor): ?>
                            <option value="<?php echo $floor['id']; ?>">Floor <?php echo htmlspecialchars($floor['floor_number']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="room_id" class="block text-sm font-medium text-gray-700 mb-2">Room</label>
                    <select id="room_id" name="room_id" required
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                        <option value="">Select Room</option>
                    </select>
                </div>
            </div>

            <button type="submit" name="assign_mentor"
                class="w-full bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 shadow-md hover:shadow-lg flex items-center justify-center">
                <i class="fas fa-plus mr-2"></i>
                Assign Mentor
            </button>
        </form>
    </div>

                    <!-- Bulk Assignment Form -->
                    <div class="assignment-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                <i class="fas fa-users-cog text-green-600 mr-3"></i>
                Bulk Assignment
            </h3>
            <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded-full">Multiple</span>
        </div>

        <form method="POST" class="space-y-5">
            <div>
                <label for="bulk_mentor_id" class="block text-sm font-medium text-gray-700 mb-2">Select Mentor</label>
                <select id="bulk_mentor_id" name="bulk_mentor_id" required
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                    <option value="">Choose a mentor...</option>
                    <?php foreach ($mentors as $mentor): ?>
                        <option value="<?php echo $mentor['id']; ?>">
                            <?php echo htmlspecialchars($mentor['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">Select Locations</label>
                <div class="max-h-64 overflow-y-auto border border-gray-200 rounded-xl p-4 space-y-4 bg-gray-50">
                    <?php foreach ($floors_data as $floor): ?>
                        <div class="border-b border-gray-200 pb-4 last:border-b-0">
                            <h4 class="font-medium text-gray-800 mb-3 flex items-center">
                                <i class="fas fa-layer-group mr-2 text-teal-600"></i>
                                Floor <?php echo htmlspecialchars($floor['floor_number']); ?>
                            </h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 ml-6">
                                <?php foreach ($floor['rooms'] as $room): ?>
                                    <label class="flex items-center space-x-2 text-sm p-2 rounded-lg hover:bg-white transition-colors cursor-pointer">
                                        <input type="checkbox" name="selected_locations[]"
                                            value="<?php echo $floor['id'] . '-' . $room['id']; ?>"
                                            class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                        <span class="text-gray-700"><?php echo htmlspecialchars($room['room_number']); ?></span>
                                        <span class="text-xs text-gray-400">(Cap: <?php echo $room['capacity']; ?>)</span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" name="bulk_assign"
                class="w-full bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 shadow-md hover:shadow-lg flex items-center justify-center">
                <i class="fas fa-layer-group mr-2"></i>
                Bulk Assign
            </button>
                        </form>
                    </div>
                </div>

                <!-- Current Assignments -->
                <div class="assignment-card bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white">
        <h3 class="text-xl font-semibold text-gray-800 flex items-center">
            <i class="fas fa-list-check text-gray-600 mr-3"></i>
            Current Mentor Assignments
        </h3>
        <p class="text-sm text-gray-500 mt-1">Manage all mentor-to-location assignments</p>
    </div>

    <?php if (empty($existing_assignments)): ?>
        <div class="p-8 text-center">
            <div class="mx-auto w-16 h-16 flex items-center justify-center bg-gray-100 rounded-full mb-4">
                <i class="fas fa-clipboard-list text-gray-400 text-2xl"></i>
            </div>
            <p class="text-gray-500 font-medium">No mentor assignments yet</p>
            <p class="text-sm text-gray-400 mt-2">Use the forms above to assign mentors to locations</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mentor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room Info</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teams</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($existing_assignments as $assignment): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center shadow-inner">
                                        <i class="fas fa-user text-indigo-600"></i>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($assignment['mentor_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($assignment['mentor_email']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3 shadow-inner">
                                        <i class="fas fa-layer-group text-blue-600 text-xs"></i>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            Floor <?php echo htmlspecialchars($assignment['floor_number']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            Room <?php echo htmlspecialchars($assignment['room_number']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 flex items-center">
                                    <i class="fas fa-users mr-2 text-purple-500"></i>
                                    Capacity: <?php echo $assignment['capacity']; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php echo $assignment['assigned_teams'] > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <i class="fas fa-users mr-1 text-xs"></i>
                                    <?php echo $assignment['assigned_teams']; ?> team<?php echo $assignment['assigned_teams'] != 1 ? 's' : ''; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to remove this mentor assignment?');">
                                    <input type="hidden" name="mentor_id" value="<?php echo $assignment['mentor_id']; ?>">
                                    <input type="hidden" name="floor_id" value="<?php echo $assignment['floor_id']; ?>">
                                    <input type="hidden" name="room_id" value="<?php echo $assignment['room_id']; ?>">
                                    <button type="submit" name="remove_assignment"
                                        class="text-red-600 hover:text-red-800 flex items-center transition-colors">
                                        <i class="fas fa-trash-alt mr-1.5"></i>
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
        // Dynamic room loading based on floor selection
        document.getElementById('floor_id').addEventListener('change', function() {
            const floorId = this.value;
            const roomSelect = document.getElementById('room_id');

            // Clear existing options
            roomSelect.innerHTML = '<option value="">Select Room</option>';

            if (floorId) {
                const floors = <?php echo json_encode($floors_data); ?>;
                const selectedFloor = floors.find(floor => floor.id == floorId);

                if (selectedFloor && selectedFloor.rooms) {
                    selectedFloor.rooms.forEach(room => {
                        const option = document.createElement('option');
                        option.value = room.id;
                        option.textContent = `Room ${room.room_number} (Capacity: ${room.capacity})`;
                        roomSelect.appendChild(option);
                    });
                }
            }
        });

        // Add some interactivity to checkboxes in bulk assignment
        document.querySelectorAll('input[name="selected_locations[]"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    this.parentElement.classList.add('bg-green-50', 'border', 'border-green-200');
                } else {
                    this.parentElement.classList.remove('bg-green-50', 'border', 'border-green-200');
                }
            });
        });
    </script>
</body>

</html>