<?php
require_once 'includes/db.php';
require_once 'includes/auth_check.php';
require_once 'includes/utils.php';

checkAuth(['admin', 'mentor', 'participant', 'volunteer']);
$user = getCurrentUser();

$announcement_id = intval($_GET['id'] ?? 0);

if ($announcement_id <= 0) {
    $redirect_url = match($user['role']) {
        'admin' => 'admin/dashboard.php',
        'mentor' => 'mentor/dashboard.php',
        'participant' => 'participant/dashboard.php',
        'volunteer' => 'volunteer/dashboard.php',
        default => 'index.php'
    };
    header('Location: ' . $redirect_url . '?error=' . urlencode('Invalid announcement ID'));
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
    $redirect_url = match($user['role']) {
        'admin' => 'admin/dashboard.php',
        'mentor' => 'mentor/dashboard.php',
        'participant' => 'participant/dashboard.php',
        'volunteer' => 'volunteer/dashboard.php',
        default => 'index.php'
    };
    header('Location: ' . $redirect_url . '?error=' . urlencode('Announcement not found'));
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
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- PWA Configuration -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#4F46E5">
    
    <!-- Meta Tags -->
    <meta name="description" content="<?php echo $page_description; ?>">
    <meta name="keywords" content="hackathon, announcement, <?php echo htmlspecialchars($announcement['title']); ?>">
    
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
                    $dashboard_url = match($user['role']) {
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
                        Announcement Details
                    </h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Welcome, <?php echo htmlspecialchars($user['name']); ?></span>
                    <a href="logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto py-6 px-4">
        <!-- Announcement Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
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
                            <a href="admin/posts.php" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
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
                <a href="support.php" class="inline-flex items-center bg-blue-100 text-blue-800 px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-200 transition-colors">
                    <i class="fas fa-headset mr-2"></i>
                    Contact Support
                </a>
            </div>
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
    }
    </style>

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
        }, 2000);
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
        alert('Could not copy link. Please copy the URL manually.');
    });
}
</script>

<!-- PWA Scripts -->
<script src="assets/js/pwa.js"></script>
</body>
</html>