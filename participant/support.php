<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';
require_once '../includes/system_settings.php';

checkAuth('participant');
$user = getCurrentUser();

$message = '';
$error = '';

// Check if user is a team leader and get team information
$stmt = $pdo->prepare("
    SELECT t.*, f.floor_number, r.room_number 
    FROM teams t 
    LEFT JOIN floors f ON t.floor_id = f.id
    LEFT JOIN rooms r ON t.room_id = r.id
    WHERE t.leader_id = ? AND t.status = 'approved'
");
$stmt->execute([$user['id']]);
$user_team = $stmt->fetch();

// Check if user is in any team (but not leader)
$stmt = $pdo->prepare("
    SELECT t.*, u.name as leader_name, f.floor_number, r.room_number 
    FROM teams t 
    JOIN team_members tm ON t.id = tm.team_id
    JOIN users u ON t.leader_id = u.id
    LEFT JOIN floors f ON t.floor_id = f.id
    LEFT JOIN rooms r ON t.room_id = r.id
    WHERE tm.user_id = ? AND t.status = 'approved' AND t.leader_id != ?
");
$stmt->execute([$user['id'], $user['id']]);
$member_team = $stmt->fetch();

// Handle support message submission
if ($_POST) {
    $support_message = sanitize($_POST['message']);
    
    if (empty($support_message)) {
        $error = 'Message is required';
    } elseif (!$user_team) {
        $error = 'Only team leaders can raise support requests. You are not a team leader or your team is not approved yet.';
    } elseif (!$user_team['floor_id'] || !$user_team['room_id']) {
        $error = 'Your team has not been assigned a floor and room yet. Please wait for admin approval.';
    } else {
        // Use team's floor_id and room_id directly
        $stmt = $pdo->prepare("INSERT INTO support_messages (from_id, from_role, to_role, message, floor_id, room_id) VALUES (?, 'participant', 'mentor', ?, ?, ?)");
        if ($stmt->execute([$user['id'], $support_message, $user_team['floor_id'], $user_team['room_id']])) {
            $message = 'Support message sent successfully! A mentor from your team\'s assigned area will respond soon.';
        } else {
            $error = 'Failed to send message. Please try again.';
        }
    }
}

// Get user's support messages
$stmt = $pdo->prepare("
    SELECT sm.*, u.name as from_name, f.floor_number, r.room_number 
    FROM support_messages sm 
    JOIN users u ON sm.from_id = u.id 
    LEFT JOIN floors f ON sm.floor_id = f.id
    LEFT JOIN rooms r ON sm.room_id = r.id
    WHERE sm.from_id = ? 
    ORDER BY sm.created_at DESC
");
$stmt->execute([$user['id']]);
$support_messages = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - <?php echo htmlspecialchars(getSystemSetting('hackathon_name', 'HackMate')); ?></title>
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
                    <h1 class="text-lg font-semibold text-gray-900">Get Support</h1>
                    <div class="w-8"></div> <!-- Spacer for centering -->
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto">
                <div class="p-6">
                    <!-- Page Header -->
                    <div class="mb-8">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-pink-600 rounded-xl flex items-center justify-center">
                                <i class="fas fa-life-ring text-white text-lg"></i>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Get Support</h1>
                                <p class="text-gray-600 mt-1">Need help? Send a message to our support team</p>
                            </div>
                        </div>
                    </div>
                    <!-- Messages -->
                    <?php if ($message): ?>
                        <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-2xl p-6 mb-8">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 bg-green-500 rounded-xl flex items-center justify-center">
                                        <i class="fas fa-check text-white"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-green-900">Success!</h3>
                                    <p class="text-green-700 mt-1"><?php echo $message; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="bg-gradient-to-r from-red-50 to-pink-50 border border-red-200 rounded-2xl p-6 mb-8">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 bg-red-500 rounded-xl flex items-center justify-center">
                                        <i class="fas fa-exclamation-triangle text-white"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-red-900">Error</h3>
                                    <p class="text-red-700 mt-1"><?php echo $error; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Team Status Information -->
                    <?php if ($user_team): ?>
                        <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-2xl p-6 mb-8">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center">
                                        <i class="fas fa-crown text-white text-lg"></i>
                                    </div>
                                </div>
                                <div class="ml-4 flex-1">
                                    <h3 class="text-lg font-semibold text-green-900">Team Leader Access</h3>
                                    <p class="text-green-700 mt-1">You can raise support requests for your team "<?php echo htmlspecialchars($user_team['name']); ?>"</p>
                                    <div class="mt-3 flex items-center text-sm text-green-600">
                                        <i class="fas fa-map-marker-alt mr-2"></i>
                                        <span>Location: <?php echo $user_team['floor_number'] ?: 'Not assigned'; ?> - <?php echo $user_team['room_number'] ?: 'Not assigned'; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($member_team): ?>
                        <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border border-yellow-200 rounded-2xl p-6 mb-8">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-xl flex items-center justify-center">
                                        <i class="fas fa-users text-white text-lg"></i>
                                    </div>
                                </div>
                                <div class="ml-4 flex-1">
                                    <h3 class="text-lg font-semibold text-yellow-900">Team Member</h3>
                                    <p class="text-yellow-700 mt-1">You are a member of team "<?php echo htmlspecialchars($member_team['name']); ?>"</p>
                                    <div class="mt-3 space-y-1 text-sm text-yellow-600">
                                        <div class="flex items-center">
                                            <i class="fas fa-user-tie mr-2"></i>
                                            <span>Team Leader: <?php echo htmlspecialchars($member_team['leader_name']); ?></span>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-map-marker-alt mr-2"></i>
                                            <span>Location: <?php echo $member_team['floor_number'] ?: 'Not assigned'; ?> - <?php echo $member_team['room_number'] ?: 'Not assigned'; ?></span>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-info-circle mr-2"></i>
                                            <span>Only team leaders can raise support requests</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-gradient-to-r from-gray-50 to-slate-50 border border-gray-200 rounded-2xl p-6 mb-8">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-gradient-to-br from-gray-500 to-slate-600 rounded-xl flex items-center justify-center">
                                        <i class="fas fa-user-slash text-white text-lg"></i>
                                    </div>
                                </div>
                                <div class="ml-4 flex-1">
                                    <h3 class="text-lg font-semibold text-gray-900">No Team Access</h3>
                                    <p class="text-gray-700 mt-1">You are not part of any approved team. Only team leaders can raise support requests.</p>
                                    <div class="mt-4 flex space-x-3">
                                        <a href="create_team.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                                            Create Team
                                        </a>
                                        <a href="join_team.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg text-sm font-medium border border-blue-300 hover:bg-blue-50 transition-colors">
                                            Join Team
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Support Request Form -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
                        <div class="flex items-center space-x-3 mb-6">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center">
                                <i class="fas fa-plus text-white"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">New Support Request</h3>
                                <p class="text-gray-600 text-sm">Send a message to our support team</p>
                            </div>
                        </div>
                        
                        <?php if ($user_team && $user_team['floor_id'] && $user_team['room_id']): ?>
                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-4 mb-6">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-info-circle text-white text-sm"></i>
                                        </div>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-blue-700">
                                            Your message will be sent to mentors and volunteers assigned to your team's location 
                                            (<?php echo $user_team['floor_number']; ?> - <?php echo $user_team['room_number']; ?>). 
                                            They will respond as soon as possible.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($user_team && $user_team['floor_id'] && $user_team['room_id']): ?>
                            <form method="POST" class="space-y-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-3">
                                        <i class="fas fa-comment mr-2 text-gray-400"></i>
                                        Describe your issue or question *
                                    </label>
                                    <textarea name="message" required rows="6"
                                              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 resize-none"
                                              placeholder="Please describe your issue, question, or what kind of help you need in detail..."></textarea>
                                </div>

                                <div class="flex items-center justify-between pt-4">
                                    <div class="text-sm text-gray-500">
                                        <i class="fas fa-clock mr-1"></i>
                                        Response time: Usually within 30 minutes
                                    </div>
                                    <button type="submit" 
                                            class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg">
                                        <i class="fas fa-paper-plane mr-2"></i>
                                        Send Support Request
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="bg-gradient-to-br from-gray-50 to-slate-50 border border-gray-200 rounded-xl p-8 text-center">
                                <div class="w-16 h-16 bg-gray-300 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-lock text-gray-500 text-xl"></i>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-900 mb-2">Support Request Form Disabled</h4>
                                <p class="text-gray-600 mb-4">
                                    <?php if (!$user_team): ?>
                                        You need to be a team leader to raise support requests.
                                    <?php elseif (!$user_team['floor_id'] || !$user_team['room_id']): ?>
                                        Your team needs to be assigned a floor and room first.
                                    <?php endif; ?>
                                </p>
                                <?php if (!$user_team): ?>
                                    <div class="flex justify-center space-x-3">
                                        <a href="create_team.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                                            Create Team
                                        </a>
                                        <a href="join_team.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg text-sm font-medium border border-blue-300 hover:bg-blue-50 transition-colors">
                                            Join Team
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Previous Support Messages -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center">
                                        <i class="fas fa-history text-white"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold text-gray-900">Support History</h3>
                                        <p class="text-gray-600 text-sm"><?php echo count($support_messages); ?> message<?php echo count($support_messages) != 1 ? 's' : ''; ?> total</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (empty($support_messages)): ?>
                            <div class="px-6 py-12 text-center">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-inbox text-gray-400 text-xl"></i>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-900 mb-2">No Support Requests Yet</h4>
                                <p class="text-gray-600">Submit your first request above to get help from our support team!</p>
                            </div>
                        <?php else: ?>
                            <div class="divide-y divide-gray-100">
                                <?php foreach ($support_messages as $msg): ?>
                                    <div class="px-6 py-6 hover:bg-gray-50 transition-colors">
                                        <div class="flex items-start space-x-4">
                                            <div class="flex-shrink-0">
                                                <div class="w-10 h-10 bg-gradient-to-br <?php echo $msg['status'] == 'open' ? 'from-yellow-500 to-orange-600' : 'from-green-500 to-emerald-600'; ?> rounded-xl flex items-center justify-center">
                                                    <i class="fas <?php echo $msg['status'] == 'open' ? 'fa-clock' : 'fa-check-circle'; ?> text-white text-sm"></i>
                                                </div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center justify-between mb-2">
                                                    <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full <?php echo $msg['status'] == 'open' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'; ?>">
                                                        <i class="fas <?php echo $msg['status'] == 'open' ? 'fa-clock' : 'fa-check-circle'; ?> mr-1"></i>
                                                        <?php echo ucfirst($msg['status']); ?>
                                                    </span>
                                                    <div class="flex items-center text-xs text-gray-500">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        <span><?php echo formatDateTime($msg['created_at']); ?></span>
                                                    </div>
                                                </div>
                                                <p class="text-gray-800 mb-3 leading-relaxed">
                                                    <?php echo strlen($msg['message']) > 200 ? nl2br(htmlspecialchars(substr($msg['message'], 0, 200))) . '...' : nl2br(htmlspecialchars($msg['message'])); ?>
                                                </p>
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center text-sm text-gray-500">
                                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                                        <span>Floor: <?php echo $msg['floor_number'] ?: 'N/A'; ?>, Room: <?php echo $msg['room_number'] ?: 'N/A'; ?></span>
                                                    </div>
                                                    <a href="view_support_message.php?id=<?php echo $msg['id']; ?>" 
                                                       class="inline-flex items-center text-blue-600 hover:text-blue-800 text-sm font-medium transition-colors">
                                                        <i class="fas fa-eye mr-1"></i>
                                                        View Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
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
