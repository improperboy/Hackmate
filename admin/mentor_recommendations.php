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

// Check for assignment success message
if (isset($_GET['assigned']) && $_GET['assigned'] == '1') {
    $mentor_id = isset($_GET['mentor_id']) ? intval($_GET['mentor_id']) : 0;
    if ($mentor_id > 0) {
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? AND role = 'mentor'");
        $stmt->execute([$mentor_id]);
        $mentor_name = $stmt->fetchColumn();
        $message = $mentor_name ? "Mentor {$mentor_name} assigned successfully!" : 'Mentor assigned successfully!';
    } else {
        $message = 'Mentor assigned successfully!';
    }
}

// Technology categories for better matching
$techCategories = [
    'frontend' => ['react', 'vue', 'angular', 'svelte', 'nextjs', 'nuxt', 'gatsby', 'html', 'css', 'javascript', 'typescript', 'tailwind', 'bootstrap', 'sass', 'scss', 'jquery', 'webpack', 'vite', 'parcel'],
    'backend' => ['node', 'express', 'fastify', 'nestjs', 'python', 'django', 'flask', 'fastapi', 'java', 'spring', 'php', 'laravel', 'symfony', 'ruby', 'rails', 'go', 'gin', 'fiber', 'rust', 'actix', 'c#', 'dotnet', 'asp.net'],
    'database' => ['mysql', 'postgresql', 'mongodb', 'redis', 'sqlite', 'firebase', 'supabase', 'prisma', 'sequelize', 'mongoose', 'typeorm', 'knex', 'drizzle'],
    'mobile' => ['react native', 'flutter', 'ionic', 'xamarin', 'swift', 'kotlin', 'java', 'dart'],
    'cloud' => ['aws', 'azure', 'gcp', 'docker', 'kubernetes', 'vercel', 'netlify', 'heroku', 'digitalocean'],
    'ai_ml' => ['tensorflow', 'pytorch', 'scikit-learn', 'pandas', 'numpy', 'opencv', 'keras', 'huggingface', 'langchain', 'openai', 'anthropic', 'gemini'],
    'devops' => ['git', 'github', 'gitlab', 'jenkins', 'circleci', 'travis', 'docker', 'kubernetes', 'terraform', 'ansible']
];

// Technology relationships and synonyms
$techSynonyms = [
    'react.js' => 'react',
    'reactjs' => 'react',
    'node.js' => 'node',
    'nodejs' => 'node',
    'vue.js' => 'vue',
    'vuejs' => 'vue',
    'angular.js' => 'angular',
    'angularjs' => 'angular',
    'express.js' => 'express',
    'expressjs' => 'express',
    'next.js' => 'nextjs',
    'next' => 'nextjs',
    'nuxt.js' => 'nuxt',
    'postgresql' => 'postgres',
    'postgres' => 'postgresql',
    'mongodb' => 'mongo',
    'mongo' => 'mongodb',
    'javascript' => 'js',
    'js' => 'javascript',
    'typescript' => 'ts',
    'ts' => 'typescript',
    'tailwindcss' => 'tailwind',
    'react native' => 'react-native',
    'react-native' => 'react native',
    'machine learning' => 'ml',
    'ml' => 'machine learning',
    'artificial intelligence' => 'ai',
    'ai' => 'artificial intelligence'
];

// Related technologies (if mentor knows A, they can likely help with B)
$techRelations = [
    'react' => ['nextjs', 'gatsby', 'javascript', 'typescript', 'jsx'],
    'vue' => ['nuxt', 'javascript', 'typescript'],
    'angular' => ['typescript', 'javascript', 'rxjs'],
    'node' => ['express', 'fastify', 'nestjs', 'javascript', 'typescript'],
    'python' => ['django', 'flask', 'fastapi', 'pandas', 'numpy'],
    'javascript' => ['typescript', 'node', 'react', 'vue', 'angular'],
    'typescript' => ['javascript', 'node', 'react', 'vue', 'angular'],
    'mysql' => ['postgresql', 'sqlite', 'sql'],
    'postgresql' => ['mysql', 'sqlite', 'sql'],
    'mongodb' => ['mongoose', 'nosql'],
    'docker' => ['kubernetes', 'containerization'],
    'aws' => ['cloud', 'azure', 'gcp'],
    'tensorflow' => ['keras', 'python', 'ai', 'ml'],
    'pytorch' => ['python', 'ai', 'ml']
];

