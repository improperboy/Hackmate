<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('admin');
$user = getCurrentUser();

// Get counts for sidebar notifications
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_teams = $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
$pending_teams = $pdo->query("SELECT COUNT(*) FROM teams WHERE status = 'pending'")->fetchColumn();
$total_submissions = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
$open_support_requests = $pdo->query("SELECT COUNT(*) FROM support_messages WHERE status = 'open'")->fetchColumn();

$message = '';
$error = '';

// Handle user deletion
if ($_POST && isset($_POST['delete_user'])) {
    $user_id = intval($_POST['user_id']);
    if ($user_id === $user['id']) {
        $error = 'You cannot delete your own admin account.';
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $message = 'User deleted successfully!';
            } else {
                $error = 'Failed to delete user.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle user role update
if ($_POST && isset($_POST['update_role'])) {
    $user_id = intval($_POST['user_id']);
    $new_role = sanitize($_POST['new_role']);

    if ($user_id === $user['id'] && $new_role !== 'admin') {
        $error = 'You cannot change your own role from admin.';
    } elseif (!in_array($new_role, ['admin', 'mentor', 'participant', 'volunteer'])) {
        $error = 'Invalid role selected.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            if ($stmt->execute([$new_role, $user_id])) {
                $message = 'User role updated successfully!';
            } else {
                $error = 'Failed to update user role.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

// Fetch all users with search and filter
$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($role_filter) {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                    <h1 class="text-lg font-semibold text-gray-900">Manage Users</h1>
                    <div class="w-6"></div>
                </div>
            </header>
            
            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-6">
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

        <!-- Header with Add User Button -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">
                <i class="fas fa-users-cog text-blue-600"></i>
                User Management
            </h2>
            <a href="add_user.php" 
               class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition-colors shadow-md">
                <i class="fas fa-user-plus mr-2"></i>
                Add New User
            </a>
        </div>

        <!-- Search and Filter -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4">
                <i class="fas fa-search text-blue-600"></i>
                Search & Filter Users
            </h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search Users</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by name or email..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                    <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="mentor" <?php echo $role_filter == 'mentor' ? 'selected' : ''; ?>>Mentor</option>
                        <option value="participant" <?php echo $role_filter == 'participant' ? 'selected' : ''; ?>>Participant</option>
                        <option value="volunteer" <?php echo $role_filter == 'volunteer' ? 'selected' : ''; ?>>Volunteer</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition-colors mr-2">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                    <a href="manage_users.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md transition-colors">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Users List -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold">
                    <i class="fas fa-list text-gray-600"></i>
                    All Users (<?php echo count($users_data); ?>)
                </h3>
            </div>
            
            <?php if (empty($users_data)): ?>
                <div class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-user-slash text-4xl mb-4"></i>
                    <p>No users found matching your criteria.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($users_data as $u): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($u['name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($u['email']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php 
                                                if ($u['role'] == 'admin') echo 'bg-red-100 text-red-800';
                                                elseif ($u['role'] == 'mentor') echo 'bg-green-100 text-green-800';
                                                elseif ($u['role'] == 'participant') echo 'bg-blue-100 text-blue-800';
                                                else echo 'bg-purple-100 text-purple-800';
                                            ?>">
                                            <?php echo htmlspecialchars(ucfirst($u['role'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo formatDateTime($u['created_at']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <form method="POST" class="inline-block mr-2">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <select name="new_role" onchange="this.form.submit()" 
                                                    class="px-2 py-1 border border-gray-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <option value="admin" <?php echo $u['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                <option value="mentor" <?php echo $u['role'] == 'mentor' ? 'selected' : ''; ?>>Mentor</option>
                                                <option value="participant" <?php echo $u['role'] == 'participant' ? 'selected' : ''; ?>>Participant</option>
                                                <option value="volunteer" <?php echo $u['role'] == 'volunteer' ? 'selected' : ''; ?>>Volunteer</option>
                                            </select>
                                            <input type="hidden" name="update_role" value="1">
                                        </form>
                                        <?php if ($u['id'] !== $user['id']): // Prevent admin from deleting self ?>
                                            <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <input type="hidden" name="delete_user" value="1">
                                                <button type="submit" class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
