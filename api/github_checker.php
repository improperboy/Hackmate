<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Ensure user is authenticated
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];

switch ($method) {
    case 'POST':
        checkGitHubRepository();
        break;
    case 'GET':
        getSubmittedRepositories();
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function checkGitHubRepository() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['github_url'])) {
        http_response_code(400);
        echo json_encode(['error' => 'GitHub URL is required']);
        return;
    }
    
    $github_url = trim($input['github_url']);
    
    // Validate GitHub URL format
    if (!isValidGitHubUrl($github_url)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid GitHub URL format. Please use: https://github.com/username/repository']);
        return;
    }
    
    // Normalize URL (remove trailing slash, convert to lowercase)
    $github_url = normalizeGitHubUrl($github_url);
    
    // Extract owner and repository name
    $repo_info = extractRepoInfo($github_url);
    if (!$repo_info) {
        http_response_code(400);
        echo json_encode(['error' => 'Could not extract repository information from URL']);
        return;
    }
    
    try {
        // Check if repository already exists in database
        $stmt = $pdo->prepare("SELECT id, submitted_by, status FROM github_repositories WHERE github_url = ?");
        $stmt->execute([$github_url]);
        $existing_repo = $stmt->fetch();
        
        if ($existing_repo) {
            http_response_code(409);
            echo json_encode([
                'error' => 'Please do original submission',
                'message' => 'This repository has already been submitted',
                'existing_submission' => [
                    'id' => $existing_repo['id'],
                    'status' => $existing_repo['status']
                ]
            ]);
            return;
        }
        
        // Check if repository exists on GitHub
        $github_data = checkGitHubRepoExists($repo_info['owner'], $repo_info['name']);
        
        if (!$github_data) {
            http_response_code(404);
            echo json_encode([
                'error' => 'Repository not found on GitHub',
                'message' => 'The specified repository does not exist or is private'
            ]);
            return;
        }
        
        // Repository is valid and unique, save to database
        $stmt = $pdo->prepare("
            INSERT INTO github_repositories 
            (github_url, repository_name, repository_owner, submitted_by, status, github_data, verification_date) 
            VALUES (?, ?, ?, ?, 'verified', ?, NOW())
        ");
        
        $stmt->execute([
            $github_url,
            $repo_info['name'],
            $repo_info['owner'],
            $user_id,
            json_encode($github_data)
        ]);
        
        $repo_id = $pdo->lastInsertId();
        
        // Log activity
        logActivity($user_id, 'SUBMIT', 'github_repository', $repo_id, [
            'repository_url' => $github_url,
            'repository_name' => $repo_info['name'],
            'repository_owner' => $repo_info['owner']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Repository verified and submitted successfully',
            'repository' => [
                'id' => $repo_id,
                'url' => $github_url,
                'name' => $repo_info['name'],
                'owner' => $repo_info['owner'],
                'github_data' => $github_data
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("GitHub checker error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
    }
}

function getSubmittedRepositories() {
    global $pdo, $user_id;
    
    try {
        $stmt = $pdo->prepare("
            SELECT gr.*, u.name as submitted_by_name 
            FROM github_repositories gr 
            JOIN users u ON gr.submitted_by = u.id 
            WHERE gr.submitted_by = ? 
            ORDER BY gr.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $repositories = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'repositories' => $repositories
        ]);
        
    } catch (Exception $e) {
        error_log("Get repositories error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
    }
}

function isValidGitHubUrl($url) {
    $pattern = '/^https:\/\/github\.com\/[a-zA-Z0-9._-]+\/[a-zA-Z0-9._-]+\/?$/';
    return preg_match($pattern, $url);
}

function normalizeGitHubUrl($url) {
    // Remove trailing slash and convert to lowercase
    $url = rtrim($url, '/');
    return strtolower($url);
}

function extractRepoInfo($url) {
    $pattern = '/^https:\/\/github\.com\/([a-zA-Z0-9._-]+)\/([a-zA-Z0-9._-]+)\/?$/';
    if (preg_match($pattern, $url, $matches)) {
        return [
            'owner' => $matches[1],
            'name' => $matches[2]
        ];
    }
    return false;
}

function checkGitHubRepoExists($owner, $repo) {
    $api_url = "https://api.github.com/repos/{$owner}/{$repo}";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: HackMate-GitHub-Checker/1.0',
                'Accept: application/vnd.github.v3+json'
            ],
            'timeout' => 10
        ]
    ]);
    
    $response = @file_get_contents($api_url, false, $context);
    
    if ($response === false) {
        return false;
    }
    
    $data = json_decode($response, true);
    
    // Check if repository exists and is public
    if (isset($data['id']) && !isset($data['message'])) {
        return [
            'id' => $data['id'],
            'name' => $data['name'],
            'full_name' => $data['full_name'],
            'description' => $data['description'] ?? '',
            'language' => $data['language'] ?? '',
            'stars' => $data['stargazers_count'] ?? 0,
            'forks' => $data['forks_count'] ?? 0,
            'created_at' => $data['created_at'] ?? '',
            'updated_at' => $data['updated_at'] ?? '',
            'private' => $data['private'] ?? false
        ];
    }
    
    return false;
}

function logActivity($user_id, $action, $entity_type, $entity_id, $details) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $action,
            $entity_type,
            $entity_id,
            json_encode($details),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Activity logging error: " . $e->getMessage());
    }
}
?>