// Extract and normalize tech stack with enhanced processing
function extractTechStack($techStackString) {
    global $techSynonyms;
    
    if (empty($techStackString)) return [];
    
    // Split by common separators
    $techs = preg_split('/[,;|\/\n\r]+/', strtolower($techStackString));
    $normalized = [];
    
    foreach ($techs as $tech) {
        $tech = trim($tech);
        if (empty($tech)) continue;
        
        // Remove common prefixes/suffixes
        $tech = preg_replace('/\.(js|css|html|php|py)$/', '', $tech);
        $tech = preg_replace('/^(the|a|an)\s+/', '', $tech);
        
        // Apply synonyms
        if (isset($techSynonyms[$tech])) {
            $tech = $techSynonyms[$tech];
        }
        
        // Handle compound technologies
        if (strpos($tech, ' ') !== false) {
            // Keep compound names as-is for things like "react native"
            $normalized[] = $tech;
        } else {
            $normalized[] = $tech;
        }
    }
    
    return array_unique(array_filter($normalized));
}

// Helper function to get technology category
function getTechCategory($tech) {
    global $techCategories;
    
    foreach ($techCategories as $category => $techs) {
        if (in_array($tech, $techs)) {
            return $category;
        }
    }
    return null;
}

// Calculate enhanced compatibility score between mentor and room
function calculateCompatibilityScore($mentorTechs, $roomTechData) {
    global $techRelations;
    
    if (empty($mentorTechs) || empty($roomTechData['tech_counts'])) return 0;
    
    $roomTechs = $roomTechData['tech_counts'];
    $totalMatches = 0;
    $totalRoomTechs = array_sum($roomTechs);
    
    if ($totalRoomTechs == 0) return 0;
    
    foreach ($roomTechs as $roomTech => $frequency) {
        $matchWeight = 0;
        
        // Direct match (full weight)
        if (in_array($roomTech, $mentorTechs)) {
            $matchWeight = 1.0;
        } else {
            // Check for related technologies (partial weight)
            foreach ($mentorTechs as $mentorTech) {
                if (isset($techRelations[$mentorTech]) && in_array($roomTech, $techRelations[$mentorTech])) {
                    $matchWeight = max($matchWeight, 0.7); // 70% weight for related tech
                } elseif (isset($techRelations[$roomTech]) && in_array($mentorTech, $techRelations[$roomTech])) {
                    $matchWeight = max($matchWeight, 0.7); // 70% weight for related tech
                }
            }
        }
        
        $totalMatches += $frequency * $matchWeight;
    }
    
    return round(($totalMatches / $totalRoomTechs) * 100, 1);
}

// Get mentor workload
function getMentorWorkload($mentorId, $pdo) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as assignment_count,
               GROUP_CONCAT(CONCAT('Floor ', f.floor_number, ' Room ', r.room_number) SEPARATOR ', ') as locations
        FROM mentor_assignments ma
        JOIN floors f ON ma.floor_id = f.id
        JOIN rooms r ON ma.room_id = r.id
        WHERE ma.mentor_id = ?
    ");
    $stmt->execute([$mentorId]);
    $workload = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT COUNT(t.id) as team_count
        FROM mentor_assignments ma
        JOIN teams t ON ma.room_id = t.room_id AND ma.floor_id = t.floor_id
        WHERE ma.mentor_id = ? AND t.status = 'approved'
    ");
    $stmt->execute([$mentorId]);
    $teamCount = $stmt->fetchColumn();
    
    return [
        'assignment_count' => $workload['assignment_count'] ?? 0,
        'locations' => $workload['locations'] ?? '',
        'team_count' => $teamCount ?? 0
    ];
}

