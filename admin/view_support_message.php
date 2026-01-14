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
    } elseif ($action == 'reopen') {
        $stmt = $pdo->prepare("UPDATE support_messages SET status = 'open', resolved_at = NULL, resolved_by = NULL, resolution_notes = NULL WHERE id = ?");
        if ($stmt->execute([$message_id])) {
            $message = 'Support message reopened!';
        } else {
            $error = 'Failed to reopen message.';
        }
    }
}

// Get the support message
$stmt = $pdo->prepare("
    SELECT sm.*, u.name as from_name, u.email as from_email,
           f.floor_number, r.room_number,
           res_u.name as resolved_by_name, res_u.email as resolved_by_email
    FROM support_messages sm 
    JOIN users u ON sm.from_id = u.id 
    LEFT JOIN floors f ON sm.floor_id = f.id
    LEFT JOIN rooms r ON sm.room_id = r.id
    LEFT JOIN users res_u ON sm.resolved_by = res_u.id
    WHERE sm.id = ?
");
$stmt->execute([$message_id]);
$support_message = $stmt->fetch();

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
    <title>View Support Message - <?php echo htmlspecialchars(getSystemSetting('hackathon_name', 'HackMate')); ?></title>

    <!-- Primary Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-gray-50 min-h-screen">
    <div class="flex h-screen">
        <!-- Include Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center">
                        <button id="sidebar-toggle" class="text-gray-500 hover:text-gray-700 lg:hidden">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div class="ml-4 lg:ml-0">
                            <h1 class="text-2xl font-semibold text-gray-900">
                                <i class="fas fa-eye text-blue-600 mr-2"></i>
                                View Support Message
                            </h1>
                            <p class="text-sm text-gray-500">Support Message #<?php echo $support_message['id'] ?? 'Loading...'; ?></p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <!-- Quick Actions -->
                        <div class="hidden md:flex items-center space-x-2">
                            <a href="support_messages.php" class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm font-medium hover:bg-gray-200 transition-colors">
                                <i class="fas fa-list mr-1"></i>
                                All Messages
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto bg-gray-50">
                <div class="p-6">
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
                                    <i class="fas fa-envelope text-blue-600 mr-2"></i>
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
                                                <p class="font-medium text-gray-900 capitalize"><?php echo $support_message['to_role']; ?></p>
                                                <p class="text-sm text-gray-500">Support Team</p>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($support_message['subject']): ?>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-600 mb-1">Subject</label>
                                            <p class="text-gray-900"><?php echo htmlspecialchars($support_message['subject']); ?></p>
                                        </div>
                                    <?php endif; ?>
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
                                                    <?php echo $support_message['floor_number'] ? $support_message['floor_number'] . ' - ' . $support_message['room_number'] : 'Not specified'; ?>
                                                </p>
                                                <p class="text-sm text-gray-500">Floor - Room</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Priority</label>
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center mr-3">
                                                <i class="fas fa-exclamation text-orange-600"></i>
                                            </div>
                                            <div>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php
                                        $priority = $support_message['priority'] ?? 'medium';
                                        switch ($priority) {
                                            case 'urgent':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            case 'high':
                                                echo 'bg-orange-100 text-orange-800';
                                                break;
                                            case 'low':
                                                echo 'bg-gray-100 text-gray-800';
                                                break;
                                            default:
                                                echo 'bg-yellow-100 text-yellow-800';
                                        }
                                        ?>">
                                                    <?php echo ucfirst($priority); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Submitted</label>
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
                            <?php endif; ?>

                            <!-- Action Forms -->
                            <?php if ($support_message['status'] == 'open'): ?>
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
                                                placeholder="Add any notes about how this issue was resolved..."></textarea>
                                        </div>
                                        <button type="submit"
                                            onclick="return confirm('Mark this support message as resolved?')"
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                                            <i class="fas fa-check mr-2"></i>
                                            Mark as Resolved
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <!-- Reopen Form -->
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <h4 class="text-sm font-medium text-yellow-800 mb-3">
                                        <i class="fas fa-redo mr-1"></i>
                                        Reopen Support Message
                                    </h4>
                                    <p class="text-sm text-yellow-700 mb-3">
                                        If this issue needs further attention, you can reopen this support message.
                                    </p>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="reopen">
                                        <button type="submit"
                                            onclick="return confirm('Reopen this support message?')"
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500">
                                            <i class="fas fa-redo mr-2"></i>
                                            Reopen Message
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
            </main>
        </div>
    </div>

    <script>
        // Sidebar toggle functionality
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            toggleSidebar();
        });
    </script>
</body>

</html>