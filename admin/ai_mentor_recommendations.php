<?php
require_once '../includes/db.php';
require_once '../includes/session_config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    startSecureSession();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get user info and check admin role
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header('Location: ../login.php');
        exit;
    }
    
    if ($user['role'] !== 'admin') {
        header('Location: ../unauthorized.php');
        exit;
    }
    
    // Update session
    $_SESSION['role'] = $user['role'];
    $_SESSION['user_role'] = $user['role'];
    
} catch (Exception $e) {
    error_log("Error checking user role: " . $e->getMessage());
    header('Location: ../login.php');
    exit;
}

// Include utils after we know user is authenticated
require_once '../includes/utils.php';

// Get counts for sidebar notifications
$pending_teams = $pdo->query("SELECT COUNT(*) FROM teams WHERE status = 'pending'")->fetchColumn();
$open_support_requests = $pdo->query("SELECT COUNT(*) FROM support_messages WHERE status = 'open'")->fetchColumn();

$message = '';
$error = '';

// Handle actions
if ($_POST) {
    if (isset($_POST['generate_recommendations'])) {
        // This will be handled via AJAX
    } elseif (isset($_POST['assign_mentor'])) {
        $recommendation_id = intval($_POST['recommendation_id']);
        $participant_id = intval($_POST['participant_id']);
        $mentor_id = intval($_POST['mentor_id']);
        $floor_id = intval($_POST['floor_id']);
        $room_id = intval($_POST['room_id']);
        
        try {
            $pdo->beginTransaction();
            
            // Create mentor assignment
            $stmt = $pdo->prepare("INSERT INTO mentor_assignments (mentor_id, floor_id, room_id) VALUES (?, ?, ?)");
            $stmt->execute([$mentor_id, $floor_id, $room_id]);
            
            // Update recommendation status
            $stmt = $pdo->prepare("UPDATE mentor_recommendations SET status = 'assigned' WHERE id = ?");
            $stmt->execute([$recommendation_id]);
            
            $pdo->commit();
            $message = "Mentor assigned successfully based on AI recommendation!";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to assign mentor: " . $e->getMessage();
        }
    }
}

// Get statistics
$stats = [
    'total_participants' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'participant'")->fetchColumn(),
    'total_mentors' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mentor'")->fetchColumn(),
    'total_recommendations' => $pdo->query("SELECT COUNT(*) FROM mentor_recommendations")->fetchColumn(),
    'high_match_recommendations' => $pdo->query("SELECT COUNT(*) FROM mentor_recommendations WHERE match_score >= 70")->fetchColumn()
];

