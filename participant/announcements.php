<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth(['participant']);
$user = getCurrentUser();

// Pagination
$page = intval($_GET['page'] ?? 1);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count
$total_announcements = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
$total_pages = ceil($total_announcements / $per_page);

// Fetch announcements with pagination
$stmt = $pdo->prepare("
    SELECT p.*, u.name as author_name, u.role as author_role
    FROM posts p 
    JOIN users u ON p.author_id = u.id 
    ORDER BY p.created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$per_page, $offset]);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Announcements';
$page_description = 'View all announcements and updates for participants';
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
    <meta name="keywords" content="hackathon, announcements, participant, updates">
    
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
                    <h1 class="text-lg font-semibold text-gray-800">Announcements</h1>
                    <div class="w-6"></div> <!-- Spacer for centering -->
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto">
                <div class="max-w-5xl mx-auto py-6 px-4 lg:px-8">
                    <!-- Page Header -->
                    <div class="mb-6">
                        <div class="flex items-center space-x-3 mb-2">
                            <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-xl flex items-center justify-center">
                                <i class="fas fa-bullhorn text-white text-lg"></i>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Announcements</h1>
                                <p class="text-gray-600">Stay updated with important hackathon information</p>
                            </div>
                        </div>
                    </div>
                    <!-- Stats Overview -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-chart-bar text-white text-sm"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900">Overview</h3>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 p-6 rounded-xl border border-indigo-200">
                                <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-bullhorn text-white text-lg"></i>
                                </div>
                                <div class="text-2xl font-bold text-indigo-700 mb-2 text-center"><?php echo $total_announcements; ?></div>
                                <div class="text-sm text-indigo-600 font-medium text-center">Total Announcements</div>
                            </div>
                            <div class="bg-gradient-to-br from-green-50 to-green-100 p-6 rounded-xl border border-green-200">
                                <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 rounded-xl flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-clock text-white text-lg"></i>
                                </div>
                                <div class="text-2xl font-bold text-green-700 mb-2 text-center"><?php echo $page; ?></div>
                                <div class="text-sm text-green-600 font-medium text-center">Current Page</div>
                            </div>
                            <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-6 rounded-xl border border-purple-200">
                                <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-file-alt text-white text-lg"></i>
                                </div>
                                <div class="text-2xl font-bold text-purple-700 mb-2 text-center"><?php echo $total_pages; ?></div>
                                <div class="text-sm text-purple-600 font-medium text-center">Total Pages</div>
                            </div>
                        </div>
                    </div>

                    <!-- Announcements List -->
                    <?php if (empty($announcements)): ?>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-bullhorn text-gray-400 text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-2">No Announcements Yet</h3>
                            <p class="text-gray-500 mb-6">Check back later for updates and important information.</p>
                            <a href="dashboard.php" class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Dashboard
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-6">
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-lg hover:border-indigo-200 transition-all duration-200">
                                    <!-- Announcement Header -->
                                    <div class="bg-gradient-to-r from-indigo-50 to-purple-50 px-6 py-5 border-b border-gray-200">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center mb-3">
                                                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-lg flex items-center justify-center mr-3">
                                                        <i class="fas fa-bullhorn text-white text-sm"></i>
                                                    </div>
                                                    <h3 class="text-xl font-bold text-gray-900 mr-3">
                                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                                    </h3>
                                                    <?php if (!empty($announcement['link_url']) && !empty($announcement['link_text'])): ?>
                                                        <span class="bg-gradient-to-r from-blue-500 to-indigo-500 text-white text-xs font-bold px-3 py-1 rounded-full">
                                                            <i class="fas fa-link mr-1"></i>
                                                            Has Link
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                                                    <div class="flex items-center">
                                                        <div class="w-6 h-6 bg-gradient-to-br from-green-500 to-blue-500 rounded-full flex items-center justify-center mr-2">
                                                            <i class="fas fa-user text-white text-xs"></i>
                                                        </div>
                                                        <span class="font-medium"><?php echo htmlspecialchars($announcement['author_name']); ?></span>
                                                        <span class="ml-2 bg-white bg-opacity-80 text-gray-700 text-xs px-2 py-1 rounded-full border">
                                                            <?php echo ucfirst($announcement['author_role']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center">
                                                        <div class="w-6 h-6 bg-gradient-to-br from-orange-500 to-red-500 rounded-full flex items-center justify-center mr-2">
                                                            <i class="fas fa-calendar text-white text-xs"></i>
                                                        </div>
                                                        <span><?php echo formatDateTime($announcement['created_at']); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Announcement Content -->
                                    <div class="px-6 py-6">
                                        <div class="text-gray-800 leading-relaxed mb-6 whitespace-pre-line">
                                            <?php
                                            $content = htmlspecialchars($announcement['content']);
                                            if (strlen($content) > 300) {
                                                echo substr($content, 0, 300) . '...';
                                            } else {
                                                echo $content;
                                            }
                                            ?>
                                        </div>

                                        <!-- Quick Link Preview -->
                                        <?php if (!empty($announcement['link_url']) && !empty($announcement['link_text'])): ?>
                                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-4 mb-6">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-lg flex items-center justify-center mr-3">
                                                        <i class="fas fa-link text-white text-sm"></i>
                                                    </div>
                                                    <div>
                                                        <span class="font-semibold text-blue-800">Additional Resource:</span>
                                                        <span class="ml-2 text-blue-700"><?php echo htmlspecialchars($announcement['link_text']); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center text-xs text-gray-500">
                                                <div class="w-5 h-5 bg-gray-200 rounded-full flex items-center justify-center mr-2">
                                                    <i class="fas fa-clock text-gray-400 text-xs"></i>
                                                </div>
                                                <span>Published <?php echo timeAgo($announcement['created_at']); ?></span>
                                            </div>
                                            <a href="view_announcement.php?id=<?php echo $announcement['id']; ?>"
                                                class="bg-gradient-to-r from-indigo-500 to-purple-500 hover:from-indigo-600 hover:to-purple-600 text-white px-6 py-2 rounded-xl text-sm font-semibold transition-all duration-200 transform hover:scale-105 shadow-md hover:shadow-lg">
                                                <i class="fas fa-eye mr-2"></i>
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="mt-8 flex justify-center">
                                <nav class="flex items-center space-x-2">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo $page - 1; ?>"
                                            class="px-4 py-2 bg-white border border-gray-300 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50 hover:border-indigo-300 transition-all duration-200">
                                            <i class="fas fa-chevron-left mr-2"></i>
                                            Previous
                                        </a>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <a href="?page=<?php echo $i; ?>"
                                            class="px-4 py-2 <?php echo $i === $page ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-lg' : 'bg-white text-gray-700 hover:bg-gray-50 hover:border-indigo-300'; ?> border border-gray-300 rounded-xl text-sm font-semibold transition-all duration-200">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?php echo $page + 1; ?>"
                                            class="px-4 py-2 bg-white border border-gray-300 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50 hover:border-indigo-300 transition-all duration-200">
                                            Next
                                            <i class="fas fa-chevron-right ml-2"></i>
                                        </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- PWA Scripts -->
    <script src="../assets/js/pwa.js"></script>
</body>
</html>