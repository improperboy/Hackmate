<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('mentor');
$user = getCurrentUser();

// Get message ID from URL
$message_id = $_GET['id'] ?? null;

if (!$message_id) {
    header('Location: support_messages.php');
    exit();
}

$message = '';
$error = '';

// Handle message status update
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    $resolution_notes = trim($_POST['resolution_notes'] ?? '');
    
    if ($action == 'resolve') {
        $stmt = $pdo->prepare("UPDATE support_messages SET status = 'resolved', resolved_at = NOW(), resolved_by = ?, resolution_notes = ? WHERE id = ?");
        if ($stmt->execute([$user['id'], $resolution_notes, $message_id])) {
            $message = 'Support message marked as resolved!';
        } else {
            $error = 'Failed to update message status.';
        }
    }
}

// Get mentor's assignments to verify access
$stmt = $pdo->prepare("
    SELECT ma.*, f.floor_number, r.room_number 
    FROM mentor_assignments ma
    JOIN floors f ON ma.floor_id = f.id
    JOIN rooms r ON ma.room_id = r.id
    WHERE ma.mentor_id = ?
");
$stmt->execute([$user['id']]);
$assignments = $stmt->fetchAll();

// Get the support message - only if it's in mentor's assigned area
$support_message = null;
if (!empty($assignments)) {
    $floor_room_conditions = [];
    $params = [$message_id];
    
    foreach ($assignments as $assignment) {
        $floor_room_conditions[] = "(sm.floor_id = ? AND sm.room_id = ?)";
        $params[] = $assignment['floor_id'];
        $params[] = $assignment['room_id'];
    }
    
    $query = "
        SELECT sm.*, u.name as from_name, u.email as from_email,
               f.floor_number, r.room_number,
               res_u.name as resolved_by_name, res_u.email as resolved_by_email
        FROM support_messages sm 
        JOIN users u ON sm.from_id = u.id 
        LEFT JOIN floors f ON sm.floor_id = f.id
        LEFT JOIN rooms r ON sm.room_id = r.id
        LEFT JOIN users res_u ON sm.resolved_by = res_u.id
        WHERE sm.id = ? AND sm.to_role = 'mentor' AND (" . implode(' OR ', $floor_room_conditions) . ")
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $support_message = $stmt->fetch();
}

if (!$support_message) {
    header('Location: support_messages.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Support Message - Mentor Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-4">
                    <a href="support_messages.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-arrow-left"></i>
                        Back to Support Messages
                    </a>
                    <h1 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-eye text-green-600"></i>
                        View Support Message
                    </h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="../logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto py-6 px-4">
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Message Details -->
        <div class="bg-white rounded-lg shadow">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-envelope text-green-600 mr-2"></i>
                        Support Message #<?php echo $support_message['id']; ?>
                    </h3>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                        <?php echo $support_message['status'] == 'open' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'; ?>">
                        <i class="fas fa-<?php echo $support_message['status'] == 'open' ? 'clock' : 'check-circle'; ?> mr-1"></i>
                        <?php echo ucfirst($support_message['status']); ?>
                    </span>
                </div>
            </div>

            <!-- Message Content -->
            <div class="px-6 py-6">
                <!-- Message Info Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">From</label>
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-user text-blue-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($support_message['from_name']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($support_message['from_email']); ?></p>
                                    <p class="text-xs text-gray-400 capitalize"><?php echo $support_message['from_role']; ?></p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Directed To</label>
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-user-tie text-green-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Mentor</p>
                                    <p class="text-sm text-gray-500">You are assigned to handle this</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Location</label>
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-map-marker-alt text-purple-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">
                                        <?php echo $support_message['floor_number'] . ' - ' . $support_message['room_number']; ?>
                                    </p>
                                    <p class="text-sm text-gray-500">Floor - Room</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Received</label>
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-clock text-gray-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900"><?php echo formatDateTime($support_message['created_at']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo timeAgo($support_message['created_at']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Message Content -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-600 mb-3">Message Content</label>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <div class="prose max-w-none">
                            <p class="text-gray-800 leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($support_message['message']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Resolution Section -->
                <?php if ($support_message['status'] == 'resolved'): ?>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-600 text-lg"></i>
                            </div>
                            <div class="ml-3 flex-1">
                                <h4 class="text-sm font-medium text-green-800 mb-2">Message Resolved</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-green-700">
                                    <?php if ($support_message['resolved_by_name']): ?>
                                        <div>
                                            <strong>Resolved by:</strong> <?php echo htmlspecialchars($support_message['resolved_by_name']); ?>
                                            <?php if ($support_message['resolved_by_email']): ?>
                                                <br><span class="text-green-600"><?php echo htmlspecialchars($support_message['resolved_by_email']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($support_message['resolved_at']): ?>
                                        <div>
                                            <strong>Resolved on:</strong> <?php echo formatDateTime($support_message['resolved_at']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($support_message['resolution_notes']): ?>
                                    <div class="mt-3">
                                        <strong class="text-green-800">Resolution Notes:</strong>
                                        <p class="mt-1 text-green-700 bg-green-100 p-2 rounded"><?php echo htmlspecialchars($support_message['resolution_notes']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Resolve Form -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-blue-800 mb-3">
                            <i class="fas fa-check-circle mr-1"></i>
                            Resolve Support Message
                        </h4>
                        <form method="POST" class="space-y-3">
                            <input type="hidden" name="action" value="resolve">
                            <div>
                                <label for="resolution_notes" class="block text-sm font-medium text-blue-700 mb-1">
                                    Resolution Notes (Optional)
                                </label>
                                <textarea id="resolution_notes" name="resolution_notes" rows="3"
                                          class="w-full px-3 py-2 border border-blue-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                          placeholder="Add any notes about how you helped resolve this issue..."></textarea>
                            </div>
                            <button type="submit" 
                                    onclick="return confirm('Mark this support message as resolved?')"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                                <i class="fas fa-check mr-2"></i>
                                Mark as Resolved
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer Actions -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-lg">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-500">
                        <i class="fas fa-info-circle mr-1"></i>
                        Message ID: #<?php echo $support_message['id']; ?>
                    </div>
                    <div class="flex space-x-3">
                        <a href="support_messages.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <i class="fas fa-list mr-2"></i>
                            Back to Messages List
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>