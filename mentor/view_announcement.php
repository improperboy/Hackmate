<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth(['mentor']);
$user = getCurrentUser();

$announcement_id = intval($_GET['id'] ?? 0);

if ($announcement_id <= 0) {
    header('Location: announcements.php?error=' . urlencode('Invalid announcement ID'));
    exit;
}

// Fetch the announcement
$stmt = $pdo->prepare("
    SELECT p.*, u.name as author_name, u.role as author_role
    FROM posts p 
    JOIN users u ON p.author_id = u.id 
    WHERE p.id = ?
");
$stmt->execute([$announcement_id]);
$announcement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$announcement) {
    header('Location: announcements.php?error=' . urlencode('Announcement not found'));
    exit;
}

$page_title = 'Announcement Details';
$page_description = 'View detailed announcement information';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - HackMate Mentor</title>
    
    <!-- Primary Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- PWA Configuration -->
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#059669">
    
    <!-- Meta Tags -->
    <meta name="description" content="<?php echo $page_description; ?>">
    <meta name="keywords" content="hackathon, announcement, mentor, <?php echo htmlspecialchars($announcement['title']); ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/icons/icon-96x96.png">
</head>
<body class="bg-gray-100 min-h-screen flex"> 
   <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Top Navigation -->
        <header class="bg-white shadow-sm border-b border-gray-200 lg:hidden">
            <div class="flex items-center justify-between px-4 py-3">
                <button onclick="toggleSidebar()" class="text-gray-600 hover:text-gray-900">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="text-lg font-semibold text-gray-800">Announcement Details</h1>
                <div class="w-8"></div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 overflow-y-auto">
            <div class="max-w-4xl mx-auto py-6 px-4 lg:px-6">
         
                <!-- Announcement Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <!-- Header -->
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-8 py-6 border-b border-gray-200">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                        <i class="fas fa-bullhorn text-green-600"></i>
                                    </div>
                                    <span class="bg-green-100 text-green-800 text-xs font-medium px-3 py-1 rounded-full">
                                        Official Announcement
                                    </span>
                                </div>
                                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                                    <?php echo htmlspecialchars($announcement['title']); ?>
                                </h1>
                                <div class="flex items-center text-sm text-gray-600">
                                    <div class="flex items-center mr-6">
                                        <i class="fas fa-user mr-2"></i>
                                        <span>Posted by <?php echo htmlspecialchars($announcement['author_name']); ?></span>
                                        <span class="ml-2 bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded-full">
                                            <?php echo ucfirst($announcement['author_role']); ?>
                                        </span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-calendar mr-2"></i>
                                        <span><?php echo formatDateTime($announcement['created_at']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>      
              <!-- Content -->
                    <div class="px-8 py-6">
                        <div class="prose max-w-none">
                            <div class="text-gray-800 leading-relaxed text-lg whitespace-pre-line">
                                <?php echo htmlspecialchars($announcement['content']); ?>
                            </div>
                        </div>

                        <!-- Link Section -->
                        <?php if (!empty($announcement['link_url']) && !empty($announcement['link_text'])): ?>
                            <div class="mt-8 p-6 bg-green-50 border border-green-200 rounded-xl">
                                <h3 class="text-lg font-semibold text-green-900 mb-3 flex items-center">
                                    <i class="fas fa-link mr-2"></i>
                                    Additional Resource
                                </h3>
                                <a href="<?php echo htmlspecialchars($announcement['link_url']); ?>" 
                                   target="_blank" 
                                   class="inline-flex items-center px-6 py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-colors shadow-md hover:shadow-lg">
                                    <i class="fas fa-external-link-alt mr-3"></i>
                                    <?php echo htmlspecialchars($announcement['link_text']); ?>
                                </a>
                                <p class="text-sm text-green-700 mt-3">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    This link will open in a new tab
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Footer -->
                    <div class="bg-gray-50 px-8 py-4 border-t border-gray-200">
                        <div class="text-sm text-gray-500">
                            <i class="fas fa-clock mr-1"></i>
                            Published <?php echo timeAgo($announcement['created_at']); ?>
                        </div>
                    </div>
                </div>

                <!-- Mentor Actions -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="fas fa-users text-green-600 mr-2"></i>
                            My Teams
                        </h3>
                        <p class="text-gray-600 text-sm mb-4">View and manage assigned teams</p>
                        <a href="assigned_teams.php" class="inline-flex items-center bg-green-100 text-green-800 px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-200 transition-colors">
                            <i class="fas fa-arrow-right mr-2"></i>
                            View Teams
                        </a>
                    </div>          
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="fas fa-star text-blue-600 mr-2"></i>
                            Score Teams
                        </h3>
                        <p class="text-gray-600 text-sm mb-4">Evaluate team progress and performance</p>
                        <a href="score_teams.php" class="inline-flex items-center bg-blue-100 text-blue-800 px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-200 transition-colors">
                            <i class="fas fa-clipboard-check mr-2"></i>
                            Score Teams
                        </a>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="fas fa-life-ring text-purple-600 mr-2"></i>
                            Support Messages
                        </h3>
                        <p class="text-gray-600 text-sm mb-4">Handle team support requests</p>
                        <a href="support_messages.php" class="inline-flex items-center bg-purple-100 text-purple-800 px-4 py-2 rounded-lg text-sm font-medium hover:bg-purple-200 transition-colors">
                            <i class="fas fa-headset mr-2"></i>
                            View Messages
                        </a>
                    </div>
                </div>

                <!-- Navigation Links -->
                <div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-compass text-green-600 mr-2"></i>
                        Quick Navigation
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <a href="dashboard.php" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <i class="fas fa-tachometer-alt text-gray-600 mr-3"></i>
                            <span class="text-sm font-medium text-gray-800">Dashboard</span>
                        </a>
                        <a href="announcements.php" class="flex items-center p-3 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                            <i class="fas fa-bullhorn text-green-600 mr-3"></i>
                            <span class="text-sm font-medium text-green-800">All Announcements</span>
                        </a>
                        <a href="schedule.php" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <i class="fas fa-calendar text-gray-600 mr-3"></i>
                            <span class="text-sm font-medium text-gray-800">Schedule</span>
                        </a>
                        <a href="mentor_guidelines.php" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <i class="fas fa-book text-gray-600 mr-3"></i>
                            <span class="text-sm font-medium text-gray-800">Guidelines</span>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
    @media print {
        .no-print, nav, header, .mt-8 {
            display: none !important;
        }
        
        body {
            background: white !important;
        }
        
        .bg-gradient-to-r {
            background: #f8fafc !important;
        }
        
        #sidebar {
            display: none !important;
        }
        
        .flex-1 {
            margin-left: 0 !important;
        }
    }
    </style>  


    <!-- PWA Scripts -->
    <script src="../assets/js/pwa.js"></script>
</body>
</html>