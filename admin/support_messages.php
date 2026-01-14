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

// Handle message status update
if ($_POST && isset($_POST['action'])) {
    $message_id = $_POST['message_id'];
    $action = $_POST['action'];

    if ($action == 'resolve') {
        $stmt = $pdo->prepare("UPDATE support_messages SET status = 'resolved', resolved_at = NOW(), resolved_by = ? WHERE id = ?");
        if ($stmt->execute([$user['id'], $message_id])) {
            $message = 'Support message marked as resolved!';
        } else {
            $error = 'Failed to update message status.';
        }
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$from_role_filter = $_GET['from_role'] ?? '';
$to_role_filter = $_GET['to_role'] ?? '';
$floor_filter = $_GET['floor'] ?? '';
$room_filter = $_GET['room'] ?? '';

// Get all support messages
$query = "
    SELECT sm.*, u.name as from_name, u.email as from_email,
           f.floor_number, r.room_number,
           res_u.name as resolved_by_name
    FROM support_messages sm 
    JOIN users u ON sm.from_id = u.id 
    LEFT JOIN floors f ON sm.floor_id = f.id
    LEFT JOIN rooms r ON sm.room_id = r.id
    LEFT JOIN users res_u ON sm.resolved_by = res_u.id
    WHERE 1=1
";

$params = [];
if ($search) {
    $query .= " AND (sm.message LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status_filter) {
    $query .= " AND sm.status = ?";
    $params[] = $status_filter;
}
if ($from_role_filter) {
    $query .= " AND sm.from_role = ?";
    $params[] = $from_role_filter;
}
if ($to_role_filter) {
    $query .= " AND sm.to_role = ?";
    $params[] = $to_role_filter;
}
if ($floor_filter) {
    $query .= " AND f.floor_number = ?";
    $params[] = $floor_filter;
}
if ($room_filter) {
    $query .= " AND r.room_number = ?";
    $params[] = $room_filter;
}

$query .= " ORDER BY sm.status ASC, sm.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$support_messages = $stmt->fetchAll();

// Get floors and rooms for filters
$stmt = $pdo->query("SELECT DISTINCT floor_number FROM floors ORDER BY floor_number");
$floors = $stmt->fetchAll();

$stmt = $pdo->query("SELECT DISTINCT room_number FROM rooms ORDER BY room_number");
$rooms = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Messages - <?php echo htmlspecialchars(getSystemSetting('hackathon_name', 'HackMate')); ?></title>

    <!-- Primary Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
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
                    <h1 class="text-lg font-semibold text-gray-900">Support Messages</h1>
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
                                <i class="fas fa-life-ring text-blue-600 mr-3"></i>
                                Support Messages
                            </h1>
                            <p class="text-gray-600 mt-1">Manage participant support requests</p>
                        </div>

                        <!-- Quick Stats -->
                        <div class="flex items-center space-x-3">
                            <?php if ($open_support_requests > 0): ?>
                                <span class="bg-red-100 text-red-800 px-4 py-2 rounded-lg text-sm font-medium">
                                    <i class="fas fa-exclamation-circle mr-2"></i>
                                    <?php echo $open_support_requests; ?> Open
                                </span>
                            <?php endif; ?>
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
                        Search & Filter Messages
                    </h3>
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                placeholder="Search by sender or message..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Statuses</option>
                                <option value="open" <?php echo $status_filter == 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">From Role</label>
                            <select name="from_role" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Roles</option>
                                <option value="participant" <?php echo $from_role_filter == 'participant' ? 'selected' : ''; ?>>Participant</option>
                                <option value="mentor" <?php echo $from_role_filter == 'mentor' ? 'selected' : ''; ?>>Mentor</option>
                                <option value="volunteer" <?php echo $from_role_filter == 'volunteer' ? 'selected' : ''; ?>>Volunteer</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">To Role</label>
                            <select name="to_role" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Roles</option>
                                <option value="admin" <?php echo $to_role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="mentor" <?php echo $to_role_filter == 'mentor' ? 'selected' : ''; ?>>Mentor</option>
                                <option value="volunteer" <?php echo $to_role_filter == 'volunteer' ? 'selected' : ''; ?>>Volunteer</option>
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
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Room</label>
                            <select name="room" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Rooms</option>
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?php echo $room['room_number']; ?>" <?php echo $room_filter == $room['room_number'] ? 'selected' : ''; ?>>
                                        <?php echo $room['room_number']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-end col-span-full md:col-span-2">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition-colors mr-2">
                                <i class="fas fa-search mr-2"></i>Search
                            </button>
                            <a href="support_messages.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md transition-colors">
                                <i class="fas fa-times mr-2"></i>Clear
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Support Messages List -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold">
                            <i class="fas fa-list text-gray-600"></i>
                            All Support Messages (<?php echo count($support_messages); ?>)
                        </h3>
                    </div>

                    <?php if (empty($support_messages)): ?>
                        <div class="px-6 py-8 text-center text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-4"></i>
                            <p>No support messages found matching your criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">To Role</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Received</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($support_messages as $msg): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo $msg['from_name']; ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo $msg['from_email']; ?></div>
                                                    <div class="text-xs text-gray-500">(<?php echo ucfirst($msg['from_role']); ?>)</div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo ucfirst($msg['to_role']); ?>
                                            </td>
                                            <td class="px-6 py-4 max-w-xs text-sm text-gray-900">
                                                <div class="truncate" title="<?php echo htmlspecialchars($msg['message']); ?>">
                                                    <?php echo truncateText($msg['message'], 100); ?>
                                                </div>
                                                <a href="view_support_message.php?id=<?php echo $msg['id']; ?>"
                                                    class="text-blue-600 hover:text-blue-800 text-xs font-medium mt-1 inline-block">
                                                    <i class="fas fa-eye mr-1"></i>View Full
                                                </a>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo $msg['floor_number'] ? $msg['floor_number'] . ' - ' . $msg['room_number'] : 'N/A'; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php
                                            if ($msg['status'] == 'open') echo 'bg-yellow-100 text-yellow-800';
                                            else echo 'bg-green-100 text-green-800';
                                            ?>">
                                                    <?php echo ucfirst($msg['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo formatDateTime($msg['created_at']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <?php if ($msg['status'] == 'open'): ?>
                                                    <form method="POST" class="inline-block" onsubmit="return confirm('Mark this message as resolved?');">
                                                        <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                                        <input type="hidden" name="action" value="resolve">
                                                        <button type="submit" class="text-green-600 hover:text-green-900 mr-3">
                                                            <i class="fas fa-check"></i> Resolve
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-gray-500">Resolved by <?php echo $msg['resolved_by_name'] ?: 'N/A'; ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
            </main>
        </div>
    </div>
</body>

</html>