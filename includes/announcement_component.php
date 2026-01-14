<?php
// Announcement Component for Dashboards
// Usage: include this file in any dashboard to show recent announcements

if (!isset($pdo)) {
    require_once 'db.php';
}

// Detect current directory to set correct paths
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$is_admin = ($current_dir === 'admin');
$path_prefix = $is_admin ? '' : '../';

// Fetch recent announcements (limit to 5 most recent)
$recent_announcements = $pdo->query("
    SELECT p.*, u.name as author_name, u.role as author_role
    FROM posts p 
    JOIN users u ON p.author_id = u.id 
    ORDER BY p.created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Function to truncate text
function truncateText($text, $length = 150) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}
?>

<!-- Announcements Section -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-blue-50">
        <h3 class="text-xl font-semibold text-gray-800 flex items-center">
            <i class="fas fa-bullhorn text-indigo-600 mr-3"></i>
            Recent Announcements
        </h3>
        <p class="text-sm text-gray-600 mt-1">Stay updated with the latest information</p>
    </div>
    
    <?php if (empty($recent_announcements)): ?>
        <div class="p-8 text-center">
            <div class="mx-auto w-16 h-16 flex items-center justify-center bg-gray-100 rounded-full mb-4">
                <i class="fas fa-bullhorn text-gray-400 text-2xl"></i>
            </div>
            <p class="text-gray-500 font-medium">No announcements yet</p>
            <p class="text-sm text-gray-400 mt-2">Check back later for updates</p>
        </div>
    <?php else: ?>
        <div class="divide-y divide-gray-200">
            <?php foreach ($recent_announcements as $announcement): ?>
                <div class="p-6 hover:bg-gray-50 transition-colors">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <h4 class="text-lg font-semibold text-gray-900 mr-3">
                                    <?php echo htmlspecialchars($announcement['title']); ?>
                                </h4>
                                <?php if (!empty($announcement['link_url']) && !empty($announcement['link_text'])): ?>
                                    <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-1 rounded-full">
                                        <i class="fas fa-link mr-1"></i>
                                        Has Link
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <p class="text-gray-700 text-sm mb-3 leading-relaxed">
                                <?php echo nl2br(htmlspecialchars(truncateText($announcement['content']))); ?>
                            </p>
                            
                            <div class="flex items-center justify-between">
                                <div class="flex items-center text-xs text-gray-500">
                                    <div class="flex items-center mr-4">
                                        <i class="fas fa-user mr-1"></i>
                                        <span><?php echo htmlspecialchars($announcement['author_name']); ?></span>
                                        <span class="ml-1 bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">
                                            <?php echo ucfirst($announcement['author_role']); ?>
                                        </span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-clock mr-1"></i>
                                        <span><?php echo timeAgo($announcement['created_at']); ?></span>
                                    </div>
                                </div>
                                
                                <a href="<?php echo $path_prefix; ?>view_announcement.php?id=<?php echo $announcement['id']; ?>" 
                                   class="inline-flex items-center text-indigo-600 hover:text-indigo-800 text-sm font-medium transition-colors">
                                    <i class="fas fa-eye mr-1"></i>
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- View All Link -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
            <div class="text-center">
                <?php if ($is_admin): ?>
                    <a href="posts.php" class="inline-flex items-center text-indigo-600 hover:text-indigo-800 font-medium transition-colors">
                        <i class="fas fa-cog mr-2"></i>
                        Manage All Announcements
                    </a>
                <?php else: ?>
                    <a href="<?php echo $path_prefix; ?>announcements.php" class="inline-flex items-center text-indigo-600 hover:text-indigo-800 font-medium transition-colors">
                        <i class="fas fa-list mr-2"></i>
                        View All Announcements
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.announcement-card {
    transition: all 0.3s ease;
}

.announcement-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}
</style>