<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';

// Enable CORS for API requests
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get current user info
$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'User not found']);
    exit;
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

// Process message with rule-based chatbot
$response = processMessage($message, $user['role'], $user['name']);

// Log the conversation
logConversation($user['id'], $message, $response);

echo json_encode([
    'response' => $response,
    'user_role' => $user['role']
]);

function processMessage($message, $role, $userName) {
    $message = strtolower(trim($message));
    
    // Get role-specific responses
    $responses = getRoleResponses($role);
    
    // Check for greetings first
    if (preg_match('/^(hi|hello|hey|good morning|good afternoon|good evening)/', $message)) {
        return "Hello {$userName}! I'm your HackMate Helper. I can assist you with navigating the platform and finding the right links for your tasks. What would you like to help with today?";
    }
    
    // Check for help requests
    if (preg_match('/(help|what can you do|capabilities|features)/', $message)) {
        return getHelpResponse($role, $userName);
    }
    
    // Process message against role-specific patterns
    foreach ($responses as $pattern => $response) {
        if (preg_match($pattern, $message)) {
            return $response;
        }
    }
    
    // Check for unauthorized access attempts
    $unauthorizedResponse = checkUnauthorizedAccess($message, $role);
    if ($unauthorizedResponse) {
        return $unauthorizedResponse;
    }
    
    // Default response if no pattern matches
    return getDefaultResponse($role, $userName);
}

