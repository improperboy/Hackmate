 <?php
    require_once '../includes/db.php';
    require_once '../includes/auth_check.php';
    require_once '../includes/utils.php';

    checkAuth('mentor');
    $user = getCurrentUser();

    $message = '';
    $error = '';

    // Get mentor's assignments for context
    $stmt = $pdo->prepare("
    SELECT ma.*, f.floor_number, r.room_number 
    FROM mentor_assignments ma
    JOIN floors f ON ma.floor_id = f.id
    JOIN rooms r ON ma.room_id = r.id
    WHERE ma.mentor_id = ?
");
    $stmt->execute([$user['id']]);
    $assignments = $stmt->fetchAll();

    // Handle admin contact message
    if ($_POST) {
        $admin_message = sanitize($_POST['message']);

        if (empty($admin_message)) {
            $error = 'Message is required';
        } else {
            // Use the first assignment for floor/room context, or null if no assignments
            $floor_id = null;
            $room_id = null;

            if (!empty($assignments)) {
                $floor_id = $assignments[0]['floor_id'];
                $room_id = $assignments[0]['room_id'];
            }

            $stmt = $pdo->prepare("INSERT INTO support_messages (from_id, from_role, to_role, message, floor_id, room_id) VALUES (?, 'mentor', 'admin', ?, ?, ?)");
            if ($stmt->execute([$user['id'], $admin_message, $floor_id, $room_id])) {
                $message = 'Message sent to admin successfully!';
            } else {
                $error = 'Failed to send message. Please try again.';
            }
        }
    }

    // Get mentor's messages to admin
    $stmt = $pdo->prepare("
    SELECT sm.*, f.floor_number, r.room_number 
    FROM support_messages sm 
    LEFT JOIN floors f ON sm.floor_id = f.id
    LEFT JOIN rooms r ON sm.room_id = r.id
    WHERE sm.from_id = ? AND sm.from_role = 'mentor' AND sm.to_role = 'admin'
    ORDER BY sm.created_at DESC
");
    $stmt->execute([$user['id']]);
    $admin_messages = $stmt->fetchAll();
    ?>

 <!DOCTYPE html>
 <html lang="en">

 <head>
     <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <title>Contact Admin - HackMate</title>

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

         .contact-card {
             background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
             border: 1px solid #e2e8f0;
             transition: all 0.3s ease;
         }

         .contact-card:hover {
             transform: translateY(-2px);
             box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
         }

         .message-card {
             transition: all 0.2s ease;
         }

         .message-card:hover {
             transform: translateX(4px);
         }

         .status-open {
             background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
             border-left: 4px solid #f59e0b;
         }

         .status-resolved {
             background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
             border-left: 4px solid #10b981;
         }

         .status-in-progress {
             background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
             border-left: 4px solid #3b82f6;
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
                             <div class="w-8 h-8 bg-gradient-to-br from-red-500 to-pink-500 rounded-lg flex items-center justify-center">
                                 <i class="fas fa-phone text-white text-sm"></i>
                             </div>
                             <div>
                                 <h1 class="text-xl font-bold text-gray-900">Contact Admin</h1>
                                 <p class="text-sm text-gray-500 hidden sm:block">Get help and support</p>
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
                                 <a href="support_messages.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                     <i class="fas fa-life-ring w-4 mr-2"></i>Support Messages
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
                 <div class="bg-gradient-to-r from-red-600 to-pink-600 rounded-2xl p-6 text-white">
                     <div class="flex items-center justify-between">
                         <div>
                             <h2 class="text-2xl font-bold mb-2">Contact Admin</h2>
                             <p class="text-red-100 mb-3">Get help with assignments, resources, or urgent issues</p>

                             <?php if (!empty($assignments)): ?>
                                 <div class="flex items-center text-red-100">
                                     <i class="fas fa-map-marker-alt mr-2"></i>
                                     <span class="text-sm">
                                         Your assignments:
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
                                 <i class="fas fa-headset text-3xl text-white"></i>
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

             <!-- Contact Form -->
             <div class="contact-card rounded-2xl shadow-sm border border-gray-200 p-6 mb-8">
                 <div class="flex items-center justify-between mb-6">
                     <h3 class="text-lg font-semibold text-gray-900">
                         <i class="fas fa-envelope text-blue-500 mr-2"></i>
                         Send Message to Admin
                     </h3>
                     <div class="hidden sm:flex items-center space-x-2 text-sm text-gray-500">
                         <i class="fas fa-clock mr-1"></i>
                         <span>Response within 24 hours</span>
                     </div>
                 </div>

                 <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-blue-400 rounded-xl p-4 mb-6">
                     <div class="flex items-start">
                         <div class="flex-shrink-0">
                             <i class="fas fa-info-circle text-blue-500 mt-1"></i>
                         </div>
                         <div class="ml-3">
                             <h4 class="text-sm font-medium text-blue-800 mb-1">When to Contact Admin</h4>
                             <ul class="text-sm text-blue-700 space-y-1">
                                 <li>• Urgent technical issues or system problems</li>
                                 <li>• Assignment questions or location changes</li>
                                 <li>• Resource requests or equipment needs</li>
                                 <li>• Team management concerns</li>
                                 <li>• Any issues requiring administrative attention</li>
                             </ul>
                         </div>
                     </div>
                 </div>

                 <form method="POST" class="space-y-6">
                     <div>
                         <label class="block text-sm font-medium text-gray-700 mb-2">
                             <i class="fas fa-comment text-blue-500 mr-2"></i>
                             Message to Admin *
                         </label>
                         <textarea name="message" required rows="6"
                             class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                             placeholder="Please describe your issue, request, or concern in detail. Include any relevant context that might help the admin understand and resolve your request quickly."></textarea>
                         <p class="text-xs text-gray-500 mt-1">Be specific and include relevant details for faster resolution</p>
                     </div>

                     <!-- Your Information Card -->
                     <div class="bg-gradient-to-br from-gray-50 to-slate-50 rounded-xl p-4 border border-gray-200">
                         <h4 class="font-semibold text-gray-900 mb-3 flex items-center">
                             <i class="fas fa-user-circle text-gray-500 mr-2"></i>
                             Your Information
                         </h4>
                         <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                             <div class="bg-white rounded-lg p-3">
                                 <div class="flex items-center mb-1">
                                     <i class="fas fa-user text-blue-500 mr-2 text-sm"></i>
                                     <span class="text-sm font-medium text-gray-600">Name</span>
                                 </div>
                                 <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($user['name']); ?></p>
                             </div>
                             <div class="bg-white rounded-lg p-3">
                                 <div class="flex items-center mb-1">
                                     <i class="fas fa-envelope text-green-500 mr-2 text-sm"></i>
                                     <span class="text-sm font-medium text-gray-600">Email</span>
                                 </div>
                                 <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($user['email']); ?></p>
                             </div>
                             <div class="bg-white rounded-lg p-3">
                                 <div class="flex items-center mb-1">
                                     <i class="fas fa-shield-alt text-purple-500 mr-2 text-sm"></i>
                                     <span class="text-sm font-medium text-gray-600">Role</span>
                                 </div>
                                 <p class="text-gray-900 font-medium">Mentor</p>
                             </div>
                             <div class="bg-white rounded-lg p-3">
                                 <div class="flex items-center mb-1">
                                     <i class="fas fa-map-marker-alt text-red-500 mr-2 text-sm"></i>
                                     <span class="text-sm font-medium text-gray-600">Assignments</span>
                                 </div>
                                 <p class="text-gray-900 font-medium">
                                     <?php
                                        if (!empty($assignments)) {
                                            $assignment_list = [];
                                            foreach ($assignments as $assignment) {
                                                $assignment_list[] = $assignment['floor_number'] . '-' . $assignment['room_number'];
                                            }
                                            echo implode(', ', $assignment_list);
                                        } else {
                                            echo 'No assignments yet';
                                        }
                                        ?>
                                 </p>
                             </div>
                         </div>
                     </div>

                     <div class="flex items-center justify-between">
                         <div class="flex items-center text-sm text-gray-500">
                             <i class="fas fa-shield-check mr-2"></i>
                             <span>Your message will be sent securely to the admin team</span>
                         </div>
                         <button type="submit"
                             class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold py-3 px-6 rounded-lg transition-all duration-200 transform hover:scale-105">
                             <i class="fas fa-paper-plane mr-2"></i>
                             Send Message
                         </button>
                     </div>
                 </form>
             </div>

             <!-- Message History -->
             <div class="contact-card rounded-2xl shadow-sm border border-gray-200">
                 <div class="px-6 py-4 border-b border-gray-200">
                     <div class="flex items-center justify-between">
                         <h3 class="text-lg font-semibold text-gray-900">
                             <i class="fas fa-history text-gray-500 mr-2"></i>
                             Message History (<?php echo count($admin_messages); ?>)
                         </h3>
                         <?php if (count($admin_messages) > 0): ?>
                             <span class="text-sm text-gray-500">
                                 Latest: <?php echo date('M j, Y', strtotime($admin_messages[0]['created_at'])); ?>
                             </span>
                         <?php endif; ?>
                     </div>
                 </div>

                 <?php if (empty($admin_messages)): ?>
                     <div class="px-6 py-12 text-center">
                         <i class="fas fa-inbox text-gray-300 text-6xl mb-6"></i>
                         <h4 class="text-xl font-semibold text-gray-900 mb-3">No Messages Yet</h4>
                         <p class="text-gray-500 mb-4">You haven't sent any messages to the admin team yet.</p>
                         <p class="text-sm text-gray-400">Use the form above to send your first message</p>
                     </div>
                 <?php else: ?>
                     <div class="p-6">
                         <div class="space-y-4">
                             <?php foreach ($admin_messages as $msg): ?>
                                 <?php
                                    $status_class = 'status-open';
                                    if ($msg['status'] == 'resolved') {
                                        $status_class = 'status-resolved';
                                    } elseif ($msg['status'] == 'in_progress') {
                                        $status_class = 'status-in-progress';
                                    }
                                    ?>
                                 <div class="message-card <?php echo $status_class; ?> rounded-xl p-6">
                                     <div class="flex justify-between items-start mb-4">
                                         <div class="flex-1">
                                             <div class="bg-white bg-opacity-70 rounded-lg p-4 mb-4">
                                                 <p class="text-gray-800 leading-relaxed"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                             </div>

                                             <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                                                 <div class="flex items-center">
                                                     <i class="fas fa-clock mr-1"></i>
                                                     <span><?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></span>
                                                 </div>

                                                 <?php if ($msg['floor_number'] && $msg['room_number']): ?>
                                                     <div class="flex items-center">
                                                         <i class="fas fa-map-marker-alt mr-1"></i>
                                                         <span>Context: <?php echo $msg['floor_number']; ?>-<?php echo $msg['room_number']; ?></span>
                                                     </div>
                                                 <?php endif; ?>
                                             </div>
                                         </div>

                                         <div class="ml-4">
                                             <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full <?php
                                                                                                                                echo $msg['status'] == 'open' ? 'bg-yellow-100 text-yellow-800' : ($msg['status'] == 'resolved' ? 'bg-green-100 text-green-800' : ($msg['status'] == 'in_progress' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'));
                                                                                                                                ?>">
                                                 <?php if ($msg['status'] == 'open'): ?>
                                                     <span class="w-2 h-2 bg-yellow-500 rounded-full mr-2 animate-pulse"></span>
                                                 <?php elseif ($msg['status'] == 'resolved'): ?>
                                                     <i class="fas fa-check-circle mr-1"></i>
                                                 <?php elseif ($msg['status'] == 'in_progress'): ?>
                                                     <i class="fas fa-spinner fa-spin mr-1"></i>
                                                 <?php endif; ?>
                                                 <?php echo ucfirst(str_replace('_', ' ', $msg['status'])); ?>
                                             </span>
                                         </div>
                                     </div>
                                 </div>
                             <?php endforeach; ?>
                         </div>
                     </div>
                 <?php endif; ?>
             </div>
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

         // Form enhancements
         document.addEventListener('DOMContentLoaded', function() {
             const textarea = document.querySelector('textarea[name="message"]');
             const submitButton = document.querySelector('button[type="submit"]');

             if (textarea && submitButton) {
                 // Auto-resize textarea
                 textarea.addEventListener('input', function() {
                     this.style.height = 'auto';
                     this.style.height = Math.min(this.scrollHeight, 200) + 'px';
                 });

                 // Character counter
                 const maxLength = 1000;
                 const counter = document.createElement('div');
                 counter.className = 'text-xs text-gray-500 mt-1 text-right';
                 textarea.parentNode.appendChild(counter);

                 function updateCounter() {
                     const remaining = maxLength - textarea.value.length;
                     counter.textContent = `${textarea.value.length}/${maxLength} characters`;
                     counter.className = remaining < 50 ? 'text-xs text-red-500 mt-1 text-right' : 'text-xs text-gray-500 mt-1 text-right';
                 }

                 textarea.addEventListener('input', updateCounter);
                 textarea.setAttribute('maxlength', maxLength);
                 updateCounter();

                 // Form validation
                 submitButton.addEventListener('click', function(e) {
                     if (textarea.value.trim().length < 10) {
                         e.preventDefault();
                         alert('Please provide a more detailed message (at least 10 characters).');
                         textarea.focus();
                     }
                 });
             }
         });
     </script>
 </body>

 </html>