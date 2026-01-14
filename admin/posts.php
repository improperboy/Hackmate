<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('admin');
$user = getCurrentUser();

// Get counts for sidebar notifications
$pending_teams = $pdo->query("SELECT COUNT(*) FROM teams WHERE status = 'pending'")->fetchColumn();
$open_support_requests = $pdo->query("SELECT COUNT(*) FROM support_messages WHERE status = 'open'")->fetchColumn();
$total_submissions = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();

$message = '';
$error = '';

// Handle Add/Update Post
if ($_POST && isset($_POST['submit_post'])) {
    $post_id = intval($_POST['post_id']);
    $title = sanitize($_POST['title']);
    $content = sanitize($_POST['content']);
    $link_url = !empty($_POST['link_url']) ? sanitize($_POST['link_url']) : null;
    $link_text = !empty($_POST['link_text']) ? sanitize($_POST['link_text']) : null;

    if (empty($title) || empty($content)) {
        $error = 'Title and content are required.';
    } else {
        try {
            if ($post_id > 0) {
                // Update existing post
                $stmt = $pdo->prepare("UPDATE posts SET title = ?, content = ?, link_url = ?, link_text = ? WHERE id = ?");
                if ($stmt->execute([$title, $content, $link_url, $link_text, $post_id])) {
                    $message = 'Announcement updated successfully!';
                } else {
                    $error = 'Failed to update announcement.';
                }
            } else {
                // Add new post
                $stmt = $pdo->prepare("INSERT INTO posts (title, content, link_url, link_text, author_id) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$title, $content, $link_url, $link_text, $user['id']])) {
                    $message = 'Announcement posted successfully!';
                } else {
                    $error = 'Failed to post announcement.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle Delete Post
if ($_POST && isset($_POST['delete_post'])) {
    $post_id = intval($_POST['post_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        if ($stmt->execute([$post_id])) {
            $message = 'Announcement deleted successfully!';
        } else {
            $error = 'Failed to delete announcement.';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Fetch all posts
$posts = $pdo->query("
    SELECT p.*, u.name as author_name 
    FROM posts p 
    JOIN users u ON p.author_id = u.id 
    ORDER BY p.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Announcements - HackMate</title>
    
    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .post-card {
            transition: transform 0.2s ease-in-out;
        }
        .post-card:hover {
            transform: translateY(-2px);
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
                    <h1 class="text-lg font-semibold text-gray-900">Announcements</h1>
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
                                Manage Announcements
                            </h1>
                            <p class="text-gray-600 mt-1">Create and manage hackathon announcements</p>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="max-w-4xl mx-auto">

                <!-- Add/Edit Post Form -->
                <div class="post-card bg-white rounded-xl shadow-sm p-6 border border-gray-100 mb-6">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-plus-circle text-blue-600 mr-2"></i>
                        Create New Announcement
                    </h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="post_id" id="post_id" value="0">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
                    <input type="text" id="title" name="title" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g., Important Update, Submission Deadline Extended">
                </div>
                <div>
                    <label for="content" class="block text-sm font-medium text-gray-700 mb-2">Content *</label>
                    <textarea id="content" name="content" required rows="6"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Write your announcement details here..."></textarea>
                </div>
                
                <!-- Optional Link Section -->
                <div class="border-t pt-4">
                    <h4 class="text-md font-medium text-gray-700 mb-3">
                        <i class="fas fa-link text-blue-500 mr-2"></i>
                        Optional Link (e.g., external resources, forms, documents)
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="link_url" class="block text-sm font-medium text-gray-700 mb-2">Link URL</label>
                            <input type="url" id="link_url" name="link_url"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="https://example.com">
                        </div>
                        <div>
                            <label for="link_text" class="block text-sm font-medium text-gray-700 mb-2">Link Text</label>
                            <input type="text" id="link_text" name="link_text"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="e.g., View Form, Download Document">
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Both URL and text are required if you want to add a link to your announcement.
                    </p>
                </div>
                <div class="flex space-x-4">
                    <button type="submit" name="submit_post" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors">
                        <i class="fas fa-paper-plane mr-2"></i>Post Announcement
                    </button>
                    <button type="button" onclick="resetForm()" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors">
                        <i class="fas fa-undo mr-2"></i>Reset Form
                    </button>
                </div>
            </form>
                </div>

                <!-- Posts List -->
                <div class="post-card bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold">
                            <i class="fas fa-list text-gray-600 mr-2"></i>
                            All Announcements (<?php echo count($posts); ?>)
                        </h3>
                    </div>
            
            <?php if (empty($posts)): ?>
                <div class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-4"></i>
                    <p>No announcements posted yet.</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($posts as $post): ?>
                        <div class="px-6 py-4">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="text-xl font-semibold text-gray-900"><?php echo htmlspecialchars($post['title']); ?></h4>
                                <div class="flex space-x-2">
                                    <button onclick="editPost(<?php echo htmlspecialchars(json_encode($post)); ?>)" class="text-blue-600 hover:text-blue-900 text-sm">
                                        <i class="fas fa-edit mr-1"></i>Edit
                                    </button>
                                    <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                        <button type="submit" name="delete_post" class="text-red-600 hover:text-red-900 text-sm">
                                            <i class="fas fa-trash mr-1"></i>Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <p class="text-sm text-gray-700 mb-3"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                            
                            <?php if (!empty($post['link_url']) && !empty($post['link_text'])): ?>
                                <div class="mb-3">
                                    <a href="<?php echo htmlspecialchars($post['link_url']); ?>" 
                                       target="_blank" 
                                       class="inline-flex items-center px-3 py-2 bg-blue-100 text-blue-800 text-sm font-medium rounded-lg hover:bg-blue-200 transition-colors">
                                        <i class="fas fa-external-link-alt mr-2"></i>
                                        <?php echo htmlspecialchars($post['link_text']); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <div class="flex items-center justify-between text-sm text-gray-500">
                                <div class="flex items-center">
                                    <i class="fas fa-user mr-1"></i>
                                    <span>Posted by <?php echo htmlspecialchars($post['author_name']); ?> on <?php echo formatDateTime($post['created_at']); ?></span>
                                </div>
                                <a href="view_announcement.php?id=<?php echo $post['id']; ?>" 
                                   class="text-blue-600 hover:text-blue-800 font-medium">
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
            </main>
        </div>
    </div>

    <script>
        function editPost(post) {
            document.getElementById('post_id').value = post.id;
            document.getElementById('title').value = post.title;
            document.getElementById('content').value = post.content;
            document.getElementById('link_url').value = post.link_url || '';
            document.getElementById('link_text').value = post.link_text || '';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('post_id').value = '0';
            document.getElementById('title').value = '';
            document.getElementById('content').value = '';
            document.getElementById('link_url').value = '';
            document.getElementById('link_text').value = '';
        }
    </script>
</body>
</html>
