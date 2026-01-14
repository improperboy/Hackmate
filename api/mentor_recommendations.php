<?php
require_once '../includes/db.php';
require_once '../includes/session_config.php';

header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    startSecureSession();
}

// Debug information (remove in production)
$debug_info = [
    'session_status' => session_status(),
    'user_id' => $_SESSION['user_id'] ?? 'not set',
    'user_role' => $_SESSION['user_role'] ?? 'not set',
    'role' => $_SESSION['role'] ?? 'not set'
];

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode([
        'error' => 'No active session',
        'debug' => $debug_info
    ]);
    exit;
}

// Check admin role - try both 'role' and 'user_role' session variables
$user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? null;

if ($user_role !== 'admin') {
    // Try to get role from database
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || $user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode([
                'error' => 'Unauthorized access - Admin role required',
                'current_role' => $user_role,
                'db_role' => $user['role'] ?? 'user not found',
                'debug' => $debug_info
            ]);
            exit;
        }

        // Update session with correct role
        $_SESSION['role'] = $user['role'];
        $_SESSION['user_role'] = $user['role'];
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Database error during authentication',
            'debug' => $debug_info
        ]);
        exit;
    }
}

class MentorRecommendationEngine
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Generate mentor recommendations for participants
     */
    public function generateRecommendations($participant_id = null)
    {
        try {
            // Get all participants or specific participant
            $participants_query = "SELECT id, name, email, tech_stack FROM users WHERE role = 'participant'";
            $params = [];

            if ($participant_id) {
                $participants_query .= " AND id = ?";
                $params[] = $participant_id;
            }

            $stmt = $this->pdo->prepare($participants_query);
            $stmt->execute($params);
            $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get all mentors with their skills
            $mentors = $this->getMentorsWithSkills();

            $recommendations = [];

            foreach ($participants as $participant) {
                $participant_skills = $this->extractSkillsFromTechStack($participant['tech_stack']);

                foreach ($mentors as $mentor) {
                    $match_data = $this->calculateMatchScore($participant_skills, $mentor['skills']);

                    if ($match_data['score'] > 0) {
                        $recommendation = [
                            'participant_id' => $participant['id'],
                            'participant_name' => $participant['name'],
                            'participant_email' => $participant['email'],
                            'mentor_id' => $mentor['id'],
                            'mentor_name' => $mentor['name'],
                            'mentor_email' => $mentor['email'],
                            'match_score' => $match_data['score'],
                            'skill_matches' => $match_data['matches'],
                            'recommendation_reason' => $this->generateRecommendationReason($match_data)
                        ];

                        $recommendations[] = $recommendation;
                    }
                }
            }

            // Sort by match score descending
            usort($recommendations, function ($a, $b) {
                return $b['match_score'] <=> $a['match_score'];
            });

            // Save recommendations to database
            $this->saveRecommendations($recommendations);

            return $recommendations;
        } catch (Exception $e) {
            throw new Exception("Error generating recommendations: " . $e->getMessage());
        }
    }

    /**
     * Get mentors with their skills
     */
    private function getMentorsWithSkills()
    {
        $stmt = $this->pdo->query("
            SELECT u.id, u.name, u.email, u.tech_stack
            FROM users u 
            WHERE u.role = 'mentor'
        ");

        $mentors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($mentors as &$mentor) {
            $mentor['skills'] = $this->extractSkillsFromTechStack($mentor['tech_stack']);
        }

        return $mentors;
    }

    /**
     * Extract and normalize skills from tech_stack text
     */
    private function extractSkillsFromTechStack($tech_stack)
    {
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
     * Normalize skill names for better matching
     */
    private function normalizeSkill($skill)
    {
        // Convert to lowercase and remove common variations
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
            'aws' => 'amazon web services',
            'gcp' => 'google cloud',
            'k8s' => 'kubernetes',
            'ml' => 'machine learning',
            'ai' => 'artificial intelligence',
            'ui' => 'user interface',
            'ux' => 'user experience'
        ];

        return $variations[$skill] ?? $skill;
    }

    /**
     * Calculate match score between participant and mentor skills
     */
    private function calculateMatchScore($participant_skills, $mentor_skills)
    {
        if (empty($participant_skills) || empty($mentor_skills)) {
            return ['score' => 0, 'matches' => []];
        }

        $matches = [];
        $total_score = 0;

        foreach ($participant_skills as $p_skill) {
            foreach ($mentor_skills as $m_skill) {
                $similarity = $this->calculateSkillSimilarity($p_skill, $m_skill);

                if ($similarity > 0.7) { // 70% similarity threshold
                    $matches[] = [
                        'participant_skill' => $p_skill,
                        'mentor_skill' => $m_skill,
                        'similarity' => $similarity
                    ];
                    $total_score += $similarity;
                }
            }
        }

        // Normalize score (0-100)
        $normalized_score = min(100, ($total_score / max(count($participant_skills), 1)) * 100);

        return [
            'score' => round($normalized_score, 2),
            'matches' => $matches
        ];
    }

    /**
     * Calculate similarity between two skills using string similarity
     */
    private function calculateSkillSimilarity($skill1, $skill2)
    {
        // Exact match
        if ($skill1 === $skill2) {
            return 1.0;
        }

        // Check if one skill contains the other
        if (strpos($skill1, $skill2) !== false || strpos($skill2, $skill1) !== false) {
            return 0.9;
        }

        // Use Levenshtein distance for fuzzy matching
        $max_len = max(strlen($skill1), strlen($skill2));
        if ($max_len == 0) return 0;

        $distance = levenshtein($skill1, $skill2);
        $similarity = 1 - ($distance / $max_len);

        return max(0, $similarity);
    }

    /**
     * Generate human-readable recommendation reason
     */
    private function generateRecommendationReason($match_data)
    {
        $matches = $match_data['matches'];
        $score = $match_data['score'];

        if (empty($matches)) {
            return "General mentoring support";
        }

        $skill_names = array_unique(array_column($matches, 'mentor_skill'));
        $skill_count = count($skill_names);

        if ($skill_count == 1) {
            return "Strong expertise in " . $skill_names[0];
        } elseif ($skill_count <= 3) {
            return "Expertise in " . implode(', ', array_slice($skill_names, 0, -1)) . " and " . end($skill_names);
        } else {
            return "Expertise in " . implode(', ', array_slice($skill_names, 0, 2)) . " and " . ($skill_count - 2) . " other technologies";
        }
    }

    /**
     * Save recommendations to database
     */
    private function saveRecommendations($recommendations)
    {
        // Clear existing recommendations
        $this->pdo->exec("DELETE FROM mentor_recommendations WHERE status = 'pending'");

        $stmt = $this->pdo->prepare("
            INSERT INTO mentor_recommendations 
            (participant_id, mentor_id, match_score, skill_match_details, recommendation_reason) 
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($recommendations as $rec) {
            $stmt->execute([
                $rec['participant_id'],
                $rec['mentor_id'],
                $rec['match_score'],
                json_encode($rec['skill_matches']),
                $rec['recommendation_reason']
            ]);
        }
    }

    /**
     * Get saved recommendations from database
     */
    public function getRecommendations($limit = 50, $min_score = 10)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                mr.*,
                p.name as participant_name,
                p.email as participant_email,
                p.tech_stack as participant_skills,
                m.name as mentor_name,
                m.email as mentor_email,
                m.tech_stack as mentor_skills
            FROM mentor_recommendations mr
            JOIN users p ON mr.participant_id = p.id
            JOIN users m ON mr.mentor_id = m.id
            WHERE mr.match_score >= ?
            ORDER BY mr.match_score DESC, mr.created_at DESC
            LIMIT ?
        ");

        $stmt->execute([$min_score, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get recommendations for a specific participant
     */
    public function getRecommendationsForParticipant($participant_id, $limit = 10)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                mr.*,
                m.name as mentor_name,
                m.email as mentor_email,
                m.tech_stack as mentor_skills
            FROM mentor_recommendations mr
            JOIN users m ON mr.mentor_id = m.id
            WHERE mr.participant_id = ?
            ORDER BY mr.match_score DESC
            LIMIT ?
        ");

        $stmt->execute([$participant_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update recommendation status
     */
    public function updateRecommendationStatus($recommendation_id, $status)
    {
        $stmt = $this->pdo->prepare("
            UPDATE mentor_recommendations 
            SET status = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");

        return $stmt->execute([$status, $recommendation_id]);
    }
}

// Handle API requests
try {
    $engine = new MentorRecommendationEngine($pdo);

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'generate':
                    $participant_id = $_GET['participant_id'] ?? null;
                    $recommendations = $engine->generateRecommendations($participant_id);
                    echo json_encode([
                        'success' => true,
                        'data' => $recommendations,
                        'count' => count($recommendations)
                    ]);
                    break;

                case 'list':
                    $limit = intval($_GET['limit'] ?? 50);
                    $min_score = floatval($_GET['min_score'] ?? 10);
                    $recommendations = $engine->getRecommendations($limit, $min_score);
                    echo json_encode([
                        'success' => true,
                        'data' => $recommendations,
                        'count' => count($recommendations)
                    ]);
                    break;

                case 'participant':
                    $participant_id = intval($_GET['participant_id'] ?? 0);
                    if ($participant_id <= 0) {
                        throw new Exception("Valid participant ID required");
                    }
                    $recommendations = $engine->getRecommendationsForParticipant($participant_id);
                    echo json_encode([
                        'success' => true,
                        'data' => $recommendations,
                        'count' => count($recommendations)
                    ]);
                    break;

                default:
                    throw new Exception("Invalid action");
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);

            switch ($action) {
                case 'update_status':
                    $recommendation_id = intval($input['recommendation_id'] ?? 0);
                    $status = $input['status'] ?? '';

                    if ($recommendation_id <= 0 || !in_array($status, ['pending', 'approved', 'rejected', 'assigned'])) {
                        throw new Exception("Invalid recommendation ID or status");
                    }

                    $success = $engine->updateRecommendationStatus($recommendation_id, $status);
                    echo json_encode(['success' => $success]);
                    break;

                default:
                    throw new Exception("Invalid action");
            }
            break;

        default:
            throw new Exception("Method not allowed");
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
