<?php
require_once 'includes/db.php';
require_once 'includes/auth_check.php';
require_once 'includes/utils.php';

checkAuth(['admin', 'mentor', 'participant', 'volunteer']);
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

$page_title = 'All Announcements';
$page_description = 'View all announcements and updates';
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
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- PWA Configuration -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#4F46E5">

    <!-- Meta Tags -->
    <meta name="description" content="<?php echo $page_description; ?>">
    <meta name="keywords" content="hackathon, announcements, updates, news">

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/icon-96x96.png">
</head>

<body class="bg-gray-100 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-4">
                    <?php
                    $dashboard_url = match ($user['role']) {
                        'admin' => 'admin/dashboard.php',
                        'mentor' => 'mentor/dashboard.php',
                        'participant' => 'participant/dashboard.php',
                        'volunteer' => 'volunteer/dashboard.php',
                        default => 'index.php'
                    };
                    ?>
                    <a href="<?php echo $dashboard_url; ?>" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-arrow-left"></i>
                        Back to Dashboard
                    </a>
                    <h1 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-bullhorn text-indigo-600 mr-2"></i>
                        All Announcements
                    </h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Welcome, <?php echo htmlspecialchars($user['name']); ?></span>
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="admin/posts.php" class="text-indigo-600 hover:text-indigo-800">
                            <i class="fas fa-cog"></i>
                            Manage
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto py-6 px-4">
        <!-- Header -->
        <div class="mb-8">
            <div class="bg-gradient-to-r from-indigo-50 to-blue-50 rounded-2xl p-6 border border-indigo-100">
                <h2 class="text-2xl font-bold text-gray-900 mb-2">
                    <i class="fas fa-bullhorn text-indigo-600 mr-3"></i>
                    Announcements & Updates
                </h2>
                <p class="text-gray-600">
                    Stay informed with the latest news, updates, and important information from the hackathon organizers.
                </p>
                <div class="mt-4 flex items-center text-sm text-gray-500">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span>Total: <?php echo $total_announcements; ?> announcement<?php echo $total_announcements != 1 ? 's' : ''; ?></span>
                </div>
            </div>
        </div>

        <!-- Announcements List -->
        <?php if (empty($announcements)): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
                <div class="mx-auto w-20 h-20 flex items-center justify-center bg-gray-100 rounded-full mb-6">
                    <i class="fas fa-bullhorn text-gray-400 text-3xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No Announcements Yet</h3>
                <p class="text-gray-500">Check back later for updates and important information.</p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($announcements as $announcement): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">
                        <!-- Announcement Header -->
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <h3 class="text-xl font-semibold text-gray-900 mr-3">
                                            <?php echo htmlspecialchars($announcement['title']); ?>
                                        </h3>
                                        <?php if (!empty($announcement['link_url']) && !empty($announcement['link_text'])): ?>
                                            <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-1 rounded-full">
                                                <i class="fas fa-link mr-1"></i>
                                                Has Link
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center text-sm text-gray-500">
                                        <div class="flex items-center mr-6">
                                            <i class="fas fa-user mr-2"></i>
                                            <span><?php echo htmlspecialchars($announcement['author_name']); ?></span>
                                            <span class="ml-2 bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full">
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

                        <!-- Announcement Content -->
                        <div class="px-6 py-4">
                            <div class="text-gray-800 leading-relaxed mb-4 whitespace-pre-line">
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
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                                    <div class="flex items-center text-sm text-blue-800">
                                        <i class="fas fa-link mr-2"></i>
                                        <span class="font-medium">Additional Resource:</span>
                                        <span class="ml-2"><?php echo htmlspecialchars($announcement['link_text']); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="flex items-center justify-between">
                                <div class="text-xs text-gray-500">
                                    <i class="fas fa-clock mr-1"></i>
                                    Published <?php echo timeAgo($announcement['created_at']); ?>
                                </div>
                                <a href="view_announcement.php?id=<?php echo $announcement['id']; ?>"
                                    class="inline-flex items-center bg-indigo-100 text-indigo-800 px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-200 transition-colors">
                                    <i class="fas fa-eye mr-2"></i>
                                    View Full Details
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
                                class="px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-chevron-left mr-1"></i>
                                Previous
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>"
                                class="px-3 py-2 <?php echo $i === $page ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border border-gray-300 rounded-lg text-sm font-medium">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>"
                                class="px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Next
                                <i class="fas fa-chevron-right ml-1"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- PWA Scripts -->
    <script src="assets/js/pwa.js"></script>
</body>

</html>