// Analyze room tech requirements
function getRoomTechAnalysis($pdo) {
    $stmt = $pdo->query("
        SELECT 
            r.id as room_id,
            r.room_number,
            f.floor_number,
            f.id as floor_id,
            COUNT(t.id) as team_count,
            GROUP_CONCAT(
                CASE 
                    WHEN s.tech_stack IS NOT NULL AND s.tech_stack != '' 
                    THEN s.tech_stack 
                    ELSE NULL 
                END 
                SEPARATOR '|||'
            ) as all_tech_stacks,
            GROUP_CONCAT(t.name SEPARATOR '|||') as team_names
        FROM rooms r
        JOIN floors f ON r.floor_id = f.id
        LEFT JOIN teams t ON r.id = t.room_id AND t.status = 'approved'
        LEFT JOIN submissions s ON t.id = s.team_id
        GROUP BY r.id, r.room_number, f.floor_number, f.id
        HAVING team_count > 0
        ORDER BY f.floor_number, r.room_number
    ");
    
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $roomAnalysis = [];
    
    foreach ($rooms as $room) {
        $techCounts = [];
        
        if (!empty($room['all_tech_stacks'])) {
            $techStacksArray = explode('|||', $room['all_tech_stacks']);
            
            foreach ($techStacksArray as $techStack) {
                if (!empty($techStack)) {
                    $techs = extractTechStack($techStack);
                    foreach ($techs as $tech) {
                        $techCounts[$tech] = ($techCounts[$tech] ?? 0) + 1;
                    }
                }
            }
        }
        
        arsort($techCounts);
        
        // Determine project complexity
        $uniqueTechCount = count($techCounts);
        $projectComplexity = 'medium'; // default
        
        if ($uniqueTechCount >= 8) {
            $projectComplexity = 'high';
        } elseif ($uniqueTechCount <= 3) {
            $projectComplexity = 'low';
        }
        
        // Check for complex technologies
        $complexTechs = ['kubernetes', 'docker', 'microservices', 'tensorflow', 'pytorch', 'blockchain', 'aws', 'azure', 'gcp'];
        foreach ($complexTechs as $complexTech) {
            if (isset($techCounts[$complexTech])) {
                $projectComplexity = 'high';
                break;
            }
        }
        
        // Determine primary technology category
        $primaryCategory = null;
        if (!empty($techCounts)) {
            $topTech = array_key_first($techCounts);
            $primaryCategory = getTechCategory($topTech);
        }
        
        $roomAnalysis[] = [
            'room_id' => $room['room_id'],
            'room_number' => $room['room_number'],
            'floor_number' => $room['floor_number'],
            'floor_id' => $room['floor_id'],
            'team_count' => $room['team_count'],
            'tech_counts' => $techCounts,
            'top_techs' => array_slice($techCounts, 0, 5, true),
            'unique_tech_count' => $uniqueTechCount,
            'project_complexity' => $projectComplexity,
            'primary_category' => $primaryCategory,
            'team_names' => !empty($room['team_names']) ? explode('|||', $room['team_names']) : []
        ];
    }
    
    return $roomAnalysis;
}

// Get mentor recommendations for all rooms
function getMentorRecommendations($pdo, $roomAnalysis) {
    $stmt = $pdo->query("
        SELECT 
            u.id,
            u.name,
            u.email,
            u.tech_stack,
            ma.room_id as assigned_room_id,
            ma.floor_id as assigned_floor_id
        FROM users u
        LEFT JOIN mentor_assignments ma ON u.id = ma.mentor_id
        WHERE u.role = 'mentor'
        ORDER BY u.name
    ");
    
    $mentors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $recommendations = [];
    
    foreach ($roomAnalysis as $room) {
        $roomRecommendations = [];
        
        foreach ($mentors as $mentor) {
            $mentorTechs = extractTechStack($mentor['tech_stack']);
            $workload = getMentorWorkload($mentor['id'], $pdo);
            
            $compatibilityScore = calculateCompatibilityScore($mentorTechs, $room);
            $availabilityScore = max(20, 100 - ($workload['team_count'] * 15));
            $finalScore = ($compatibilityScore * 0.7) + ($availabilityScore * 0.3);
            
            $isAssigned = ($mentor['assigned_room_id'] == $room['room_id']);
            $isAssignedElsewhere = (!empty($mentor['assigned_room_id']) && $mentor['assigned_room_id'] != $room['room_id']);
            
            // Get mentor's primary categories
            $mentorCategories = array_unique(array_filter(array_map('getTechCategory', $mentorTechs)));
            
            $roomRecommendations[] = [
                'mentor_id' => $mentor['id'],
                'mentor_name' => $mentor['name'],
                'mentor_email' => $mentor['email'],
                'mentor_techs' => $mentorTechs,
                'mentor_categories' => $mentorCategories,
                'compatibility_score' => $compatibilityScore,
                'availability_score' => $availabilityScore,
                'final_score' => round($finalScore, 1),
                'workload' => $workload,
                'is_assigned' => $isAssigned,
                'is_assigned_elsewhere' => $isAssignedElsewhere,
                'assigned_location' => $isAssignedElsewhere ? 
                    "Floor {$mentor['assigned_floor_id']}, Room {$mentor['assigned_room_id']}" : null
            ];
        }
        
        usort($roomRecommendations, function($a, $b) {
            return $b['final_score'] <=> $a['final_score'];
        });
        
        $recommendations[$room['room_id']] = [
            'room_info' => $room,
            'recommendations' => $roomRecommendations
        ];
    }
    
    return $recommendations;
}

// Get the analysis
$roomAnalysis = getRoomTechAnalysis($pdo);
$recommendations = getMentorRecommendations($pdo, $roomAnalysis);

// Handle mentor assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_mentor'])) {
    $mentor_id = intval($_POST['mentor_id']);
    $floor_id = intval($_POST['floor_id']);
    $room_id = intval($_POST['room_id']);
    
    if ($mentor_id <= 0 || $floor_id <= 0 || $room_id <= 0) {
        $error = 'All assignment fields are required.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM mentor_assignments WHERE mentor_id = ? AND floor_id = ? AND room_id = ?");
            $stmt->execute([$mentor_id, $floor_id, $room_id]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'This mentor is already assigned to this location.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO mentor_assignments (mentor_id, floor_id, room_id) VALUES (?, ?, ?)");
                if ($stmt->execute([$mentor_id, $floor_id, $room_id])) {
                    header("Location: " . $_SERVER['PHP_SELF'] . "?assigned=1&mentor_id=" . $mentor_id);
                    exit();
                } else {
                    $error = 'Failed to assign mentor.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?><!DOCTY
PE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentor Recommendations - HackMate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .recommendation-card {
            transition: all 0.3s ease;
        }
        .recommendation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .tech-badge {
            transition: all 0.2s ease;
        }
        .tech-badge:hover {
            transform: scale(1.05);
        }
        .score-bar {
            background: linear-gradient(90deg, #ef4444 0%, #f59e0b 50%, #10b981 100%);
            height: 8px;
            border-radius: 4px;
            position: relative;
            overflow: hidden;
        }
        .score-indicator {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
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
                    <h1 class="text-lg font-semibold text-gray-900">Mentor Recommendations</h1>
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
                                <i class="fas fa-users text-purple-600 mr-3"></i>
                                Mentor Recommendations
                            </h1>
                            <p class="text-gray-600 mt-1">Find the best mentors for each room based on skills match</p>
                        </div>
                        <div class="flex space-x-3">
                            <a href="mentor_assignments.php"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center transition-colors">
                                <i class="fas fa-cog mr-2"></i>
                                Manual Assignments
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg shadow-sm animate-fade-in">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                            <p class="text-green-800 font-medium"><?php echo htmlspecialchars($message); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg shadow-sm animate-fade-in">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3"></i>
                            <p class="text-red-800 font-medium"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Overview Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-blue-50 text-blue-600">
                                <i class="fas fa-door-open text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Rooms with Teams</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo count($roomAnalysis); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-green-50 text-green-600">
                                <i class="fas fa-users text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total Teams</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?php echo array_sum(array_column($roomAnalysis, 'team_count')); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-purple-50 text-purple-600">
                                <i class="fas fa-chalkboard-teacher text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Available Mentors</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?php
                                    $totalMentors = 0;
                                    if (!empty($recommendations)) {
                                        $firstRoom = reset($recommendations);
                                        $totalMentors = count($firstRoom['recommendations']);
                                    }
                                    echo $totalMentors;
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Room Recommendations -->
                <?php if (empty($recommendations)): ?>
                    <div class="bg-white rounded-xl shadow-sm p-8 text-center border border-gray-100">
                        <div class="mx-auto w-16 h-16 flex items-center justify-center bg-gray-100 rounded-full mb-4">
                            <i class="fas fa-search text-gray-400 text-2xl"></i>
                        </div>
                        <p class="text-gray-500 font-medium">No rooms with teams found</p>
                        <p class="text-sm text-gray-400 mt-2">Teams need to be approved and have project submissions to appear in recommendations</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-8">
                        <?php foreach ($recommendations as $roomId => $data): ?>
                            <div class="recommendation-card bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                                <!-- Room Header -->
                                <div class="bg-gradient-to-r from-indigo-50 to-purple-50 px-6 py-4 border-b border-gray-200">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center mr-4">
                                                <i class="fas fa-door-open text-indigo-600 text-xl"></i>
                                            </div>
                                            <div>
                                                <h3 class="text-xl font-bold text-gray-900">
                                                    Floor <?php echo $data['room_info']['floor_number']; ?> - Room <?php echo $data['room_info']['room_number']; ?>
                                                </h3>
                                                <p class="text-sm text-gray-600">
                                                    <?php echo $data['room_info']['team_count']; ?> team<?php echo $data['room_info']['team_count'] != 1 ? 's' : ''; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Room Analysis -->
                                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <!-- Tech Stack -->
                                        <?php if (!empty($data['room_info']['top_techs'])): ?>
                                            <div>
                                                <p class="text-sm font-medium text-gray-700 mb-2">Most Used Skills:</p>
                                                <div class="flex flex-wrap gap-2">
                                                    <?php foreach ($data['room_info']['top_techs'] as $tech => $count): ?>
                                                        <span class="tech-badge inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                            <i class="fas fa-code mr-1"></i>
                                                            <?php echo htmlspecialchars(ucfirst($tech)); ?>
                                                            <span class="ml-1 bg-indigo-200 text-indigo-900 px-1.5 py-0.5 rounded-full text-xs">
                                                                <?php echo $count; ?>
                                                            </span>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Room Characteristics -->
                                        <div>
                                            <p class="text-sm font-medium text-gray-700 mb-2">Room Characteristics:</p>
                                            <div class="flex flex-wrap gap-2">
                                                <!-- Project Complexity -->
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php
                                                    echo $data['room_info']['project_complexity'] === 'high' ? 'bg-red-100 text-red-800' : 
                                                        ($data['room_info']['project_complexity'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800');
                                                ?>">
                                                    <i class="fas fa-<?php
                                                        echo $data['room_info']['project_complexity'] === 'high' ? 'fire' : 
                                                            ($data['room_info']['project_complexity'] === 'medium' ? 'balance-scale' : 'leaf');
                                                    ?> mr-1"></i>
                                                    <?php echo ucfirst($data['room_info']['project_complexity']); ?> Complexity
                                                </span>

                                                <!-- Skills Count -->
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    <i class="fas fa-layer-group mr-1"></i>
                                                    <?php echo $data['room_info']['unique_tech_count']; ?> Skills
                                                </span>

                                                <!-- Primary Category -->
                                                <?php if ($data['room_info']['primary_category']): ?>
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                        <i class="fas fa-tag mr-1"></i>
                                                        <?php echo ucfirst($data['room_info']['primary_category']); ?> Focus
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Team Names -->
                                    <?php if (!empty($data['room_info']['team_names'])): ?>
                                        <div class="mt-3">
                                            <p class="text-xs text-gray-600 mb-1">Teams:</p>
                                            <div class="text-sm text-gray-700">
                                                <?php echo implode(', ', array_slice($data['room_info']['team_names'], 0, 3)); ?>
                                                <?php if (count($data['room_info']['team_names']) > 3): ?>
                                                    <span class="text-gray-500">+<?php echo count($data['room_info']['team_names']) - 3; ?> more</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Mentor Recommendations -->
                                <div class="p-6">
                                    <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                        <i class="fas fa-star text-yellow-500 mr-2"></i>
                                        Recommended Mentors
                                    </h4>

                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                        <?php
                                        $topRecommendations = array_slice($data['recommendations'], 0, 6);
                                        foreach ($topRecommendations as $rec):
                                        ?>
                                            <div class="border border-gray-200 rounded-lg p-4 hover:border-indigo-300 transition-colors <?php echo $rec['is_assigned'] ? 'bg-green-50 border-green-300' : ($rec['is_assigned_elsewhere'] ? 'bg-yellow-50 border-yellow-300' : ''); ?>">
                                                <div class="flex items-start justify-between mb-3">
                                                    <div class="flex items-center">
                                                        <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center mr-3">
                                                            <i class="fas fa-user text-gray-600"></i>
                                                        </div>
                                                        <div>
                                                            <h5 class="font-medium text-gray-900"><?php echo htmlspecialchars($rec['mentor_name']); ?></h5>
                                                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($rec['mentor_email']); ?></p>
                                                        </div>
                                                    </div>

                                                    <div class="text-right">
                                                        <div class="text-lg font-bold <?php
                                                            echo $rec['final_score'] >= 80 ? 'text-green-600' : 
                                                                ($rec['final_score'] >= 60 ? 'text-blue-600' : 
                                                                ($rec['final_score'] >= 40 ? 'text-yellow-600' : 'text-red-600'));
                                                        ?>">
                                                            <?php echo $rec['final_score']; ?>%
                                                        </div>
                                                        <p class="text-xs text-gray-500">match score</p>
                                                    </div>
                                                </div>

                                                <!-- Score Breakdown -->
                                                <div class="mb-3">
                                                    <div class="score-bar">
                                                        <div class="score-indicator" style="width: <?php echo $rec['final_score']; ?>%"></div>
                                                    </div>
                                                    <div class="grid grid-cols-2 gap-2 text-xs mt-2">
                                                        <div class="text-center">
                                                            <div class="h-2 bg-gray-200 rounded">
                                                                <div class="h-2 bg-blue-500 rounded" style="width: <?php echo $rec['compatibility_score']; ?>%"></div>
                                                            </div>
                                                            <span class="text-gray-600">Skills: <?php echo $rec['compatibility_score']; ?>%</span>
                                                        </div>
                                                        <div class="text-center">
                                                            <div class="h-2 bg-gray-200 rounded">
                                                                <div class="h-2 bg-green-500 rounded" style="width: <?php echo $rec['availability_score']; ?>%"></div>
                                                            </div>
                                                            <span class="text-gray-600">Available: <?php echo $rec['availability_score']; ?>%</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Mentor Tech Stack -->
                                                <?php if (!empty($rec['mentor_techs'])): ?>
                                                    <div class="mb-3">
                                                        <p class="text-xs text-gray-600 mb-1">Skills:</p>
                                                        <div class="flex flex-wrap gap-1">
                                                            <?php foreach (array_slice($rec['mentor_techs'], 0, 4) as $tech): ?>
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php
                                                                    echo isset($data['room_info']['tech_counts'][$tech]) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
                                                                ?>">
                                                                    <?php echo htmlspecialchars(ucfirst($tech)); ?>
                                                                    <?php if (isset($data['room_info']['tech_counts'][$tech])): ?>
                                                                        <i class="fas fa-check ml-1 text-xs"></i>
                                                                    <?php endif; ?>
                                                                </span>
                                                            <?php endforeach; ?>
                                                            <?php if (count($rec['mentor_techs']) > 4): ?>
                                                                <span class="text-xs text-gray-500">+<?php echo count($rec['mentor_techs']) - 4; ?> more</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Status and Actions -->
                                                <div class="flex items-center justify-between pt-3 border-t border-gray-200">
                                                    <div class="text-xs">
                                                        <?php if ($rec['is_assigned']): ?>
                                                            <span class="inline-flex items-center px-2 py-1 rounded-full bg-green-100 text-green-800">
                                                                <i class="fas fa-check mr-1"></i>
                                                                Already Assigned
                                                            </span>
                                                        <?php elseif ($rec['is_assigned_elsewhere']): ?>
                                                            <span class="inline-flex items-center px-2 py-1 rounded-full bg-yellow-100 text-yellow-800">
                                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                                Assigned to <?php echo htmlspecialchars($rec['assigned_location']); ?>
                                                            </span>
                                                        <?php elseif ($rec['workload']['team_count'] > 0): ?>
                                                            <span class="text-gray-500">
                                                                <i class="fas fa-tasks mr-1"></i>
                                                                Mentoring <?php echo $rec['workload']['team_count']; ?> team<?php echo $rec['workload']['team_count'] != 1 ? 's' : ''; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-green-600">
                                                                <i class="fas fa-check-circle mr-1"></i>
                                                                Available
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <?php if (!$rec['is_assigned']): ?>
                                                        <form method="POST" class="inline">
                                                            <input type="hidden" name="mentor_id" value="<?php echo $rec['mentor_id']; ?>">
                                                            <input type="hidden" name="floor_id" value="<?php echo $data['room_info']['floor_id']; ?>">
                                                            <input type="hidden" name="room_id" value="<?php echo $data['room_info']['room_id']; ?>">
                                                            <button type="submit" name="assign_mentor" 
                                                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded text-xs font-medium transition-colors">
                                                                <i class="fas fa-plus mr-1"></i>
                                                                Assign
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <?php if (count($data['recommendations']) > 6): ?>
                                        <div class="mt-4 text-center">
                                            <button onclick="showAllMentors(<?php echo $roomId; ?>)" 
                                                class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                                <i class="fas fa-chevron-down mr-1"></i>
                                                Show <?php echo count($data['recommendations']) - 6; ?> more mentors
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide messages after 5 seconds
            const messages = document.querySelectorAll('.animate-fade-in');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.opacity = '0';
                    message.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        message.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });

        function showAllMentors(roomId) {
            // This would expand to show all mentors for the room
            // Implementation depends on your specific needs
            alert('Feature to show all mentors would be implemented here');
        }

        function toggleSidebar() {
            // Sidebar toggle functionality
            const sidebar = document.querySelector('aside');
            if (sidebar) {
                sidebar.classList.toggle('hidden');
            }
        }
    </script>
</body>
</html>