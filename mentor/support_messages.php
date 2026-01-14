<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('mentor');
$user = getCurrentUser();

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

// Get mentor's assignments
$stmt = $pdo->prepare("
    SELECT ma.*, f.floor_number, r.room_number 
    FROM mentor_assignments ma
    JOIN floors f ON ma.floor_id = f.id
    JOIN rooms r ON ma.room_id = r.id
    WHERE ma.mentor_id = ?
");
$stmt->execute([$user['id']]);
$assignments = $stmt->fetchAll();

// Get support messages for mentor's assigned areas
$support_messages = [];
if (!empty($assignments)) {
    $floor_room_conditions = [];
    $params = [];
    
    foreach ($assignments as $assignment) {
        $floor_room_conditions[] = "(sm.floor_id = ? AND sm.room_id = ?)";
        $params[] = $assignment['floor_id'];
        $params[] = $assignment['room_id'];
    }
    
    $support_query = "
        SELECT sm.*, u.name as from_name, u.email as from_email
        FROM support_messages sm 
        JOIN users u ON sm.from_id = u.id 
        WHERE sm.to_role = 'mentor' AND (" . implode(' OR ', $floor_room_conditions) . ")
        ORDER BY sm.status ASC, sm.created_at DESC
    ";
    
    $stmt = $pdo->prepare($support_query);
    $stmt->execute($params);
    $support_messages = $stmt->fetchAll();
}

// Separate open and resolved messages
$open_messages = array_filter($support_messages, function($msg) { return $msg['status'] == 'open'; });
$resolved_messages = array_filter($support_messages, function($msg) { return $msg['status'] == 'resolved'; });
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Messages - HackMate</title>
    
    <!-- Primary Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- PWA Configuration -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#10B981">
    <meta name="background-color" content="#10B981">
    
    <style>
        .mobile-menu-btn {
            display: none;
        }

        @media (max-width: 1024px) {
            .mobile-menu-btn {
                display: block;
            }
            
            .lg\:ml-64 {
                margin-left: 0 !important;
            }
        }
        
        /* Ensure sidebar is properly positioned */
        #sidebar {
            position: fixed !important;
            top: 0;
            left: 0;
            z-index: 40;
            width: 16rem;
            height: 100vh;
        }
        
        /* Main content positioning */
        .main-content {
            margin-left: 0;
            min-height: 100vh;
        }
        
        @media (min-width: 1024px) {
            .main-content {
                margin-left: 16rem !important;
            }
        }
        
        /* Ensure proper layout on mobile */
        @media (max-width: 1023px) {
            #sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
            }
            
            #sidebar.show {
                transform: translateX(0);
            }
        }
        
        .support-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .support-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .message-card {
            transition: all 0.2s ease;
        }
        
        .message-card:hover {
            transform: translateX(4px);
        }
        
        .priority-urgent {
            border-left: 4px solid #ef4444;
            background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
        }
        
        .priority-high {
            border-left: 4px solid #f59e0b;
            background: linear-gradient(135deg, #fffbeb 0%, #fed7aa 100%);
        }
        
        .priority-medium {
            border-left: 4px solid #3b82f6;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        }
        
        .priority-low {
            border-left: 4px solid #10b981;
            background: linear-gradient(135deg, #f0fdf4 0%, #bbf7d0 100%);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content min-h-screen bg-gray-50">
        <!-- Top Navigation Bar -->
        <nav class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <!-- Mobile menu button -->
                        <button onclick="toggleSidebar()" class="mobile-menu-btn text-gray-600 hover:text-gray-900 focus:outline-none focus:text-gray-900 mr-4">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-gradient-to-br from-green-500 to-emerald-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-life-ring text-white text-sm"></i>
                            </div>
                            <div>
                                <h1 class="text-xl font-bold text-gray-900">Support Messages</h1>
                                <p class="text-sm text-gray-500 hidden sm:block">Help participants in your areas</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <!-- Quick Actions Dropdown -->
                        <div class="relative">
                            <button onclick="toggleQuickActions()" class="flex items-center space-x-2 text-gray-600 hover:text-gray-900 focus:outline-none">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div id="quickActionsMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-20">
                                <a href="dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fas fa-tachometer-alt w-4 mr-2"></i>Dashboard
                                </a>
                                <a href="assigned_teams.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fas fa-users w-4 mr-2"></i>My Teams
                                </a>
                                <a href="../logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <i class="fas fa-sign-out-alt w-4 mr-2"></i>Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="p-4 sm:p-6 lg:p-8">
            <!-- Page Header -->
            <div class="mb-8">
                <div class="bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-bold mb-2">Support Messages</h2>
                            <p class="text-green-100 mb-3">Help participants in your assigned areas</p>
                            
                            <?php if (!empty($assignments)): ?>
                                <div class="flex items-center text-green-100">
                                    <i class="fas fa-map-marker-alt mr-2"></i>
                                    <span class="text-sm">
                                        Viewing messages for: 
                                        <?php 
                                        $assignment_list = [];
                                        foreach ($assignments as $assignment) {
                                            $assignment_list[] = $assignment['floor_number'] . '-' . $assignment['room_number'];
                                        }
                                        echo implode(', ', $assignment_list);
                                        ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="hidden md:block">
                            <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center backdrop-blur-sm">
                                <i class="fas fa-life-ring text-3xl text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-400 rounded-xl p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                        <p class="text-green-700 font-medium"><?php echo $message; ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-400 rounded-xl p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                        <p class="text-red-700 font-medium"><?php echo $error; ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($assignments)): ?>
                <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border-l-4 border-yellow-400 rounded-xl p-6 mb-8">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-yellow-400 rounded-lg flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-white"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h4 class="text-lg font-medium text-yellow-800 mb-2">No Assignments</h4>
                            <p class="text-yellow-700 mb-3">You are not assigned to any specific floor/room. Please contact the admin to receive assignments to view support messages.</p>
                            <a href="dashboard.php" class="inline-flex items-center px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white font-medium rounded-lg transition-colors">
                                <i class="fas fa-tachometer-alt mr-2"></i>
                                Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>

                <!-- Open Support Messages -->
                <div class="support-card rounded-2xl shadow-sm border border-gray-200 mb-8">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-exclamation-circle text-orange-500 mr-2"></i>
                                Open Messages (<?php echo count($open_messages); ?>)
                            </h3>
                            <?php if (count($open_messages) > 0): ?>
                                <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full bg-orange-100 text-orange-800">
                                    <span class="w-2 h-2 bg-orange-500 rounded-full mr-2 animate-pulse"></span>
                                    Needs Attention
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                
                    <?php if (empty($open_messages)): ?>
                        <div class="px-6 py-12 text-center">
                            <i class="fas fa-check-circle text-gray-300 text-6xl mb-6"></i>
                            <h4 class="text-xl font-semibold text-gray-900 mb-3">All Caught Up!</h4>
                            <p class="text-gray-500 mb-4">No open support messages in your assigned areas.</p>
                            <a href="assigned_teams.php" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors">
                                <i class="fas fa-users mr-2"></i>
                                View My Teams
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="p-6">
                            <div class="space-y-4">
                                <?php foreach ($open_messages as $msg): ?>
                                    <?php
                                    $priority_class = 'priority-medium';
                                    if (isset($msg['priority'])) {
                                        switch ($msg['priority']) {
                                            case 'urgent':
                                                $priority_class = 'priority-urgent';
                                                break;
                                            case 'high':
                                                $priority_class = 'priority-high';
                                                break;
                                            case 'low':
                                                $priority_class = 'priority-low';
                                                break;
                                            default:
                                                $priority_class = 'priority-medium';
                                        }
                                    }
                                    ?>
                                    <div class="message-card <?php echo $priority_class; ?> rounded-xl p-6">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <div class="flex items-center justify-between mb-3">
                                                    <div class="flex items-center">
                                                        <h4 class="font-semibold text-gray-900 mr-3">
                                                            <?php echo htmlspecialchars($msg['from_name']); ?>
                                                        </h4>
                                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                                                            <?php echo ucfirst($msg['from_role']); ?>
                                                        </span>
                                                        <?php if (isset($msg['priority'])): ?>
                                                            <span class="ml-2 inline-flex items-center px-2 py-1 text-xs font-medium rounded-full <?php 
                                                                echo $msg['priority'] == 'urgent' ? 'bg-red-100 text-red-800' : 
                                                                    ($msg['priority'] == 'high' ? 'bg-orange-100 text-orange-800' : 
                                                                    ($msg['priority'] == 'medium' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800')); 
                                                            ?>">
                                                                <?php echo ucfirst($msg['priority']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full bg-yellow-100 text-yellow-800">
                                                        <span class="w-2 h-2 bg-yellow-500 rounded-full mr-2 animate-pulse"></span>
                                                        Open
                                                    </span>
                                                </div>
                                                
                                                <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($msg['from_email']); ?></p>
                                                
                                                <div class="bg-white bg-opacity-70 rounded-lg p-4 mb-4">
                                                    <p class="text-sm text-gray-800">
                                                        <?php echo strlen($msg['message']) > 200 ? nl2br(htmlspecialchars(substr($msg['message'], 0, 200))) . '...' : nl2br(htmlspecialchars($msg['message'])); ?>
                                                    </p>
                                                    <?php if (strlen($msg['message']) > 200): ?>
                                                        <a href="view_support_message.php?id=<?php echo $msg['id']; ?>" 
                                                           class="text-blue-600 hover:text-blue-800 text-sm font-medium mt-2 inline-flex items-center">
                                                            <i class="fas fa-eye mr-1"></i>View Full Message
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="flex items-center text-xs text-gray-600">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    <span>Received: <?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="ml-6">
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="resolve">
                                                    <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                                    <button type="submit" 
                                                            class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors transform hover:scale-105"
                                                            onclick="return confirm('Mark this message as resolved?')">
                                                        <i class="fas fa-check mr-2"></i>
                                                        Resolve
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
            </div>

                <!-- Resolved Support Messages -->
                <div class="support-card rounded-2xl shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">
                            <i class="fas fa-history text-gray-500 mr-2"></i>
                            Resolved Messages (<?php echo count($resolved_messages); ?>)
                        </h3>
                    </div>
                
                    <?php if (empty($resolved_messages)): ?>
                        <div class="px-6 py-12 text-center">
                            <i class="fas fa-history text-gray-300 text-5xl mb-4"></i>
                            <h4 class="text-lg font-medium text-gray-900 mb-2">No Resolved Messages</h4>
                            <p class="text-gray-500">No support messages have been resolved yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="p-6">
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                <?php foreach ($resolved_messages as $msg): ?>
                                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-xl p-4">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <div class="flex items-center justify-between mb-2">
                                                    <div class="flex items-center">
                                                        <h4 class="font-medium text-gray-900 mr-3">
                                                            <?php echo htmlspecialchars($msg['from_name']); ?>
                                                        </h4>
                                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                                                            <?php echo ucfirst($msg['from_role']); ?>
                                                        </span>
                                                    </div>
                                                    <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full bg-green-100 text-green-800">
                                                        <i class="fas fa-check-circle mr-1"></i>
                                                        Resolved
                                                    </span>
                                                </div>
                                                
                                                <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($msg['from_email']); ?></p>
                                                
                                                <div class="bg-white bg-opacity-70 rounded-lg p-3 mb-3">
                                                    <p class="text-xs text-gray-700">
                                                        <?php 
                                                        $truncated = strlen($msg['message']) > 100 ? substr($msg['message'], 0, 100) . '...' : $msg['message'];
                                                        echo nl2br(htmlspecialchars($truncated)); 
                                                        ?>
                                                    </p>
                                                    <?php if (strlen($msg['message']) > 100): ?>
                                                        <a href="view_support_message.php?id=<?php echo $msg['id']; ?>" 
                                                           class="text-blue-600 hover:text-blue-800 text-xs font-medium mt-1 inline-flex items-center">
                                                            <i class="fas fa-eye mr-1"></i>View Full
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="flex items-center text-xs text-gray-500">
                                                    <i class="fas fa-check mr-1"></i>
                                                    <span>Resolved: <?php echo date('M j, Y g:i A', strtotime($msg['resolved_at'])); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Sidebar functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');

            if (sidebar) {
                sidebar.classList.toggle('-translate-x-full');
                sidebar.classList.toggle('show');
            }
            if (overlay) {
                overlay.classList.toggle('hidden');
            }
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');

            if (sidebar) {
                sidebar.classList.add('-translate-x-full');
                sidebar.classList.remove('show');
            }
            if (overlay) {
                overlay.classList.add('hidden');
            }
        }

        // Quick Actions Menu Toggle
        function toggleQuickActions() {
            const menu = document.getElementById('quickActionsMenu');
            menu.classList.toggle('hidden');
        }

        // Close quick actions menu when clicking outside
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('quickActionsMenu');
            const button = event.target.closest('button');
            
            if (!button || !button.onclick || button.onclick.toString().indexOf('toggleQuickActions') === -1) {
                menu.classList.add('hidden');
            }
        });

        // Close sidebar on escape key (mobile)
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeSidebar();
            }
        });

        // Auto-close sidebar on mobile when clicking nav items
        document.querySelectorAll('.sidebar-item').forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth < 1024) {
                    setTimeout(closeSidebar, 150);
                }
            });
        });

        // Auto-refresh support messages every 30 seconds
        setInterval(function() {
            // Only refresh if there are open messages
            const openMessages = document.querySelectorAll('.message-card');
            if (openMessages.length > 0) {
                // Add a subtle indicator that data is being refreshed
                console.log('Checking for new support messages...');
                // You could implement AJAX refresh here if needed
            }
        }, 30000);
    </script>
</body>
</html>
