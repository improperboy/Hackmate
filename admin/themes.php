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

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = sanitize($_POST['name']);
                $description = sanitize($_POST['description']);
                $color_code = sanitize($_POST['color_code']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;

                if (empty($name) || empty($description) || empty($color_code)) {
                    $error = 'Name, description, and color are required.';
                } else {
                    // Check if theme name already exists
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM themes WHERE name = ?");
                    $stmt->execute([$name]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'Theme name already exists.';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO themes (name, description, color_code, is_active) VALUES (?, ?, ?, ?)");
                        if ($stmt->execute([$name, $description, $color_code, $is_active])) {
                            $message = 'Theme added successfully.';
                        } else {
                            $error = 'Failed to add theme.';
                        }
                    }
                }
                break;

            case 'edit':
                $id = (int)$_POST['id'];
                $name = sanitize($_POST['name']);
                $description = sanitize($_POST['description']);
                $color_code = sanitize($_POST['color_code']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;

                if (empty($name) || empty($description) || empty($color_code)) {
                    $error = 'Name, description, and color are required.';
                } else {
                    // Check if theme name already exists (excluding current theme)
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM themes WHERE name = ? AND id != ?");
                    $stmt->execute([$name, $id]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'Theme name already exists.';
                    } else {
                        $stmt = $pdo->prepare("UPDATE themes SET name = ?, description = ?, color_code = ?, is_active = ? WHERE id = ?");
                        if ($stmt->execute([$name, $description, $color_code, $is_active, $id])) {
                            $message = 'Theme updated successfully.';
                        } else {
                            $error = 'Failed to update theme.';
                        }
                    }
                }
                break;

            case 'toggle':
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("UPDATE themes SET is_active = NOT is_active WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $message = 'Theme status updated successfully.';
                } else {
                    $error = 'Failed to update theme status.';
                }
                break;
        }
    }
}

// Get all themes with statistics
$stmt = $pdo->prepare("
    SELECT 
        th.*,
        COUNT(t.id) as team_count,
        COUNT(CASE WHEN t.status = 'approved' THEN 1 END) as approved_teams,
        COUNT(CASE WHEN t.status = 'pending' THEN 1 END) as pending_teams
    FROM themes th
    LEFT JOIN teams t ON th.id = t.theme_id
    GROUP BY th.id, th.name, th.description, th.color_code, th.is_active, th.created_at
    ORDER BY th.name ASC
");
$stmt->execute();
$themes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme Management - HackMate</title>
    
    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .theme-card {
            transition: transform 0.2s ease-in-out;
        }
        .theme-card:hover {
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
                    <h1 class="text-lg font-semibold text-gray-900">Themes</h1>
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
                                <i class="fas fa-palette text-blue-600 mr-3"></i>
                                Theme Management
                            </h1>
                            <p class="text-gray-600 mt-1">Manage hackathon themes and categories</p>
                        </div>
                    </div>
                </div>

                <div class="max-w-7xl mx-auto">
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

                <!-- Add New Theme -->
                <div class="theme-card bg-white rounded-xl shadow-sm p-6 border border-gray-100 mb-6">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-plus text-green-600 mr-2"></i>
                        Add New Theme
                    </h3>
            
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <input type="hidden" name="action" value="add">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Theme Name *</label>
                    <input type="text" name="name" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g., Education">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Color Code *</label>
                    <input type="color" name="color_code" value="#3B82F6" required 
                           class="w-full h-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                    <input type="text" name="description" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Brief description of the theme">
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="is_active" id="is_active_new" checked 
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="is_active_new" class="ml-2 block text-sm text-gray-700">Active</label>
                </div>

                <div class="flex items-end">
                    <button type="submit" 
                            class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>
                        Add Theme
                    </button>
                </div>
            </form>
                </div>

                <!-- Themes List -->
                <div class="theme-card bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold">
                            <i class="fas fa-list text-blue-600 mr-2"></i>
                            All Themes (<?php echo count($themes); ?>)
                        </h3>
                    </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Theme</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teams</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($themes as $theme): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-4 h-4 rounded-full mr-3" style="background-color: <?php echo $theme['color_code']; ?>"></div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($theme['name']); ?></div>
                                            <div class="text-sm text-gray-500">Created: <?php echo date('M j, Y', strtotime($theme['created_at'])); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($theme['description']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <span class="font-medium"><?php echo $theme['team_count']; ?></span> total
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo $theme['approved_teams']; ?> approved, <?php echo $theme['pending_teams']; ?> pending
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($theme['is_active']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            <i class="fas fa-times-circle mr-1"></i>
                                            Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <button onclick="editTheme(<?php echo htmlspecialchars(json_encode($theme)); ?>)" 
                                            class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-edit"></i>
                                        Edit
                                    </button>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?php echo $theme['id']; ?>">
                                        <button type="submit" 
                                                class="<?php echo $theme['is_active'] ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'; ?>"
                                                onclick="return confirm('Are you sure you want to <?php echo $theme['is_active'] ? 'deactivate' : 'activate'; ?> this theme?')">
                                            <i class="fas fa-<?php echo $theme['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                            <?php echo $theme['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit Theme Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold">Edit Theme</h3>
                </div>
                
                <form method="POST" id="editForm" class="p-6 space-y-4">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Theme Name *</label>
                        <input type="text" name="name" id="edit_name" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Color Code *</label>
                        <input type="color" name="color_code" id="edit_color_code" required 
                               class="w-full h-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                        <textarea name="description" id="edit_description" required rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" name="is_active" id="edit_is_active" 
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="edit_is_active" class="ml-2 block text-sm text-gray-700">Active</label>
                    </div>

                    <div class="flex space-x-3 pt-4">
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md transition-colors">
                            <i class="fas fa-save mr-2"></i>
                            Update Theme
                        </button>
                        <button type="button" onclick="closeEditModal()" 
                                class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-md transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editTheme(theme) {
            document.getElementById('edit_id').value = theme.id;
            document.getElementById('edit_name').value = theme.name;
            document.getElementById('edit_description').value = theme.description;
            document.getElementById('edit_color_code').value = theme.color_code;
            document.getElementById('edit_is_active').checked = theme.is_active == 1;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>