<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth(['participant']);
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
    <title><?php echo $page_title; ?> - HackMate</title>
    
    <!-- Primary Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- PWA Configuration -->
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#4F46E5">
    
    <!-- Meta Tags -->
    <meta name="description" content="<?php echo $page_description; ?>">
    <meta name="keywords" content="hackathon, announcement, participant, <?php echo htmlspecialchars($announcement['title']); ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/icons/icon-96x96.png">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden lg:ml-0">
            <!-- Mobile Header -->
            <header class="lg:hidden bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-4 py-3">
                    <button onclick="toggleSidebar()" class="text-gray-600 hover:text-gray-900 transition-colors">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-semibold text-gray-800">Announcement</h1>
                    <a href="announcements.php" class="text-indigo-600 hover:text-indigo-800">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </a>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto">
                <div class="max-w-5xl mx-auto py-6 px-4 lg:px-8">
                    <!-- Back Navigation -->
                    <div class="mb-6">
                        <a href="announcements.php" class="inline-flex items-center text-indigo-600 hover:text-indigo-800 font-medium transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back to Announcements
                        </a>
                    </div>

                    <!-- Announcement Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <!-- Header -->
                        <div class="bg-gradient-to-r from-indigo-50 to-purple-50 px-8 py-8 border-b border-gray-200">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-4">
                                        <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-xl flex items-center justify-center mr-4">
                                            <i class="fas fa-bullhorn text-white text-lg"></i>
                                        </div>
                                        <span class="bg-gradient-to-r from-indigo-500 to-purple-500 text-white text-sm font-bold px-4 py-2 rounded-full">
                                            Official Announcement
                                        </span>
                                    </div>
                                    <h1 class="text-3xl font-bold text-gray-900 mb-4">
                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                    </h1>
                                    <div class="flex flex-wrap items-center gap-6 text-sm text-gray-600">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-gradient-to-br from-green-500 to-blue-500 rounded-full flex items-center justify-center mr-3">
                                                <i class="fas fa-user text-white text-xs"></i>
                                            </div>
                                            <div>
                                                <span class="font-medium">Posted by <?php echo htmlspecialchars($announcement['author_name']); ?></span>
                                                <span class="ml-2 bg-white bg-opacity-80 text-gray-700 text-xs px-3 py-1 rounded-full border">
                                                    <?php echo ucfirst($announcement['author_role']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-gradient-to-br from-orange-500 to-red-500 rounded-full flex items-center justify-center mr-3">
                                                <i class="fas fa-calendar text-white text-xs"></i>
                                            </div>
                                            <span class="font-medium"><?php echo formatDateTime($announcement['created_at']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="px-8 py-8">
                            <div class="prose max-w-none">
                                <div class="text-gray-800 leading-relaxed text-lg whitespace-pre-line">
                                    <?php echo htmlspecialchars($announcement['content']); ?>
                                </div>
                            </div>

                            <!-- Link Section -->
                            <?php if (!empty($announcement['link_url']) && !empty($announcement['link_text'])): ?>
                                <div class="mt-8 p-6 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl">
                                    <div class="flex items-center space-x-3 mb-4">
                                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-link text-white text-sm"></i>
                                        </div>
                                        <h3 class="text-lg font-semibold text-blue-900">Additional Resource</h3>
                                    </div>
                                    <a href="<?php echo htmlspecialchars($announcement['link_url']); ?>" 
                                       target="_blank" 
                                       class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl">
                                        <i class="fas fa-external-link-alt mr-3"></i>
                                        <?php echo htmlspecialchars($announcement['link_text']); ?>
                                    </a>
                                    <p class="text-sm text-blue-700 mt-3 flex items-center">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        This link will open in a new tab
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Footer -->
                        <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-8 py-4 border-t border-gray-200">
                            <div class="flex items-center text-sm text-gray-500">
                                <div class="w-6 h-6 bg-gray-300 rounded-full flex items-center justify-center mr-2">
                                    <i class="fas fa-clock text-gray-500 text-xs"></i>
                                </div>
                                <span>Published <?php echo timeAgo($announcement['created_at']); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-lg transition-all duration-200">
                            <div class="flex items-center space-x-3 mb-4">
                                <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-blue-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-users text-white text-sm"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">My Team</h3>
                            </div>
                            <p class="text-gray-600 text-sm mb-4">Check your team status and collaborate</p>
                            <a href="team_details.php" class="inline-flex items-center bg-gradient-to-r from-green-500 to-blue-500 hover:from-green-600 hover:to-blue-600 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-200 transform hover:scale-105">
                                <i class="fas fa-arrow-right mr-2"></i>
                                View Team
                            </a>
                        </div>

                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-lg transition-all duration-200">
                            <div class="flex items-center space-x-3 mb-4">
                                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-upload text-white text-sm"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">Project Submission</h3>
                            </div>
                            <p class="text-gray-600 text-sm mb-4">Submit your hackathon project</p>
                            <a href="submit_project.php" class="inline-flex items-center bg-gradient-to-r from-blue-500 to-indigo-500 hover:from-blue-600 hover:to-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-200 transform hover:scale-105">
                                <i class="fas fa-arrow-right mr-2"></i>
                                Submit Project
                            </a>
                        </div>

                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-lg transition-all duration-200">
                            <div class="flex items-center space-x-3 mb-4">
                                <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-question-circle text-white text-sm"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">Need Help?</h3>
                            </div>
                            <p class="text-gray-600 text-sm mb-4">Get support from organizers</p>
                            <a href="support.php" class="inline-flex items-center bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-600 hover:to-pink-600 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-200 transform hover:scale-105">
                                <i class="fas fa-headset mr-2"></i>
                                Contact Support
                            </a>
                        </div>
                    </div>

                    <!-- Navigation Links -->
                    <div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center space-x-3 mb-6">
                            <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-compass text-white text-sm"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-800">Quick Navigation</h3>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <a href="dashboard.php" class="flex items-center p-4 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl hover:from-gray-100 hover:to-gray-200 transition-all duration-200 border border-gray-200">
                                <i class="fas fa-tachometer-alt text-gray-600 mr-3"></i>
                                <span class="text-sm font-semibold text-gray-800">Dashboard</span>
                            </a>
                            <a href="announcements.php" class="flex items-center p-4 bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl hover:from-indigo-100 hover:to-purple-100 transition-all duration-200 border border-indigo-200">
                                <i class="fas fa-bullhorn text-indigo-600 mr-3"></i>
                                <span class="text-sm font-semibold text-indigo-800">All Announcements</span>
                            </a>
                            <a href="rankings.php" class="flex items-center p-4 bg-gradient-to-br from-yellow-50 to-orange-50 rounded-xl hover:from-yellow-100 hover:to-orange-100 transition-all duration-200 border border-yellow-200">
                                <i class="fas fa-trophy text-yellow-600 mr-3"></i>
                                <span class="text-sm font-semibold text-yellow-800">Rankings</span>
                            </a>
                            <a href="mentoring_rounds.php" class="flex items-center p-4 bg-gradient-to-br from-green-50 to-blue-50 rounded-xl hover:from-green-100 hover:to-blue-100 transition-all duration-200 border border-green-200">
                                <i class="fas fa-chalkboard-teacher text-green-600 mr-3"></i>
                                <span class="text-sm font-semibold text-green-800">Mentoring</span>
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <style>
    @media print {
        .no-print {
            display: none !important;
        }
        
        body {
            background: white !important;
        }
        
        .bg-gradient-to-r {
            background: #f8fafc !important;
        }
        
        nav, .mt-8 {
            display: none !important;
        }
    }
    </style>

    <!-- PWA Scripts -->
    <script src="../assets/js/pwa.js"></script>
</body>
</html>