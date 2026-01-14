<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';
require_once '../includes/system_settings.php';

checkAuth('participant');
$user = getCurrentUser();

// Get message ID from URL
$message_id = $_GET['id'] ?? null;

if (!$message_id) {
    header('Location: support.php');
    exit();
}

// Get the support message - only allow viewing own messages
$stmt = $pdo->prepare("
    SELECT sm.*, u.name as from_name, u.email as from_email,
           f.floor_number, r.room_number,
           res_u.name as resolved_by_name
    FROM support_messages sm 
    JOIN users u ON sm.from_id = u.id 
    LEFT JOIN floors f ON sm.floor_id = f.id
    LEFT JOIN rooms r ON sm.room_id = r.id
    LEFT JOIN users res_u ON sm.resolved_by = res_u.id
    WHERE sm.id = ? AND sm.from_id = ?
");
$stmt->execute([$message_id, $user['id']]);
$message = $stmt->fetch();

if (!$message) {
    header('Location: support.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Support Message - <?php echo htmlspecialchars(getSystemSetting('hackathon_name', 'HackMate')); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8'
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm border-b border-gray-200 lg:hidden">
                <div class="flex items-center justify-between h-16 px-4">
                    <button onclick="toggleSidebar()" class="text-gray-600 hover:text-gray-900 focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-semibold text-gray-900">Support Message</h1>
                    <div class="w-8"></div> <!-- Spacer for centering -->
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto">
                <div class="p-6">
                    <!-- Page Header -->
                    <div class="mb-8">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center">
                                    <i class="fas fa-eye text-white text-lg"></i>
                                </div>
                                <div>
                                    <h1 class="text-2xl font-bold text-gray-900">Support Message Details</h1>
                                    <p class="text-gray-600 mt-1">Message ID: #<?php echo $message['id']; ?></p>
                                </div>
                            </div>
                            <a href="support.php" class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Support
                            </a>
                        </div>
                    </div>
                    <!-- Message Details -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
                        <!-- Header -->
                        <div class="px-6 py-6 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center">
                                        <i class="fas fa-envelope text-white"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold text-gray-900">Message Details</h3>
                                        <p class="text-gray-600 text-sm">Support request information</p>
                                    </div>
                                </div>
                                <span class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold
                                    <?php echo $message['status'] == 'open' ? 'bg-gradient-to-r from-yellow-100 to-orange-100 text-yellow-800 border border-yellow-200' : 'bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 border border-green-200'; ?>">
                                    <i class="fas fa-<?php echo $message['status'] == 'open' ? 'clock' : 'check-circle'; ?> mr-2"></i>
                                    <?php echo ucfirst($message['status']); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Message Content -->
                        <div class="px-6 py-6">
                            <!-- Message Info Cards -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                                <div class="space-y-6">
                                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-4 border border-blue-200">
                                        <label class="block text-sm font-semibold text-blue-900 mb-3">From</label>
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center">
                                                <i class="fas fa-user text-white text-sm"></i>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($message['from_name']); ?></p>
                                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($message['from_email']); ?></p>
                                                <p class="text-xs text-blue-600 font-medium"><?php echo ucfirst($message['from_role']); ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl p-4 border border-green-200">
                                        <label class="block text-sm font-semibold text-green-900 mb-3">Sent To</label>
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center">
                                                <i class="fas fa-user-tie text-white text-sm"></i>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-900"><?php echo ucfirst($message['to_role']); ?></p>
                                                <p class="text-sm text-gray-600">Support Team</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-6">
                                    <div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl p-4 border border-purple-200">
                                        <label class="block text-sm font-semibold text-purple-900 mb-3">Location</label>
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center">
                                                <i class="fas fa-map-marker-alt text-white text-sm"></i>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-900">
                                                    <?php echo $message['floor_number'] ? $message['floor_number'] . ' - ' . $message['room_number'] : 'Not specified'; ?>
                                                </p>
                                                <p class="text-sm text-gray-600">Floor - Room</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-gradient-to-r from-gray-50 to-slate-50 rounded-xl p-4 border border-gray-200">
                                        <label class="block text-sm font-semibold text-gray-900 mb-3">Submitted</label>
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-gradient-to-br from-gray-500 to-slate-600 rounded-xl flex items-center justify-center">
                                                <i class="fas fa-clock text-white text-sm"></i>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-900"><?php echo formatDateTime($message['created_at']); ?></p>
                                                <p class="text-sm text-gray-600"><?php echo timeAgo($message['created_at']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Message Content -->
                            <div class="mb-8">
                                <label class="block text-sm font-semibold text-gray-900 mb-4">
                                    <i class="fas fa-comment mr-2 text-gray-400"></i>
                                    Message Content
                                </label>
                                <div class="bg-gradient-to-br from-gray-50 to-slate-50 border border-gray-200 rounded-xl p-6">
                                    <div class="prose max-w-none">
                                        <p class="text-gray-800 leading-relaxed whitespace-pre-wrap text-base"><?php echo htmlspecialchars($message['message']); ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Resolution Info -->
                            <?php if ($message['status'] == 'resolved'): ?>
                                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-xl p-6">
                                    <div class="flex items-start space-x-4">
                                        <div class="flex-shrink-0">
                                            <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center">
                                                <i class="fas fa-check-circle text-white text-lg"></i>
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="text-lg font-semibold text-green-900 mb-2">Message Resolved</h4>
                                            <div class="space-y-2 text-sm text-green-700">
                                                <?php if ($message['resolved_by_name']): ?>
                                                    <div class="flex items-center">
                                                        <i class="fas fa-user mr-2"></i>
                                                        <span><strong>Resolved by:</strong> <?php echo htmlspecialchars($message['resolved_by_name']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($message['resolved_at']): ?>
                                                    <div class="flex items-center">
                                                        <i class="fas fa-calendar mr-2"></i>
                                                        <span><strong>Resolved on:</strong> <?php echo formatDateTime($message['resolved_at']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border border-yellow-200 rounded-xl p-6">
                                    <div class="flex items-start space-x-4">
                                        <div class="flex-shrink-0">
                                            <div class="w-12 h-12 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-xl flex items-center justify-center">
                                                <i class="fas fa-clock text-white text-lg"></i>
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="text-lg font-semibold text-yellow-900 mb-2">Awaiting Response</h4>
                                            <p class="text-sm text-yellow-700">
                                                Your support request is currently open and waiting for a response from the support team. 
                                                You'll be notified when there's an update.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Actions -->
                        <div class="px-6 py-6 bg-gradient-to-r from-gray-50 to-slate-50 border-t border-gray-200 rounded-b-2xl">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center text-sm text-gray-500">
                                    <div class="w-6 h-6 bg-gray-300 rounded-lg flex items-center justify-center mr-2">
                                        <i class="fas fa-hashtag text-gray-600 text-xs"></i>
                                    </div>
                                    <span>Message ID: <?php echo $message['id']; ?></span>
                                </div>
                                <div class="flex space-x-3">
                                    <a href="support.php" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-xl shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                        <i class="fas fa-list mr-2"></i>
                                        Back to Support
                                    </a>
                                    <?php if ($message['status'] == 'open'): ?>
                                        <a href="support.php" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl shadow-sm text-sm font-medium transition-all duration-200">
                                            <i class="fas fa-plus mr-2"></i>
                                            Send New Message
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- Security Script -->
    <script src="../assets/js/security.js"></script>

    <!-- Include AI Chatbot -->
    <?php include '../includes/chatbot_component.php'; ?>
</body>

</html>