function getRoleResponses($role) {
    $base_url = $_SERVER['HTTP_HOST'] ?? 'hackmate.ct.ws';
    $protocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
    $full_base = $protocol . $base_url;
    
    $responses = [
        'admin' => [
            '/(add|create|new).*user/' => "To add a new user:\n\n1. Go to the Admin Dashboard\n2. Click on 'Manage Users'\n3. Click the 'Add New User' button\n4. Fill in the user details (name, email, role)\n5. Click 'Create User'\n\n🔗 **Direct Link**: [{$full_base}/admin/add_user.php]({$full_base}/admin/add_user.php)",
            
            '/(manage|edit|view).*user/' => "To manage users:\n\n1. Navigate to Admin Dashboard\n2. Click 'Manage Users' from the menu\n3. You can view, edit, or delete users from the table\n4. Use the search function to find specific users\n\n🔗 **Direct Link**: [{$full_base}/admin/manage_users.php]({$full_base}/admin/manage_users.php)",
            
            '/(team|approve|reject).*team/' => "To manage teams:\n\n1. Go to Admin Dashboard\n2. Click 'Teams' in the navigation\n3. Review pending team approvals\n4. Click 'Approve' or 'Reject' for each team\n5. View team details and member information\n\n🔗 **Direct Link**: [{$full_base}/admin/teams.php]({$full_base}/admin/teams.php)",
            
            '/(submission|project|view).*submission/' => "To view project submissions:\n\n1. Access Admin Dashboard\n2. Click 'View Submissions'\n3. Browse all team submissions\n4. Review project details, GitHub links, and demo videos\n5. Export submission data if needed\n\n🔗 **Direct Link**: [{$full_base}/admin/view_submissions.php]({$full_base}/admin/view_submissions.php)",
            
            '/(analytics|report|statistic)/' => "To view analytics:\n\n1. Go to Admin Dashboard\n2. Click 'Analytics' from the menu\n3. View user registration statistics\n4. Check team formation metrics\n5. Review submission and scoring data\n\n🔗 **Direct Link**: [{$full_base}/admin/analytics.php]({$full_base}/admin/analytics.php)",
            
            '/(mentor|assign).*mentor/' => "To assign mentors:\n\n1. Navigate to Admin Dashboard\n2. Go to 'Mentor Assignments'\n3. Select mentors from the dropdown\n4. Assign them to specific floors/rooms\n5. Set mentoring round schedules\n\n🔗 **Direct Link**: [{$full_base}/admin/mentor_assignments.php]({$full_base}/admin/mentor_assignments.php)",
            
            '/(support|message|help).*request/' => "To handle support requests:\n\n1. Access Admin Dashboard\n2. Click 'Support Messages'\n3. View all pending support requests\n4. Respond to user queries\n5. Mark requests as resolved\n\n🔗 **Direct Link**: [{$full_base}/admin/support_messages.php]({$full_base}/admin/support_messages.php)",
            
            '/(setting|config|system)/' => "To manage system settings:\n\n1. Go to Admin Dashboard\n2. Click 'System Settings'\n3. Configure hackathon details\n4. Set team size limits\n5. Manage registration settings\n\n🔗 **Direct Link**: [{$full_base}/admin/system_settings.php]({$full_base}/admin/system_settings.php)",
            
            '/(dashboard|home|main)/' => "Your Admin Dashboard provides:\n\n• User management tools\n• Team approval system\n• Analytics and reports\n• System configuration\n• Support message handling\n\n🔗 **Direct Link**: [{$full_base}/admin/dashboard.php]({$full_base}/admin/dashboard.php)"
        ],
        
        'participant' => [
            '/(create|make|new).*team/' => "To create a new team:\n\n1. Go to your Participant Dashboard\n2. Click 'Create Team'\n3. Enter team name and description\n4. Select a theme (optional)\n5. Add your project idea\n6. Submit for admin approval\n\n🔗 **Direct Link**: [{$full_base}/participant/create_team.php]({$full_base}/participant/create_team.php)",
            
            '/(join|find).*team/' => "To join an existing team:\n\n1. Visit your dashboard\n2. Click 'Join Team'\n3. Browse available teams\n4. Send join requests to teams you're interested in\n5. Wait for team leader approval\n\n🔗 **Direct Link**: [{$full_base}/participant/join_team.php]({$full_base}/participant/join_team.php)",
            
            '/(submit|upload).*project/' => "To submit your project:\n\n1. Go to Participant Dashboard\n2. Click 'Submit Project'\n3. Enter your GitHub repository URL\n4. Add live demo link (optional)\n5. Describe technologies used\n6. Submit before the deadline\n\n🔗 **Direct Link**: [{$full_base}/participant/submit_project.php]({$full_base}/participant/submit_project.php)",
            
            '/(ranking|leaderboard|score)/' => "To view team rankings:\n\n1. Access your dashboard\n2. Click 'View Rankings'\n3. See your team's current position\n4. View scores from different mentoring rounds\n5. Check overall leaderboard\n\n🔗 **Direct Link**: [{$full_base}/participant/rankings.php]({$full_base}/participant/rankings.php)",
            
            '/(support|help|problem)/' => "To get support:\n\n1. Go to your dashboard\n2. Click 'Support'\n3. Describe your issue\n4. Select priority level\n5. Submit your request\n6. Track response status\n\n🔗 **Direct Link**: [{$full_base}/participant/support.php]({$full_base}/participant/support.php)",
            
            '/(dashboard|home|main)/' => "Your Participant Dashboard shows:\n\n• Your team status\n• Available actions (create/join team)\n• Project submission status\n• Current rankings\n• Support options\n\n🔗 **Direct Link**: [{$full_base}/participant/dashboard.php]({$full_base}/participant/dashboard.php)"
        ],
        
        'mentor' => [
            '/(assigned|my).*team/' => "To view your assigned teams:\n\n1. Go to Mentor Dashboard\n2. Click 'Assigned Teams'\n3. See teams in your assigned location\n4. View team details and project information\n5. Access scoring interface\n\n🔗 **Direct Link**: [{$full_base}/mentor/assigned_teams.php]({$full_base}/mentor/assigned_teams.php)",
            
            '/(score|evaluate|grade).*team/' => "To score teams:\n\n1. Access Mentor Dashboard\n2. Click 'Score Teams'\n3. Select the mentoring round\n4. Enter scores for each team\n5. Add comments and feedback\n6. Submit your evaluations\n\n🔗 **Direct Link**: [{$full_base}/mentor/score_teams.php]({$full_base}/mentor/score_teams.php)",
            
            '/(schedule|time|round)/' => "To manage your schedule:\n\n1. Go to Mentor Dashboard\n2. Click 'Schedule'\n3. View mentoring round timings\n4. Check your assigned time slots\n5. See team meeting schedules\n\n🔗 **Direct Link**: [{$full_base}/mentor/schedule.php]({$full_base}/mentor/schedule.php)",
            
            '/(support|help).*team/' => "To provide team support:\n\n1. Access your dashboard\n2. Click 'Support Messages'\n3. View support requests from your assigned teams\n4. Respond to team queries\n5. Escalate complex issues if needed\n\n🔗 **Direct Link**: [{$full_base}/mentor/support_messages.php]({$full_base}/mentor/support_messages.php)",
            
            '/(dashboard|home|main)/' => "Your Mentor Dashboard provides:\n\n• Assigned teams overview\n• Scoring interface\n• Schedule management\n• Team support tools\n• Performance tracking\n\n🔗 **Direct Link**: [{$full_base}/mentor/dashboard.php]({$full_base}/mentor/dashboard.php)"
        ],
        
        'volunteer' => [
            '/(assignment|task|duty)/' => "To view your assignments:\n\n1. Go to Volunteer Dashboard\n2. Check your assigned tasks\n3. View location assignments\n4. See scheduled duties\n5. Update task status\n\n🔗 **Direct Link**: [{$full_base}/volunteer/dashboard.php]({$full_base}/volunteer/dashboard.php)",
            
            '/(support|help).*participant/' => "To help participants:\n\n1. Access Volunteer Dashboard\n2. Click 'Support'\n3. View participant support requests\n4. Provide assistance within your scope\n5. Escalate technical issues to mentors\n\n🔗 **Direct Link**: [{$full_base}/volunteer/support.php]({$full_base}/volunteer/support.php)",
            
            '/(dashboard|home|main)/' => "Your Volunteer Dashboard shows:\n\n• Your current assignments\n• Support request queue\n• Task completion status\n• Contact information for escalation\n\n🔗 **Direct Link**: [{$full_base}/volunteer/dashboard.php]({$full_base}/volunteer/dashboard.php)"
        ]
    ];
    
    return $responses[$role] ?? $responses['participant'];
}

