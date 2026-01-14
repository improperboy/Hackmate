<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';

checkAuth('admin');

/**
 * Migration script to convert existing tech_stack data to normalized skills
 */

class SkillMigration {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Migrate all users' tech_stack to user_skills table
     */
    public function migrateUserSkills() {
        try {
            // Get all users with tech_stack data
            $stmt = $this->pdo->query("SELECT id, name, email, tech_stack, role FROM users WHERE tech_stack IS NOT NULL AND tech_stack != ''");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $migrated_count = 0;
            $skill_matches = 0;
            
            foreach ($users as $user) {
                $skills = $this->extractSkillsFromTechStack($user['tech_stack']);
                
                foreach ($skills as $skill_name) {
                    // Find or create skill
                    $skill_id = $this->findOrCreateSkill($skill_name);
                    
                    if ($skill_id) {
                        // Insert user skill if not exists
                        $stmt = $this->pdo->prepare("
                            INSERT IGNORE INTO user_skills (user_id, skill_id, proficiency_level) 
                            VALUES (?, ?, ?)
                        ");
                        
                        // Determine proficiency level based on role
                        $proficiency = $user['role'] === 'mentor' ? 'advanced' : 'intermediate';
                        
                        if ($stmt->execute([$user['id'], $skill_id, $proficiency])) {
                            $skill_matches++;
                        }
                    }
                }
                
                $migrated_count++;
            }
            
            return [
                'success' => true,
                'migrated_users' => $migrated_count,
                'skill_matches' => $skill_matches,
                'message' => "Successfully migrated {$migrated_count} users with {$skill_matches} skill matches"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Extract skills from tech_stack text
     */
    private function extractSkillsFromTechStack($tech_stack) {
        if (empty($tech_stack)) {
            return [];
        }
        
        // Split by common separators and clean up
        $skills = preg_split('/[,;|\n]+/', $tech_stack);
        $normalized_skills = [];
        
        foreach ($skills as $skill) {
            $skill = trim($skill);
            if (!empty($skill)) {
                $normalized_skills[] = $this->normalizeSkill($skill);
            }
        }
        
        return array_unique($normalized_skills);
    }
    
    /**
     * Normalize skill names
     */
    private function normalizeSkill($skill) {
        $skill = strtolower(trim($skill));
        
        // Handle common variations
        $variations = [
            'js' => 'javascript',
            'ts' => 'typescript',
            'py' => 'python',
            'react.js' => 'react',
            'vue' => 'vue.js',
            'node' => 'node.js',
            'express' => 'express.js',
            'mongo' => 'mongodb',
            'postgres' => 'postgresql',
            'aws' => 'aws',
            'gcp' => 'google cloud',
            'k8s' => 'kubernetes',
            'ml' => 'machine learning',
            'ai' => 'machine learning',
            'ui' => 'ui/ux design',
            'ux' => 'ui/ux design'
        ];
        
        return $variations[$skill] ?? $skill;
    }
    
    /**
     * Find existing skill or create new one
     */
    private function findOrCreateSkill($skill_name) {
        // First try to find exact match
        $stmt = $this->pdo->prepare("SELECT id FROM skills WHERE LOWER(name) = LOWER(?)");
        $stmt->execute([$skill_name]);
        $skill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($skill) {
            return $skill['id'];
        }
        
        // Try to find similar skill
        $stmt = $this->pdo->prepare("SELECT id, name FROM skills WHERE LOWER(name) LIKE LOWER(?)");
        $stmt->execute(['%' . $skill_name . '%']);
        $similar_skill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($similar_skill) {
            return $similar_skill['id'];
        }
        
        // Create new skill
        $category = $this->determineSkillCategory($skill_name);
        $stmt = $this->pdo->prepare("INSERT INTO skills (name, category) VALUES (?, ?)");
        
        if ($stmt->execute([$skill_name, $category])) {
            return $this->pdo->lastInsertId();
        }
        
        return null;
    }
    
    /**
     * Determine skill category based on skill name
     */
    private function determineSkillCategory($skill_name) {
        $skill_lower = strtolower($skill_name);
        
        $categories = [
            'programming' => ['javascript', 'python', 'java', 'c++', 'c#', 'php', 'typescript', 'go', 'rust', 'swift', 'kotlin'],
            'frontend' => ['react', 'vue.js', 'angular', 'html', 'css', 'sass', 'bootstrap', 'tailwind', 'jquery'],
            'backend' => ['node.js', 'express.js', 'django', 'flask', 'spring', 'laravel', 'asp.net', 'fastapi'],
            'database' => ['mysql', 'postgresql', 'mongodb', 'redis', 'sqlite', 'oracle', 'cassandra', 'firebase'],
            'cloud' => ['aws', 'azure', 'google cloud', 'heroku', 'digitalocean'],
            'devops' => ['docker', 'kubernetes', 'jenkins', 'git', 'linux', 'nginx', 'apache'],
            'mobile' => ['react native', 'flutter', 'ios', 'android', 'xamarin'],
            'ai' => ['machine learning', 'deep learning', 'tensorflow', 'pytorch', 'scikit-learn'],
            'data' => ['data analysis', 'pandas', 'numpy', 'matplotlib', 'tableau'],
            'design' => ['ui/ux design', 'figma', 'photoshop', 'illustrator']
        ];
        
        foreach ($categories as $category => $skills) {
            foreach ($skills as $skill) {
                if (strpos($skill_lower, $skill) !== false || strpos($skill, $skill_lower) !== false) {
                    return $category;
                }
            }
        }
        
        return 'general';
    }
    
    /**
     * Get migration statistics
     */
    public function getMigrationStats() {
        $stats = [];
        
        $stats['users_with_tech_stack'] = $this->pdo->query("
            SELECT COUNT(*) FROM users 
            WHERE tech_stack IS NOT NULL AND tech_stack != ''
        ")->fetchColumn();
        
        $stats['users_with_skills'] = $this->pdo->query("
            SELECT COUNT(DISTINCT user_id) FROM user_skills
        ")->fetchColumn();
        
        $stats['total_skills'] = $this->pdo->query("SELECT COUNT(*) FROM skills")->fetchColumn();
        
        $stats['total_user_skills'] = $this->pdo->query("SELECT COUNT(*) FROM user_skills")->fetchColumn();
        
        return $stats;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $migration = new SkillMigration($pdo);
    
    switch ($_POST['action']) {
        case 'migrate':
            $result = $migration->migrateUserSkills();
            echo json_encode($result);
            break;
            
        case 'stats':
            $stats = $migration->getMigrationStats();
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

// Get current stats for display
$migration = new SkillMigration($pdo);
$stats = $migration->getMigrationStats();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrate User Skills - HackMate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-50">
    <div class="min-h-screen py-12">
        <div class="max-w-4xl mx-auto px-4">
            <div class="bg-white rounded-xl shadow-lg p-8">
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">
                        <i class="fas fa-database text-blue-600 mr-3"></i>
                        User Skills Migration
                    </h1>
                    <p class="text-gray-600">Convert existing tech_stack data to normalized skills system</p>
                </div>
                
                <!-- Current Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-blue-50 rounded-lg p-6 text-center">
                        <div class="text-3xl font-bold text-blue-600 mb-2"><?php echo $stats['users_with_tech_stack']; ?></div>
                        <div class="text-sm text-gray-600">Users with Tech Stack</div>
                    </div>
                    
                    <div class="bg-green-50 rounded-lg p-6 text-center">
                        <div class="text-3xl font-bold text-green-600 mb-2"><?php echo $stats['users_with_skills']; ?></div>
                        <div class="text-sm text-gray-600">Users with Skills</div>
                    </div>
                    
                    <div class="bg-purple-50 rounded-lg p-6 text-center">
                        <div class="text-3xl font-bold text-purple-600 mb-2"><?php echo $stats['total_skills']; ?></div>
                        <div class="text-sm text-gray-600">Total Skills</div>
                    </div>
                    
                    <div class="bg-yellow-50 rounded-lg p-6 text-center">
                        <div class="text-3xl font-bold text-yellow-600 mb-2"><?php echo $stats['total_user_skills']; ?></div>
                        <div class="text-sm text-gray-600">User-Skill Mappings</div>
                    </div>
                </div>
                
                <!-- Migration Controls -->
                <div class="bg-gray-50 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Migration Actions</h3>
                    
                    <div class="flex flex-wrap gap-4">
                        <button onclick="runMigration()" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors flex items-center">
                            <i class="fas fa-play mr-2"></i>
                            <span id="migrate-btn-text">Run Migration</span>
                            <div id="migrate-spinner" class="ml-2 hidden">
                                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                            </div>
                        </button>
                        
                        <button onclick="refreshStats()" 
                                class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg font-medium transition-colors flex items-center">
                            <i class="fas fa-refresh mr-2"></i>
                            Refresh Stats
                        </button>
                        
                        <a href="ai_mentor_recommendations.php" 
                           class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg font-medium transition-colors flex items-center">
                            <i class="fas fa-robot mr-2"></i>
                            Go to AI Recommendations
                        </a>
                    </div>
                </div>
                
                <!-- Migration Log -->
                <div class="bg-white border border-gray-200 rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Migration Log</h3>
                    </div>
                    <div id="migration-log" class="p-6 min-h-32 max-h-64 overflow-y-auto">
                        <p class="text-gray-500 italic">No migration run yet. Click "Run Migration" to start.</p>
                    </div>
                </div>
                
                <!-- Back to Admin -->
                <div class="mt-8 text-center">
                    <a href="dashboard.php" class="text-blue-600 hover:text-blue-800 font-medium">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Admin Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        async function runMigration() {
            const btn = document.getElementById('migrate-btn-text');
            const spinner = document.getElementById('migrate-spinner');
            const log = document.getElementById('migration-log');
            
            btn.textContent = 'Running...';
            spinner.classList.remove('hidden');
            
            log.innerHTML = '<p class="text-blue-600"><i class="fas fa-spinner fa-spin mr-2"></i>Starting migration...</p>';
            
            try {
                const formData = new FormData();
                formData.append('action', 'migrate');
                
                const response = await fetch('migrate_user_skills.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    log.innerHTML = `
                        <div class="space-y-2">
                            <p class="text-green-600"><i class="fas fa-check-circle mr-2"></i>Migration completed successfully!</p>
                            <p class="text-gray-700">• Migrated users: ${result.migrated_users}</p>
                            <p class="text-gray-700">• Skill matches created: ${result.skill_matches}</p>
                            <p class="text-sm text-gray-500 mt-2">${result.message}</p>
                        </div>
                    `;
                    
                    // Refresh stats
                    await refreshStats();
                } else {
                    log.innerHTML = `<p class="text-red-600"><i class="fas fa-exclamation-circle mr-2"></i>Migration failed: ${result.error}</p>`;
                }
            } catch (error) {
                log.innerHTML = `<p class="text-red-600"><i class="fas fa-exclamation-circle mr-2"></i>Error: ${error.message}</p>`;
            } finally {
                btn.textContent = 'Run Migration';
                spinner.classList.add('hidden');
            }
        }
        
        async function refreshStats() {
            try {
                const formData = new FormData();
                formData.append('action', 'stats');
                
                const response = await fetch('migrate_user_skills.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update stats display
                    const stats = result.stats;
                    document.querySelector('.bg-blue-50 .text-3xl').textContent = stats.users_with_tech_stack;
                    document.querySelector('.bg-green-50 .text-3xl').textContent = stats.users_with_skills;
                    document.querySelector('.bg-purple-50 .text-3xl').textContent = stats.total_skills;
                    document.querySelector('.bg-yellow-50 .text-3xl').textContent = stats.total_user_skills;
                }
            } catch (error) {
                console.error('Failed to refresh stats:', error);
            }
        }
    </script>
</body>
</html>