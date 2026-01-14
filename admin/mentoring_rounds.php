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

// Handle Add/Update Round
if ($_POST && isset($_POST['submit_round'])) {
    $round_id = intval($_POST['round_id']);
    $round_name = sanitize($_POST['round_name']);
    $start_time = sanitize($_POST['start_time']);
    $end_time = sanitize($_POST['end_time']);
    $max_score = intval($_POST['max_score']);
    $description = sanitize($_POST['description']);

    if (empty($round_name) || empty($start_time) || empty($end_time) || $max_score <= 0) {
        $error = 'All fields are required and max score must be positive.';
    } elseif (new DateTime($start_time) >= new DateTime($end_time)) {
        $error = 'End time must be after start time.';
    } else {
        try {
            if ($round_id > 0) {
                // Update existing round
                $stmt = $pdo->prepare("UPDATE mentoring_rounds SET round_name = ?, start_time = ?, end_time = ?, max_score = ?, description = ? WHERE id = ?");
                if ($stmt->execute([$round_name, $start_time, $end_time, $max_score, $description, $round_id])) {
                    $message = 'Mentoring round updated successfully!';
                } else {
                    $error = 'Failed to update mentoring round.';
                }
            } else {
                // Add new round
                $stmt = $pdo->prepare("INSERT INTO mentoring_rounds (round_name, start_time, end_time, max_score, description) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$round_name, $start_time, $end_time, $max_score, $description])) {
                    $message = 'Mentoring round added successfully!';
                } else {
                    $error = 'Failed to add mentoring round.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle Delete Round
if ($_POST && isset($_POST['delete_round'])) {
    $round_id = intval($_POST['round_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM mentoring_rounds WHERE id = ?");
        if ($stmt->execute([$round_id])) {
            $message = 'Mentoring round deleted successfully!';
        } else {
            $error = 'Failed to delete mentoring round.';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Fetch all mentoring rounds
$rounds = $pdo->query("SELECT * FROM mentoring_rounds ORDER BY start_time DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentoring Rounds - HackMate</title>
    
    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .round-card {
            transition: transform 0.2s ease-in-out;
        }
        .round-card:hover {
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
                    <h1 class="text-lg font-semibold text-gray-900">Mentoring</h1>
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
                                <i class="fas fa-chalkboard-teacher text-blue-600 mr-3"></i>
                                Mentoring Rounds
                            </h1>
                            <p class="text-gray-600 mt-1">Manage mentoring sessions and scoring rounds</p>
                        </div>
                    </div>
                </div>

                <div class="max-w-4xl mx-auto">
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

                <!-- Add/Edit Round Form -->
                <div class="round-card bg-white rounded-xl shadow-sm p-6 border border-gray-100 mb-6">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-plus-circle text-blue-600 mr-2"></i>
                        Add/Edit Mentoring Round
                    </h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="round_id" id="round_id" value="0">
                <div>
                    <label for="round_name" class="block text-sm font-medium text-gray-700 mb-2">Round Name *</label>
                    <input type="text" id="round_name" name="round_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g., Initial Pitch, Final Presentation">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="start_time" class="block text-sm font-medium text-gray-700 mb-2">Start Time *</label>
                        <input type="datetime-local" id="start_time" name="start_time" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="end_time" class="block text-sm font-medium text-gray-700 mb-2">End Time *</label>
                        <input type="datetime-local" id="end_time" name="end_time" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div>
                    <label for="max_score" class="block text-sm font-medium text-gray-700 mb-2">Maximum Score *</label>
                    <input type="number" id="max_score" name="max_score" required min="1" value="100"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea id="description" name="description" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Optional description for the round"></textarea>
                </div>
                <div class="flex space-x-4">
                    <button type="submit" name="submit_round" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors">
                        <i class="fas fa-save mr-2"></i>Save Round
                    </button>
                    <button type="button" onclick="resetForm()" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors">
                        <i class="fas fa-undo mr-2"></i>Reset Form
                    </button>
                </div>
            </form>
                </div>

                <!-- Rounds List -->
                <div class="round-card bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold">
                            <i class="fas fa-list text-gray-600 mr-2"></i>
                            All Mentoring Rounds (<?php echo count($rounds); ?>)
                        </h3>
                    </div>
            
            <?php if (empty($rounds)): ?>
                <div class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-calendar-times text-4xl mb-4"></i>
                    <p>No mentoring rounds created yet.</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($rounds as $round): ?>
                        <div class="px-6 py-4">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="text-xl font-semibold text-gray-900"><?php echo htmlspecialchars($round['round_name']); ?></h4>
                                <div class="flex space-x-2">
                                    <button onclick="editRound(<?php echo htmlspecialchars(json_encode($round)); ?>)" class="text-blue-600 hover:text-blue-900 text-sm">
                                        <i class="fas fa-edit mr-1"></i>Edit
                                    </button>
                                    <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this round? This will also delete all associated scores.');">
                                        <input type="hidden" name="round_id" value="<?php echo $round['id']; ?>">
                                        <button type="submit" name="delete_round" class="text-red-600 hover:text-red-900 text-sm">
                                            <i class="fas fa-trash mr-1"></i>Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <p class="text-sm text-gray-700 mb-1">
                                <span class="font-medium">Duration:</span> 
                                <?php echo formatDateTime($round['start_time']); ?> to <?php echo formatDateTime($round['end_time']); ?>
                            </p>
                            <p class="text-sm text-gray-700 mb-1">
                                <span class="font-medium">Max Score:</span> <?php echo htmlspecialchars($round['max_score']); ?> points
                            </p>
                            <?php if ($round['description']): ?>
                                <p class="text-sm text-gray-600 mt-2"><?php echo nl2br(htmlspecialchars($round['description'])); ?></p>
                            <?php endif; ?>
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
        function editRound(round) {
            document.getElementById('round_id').value = round.id;
            document.getElementById('round_name').value = round.round_name;
            document.getElementById('start_time').value = round.start_time.replace(' ', 'T');
            document.getElementById('end_time').value = round.end_time.replace(' ', 'T');
            document.getElementById('max_score').value = round.max_score;
            document.getElementById('description').value = round.description;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('round_id').value = '0';
            document.getElementById('round_name').value = '';
            document.getElementById('start_time').value = '';
            document.getElementById('end_time').value = '';
            document.getElementById('max_score').value = '100';
            document.getElementById('description').value = '';
        }
    </script>
</body>
</html>
