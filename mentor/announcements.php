<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth(['mentor']);
$user = getCurrentUser();

// Get all posts
$stmt = $pdo->query("
    SELECT p.*, u.name as author_name, u.role as author_role
    FROM posts p 
    JOIN users u ON p.author_id = u.id 
    ORDER BY p.is_pinned DESC, p.created_at DESC
");
$posts = $stmt->fetchAll();

$page_title = 'Announcements';
$page_description = 'Stay updated with the latest hackathon news and announcements';
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
    <meta name="keywords" content="hackathon, announcements, mentor, updates, news">

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

        .announcement-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .announcement-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .announcement-item {
            transition: all 0.2s ease;
        }

        .announcement-item:hover {
            transform: translateX(4px);
        }

        .pinned-announcement {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-left: 4px solid #f59e0b;
        }

        .regular-announcement {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-left: 4px solid #0ea5e9;
        }

        .announcement-content {
            line-height: 1.7;
        }

        .announcement-meta {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
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
                            <div class="w-8 h-8 bg-gradient-to-br from-orange-500 to-red-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-bullhorn text-white text-sm"></i>
                            </div>
                            <div>
                                <h1 class="text-xl font-bold text-gray-900">Announcements</h1>
                                <p class="text-sm text-gray-500 hidden sm:block">Stay updated with latest news</p>
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
                <div class="bg-gradient-to-r from-orange-600 to-red-600 rounded-2xl p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-bold mb-2">Announcements</h2>
                            <p class="text-orange-100 mb-3">Stay updated with the latest hackathon news and updates</p>

                            <div class="flex items-center text-orange-100">
                                <i class="fas fa-bell mr-2"></i>
                                <span class="text-sm"><?php echo count($posts); ?> announcement<?php echo count($posts) != 1 ? 's' : ''; ?> available</span>
                            </div>
                        </div>
                        <div class="hidden md:block">
                            <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center backdrop-blur-sm">
                                <i class="fas fa-bullhorn text-3xl text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Announcements List -->
            <div class="announcement-card rounded-2xl shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">
                            <i class="fas fa-list text-orange-500 mr-2"></i>
                            All Announcements (<?php echo count($posts); ?>)
                        </h3>
                        <?php if (count($posts) > 0): ?>
                            <div class="flex items-center text-sm text-gray-500">
                                <i class="fas fa-clock mr-1"></i>
                                <span>Latest updates</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (empty($posts)): ?>
                    <div class="px-6 py-16 text-center">
                        <i class="fas fa-bullhorn text-gray-300 text-6xl mb-6"></i>
                        <h4 class="text-xl font-semibold text-gray-900 mb-3">No Announcements Yet</h4>
                        <p class="text-gray-500 mb-6">There are no announcements posted at this time.</p>
                        <a href="dashboard.php" class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-tachometer-alt mr-2"></i>
                            Back to Dashboard
                        </a>
                    </div>
                <?php else: ?>
                    <div class="p-6">
                        <div class="space-y-6">
                            <?php
                            // Separate pinned and regular announcements
                            $pinned_posts = array_filter($posts, function ($post) {
                                return $post['is_pinned'];
                            });
                            $regular_posts = array_filter($posts, function ($post) {
                                return !$post['is_pinned'];
                            });
                            ?>

                            <?php if (!empty($pinned_posts)): ?>
                                <!-- Pinned Announcements -->
                                <div class="mb-8">
                                    <h4 class="text-sm font-semibold text-gray-700 mb-4 flex items-center">
                                        <i class="fas fa-thumbtack text-orange-500 mr-2"></i>
                                        Pinned Announcements
                                    </h4>
                                    <div class="space-y-4">
                                        <?php foreach ($pinned_posts as $post): ?>
                                            <div class="announcement-item pinned-announcement rounded-xl p-6">
                                                <div class="flex items-start justify-between mb-4">
                                                    <div class="flex-1">
                                                        <div class="flex items-center mb-2">
                                                            <i class="fas fa-thumbtack text-orange-600 mr-2"></i>
                                                            <h4 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($post['title']); ?></h4>
                                                        </div>

                                                        <?php if ($post['link_url']): ?>
                                                            <div class="mb-3">
                                                                <a href="<?php echo htmlspecialchars($post['link_url']); ?>"
                                                                    target="_blank"
                                                                    class="inline-flex items-center text-orange-700 hover:text-orange-800 font-medium">
                                                                    <i class="fas fa-external-link-alt mr-1"></i>
                                                                    <?php echo htmlspecialchars($post['link_text'] ?: 'View Link'); ?>
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="announcement-meta rounded-lg px-3 py-2 ml-4">
                                                        <div class="text-sm text-gray-600 text-center">
                                                            <i class="fas fa-user mb-1"></i>
                                                            <p class="font-medium"><?php echo htmlspecialchars($post['author_name']); ?></p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="announcement-content mb-4">
                                                    <p class="text-gray-800 leading-relaxed"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                                                </div>

                                                <div class="flex items-center justify-between text-sm text-gray-600">
                                                    <div class="flex items-center">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        <span>Posted on <?php echo date('M j, Y g:i A', strtotime($post['created_at'])); ?></span>
                                                    </div>
                                                    <div class="flex items-center space-x-2">
                                                        <a href="view_announcement.php?id=<?php echo $post['id']; ?>" 
                                                           class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 hover:bg-green-200 transition-colors">
                                                            <i class="fas fa-eye mr-1"></i>
                                                            View Details
                                                        </a>
                                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-orange-100 text-orange-800">
                                                            <i class="fas fa-star mr-1"></i>
                                                            Pinned
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($regular_posts)): ?>
                                <!-- Regular Announcements -->
                                <?php if (!empty($pinned_posts)): ?>
                                    <div class="mb-6">
                                        <h4 class="text-sm font-semibold text-gray-700 mb-4 flex items-center">
                                            <i class="fas fa-list text-blue-500 mr-2"></i>
                                            Recent Announcements
                                        </h4>
                                    </div>
                                <?php endif; ?>

                                <div class="space-y-4">
                                    <?php foreach ($regular_posts as $post): ?>
                                        <div class="announcement-item regular-announcement rounded-xl p-6">
                                            <div class="flex items-start justify-between mb-4">
                                                <div class="flex-1">
                                                    <h4 class="text-xl font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($post['title']); ?></h4>

                                                    <?php if ($post['link_url']): ?>
                                                        <div class="mb-3">
                                                            <a href="<?php echo htmlspecialchars($post['link_url']); ?>"
                                                                target="_blank"
                                                                class="inline-flex items-center text-blue-700 hover:text-blue-800 font-medium">
                                                                <i class="fas fa-external-link-alt mr-1"></i>
                                                                <?php echo htmlspecialchars($post['link_text'] ?: 'View Link'); ?>
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="announcement-meta rounded-lg px-3 py-2 ml-4">
                                                    <div class="text-sm text-gray-600 text-center">
                                                        <i class="fas fa-user mb-1"></i>
                                                        <p class="font-medium"><?php echo htmlspecialchars($post['author_name']); ?></p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="announcement-content mb-4">
                                                <p class="text-gray-800 leading-relaxed"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                                            </div>

                                            <div class="flex items-center justify-between text-sm text-gray-600">
                                                <div class="flex items-center">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    <span>Posted on <?php echo date('M j, Y g:i A', strtotime($post['created_at'])); ?></span>
                                                    <?php if ($post['updated_at'] && $post['updated_at'] != $post['created_at']): ?>
                                                        <span class="mx-2">•</span>
                                                        <i class="fas fa-edit mr-1"></i>
                                                        <span>Updated <?php echo date('M j, Y g:i A', strtotime($post['updated_at'])); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <a href="view_announcement.php?id=<?php echo $post['id']; ?>" 
                                                   class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 hover:bg-green-200 transition-colors">
                                                    <i class="fas fa-eye mr-1"></i>
                                                    View Details
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
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

        // Enhanced announcement interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add smooth scrolling to external links
            document.querySelectorAll('a[target="_blank"]').forEach(link => {
                link.addEventListener('click', function(e) {
                    // Add a small delay to show the click effect
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Opening...';

                    setTimeout(() => {
                        this.innerHTML = originalText;
                    }, 1000);
                });
            });

            // Add reading time estimation
            document.querySelectorAll('.announcement-content').forEach(content => {
                const text = content.textContent || content.innerText;
                const words = text.trim().split(/\s+/).length;
                const readingTime = Math.ceil(words / 200); // Average reading speed: 200 words per minute

                if (readingTime > 1) {
                    const timeIndicator = document.createElement('span');
                    timeIndicator.className = 'text-xs text-gray-500 ml-2';
                    timeIndicator.innerHTML = `<i class="fas fa-book-open mr-1"></i>${readingTime} min read`;

                    const timeContainer = content.parentNode.querySelector('.flex.items-center');
                    if (timeContainer) {
                        timeContainer.appendChild(document.createTextNode(' • '));
                        timeContainer.appendChild(timeIndicator);
                    }
                }
            });

            // Add animation to announcement cards on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Initially hide announcement items and observe them
            document.querySelectorAll('.announcement-item').forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                item.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
                observer.observe(item);
            });
        });
    </script>
</body>

</html>