function checkUnauthorizedAccess($message, $role) {
    // Admin-only functions
    $adminFunctions = ['add user', 'manage user', 'delete user', 'system setting', 'analytics', 'admin'];
    
    // Mentor-only functions  
    $mentorFunctions = ['score team', 'evaluate team', 'mentor assignment'];
    
    if ($role !== 'admin') {
        foreach ($adminFunctions as $func) {
            if (strpos($message, $func) !== false) {
                return "❌ **Unauthorized Access**\n\nSorry, but the functionality you're asking about requires **Administrator** privileges. You currently have **{$role}** access.\n\nIf you need admin assistance, please contact your hackathon organizers or submit a support request.";
            }
        }
    }
    
    if ($role !== 'mentor' && $role !== 'admin') {
        foreach ($mentorFunctions as $func) {
            if (strpos($message, $func) !== false) {
                return "❌ **Unauthorized Access**\n\nSorry, but the functionality you're asking about requires **Mentor** or **Administrator** privileges. You currently have **{$role}** access.\n\nIf you need mentor assistance, please submit a support request and it will be routed to the appropriate mentor.";
            }
        }
    }
    
    return null;
}

function getHelpResponse($role, $userName) {
    $capabilities = [
        'admin' => [
            '👥 **User Management**' => 'Add, edit, and manage user accounts',
            '🏆 **Team Management**' => 'Approve teams and manage registrations', 
            '📊 **Analytics & Reports**' => 'View system analytics and generate reports',
            '⚙️ **System Settings**' => 'Configure hackathon settings and preferences',
            '💬 **Support Management**' => 'Handle support requests from all users',
            '🎯 **Mentor Assignments**' => 'Assign mentors to teams and locations',
            '📝 **Submission Review**' => 'Review and evaluate project submissions'
        ],
        'participant' => [
            '🏆 **Team Creation**' => 'Create a new team for the hackathon',
            '🤝 **Join Teams**' => 'Join existing teams or send join requests',
            '📤 **Project Submission**' => 'Submit your team\'s project and demo',
            '📊 **View Rankings**' => 'Check team rankings and leaderboard',
            '💬 **Get Support**' => 'Contact support for help with any issues'
        ],
        'mentor' => [
            '👥 **Assigned Teams**' => 'View and manage your assigned teams',
            '⭐ **Score Teams**' => 'Evaluate and score team projects',
            '📅 **Schedule Management**' => 'Manage your mentoring schedule',
            '💬 **Team Support**' => 'Provide support and guidance to teams'
        ],
        'volunteer' => [
            '📋 **View Assignments**' => 'Check your volunteer tasks and duties',
            '💬 **Help Participants**' => 'Assist participants with general queries'
        ]
    ];
    
    $userCaps = $capabilities[$role] ?? $capabilities['participant'];
    
    $response = "Hi {$userName}! I'm your HackMate Helper 🤖\n\nAs a **{$role}**, here's what I can help you with:\n\n";
    
    foreach ($userCaps as $title => $description) {
        $response .= "{$title}: {$description}\n";
    }
    
    $response .= "\n💡 **How to use me:**\n";
    $response .= "• Ask me things like \"How do I create a team?\" or \"Where can I add users?\"\n";
    $response .= "• I'll provide step-by-step instructions and direct links\n";
    $response .= "• I understand natural language, so ask me anything!\n\n";
    $response .= "What would you like help with today?";
    
    return $response;
}

function getDefaultResponse($role, $userName) {
    $suggestions = [
        'admin' => [
            "\"How do I add a new user?\"",
            "\"Where can I manage teams?\"", 
            "\"How do I view analytics?\"",
            "\"Where are the system settings?\""
        ],
        'participant' => [
            "\"How do I create a team?\"",
            "\"How can I join a team?\"",
            "\"Where do I submit my project?\"",
            "\"How do I check rankings?\""
        ],
        'mentor' => [
            "\"Where are my assigned teams?\"",
            "\"How do I score teams?\"",
            "\"Where is my schedule?\"",
            "\"How do I help teams?\""
        ],
        'volunteer' => [
            "\"What are my assignments?\"",
            "\"How do I help participants?\"",
            "\"Where is my dashboard?\""
        ]
    ];
    
    $userSuggestions = $suggestions[$role] ?? $suggestions['participant'];
    $randomSuggestions = array_rand(array_flip($userSuggestions), min(3, count($userSuggestions)));
    
    $response = "I'm not sure I understand that question, {$userName}. 🤔\n\n";
    $response .= "Here are some things you can ask me:\n\n";
    
    foreach ($randomSuggestions as $suggestion) {
        $response .= "• {$suggestion}\n";
    }
    
    $response .= "\nOr simply ask \"help\" to see all my capabilities!";
    
    return $response;
}

function logConversation($user_id, $question, $response) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO chatbot_logs (user_id, question, response, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $question, $response]);
    } catch (Exception $e) {
        error_log("Failed to log conversation: " . $e->getMessage());
    }
}
?>