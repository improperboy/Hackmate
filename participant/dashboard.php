 <?php
    require_once '../includes/db.php';
    require_once '../includes/auth_check.php';
    require_once '../includes/utils.php';

    checkAuth('participant');
    $user = getCurrentUser();

    // Get user's team
    $stmt = $pdo->prepare("
    SELECT t.*, u.name as leader_name, th.name as theme_name, th.color_code as theme_color, f.floor_number, r.room_number
    FROM teams t 
    JOIN team_members tm ON t.id = tm.team_id 
    LEFT JOIN users u ON t.leader_id = u.id
    LEFT JOIN themes th ON t.theme_id = th.id
    LEFT JOIN floors f ON t.floor_id = f.id
    LEFT JOIN rooms r ON t.room_id = r.id
    WHERE tm.user_id = ? AND t.status = 'approved'
");
    $stmt->execute([$user['id']]);
    $user_team = $stmt->fetch();

    // Check for pending join requests
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM join_requests WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$user['id']]);
    $pending_requests = $stmt->fetchColumn();

    // Get detailed join request information for dashboard
    $user_join_requests = [];
    if ($pending_requests > 0) {
        $stmt = $pdo->prepare("
        SELECT jr.*, t.name as team_name, u.name as leader_name 
        FROM join_requests jr 
        JOIN teams t ON jr.team_id = t.id 
        JOIN users u ON t.leader_id = u.id 
        WHERE jr.user_id = ? AND jr.status = 'pending' 
        ORDER BY jr.created_at DESC 
        LIMIT 3
    ");
        $stmt->execute([$user['id']]);
        $user_join_requests = $stmt->fetchAll();
    }

    // Check for pending team created by user
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE leader_id = ? AND status = 'pending'");
    $stmt->execute([$user['id']]);
    $pending_team = $stmt->fetch();

    // Check for rejected team created by user
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE leader_id = ? AND status = 'rejected'");
    $stmt->execute([$user['id']]);
    $rejected_team = $stmt->fetch();

    // Check for pending team invitations
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM team_invitations WHERE to_user_id = ? AND status = 'pending'");
    $stmt->execute([$user['id']]);
    $pending_invitations = $stmt->fetchColumn();

    // Get submission settings
    $stmt = $pdo->query("SELECT * FROM submission_settings WHERE is_active = 1 LIMIT 1");
    $submission_settings = $stmt->fetch();

    // Check if user has submitted
    $submission = null;
    if ($user_team) {
        $stmt = $pdo->prepare("SELECT * FROM submissions WHERE team_id = ?");
        $stmt->execute([$user_team['id']]);
        $submission = $stmt->fetch();
    }

    // Get team statistics
    $total_teams = $pdo->query("SELECT COUNT(*) FROM teams WHERE status = 'approved'")->fetchColumn();
    $total_participants = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'participant'")->fetchColumn();
    $total_submissions = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();

    // Get team members if user is part of a team
    $team_members = [];
    if ($user_team) {
        $stmt = $pdo->prepare("
        SELECT u.name, u.email, u.id, 
               CASE WHEN u.id = ? THEN 1 ELSE 0 END as is_current_user,
               CASE WHEN u.id = t.leader_id THEN 1 ELSE 0 END as is_leader
        FROM team_members tm 
        JOIN users u ON tm.user_id = u.id 
        JOIN teams t ON tm.team_id = t.id
        WHERE tm.team_id = ? 
        ORDER BY is_leader DESC, u.name ASC
    ");
        $stmt->execute([$user['id'], $user_team['id']]);
        $team_members = $stmt->fetchAll();
    }

    // Get team-specific information if user is part of a team
    $mentor_count = 0;
    $team_member_count = 0;
    $max_team_size = 4;
    $submission_status = 'Not Submitted';

    if ($user_team) {
        // Get count of mentors assigned to team's location
        $stmt = $pdo->prepare("
        SELECT COUNT(*) as mentor_count
        FROM mentor_assignments ma 
        JOIN users u ON ma.mentor_id = u.id 
        WHERE ma.floor_id = ? AND ma.room_id = ?
    ");
        $stmt->execute([$user_team['floor_id'], $user_team['room_id']]);
        $mentor_result = $stmt->fetch();
        $mentor_count = $mentor_result ? $mentor_result['mentor_count'] : 0;

        // Get team member count and max size
        $team_member_count = count($team_members);
        require_once '../includes/system_settings.php';
        $team_limits = getTeamSizeLimits();
        $max_team_size = $team_limits['max'];

        // Get submission status
        if ($submission) {
            $submission_status = 'Submitted';
        } else {
            $submission_status = $submission_settings ? 'Pending' : 'Not Available';
        }
    }
    ?>

 <!DOCTYPE html>
 <html lang="en">

 <head>
     <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <title>Dashboard - <?php echo htmlspecialchars(getSystemSetting('hackathon_name', 'HackMate')); ?></title>
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
                     <h1 class="text-lg font-semibold text-gray-900">Dashboard</h1>
                     <div class="w-8"></div> <!-- Spacer for centering -->
                 </div>
             </header>

             <!-- Main Content Area -->
             <main class="flex-1 overflow-y-auto">
                 <div class="p-6">
                     <!-- Welcome Header -->
                     <div class="mb-8">
                         <div>
                             <h1 class="text-2xl font-bold text-gray-900">Welcome Back</h1>
                             <p class="text-gray-600 mt-1">Hi <?php echo htmlspecialchars($user['name']); ?>, ready to build something amazing?</p>
                         </div>
                     </div>

                     <!-- Stats Cards -->
                     <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                         <!-- Team Status Card -->
                         <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                             <div class="flex items-center justify-between">
                                 <div>
                                     <p class="text-sm font-medium text-gray-600">Team Status</p>
                                     <p class="text-2xl font-bold text-gray-900 mt-1">
                                         <?php if ($user_team): ?>
                                             Active
                                         <?php elseif ($pending_team): ?>
                                             Pending
                                         <?php else: ?>
                                             No Team
                                         <?php endif; ?>
                                     </p>
                                 </div>
                                 <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center">
                                     <i class="fas fa-users text-white text-lg"></i>
                                 </div>
                             </div>
                             <?php if ($user_team): ?>
                                 <div class="flex items-center mt-4 text-green-600">
                                     <i class="fas fa-arrow-up text-sm mr-1"></i>
                                     <span class="text-sm font-medium">Team: <?php echo htmlspecialchars($user_team['name']); ?></span>
                                 </div>
                             <?php endif; ?>
                         </div>

                         <?php if ($user_team): ?>
                             <!-- Mentors Assigned Card -->
                             <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                                 <div class="flex items-center justify-between">
                                     <div>
                                         <p class="text-sm font-medium text-gray-600">Mentors Assigned</p>
                                         <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $mentor_count; ?></p>
                                     </div>
                                     <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-teal-600 rounded-xl flex items-center justify-center">
                                         <i class="fas fa-user-tie text-white text-lg"></i>
                                     </div>
                                 </div>
                                 <div class="flex items-center mt-4 text-gray-600">
                                     <i class="fas fa-handshake text-sm mr-1"></i>
                                     <span class="text-sm"><?php echo $mentor_count == 0 ? 'No mentors assigned' : ($mentor_count == 1 ? 'Mentor available' : 'Mentors available'); ?></span>
                                 </div>
                             </div>

                             <!-- Team Members Card -->
                             <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                                 <div class="flex items-center justify-between">
                                     <div>
                                         <p class="text-sm font-medium text-gray-600">Team Members</p>
                                         <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $team_member_count; ?>/<?php echo $max_team_size; ?></p>
                                     </div>
                                     <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-xl flex items-center justify-center">
                                         <i class="fas fa-user-friends text-white text-lg"></i>
                                     </div>
                                 </div>
                                 <div class="flex items-center mt-4 text-gray-600">
                                     <i class="fas fa-users text-sm mr-1"></i>
                                     <span class="text-sm"><?php echo $team_member_count < $max_team_size ? 'Can add more members' : 'Team is full'; ?></span>
                                 </div>
                             </div>

                             <!-- Submission Status Card -->
                             <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                                 <div class="flex items-center justify-between">
                                     <div>
                                         <p class="text-sm font-medium text-gray-600">Submission Status</p>
                                         <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $submission_status; ?></p>
                                     </div>
                                     <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center">
                                         <i class="fas <?php echo $submission ? 'fa-check-circle' : 'fa-upload'; ?> text-white text-lg"></i>
                                     </div>
                                 </div>
                                 <div class="flex items-center mt-4 text-gray-600">
                                     <i class="fas <?php echo $submission ? 'fa-check' : 'fa-clock'; ?> text-sm mr-1"></i>
                                     <span class="text-sm"><?php echo $submission ? 'Project submitted' : ($submission_settings ? 'Submission pending' : 'Submissions closed'); ?></span>
                                 </div>
                             </div>
                         <?php else: ?>
                             <!-- Total Teams Card -->
                             <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                                 <div class="flex items-center justify-between">
                                     <div>
                                         <p class="text-sm font-medium text-gray-600">Total Teams</p>
                                         <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $total_teams; ?></p>
                                     </div>
                                     <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-teal-600 rounded-xl flex items-center justify-center">
                                         <i class="fas fa-flag text-white text-lg"></i>
                                     </div>
                                 </div>
                                 <div class="flex items-center mt-4 text-gray-600">
                                     <i class="fas fa-chart-line text-sm mr-1"></i>
                                     <span class="text-sm">Registered teams</span>
                                 </div>
                             </div>

                             <!-- Available Spots Card -->
                             <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                                 <div class="flex items-center justify-between">
                                     <div>
                                         <p class="text-sm font-medium text-gray-600">Available Spots</p>
                                         <p class="text-2xl font-bold text-gray-900 mt-1"><?php
                                                                                            require_once '../includes/system_settings.php';
                                                                                            $team_limits = getTeamSizeLimits();
                                                                                            $available_spots = max(0, ($total_teams * $team_limits['max']) - $total_participants);
                                                                                            echo $available_spots;
                                                                                            ?></p>
                                     </div>
                                     <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-xl flex items-center justify-center">
                                         <i class="fas fa-user-plus text-white text-lg"></i>
                                     </div>
                                 </div>
                                 <div class="flex items-center mt-4 text-gray-600">
                                     <i class="fas fa-users text-sm mr-1"></i>
                                     <span class="text-sm">Open positions</span>
                                 </div>
                             </div>

                             <!-- Active Requests Card -->
                             <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                                 <div class="flex items-center justify-between">
                                     <div>
                                         <p class="text-sm font-medium text-gray-600">Your Requests</p>
                                         <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $pending_requests; ?></p>
                                     </div>
                                     <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center">
                                         <i class="fas fa-paper-plane text-white text-lg"></i>
                                     </div>
                                 </div>
                                 <div class="flex items-center mt-4 text-gray-600">
                                     <i class="fas fa-clock text-sm mr-1"></i>
                                     <span class="text-sm"><?php echo $pending_requests > 0 ? 'Pending responses' : 'No active requests'; ?></span>
                                 </div>
                             </div>
                         <?php endif; ?>
                     </div>

                     <!-- Team Status Alert -->
                     <?php if ($user_team): ?>
                         <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-2xl p-6 mb-8">
                             <div class="flex items-start">
                                 <div class="flex-shrink-0">
                                     <div class="w-10 h-10 bg-green-500 rounded-xl flex items-center justify-center">
                                         <i class="fas fa-check text-white"></i>
                                     </div>
                                 </div>
                                 <div class="ml-4 flex-1">
                                     <h3 class="text-lg font-semibold text-green-900">You're part of team: <?php echo htmlspecialchars($user_team['name']); ?></h3>
                                     <p class="text-green-700 mt-1">Leader: <?php echo htmlspecialchars($user_team['leader_name']); ?></p>
                                     <?php if ($user_team['theme_name']): ?>
                                         <div class="flex items-center mt-2">
                                             <span class="w-4 h-4 rounded-full mr-2" style="background-color: <?php echo $user_team['theme_color']; ?>"></span>
                                             <span class="text-green-700">Theme: <?php echo htmlspecialchars($user_team['theme_name']); ?></span>
                                         </div>
                                     <?php endif; ?>
                                     <?php if (!empty($user_team['floor_number']) && !empty($user_team['room_number'])): ?>
                                         <div class="flex items-center mt-2 bg-green-100 rounded-lg px-3 py-2 w-fit">
                                             <i class="fas fa-map-marker-alt text-green-700 mr-2"></i>
                                             <span class="text-green-900 font-semibold">Location: Floor <?php echo htmlspecialchars($user_team['floor_number']); ?>, Room <?php echo htmlspecialchars($user_team['room_number']); ?></span>
                                         </div>
                                     <?php else: ?>
                                         <div class="flex items-center mt-2 bg-yellow-100 rounded-lg px-3 py-2 w-fit">
                                             <i class="fas fa-exclamation-circle text-yellow-700 mr-2"></i>
                                             <span class="text-yellow-900 font-semibold">Location: Not assigned yet</span>
                                         </div>
                                     <?php endif; ?>
                                 </div>
                             </div>
                         </div>
                     <?php elseif ($pending_team): ?>
                         <div class="bg-gradient-to-r from-orange-50 to-yellow-50 border border-orange-200 rounded-2xl p-6 mb-8">
                             <div class="flex items-start">
                                 <div class="flex-shrink-0">
                                     <div class="w-10 h-10 bg-orange-500 rounded-xl flex items-center justify-center">
                                         <i class="fas fa-hourglass-half text-white"></i>
                                     </div>
                                 </div>
                                 <div class="ml-4">
                                     <h3 class="text-lg font-semibold text-orange-900">Team Pending Approval</h3>
                                     <p class="text-orange-700 mt-1">Your team "<?php echo htmlspecialchars($pending_team['name']); ?>" is waiting for admin approval.</p>
                                 </div>
                             </div>
                         </div>
                     <?php elseif ($rejected_team): ?>
                         <div class="bg-gradient-to-r from-red-50 to-pink-50 border border-red-200 rounded-2xl p-6 mb-8">
                             <div class="flex items-start">
                                 <div class="flex-shrink-0">
                                     <div class="w-10 h-10 bg-red-500 rounded-xl flex items-center justify-center">
                                         <i class="fas fa-times text-white"></i>
                                     </div>
                                 </div>
                                 <div class="ml-4 flex-1">
                                     <h3 class="text-lg font-semibold text-red-900">Team Rejected</h3>
                                     <p class="text-red-700 mt-1">Your team "<?php echo htmlspecialchars($rejected_team['name']); ?>" was rejected. You can create a new team or join an existing one.</p>
                                     <div class="flex space-x-3 mt-4">
                                         <a href="create_team.php" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">
                                             <i class="fas fa-plus mr-1"></i>
                                             Create New Team
                                         </a>
                                         <a href="join_team.php" class="bg-white text-red-600 px-4 py-2 rounded-lg text-sm font-medium border border-red-300 hover:bg-red-50 transition-colors">
                                             <i class="fas fa-user-plus mr-1"></i>
                                             Join Team
                                         </a>
                                     </div>
                                 </div>
                             </div>
                         </div>
                     <?php elseif ($pending_requests > 0): ?>
                         <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-2xl p-6 mb-8">
                             <div class="flex items-start">
                                 <div class="flex-shrink-0">
                                     <div class="w-10 h-10 bg-blue-500 rounded-xl flex items-center justify-center">
                                         <i class="fas fa-paper-plane text-white"></i>
                                     </div>
                                 </div>
                                 <div class="ml-4 flex-1">
                                     <div class="flex items-center justify-between">
                                         <h3 class="text-lg font-semibold text-blue-900">Pending Join Request<?php echo $pending_requests > 1 ? 's' : ''; ?></h3>
                                         <a href="my_join_requests.php" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                                             View Details <i class="fas fa-arrow-right ml-1"></i>
                                         </a>
                                     </div>
                                     <p class="text-blue-700 mt-1">You have <?php echo $pending_requests; ?> pending join request<?php echo $pending_requests > 1 ? 's' : ''; ?> to different teams. When any team accepts your request, all other pending requests will be automatically cancelled.</p>
                                 </div>
                             </div>
                         </div>
                     <?php else: ?>
                         <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border border-yellow-200 rounded-2xl p-6 mb-8">
                             <div class="flex items-start">
                                 <div class="flex-shrink-0">
                                     <div class="w-10 h-10 bg-yellow-500 rounded-xl flex items-center justify-center">
                                         <i class="fas fa-exclamation-triangle text-white"></i>
                                     </div>
                                 </div>
                                 <div class="ml-4">
                                     <h3 class="text-lg font-semibold text-yellow-900">Ready to Join the Action?</h3>
                                     <p class="text-yellow-700 mt-1">Create a new team or join an existing one to participate in the hackathon.</p>
                                     <div class="flex space-x-3 mt-4">
                                         <a href="create_team.php" class="bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-yellow-700 transition-colors">
                                             Create Team
                                         </a>
                                         <a href="join_team.php" class="bg-white text-yellow-600 px-4 py-2 rounded-lg text-sm font-medium border border-yellow-300 hover:bg-yellow-50 transition-colors">
                                             Join Team
                                         </a>
                                     </div>
                                 </div>
                             </div>
                         </div>
                     <?php endif; ?>

                     <!-- Submission Countdown -->
                     <?php if ($submission_settings && $user_team): ?>
                         <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
                             <div class="flex items-center justify-between mb-6">
                                 <h3 class="text-xl font-bold text-gray-900">
                                     <i class="fas fa-clock text-red-500 mr-2"></i>
                                     Submission Deadline
                                 </h3>
                                 <div class="text-sm text-gray-500">
                                     <?php echo formatDateTime($submission_settings['end_time']); ?>
                                 </div>
                             </div>
                             <div id="countdown" class="grid grid-cols-4 gap-4">
                                 <div class="bg-gradient-to-br from-red-500 to-pink-600 rounded-2xl p-4 text-center text-white">
                                     <div id="days" class="text-3xl font-bold">00</div>
                                     <div class="text-sm opacity-90">Days</div>
                                 </div>
                                 <div class="bg-gradient-to-br from-orange-500 to-red-600 rounded-2xl p-4 text-center text-white">
                                     <div id="hours" class="text-3xl font-bold">00</div>
                                     <div class="text-sm opacity-90">Hours</div>
                                 </div>
                                 <div class="bg-gradient-to-br from-yellow-500 to-orange-600 rounded-2xl p-4 text-center text-white">
                                     <div id="minutes" class="text-3xl font-bold">00</div>
                                     <div class="text-sm opacity-90">Minutes</div>
                                 </div>
                                 <div class="bg-gradient-to-br from-green-500 to-yellow-600 rounded-2xl p-4 text-center text-white">
                                     <div id="seconds" class="text-3xl font-bold">00</div>
                                     <div class="text-sm opacity-90">Seconds</div>
                                 </div>
                             </div>
                         </div>
                     <?php endif; ?>

                     <!-- Main Content Grid -->
                     <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                         <!-- Left Column - Main Content -->
                         <div class="lg:col-span-2 space-y-8">
                             <!-- Quick Actions -->
                             <?php if (!$user_team && $pending_requests == 0 && !$pending_team && !$rejected_team): ?>
                                 <!-- Show Get Started options when user has no pending actions -->
                                 <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                                     <h3 class="text-xl font-bold text-gray-900 mb-6">Get Started</h3>
                                     <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                         <a href="create_team.php" class="group bg-gradient-to-br from-blue-50 to-indigo-50 hover:from-blue-100 hover:to-indigo-100 p-6 rounded-xl border border-blue-200 transition-all duration-200">
                                             <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                                                 <i class="fas fa-plus text-white text-lg"></i>
                                             </div>
                                             <h4 class="font-semibold text-gray-900 mb-2">Create Team</h4>
                                             <p class="text-sm text-gray-600">Start your own team and invite others to join your hackathon journey.</p>
                                         </a>
                                         <a href="join_team.php" class="group bg-gradient-to-br from-green-50 to-emerald-50 hover:from-green-100 hover:to-emerald-100 p-6 rounded-xl border border-green-200 transition-all duration-200">
                                             <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                                                 <i class="fas fa-user-plus text-white text-lg"></i>
                                             </div>
                                             <h4 class="font-semibold text-gray-900 mb-2">Join Team</h4>
                                             <p class="text-sm text-gray-600">Find and join an existing team that matches your skills and interests.</p>
                                         </a>
                                     </div>
                                 </div>
                             <?php elseif (!$user_team && $pending_team): ?>
                                 <!-- Show pending team creation status -->
                                 <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                                     <h3 class="text-xl font-bold text-gray-900 mb-6">
                                         <i class="fas fa-hourglass-half text-orange-500 mr-2"></i>
                                         Team Creation Pending
                                     </h3>

                                     <div class="bg-gradient-to-br from-orange-50 to-yellow-50 border border-orange-200 rounded-xl p-6">
                                         <div class="flex items-start space-x-4">
                                             <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-yellow-600 rounded-xl flex items-center justify-center flex-shrink-0">
                                                 <i class="fas fa-users text-white text-lg"></i>
                                             </div>
                                             <div class="flex-1">
                                                 <h4 class="font-semibold text-gray-900 mb-2">Team Creation Pending</h4>
                                                 <p class="text-sm text-gray-700 mb-3">
                                                     Your team "<strong><?php echo htmlspecialchars($pending_team['name']); ?></strong>" is waiting for admin approval.
                                                 </p>
                                                 <div class="flex items-center text-sm text-orange-700">
                                                     <i class="fas fa-info-circle mr-2"></i>
                                                     <span>You cannot create another team or join other teams while your request is pending.</span>
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                             <?php elseif (!$user_team && $pending_requests > 0): ?>
                                 <!-- Show join requests status and allow more actions -->
                                 <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                                     <div class="flex items-center justify-between mb-6">
                                         <h3 class="text-xl font-bold text-gray-900">
                                             <i class="fas fa-paper-plane text-blue-500 mr-2"></i>
                                             Pending Join Requests
                                         </h3>
                                         <a href="my_join_requests.php" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                                             View All <i class="fas fa-arrow-right ml-1"></i>
                                         </a>
                                     </div>

                                     <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-6 mb-6">
                                         <div class="flex items-start space-x-4">
                                             <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center flex-shrink-0">
                                                 <i class="fas fa-envelope text-white text-lg"></i>
                                             </div>
                                             <div class="flex-1">
                                                 <h4 class="font-semibold text-gray-900 mb-2">
                                                     <?php echo $pending_requests; ?> Pending Request<?php echo $pending_requests > 1 ? 's' : ''; ?>
                                                 </h4>
                                                 <p class="text-sm text-gray-700 mb-3">
                                                     Team leaders will review and respond to your requests. You'll be notified when they make a decision.
                                                 </p>
                                                 <div class="flex items-center text-sm text-blue-700">
                                                     <i class="fas fa-info-circle mr-2"></i>
                                                     <span>You can continue sending more requests to other teams while waiting for responses.</span>
                                                 </div>
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Show recent join requests -->
                                     <?php if (!empty($user_join_requests)): ?>
                                         <div class="mb-6">
                                             <h4 class="font-semibold text-gray-900 mb-3">Recent Requests:</h4>
                                             <div class="space-y-3">
                                                 <?php foreach ($user_join_requests as $request): ?>
                                                     <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                                         <div class="flex-1">
                                                             <p class="font-medium text-gray-900"><?php echo htmlspecialchars($request['team_name']); ?></p>
                                                             <p class="text-sm text-gray-600">Leader: <?php echo htmlspecialchars($request['leader_name']); ?></p>
                                                             <p class="text-xs text-gray-500">Sent <?php echo timeAgo($request['created_at']); ?></p>
                                                         </div>
                                                         <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">
                                                             <i class="fas fa-clock mr-1"></i>
                                                             Pending
                                                         </span>
                                                     </div>
                                                 <?php endforeach; ?>
                                                 <?php if ($pending_requests > 3): ?>
                                                     <div class="text-center">
                                                         <a href="my_join_requests.php" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                                                             View <?php echo $pending_requests - 3; ?> more request<?php echo ($pending_requests - 3) > 1 ? 's' : ''; ?> <i class="fas fa-arrow-right ml-1"></i>
                                                         </a>
                                                     </div>
                                                 <?php endif; ?>
                                             </div>
                                         </div>
                                     <?php endif; ?>

                                     <!-- Allow more actions -->
                                     <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                         <a href="join_team.php" class="group bg-gradient-to-br from-green-50 to-emerald-50 hover:from-green-100 hover:to-emerald-100 p-6 rounded-xl border border-green-200 transition-all duration-200">
                                             <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                                                 <i class="fas fa-user-plus text-white text-lg"></i>
                                             </div>
                                             <h4 class="font-semibold text-gray-900 mb-2">Send More Requests</h4>
                                             <p class="text-sm text-gray-600">Send requests to multiple teams simultaneously to increase your chances of joining a team.</p>
                                         </a>
                                         <a href="create_team.php" class="group bg-gradient-to-br from-blue-50 to-indigo-50 hover:from-blue-100 hover:to-indigo-100 p-6 rounded-xl border border-blue-200 transition-all duration-200">
                                             <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                                                 <i class="fas fa-plus text-white text-lg"></i>
                                             </div>
                                             <h4 class="font-semibold text-gray-900 mb-2">Create Team</h4>
                                             <p class="text-sm text-gray-600">Or start your own team and invite others to join your hackathon journey.</p>
                                         </a>
                                     </div>
                                 </div>
                             <?php elseif ($user_team): ?>
                                 <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                                     <h3 class="text-xl font-bold text-gray-900 mb-6">Team Actions</h3>
                                     <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                         <a href="team_details.php" class="group bg-gradient-to-br from-purple-50 to-pink-50 hover:from-purple-100 hover:to-pink-100 p-4 rounded-xl border border-purple-200 transition-all duration-200">
                                             <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-600 rounded-lg flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                                 <i class="fas fa-users text-white"></i>
                                             </div>
                                             <h4 class="font-medium text-gray-900 text-sm">Team Details</h4>
                                         </a>

                                         <?php if ($user_team['leader_id'] == $user['id']): ?>
                                             <a href="manage_requests.php" class="group bg-gradient-to-br from-indigo-50 to-blue-50 hover:from-indigo-100 hover:to-blue-100 p-4 rounded-xl border border-indigo-200 transition-all duration-200 relative">
                                                 <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-blue-600 rounded-lg flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                                     <i class="fas fa-user-check text-white"></i>
                                                 </div>
                                                 <h4 class="font-medium text-gray-900 text-sm">Join Requests</h4>
                                                 <?php if ($pending_requests > 0): ?>
                                                     <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center animate-pulse">
                                                         <?php echo $pending_requests; ?>
                                                     </span>
                                                 <?php endif; ?>
                                             </a>

                                             <a href="search_users.php" class="group bg-gradient-to-br from-cyan-50 to-teal-50 hover:from-cyan-100 hover:to-teal-100 p-4 rounded-xl border border-cyan-200 transition-all duration-200">
                                                 <div class="w-10 h-10 bg-gradient-to-br from-cyan-500 to-teal-600 rounded-lg flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                                     <i class="fas fa-search text-white"></i>
                                                 </div>
                                                 <h4 class="font-medium text-gray-900 text-sm">Find Members</h4>
                                             </a>

                                             <?php if ($submission_settings): ?>
                                                 <a href="submit_project.php" class="group bg-gradient-to-br from-orange-50 to-red-50 hover:from-orange-100 hover:to-red-100 p-4 rounded-xl border border-orange-200 transition-all duration-200">
                                                     <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-red-600 rounded-lg flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                                         <i class="fas fa-upload text-white"></i>
                                                     </div>
                                                     <h4 class="font-medium text-gray-900 text-sm"><?php echo $submission ? 'Update' : 'Submit'; ?> Project</h4>
                                                 </a>
                                             <?php endif; ?>
                                         <?php endif; ?>
                                     </div>
                                 </div>
                             <?php endif; ?>

                             <!-- Team Details Section -->
                             <?php if ($user_team): ?>
                                 <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                                     <h3 class="text-xl font-bold text-gray-900 mb-6">
                                         <i class="fas fa-users text-blue-500 mr-2"></i>
                                         Team Members
                                     </h3>
                                     <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                         <?php foreach ($team_members as $member): ?>
                                             <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-4 border border-gray-200">
                                                 <div class="flex items-center space-x-3">
                                                     <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                                                         <span class="text-white font-semibold text-sm">
                                                             <?php echo strtoupper(substr($member['name'], 0, 2)); ?>
                                                         </span>
                                                     </div>
                                                     <div class="flex-1 min-w-0">
                                                         <div class="flex items-center space-x-2">
                                                             <p class="text-sm font-semibold text-gray-900 truncate">
                                                                 <?php echo htmlspecialchars($member['name']); ?>
                                                             </p>
                                                             <?php if ($member['is_leader']): ?>
                                                                 <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full font-medium">
                                                                     Leader
                                                                 </span>
                                                             <?php endif; ?>
                                                             <?php if ($member['is_current_user']): ?>
                                                                 <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full font-medium">
                                                                     You
                                                                 </span>
                                                             <?php endif; ?>
                                                         </div>
                                                         <p class="text-xs text-gray-500 truncate">
                                                             <?php echo htmlspecialchars($member['email']); ?>
                                                         </p>
                                                     </div>
                                                 </div>
                                             </div>
                                         <?php endforeach; ?>
                                     </div>

                                     <!-- Team Info -->
                                     <div class="mt-6 pt-6 border-t border-gray-200">
                                         <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                             <?php if ($user_team['theme_name']): ?>
                                                 <div class="flex items-center space-x-3">
                                                     <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: <?php echo $user_team['theme_color']; ?>">
                                                         <i class="fas fa-palette text-white text-sm"></i>
                                                     </div>
                                                     <div>
                                                         <p class="text-xs text-gray-500">Theme</p>
                                                         <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user_team['theme_name']); ?></p>
                                                     </div>
                                                 </div>
                                             <?php endif; ?>

                                             <?php if ($user_team['floor_number'] && $user_team['room_number']): ?>
                                                 <div class="flex items-center space-x-3">
                                                     <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                                                         <i class="fas fa-map-marker-alt text-white text-sm"></i>
                                                     </div>
                                                     <div>
                                                         <p class="text-xs text-gray-500">Location</p>
                                                         <p class="text-sm font-medium text-gray-900">Floor <?php echo $user_team['floor_number']; ?>, Room <?php echo $user_team['room_number']; ?></p>
                                                     </div>
                                                 </div>
                                             <?php endif; ?>

                                             <div class="flex items-center space-x-3">
                                                 <div class="w-8 h-8 bg-purple-500 rounded-lg flex items-center justify-center">
                                                     <i class="fas fa-users text-white text-sm"></i>
                                                 </div>
                                                 <div>
                                                     <p class="text-xs text-gray-500">Team Size</p>
                                                     <p class="text-sm font-medium text-gray-900"><?php echo count($team_members); ?> members</p>
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                             <?php endif; ?>

                             <!-- Your Submission -->
                             <?php if ($user_team && $submission): ?>
                                 <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                                     <h3 class="text-xl font-bold text-gray-900 mb-6">
                                         <i class="fas fa-file-check text-green-500 mr-2"></i>
                                         Your Submission
                                     </h3>
                                     <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                         <div class="space-y-4">
                                             <div>
                                                 <label class="text-sm font-medium text-gray-600">GitHub Repository</label>
                                                 <a href="<?php echo htmlspecialchars($submission['github_link']); ?>" target="_blank" class="block text-blue-600 hover:text-blue-800 text-sm break-all mt-1">
                                                     <i class="fab fa-github mr-1"></i>
                                                     <?php echo htmlspecialchars($submission['github_link']); ?>
                                                 </a>
                                             </div>
                                             <?php if ($submission['live_link']): ?>
                                                 <div>
                                                     <label class="text-sm font-medium text-gray-600">Live Demo</label>
                                                     <a href="<?php echo htmlspecialchars($submission['live_link']); ?>" target="_blank" class="block text-blue-600 hover:text-blue-800 text-sm break-all mt-1">
                                                         <i class="fas fa-external-link-alt mr-1"></i>
                                                         <?php echo htmlspecialchars($submission['live_link']); ?>
                                                     </a>
                                                 </div>
                                             <?php endif; ?>
                                         </div>
                                         <div class="space-y-4">
                                             <div>
                                                 <label class="text-sm font-medium text-gray-600">Tech Stack</label>
                                                 <p class="text-sm text-gray-800 mt-1"><?php echo htmlspecialchars($submission['tech_stack']); ?></p>
                                             </div>
                                             <div>
                                                 <label class="text-sm font-medium text-gray-600">Submitted</label>
                                                 <p class="text-sm text-gray-800 mt-1">
                                                     <i class="fas fa-clock mr-1"></i>
                                                     <?php echo formatDateTime($submission['submitted_at']); ?>
                                                 </p>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                             <?php endif; ?>
                         </div>

                         <!-- Right Column - Sidebar Content -->
                         <div class="space-y-6">
                             <!-- Latest Announcements -->
                             <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                                 <h3 class="text-lg font-bold text-gray-900 mb-4">
                                     <i class="fas fa-bullhorn text-blue-500 mr-2"></i>
                                     Latest News
                                 </h3>

                                 <?php
                                    // Get latest announcements for participant dashboard
                                    $stmt = $pdo->query("
                                    SELECT p.*, u.name as author_name 
                                    FROM posts p 
                                    JOIN users u ON p.author_id = u.id 
                                    ORDER BY p.created_at DESC 
                                    LIMIT 3
                                ");
                                    $latest_announcements = $stmt->fetchAll();
                                    ?>

                                 <?php if (empty($latest_announcements)): ?>
                                     <div class="text-center py-8">
                                         <i class="fas fa-bullhorn text-gray-300 text-3xl mb-3"></i>
                                         <p class="text-gray-500 text-sm">No announcements yet</p>
                                     </div>
                                 <?php else: ?>
                                     <div class="space-y-4">
                                         <?php foreach ($latest_announcements as $announcement): ?>
                                             <div class="border-l-4 border-blue-400 bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-r-xl">
                                                 <h4 class="font-semibold text-gray-900 text-sm mb-2">
                                                     <?php echo htmlspecialchars($announcement['title']); ?>
                                                 </h4>
                                                 <p class="text-gray-700 text-xs mb-3 leading-relaxed">
                                                     <?php echo htmlspecialchars(substr($announcement['content'], 0, 80)) . (strlen($announcement['content']) > 80 ? '...' : ''); ?>
                                                 </p>
                                                 <div class="flex justify-between items-center text-xs text-gray-500">
                                                     <span>
                                                         <i class="fas fa-user mr-1"></i>
                                                         <?php echo htmlspecialchars($announcement['author_name']); ?>
                                                     </span>
                                                     <span>
                                                         <?php echo timeAgo($announcement['created_at']); ?>
                                                     </span>
                                                 </div>
                                             </div>
                                         <?php endforeach; ?>
                                     </div>

                                     <div class="mt-4 text-center">
                                         <a href="announcements.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 text-sm font-medium">
                                             View All <i class="fas fa-arrow-right ml-1"></i>
                                         </a>
                                     </div>
                                 <?php endif; ?>
                             </div>

                             <!-- Quick Links -->
                             <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                                 <h3 class="text-lg font-bold text-gray-900 mb-4">Quick Links</h3>
                                 <div class="space-y-3">
                                     <?php if (!$user_team): ?>
                                         <!-- Only show invitations if user is not part of a team -->
                                         <a href="team_invitations.php" class="flex items-center justify-between p-3 bg-gray-50 hover:bg-gray-100 rounded-xl transition-colors">
                                             <div class="flex items-center">
                                                 <i class="fas fa-envelope text-pink-500 mr-3"></i>
                                                 <span class="text-sm font-medium text-gray-900">Invitations</span>
                                             </div>
                                             <?php if ($pending_invitations > 0): ?>
                                                 <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full animate-pulse">
                                                     <?php echo $pending_invitations; ?>
                                                 </span>
                                             <?php endif; ?>
                                         </a>
                                     <?php endif; ?>

                                     <a href="rankings.php" class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-xl transition-colors">
                                         <i class="fas fa-trophy text-yellow-500 mr-3"></i>
                                         <span class="text-sm font-medium text-gray-900">Team Rankings</span>
                                     </a>

                                     <a href="support.php" class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-xl transition-colors">
                                         <i class="fas fa-life-ring text-red-500 mr-3"></i>
                                         <span class="text-sm font-medium text-gray-900">Get Support</span>
                                     </a>
                                 </div>
                             </div>

                             <!-- Keep You Safe Section (Security) -->
                             <div class="bg-gradient-to-br from-purple-50 to-indigo-50 rounded-2xl p-6 border border-purple-200">
                                 <div class="text-center">
                                     <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-full flex items-center justify-center mx-auto mb-4">
                                         <i class="fas fa-shield-alt text-white text-xl"></i>
                                     </div>
                                     <h3 class="text-lg font-bold text-gray-900 mb-2">Keep Your Account Safe</h3>
                                     <p class="text-sm text-gray-600 mb-4">Update your security password to keep your account safe</p>
                                     <a href="../change_password.php" class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:from-purple-700 hover:to-indigo-700 transition-all duration-200">
                                         Update Password
                                     </a>
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

     <?php if ($submission_settings): ?>
         <script>
             // Countdown timer
             const endTime = new Date('<?php echo $submission_settings['end_time']; ?>').getTime();

             function updateCountdown() {
                 const now = new Date().getTime();
                 const distance = endTime - now;

                 if (distance < 0) {
                     document.getElementById('countdown').innerHTML = '<div class="text-red-600 font-bold">DEADLINE PASSED</div>';
                     return;
                 }

                 const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                 const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                 const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                 const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                 document.getElementById('days').textContent = days.toString().padStart(2, '0');
                 document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
                 document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
                 document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
             }

             updateCountdown();
             setInterval(updateCountdown, 1000);
         </script>
     <?php endif; ?>

     <!-- Include AI Chatbot -->
     <?php include '../includes/chatbot_component.php'; ?>
 </body>

 </html>