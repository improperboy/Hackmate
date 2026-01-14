<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth(['admin', 'mentor', 'participant', 'volunteer']);
$user = getCurrentUser();

// Get counts for sidebar notifications
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_teams = $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
$pending_teams = $pdo->query("SELECT COUNT(*) FROM teams WHERE status = 'pending'")->fetchColumn();
$total_submissions = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
$open_support_requests = $pdo->query("SELECT COUNT(*) FROM support_messages WHERE status = 'open'")->fetchColumn();

$announcement_id = intval($_GET['id'] ?? 0);

if ($announcement_id <= 0) {
    header('Location: dashboard.php?error=' . urlencode('Invalid announcement ID'));
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
    header('Location: dashboard.php?error=' . urlencode('Announcement not found'));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcement Details - HackMate</title>
    
    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .announcement-card {
            transition: transform 0.2s ease-in-out;
        }
        .announcement-card:hover {
            transform: translateY(-2px);
        }
        
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
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden lg:ml-0">
            <!-- Top Header -->
            <header class="bg-white shadow-sm border-b border-gray-200 lg:hidden">
                <div class="flex items-center justify-between px-4 py-3">
                    <button onclick="toggleSidebar()" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-semibold text-gray-900">Announcement</h1>
                    <div class="w-6"></div>
                </div>
            </header>
            
            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- Page Header -->
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">
                                <i class="fas fa-bullhorn text-blue-600 mr-3"></i>
                                Announcement Details
                            </h1>
                            <p class="text-gray-600 mt-1">View detailed announcement information</p>
                        </div>
                        
                        <!-- Back Button -->
                        <div class="flex items-center space-x-3">
                            <a href="<?php echo $user['role'] === 'admin' ? 'posts.php' : 'dashboard.php'; ?>" 
                               class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to <?php echo $user['role'] === 'admin' ? 'Announcements' : 'Dashboard'; ?>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="max-w-4xl mx-auto">
    <!-- Announcement Card -->
    <div class="announcement-card bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-indigo-50 to-blue-50 px-8 py-6 border-b border-gray-200">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center mb-2">
                        <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center mr-3">
                            <i class="fas fa-bullhorn text-indigo-600"></i>
                        </div>
                        <span class="bg-indigo-100 text-indigo-800 text-xs font-medium px-3 py-1 rounded-full">
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
                <div class="mt-8 p-6 bg-blue-50 border border-blue-200 rounded-xl">
                    <h3 class="text-lg font-semibold text-blue-900 mb-3 flex items-center">
                        <i class="fas fa-link mr-2"></i>
                        Additional Resource
                    </h3>
                    <a href="<?php echo htmlspecialchars($announcement['link_url']); ?>" 
                       target="_blank" 
                       class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-md hover:shadow-lg">
                        <i class="fas fa-external-link-alt mr-3"></i>
                        <?php echo htmlspecialchars($announcement['link_text']); ?>
                    </a>
                    <p class="text-sm text-blue-700 mt-3">
                        <i class="fas fa-info-circle mr-1"></i>
                        This link will open in a new tab
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 px-8 py-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-500">
                    <i class="fas fa-clock mr-1"></i>
                    Published <?php echo timeAgo($announcement['created_at']); ?>
                </div>
                <div class="flex space-x-3">
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="posts.php" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                            <i class="fas fa-edit mr-1"></i>
                            Manage Announcements
                        </a>
                    <?php endif; ?>
                    <button onclick="window.print()" class="text-gray-600 hover:text-gray-800 text-sm font-medium">
                        <i class="fas fa-print mr-1"></i>
                        Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Actions -->
    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
                <i class="fas fa-share-alt text-green-600 mr-2"></i>
                Share This Announcement
            </h3>
            <p class="text-gray-600 text-sm mb-4">Share this announcement with your team members</p>
            <div class="flex space-x-3">
                <button onclick="copyToClipboard()" class="flex-1 bg-green-100 text-green-800 px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-200 transition-colors">
                    <i class="fas fa-copy mr-2"></i>
                    Copy Link
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
                <i class="fas fa-question-circle text-blue-600 mr-2"></i>
                Need Help?
            </h3>
            <p class="text-gray-600 text-sm mb-4">Have questions about this announcement?</p>
            <a href="../support.php" class="inline-flex items-center bg-blue-100 text-blue-800 px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-200 transition-colors">
                <i class="fas fa-headset mr-2"></i>
                Contact Support
            </a>
        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        function copyToClipboard() {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(function() {
                // Show success message
                const button = event.target.closest('button');
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check mr-2"></i>Copied!';
                button.classList.add('bg-green-200');
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.classList.remove('bg-green-200');
                }, 2000);
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
                alert('Could not copy link. Please copy the URL manually.');
            });
        }
    </script>
</body>
</html>