// Get floors and rooms for assignment
$floors_data = $pdo->query("SELECT * FROM floors ORDER BY floor_number")->fetchAll(PDO::FETCH_ASSOC);
foreach ($floors_data as &$floor) {
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE floor_id = ? ORDER BY room_number");
    $stmt->execute([$floor['id']]);
    $floor['rooms'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($floor);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Mentor Recommendations - HackMate</title>
    
    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .recommendation-card {
            transition: all 0.3s ease;
        }
        .recommendation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .match-score-high { background: linear-gradient(135deg, #10B981, #059669); }
        .match-score-medium { background: linear-gradient(135deg, #F59E0B, #D97706); }
        .match-score-low { background: linear-gradient(135deg, #6B7280, #4B5563); }
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                    <h1 class="text-lg font-semibold text-gray-900">AI Recommendations</h1>
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
                                <i class="fas fa-robot text-purple-600 mr-3"></i>
                                AI Mentor Recommendations
                            </h1>
                            <p class="text-gray-600 mt-1">Intelligent mentor-participant matching based on skills and expertise</p>
                        </div>
                        
                        <div class="flex items-center space-x-3">
                            <button onclick="generateRecommendations()" 
                                    class="bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white px-6 py-3 rounded-lg font-medium transition-all duration-300 shadow-md hover:shadow-lg flex items-center">
                                <i class="fas fa-magic mr-2"></i>
                                <span id="generate-btn-text">Generate AI Recommendations</span>
                                <div id="generate-spinner" class="loading-spinner ml-2 hidden"></div>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg shadow-sm">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                            <p class="text-green-800 font-medium"><?php echo $message; ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg shadow-sm">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3"></i>
                            <p class="text-red-800 font-medium"><?php echo $error; ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-blue-50 text-blue-600">
                                <i class="fas fa-users text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Participants</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_participants']; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-green-50 text-green-600">
                                <i class="fas fa-chalkboard-teacher text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Mentors</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_mentors']; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-purple-50 text-purple-600">
                                <i class="fas fa-lightbulb text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total Recommendations</p>
                                <p class="text-2xl font-bold text-gray-900" id="total-recommendations"><?php echo $stats['total_recommendations']; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-yellow-50 text-yellow-600">
                                <i class="fas fa-star text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">High Matches (70%+)</p>
                                <p class="text-2xl font-bold text-gray-900" id="high-match-recommendations"><?php echo $stats['high_match_recommendations']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 mb-6">
                    <div class="flex flex-wrap items-center gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Minimum Match Score</label>
                            <select id="min-score-filter" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                                <option value="0">All Matches</option>
                                <option value="30">30% and above</option>
                                <option value="50">50% and above</option>
                                <option value="70" selected>70% and above</option>
                                <option value="90">90% and above</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="status-filter" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                                <option value="">All Status</option>
                                <option value="pending" selected>Pending</option>
                                <option value="approved">Approved</option>
                                <option value="assigned">Assigned</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        
                        <div class="flex-1"></div>
                        
                        <button onclick="loadRecommendations()" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center">
                            <i class="fas fa-filter mr-2"></i>
                            Apply Filters
                        </button>
                    </div>
                </div>
                
                <!-- Recommendations List -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white">
                        <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-list-ul text-purple-600 mr-3"></i>
                            AI Mentor Recommendations
                        </h3>
                        <p class="text-sm text-gray-500 mt-1">Smart mentor-participant matching based on skill compatibility</p>
                    </div>
                    
                    <div id="recommendations-container">
                        <div class="p-8 text-center">
                            <div class="mx-auto w-16 h-16 flex items-center justify-center bg-purple-100 rounded-full mb-4">
                                <i class="fas fa-robot text-purple-600 text-2xl"></i>
                            </div>
                            <p class="text-gray-500 font-medium">Click "Generate AI Recommendations" to start</p>
                            <p class="text-sm text-gray-400 mt-2">The AI will analyze skills and suggest optimal mentor-participant matches</p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Assignment Modal -->
    <div id="assignment-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Assign Mentor to Room</h3>
            </div>
            
            <form method="POST" class="p-6">
                <input type="hidden" id="modal-recommendation-id" name="recommendation_id">
                <input type="hidden" id="modal-participant-id" name="participant_id">
                <input type="hidden" id="modal-mentor-id" name="mentor_id">
                
                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-2">Assigning mentor based on AI recommendation:</p>
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <p class="font-medium text-gray-900" id="modal-mentor-name"></p>
                        <p class="text-sm text-gray-600" id="modal-participant-name"></p>
                        <p class="text-sm text-purple-600" id="modal-match-score"></p>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Floor</label>
                        <select id="modal-floor-id" name="floor_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">Select Floor</option>
                            <?php foreach ($floors_data as $floor): ?>
                                <option value="<?php echo $floor['id']; ?>">Floor <?php echo $floor['floor_number']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Room</label>
                        <select id="modal-room-id" name="room_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">Select Room</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeAssignmentModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium">
                        Cancel
                    </button>
                    <button type="submit" name="assign_mentor" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                        Assign Mentor
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const floorsData = <?php echo json_encode($floors_data); ?>;
        
        // Load recommendations on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadRecommendations();
        });
        
        // Generate new recommendations
        async function generateRecommendations() {
            const btn = document.getElementById('generate-btn-text');
            const spinner = document.getElementById('generate-spinner');
            
            btn.textContent = 'Generating...';
            spinner.classList.remove('hidden');
            
            try {
                const response = await fetch('../api/mentor_recommendations.php?action=generate');
                const data = await response.json();
                
                if (data.success) {
                    // Update statistics
                    document.getElementById('total-recommendations').textContent = data.count;
                    
                    // Reload recommendations
                    await loadRecommendations();
                    
                    // Show success message
                    showMessage('AI recommendations generated successfully!', 'success');
                } else {
                    showMessage('Error: ' + data.error, 'error');
                }
            } catch (error) {
                showMessage('Failed to generate recommendations: ' + error.message, 'error');
            } finally {
                btn.textContent = 'Generate AI Recommendations';
                spinner.classList.add('hidden');
            }
        }
        
        // Load recommendations with filters
        async function loadRecommendations() {
            const minScore = document.getElementById('min-score-filter').value;
            const status = document.getElementById('status-filter').value;
            
            const container = document.getElementById('recommendations-container');
            container.innerHTML = '<div class="p-8 text-center"><div class="loading-spinner mx-auto mb-4"></div><p class="text-gray-500">Loading recommendations...</p></div>';
            
            try {
                let url = `../api/mentor_recommendations.php?action=list&min_score=${minScore}`;
                if (status) url += `&status=${status}`;
                
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    displayRecommendations(data.data);
                } else {
                    container.innerHTML = '<div class="p-8 text-center text-red-500">Error: ' + data.error + '</div>';
                }
            } catch (error) {
                container.innerHTML = '<div class="p-8 text-center text-red-500">Failed to load recommendations</div>';
            }
        }
        
        // Display recommendations
        function displayRecommendations(recommendations) {
            const container = document.getElementById('recommendations-container');
            
            if (recommendations.length === 0) {
                container.innerHTML = `
                    <div class="p-8 text-center">
                        <div class="mx-auto w-16 h-16 flex items-center justify-center bg-gray-100 rounded-full mb-4">
                            <i class="fas fa-search text-gray-400 text-2xl"></i>
                        </div>
                        <p class="text-gray-500 font-medium">No recommendations found</p>
                        <p class="text-sm text-gray-400 mt-2">Try adjusting your filters or generate new recommendations</p>
                    </div>
                `;
                return;
            }
            
            let html = '<div class="divide-y divide-gray-200">';
            
            recommendations.forEach(rec => {
                const matchScoreClass = rec.match_score >= 70 ? 'match-score-high' : 
                                      rec.match_score >= 50 ? 'match-score-medium' : 'match-score-low';
                
                const statusColor = {
                    'pending': 'bg-yellow-100 text-yellow-800',
                    'approved': 'bg-green-100 text-green-800',
                    'assigned': 'bg-blue-100 text-blue-800',
                    'rejected': 'bg-red-100 text-red-800'
                };
                
                html += `
                    <div class="recommendation-card p-6 hover:bg-gray-50">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-4 mb-3">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-user text-blue-600"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900">${rec.participant_name}</p>
                                            <p class="text-sm text-gray-500">${rec.participant_email}</p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center text-gray-400">
                                        <i class="fas fa-arrow-right"></i>
                                    </div>
                                    
                                    <div class="flex items-center space-x-2">
                                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-chalkboard-teacher text-green-600"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900">${rec.mentor_name}</p>
                                            <p class="text-sm text-gray-500">${rec.mentor_email}</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center space-x-4 mb-3">
                                    <div class="flex items-center space-x-2">
                                        <div class="${matchScoreClass} text-white px-3 py-1 rounded-full text-sm font-medium">
                                            ${rec.match_score}% Match
                                        </div>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium ${statusColor[rec.status] || 'bg-gray-100 text-gray-800'}">
                                            ${rec.status.charAt(0).toUpperCase() + rec.status.slice(1)}
                                        </span>
                                    </div>
                                </div>
                                
                                <p class="text-sm text-gray-600 mb-3">${rec.recommendation_reason}</p>
                                
                                ${rec.skill_match_details ? `
                                    <div class="flex flex-wrap gap-2">
                                        ${JSON.parse(rec.skill_match_details).slice(0, 5).map(match => 
                                            `<span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">
                                                ${match.mentor_skill} (${Math.round(match.similarity * 100)}%)
                                            </span>`
                                        ).join('')}
                                        ${JSON.parse(rec.skill_match_details).length > 5 ? 
                                            `<span class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded-full">
                                                +${JSON.parse(rec.skill_match_details).length - 5} more
                                            </span>` : ''
                                        }
                                    </div>
                                ` : ''}
                            </div>
                            
                            <div class="flex flex-col space-y-2 ml-4">
                                ${rec.status === 'pending' ? `
                                    <button onclick="openAssignmentModal(${rec.id}, ${rec.participant_id}, ${rec.mentor_id}, '${rec.participant_name}', '${rec.mentor_name}', ${rec.match_score})" 
                                            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                                        <i class="fas fa-user-plus mr-2"></i>
                                        Assign
                                    </button>
                                    <button onclick="updateRecommendationStatus(${rec.id}, 'approved')" 
                                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                        Approve
                                    </button>
                                    <button onclick="updateRecommendationStatus(${rec.id}, 'rejected')" 
                                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                        Reject
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }
        
        // Update recommendation status
        async function updateRecommendationStatus(recommendationId, status) {
            try {
                const response = await fetch('../api/mentor_recommendations.php?action=update_status', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        recommendation_id: recommendationId,
                        status: status
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage(`Recommendation ${status} successfully!`, 'success');
                    loadRecommendations();
                } else {
                    showMessage('Error: ' + data.error, 'error');
                }
            } catch (error) {
                showMessage('Failed to update recommendation: ' + error.message, 'error');
            }
        }
        
        // Assignment modal functions
        function openAssignmentModal(recommendationId, participantId, mentorId, participantName, mentorName, matchScore) {
            document.getElementById('modal-recommendation-id').value = recommendationId;
            document.getElementById('modal-participant-id').value = participantId;
            document.getElementById('modal-mentor-id').value = mentorId;
            document.getElementById('modal-participant-name').textContent = `Participant: ${participantName}`;
            document.getElementById('modal-mentor-name').textContent = `Mentor: ${mentorName}`;
            document.getElementById('modal-match-score').textContent = `Match Score: ${matchScore}%`;
            
            document.getElementById('assignment-modal').classList.remove('hidden');
        }
        
        function closeAssignmentModal() {
            document.getElementById('assignment-modal').classList.add('hidden');
        }
        
        // Floor/Room selection for modal
        document.getElementById('modal-floor-id').addEventListener('change', function() {
            const floorId = this.value;
            const roomSelect = document.getElementById('modal-room-id');
            
            roomSelect.innerHTML = '<option value="">Select Room</option>';
            
            if (floorId) {
                const selectedFloor = floorsData.find(floor => floor.id == floorId);
                if (selectedFloor && selectedFloor.rooms) {
                    selectedFloor.rooms.forEach(room => {
                        const option = document.createElement('option');
                        option.value = room.id;
                        option.textContent = `Room ${room.room_number} (Capacity: ${room.capacity})`;
                        roomSelect.appendChild(option);
                    });
                }
            }
        });
        
        // Show message function
        function showMessage(message, type) {
            // Create and show a temporary message
            const messageDiv = document.createElement('div');
            messageDiv.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg ${
                type === 'success' ? 'bg-green-50 border-l-4 border-green-500 text-green-800' : 
                'bg-red-50 border-l-4 border-red-500 text-red-800'
            }`;
            messageDiv.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(messageDiv);
            
            setTimeout(() => {
                messageDiv.remove();
            }, 5000);
        }
    </script>
</body>
</html>