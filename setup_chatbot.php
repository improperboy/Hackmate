<?php
require_once 'includes/db.php';
require_once 'includes/auth_check.php';

// Only allow admin to run setup
checkAuth('admin');

$setup_complete = false;
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create chatbot_logs table
        $sql = "CREATE TABLE IF NOT EXISTS chatbot_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            question TEXT NOT NULL,
            response TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        $success_message = "Chatbot database table created successfully!";
        $setup_complete = true;
        
    } catch (Exception $e) {
        $error_message = "Error creating database table: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chatbot Setup - HackMate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-robot text-blue-600 text-2xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-900">AI Chatbot Setup</h1>
                <p class="text-gray-600 mt-2">Configure your HackMate AI Assistant</p>
            </div>

            <?php if ($error_message): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                        <span class="text-red-800"><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-2"></i>
                        <span class="text-green-800"><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$setup_complete): ?>
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Setup Instructions</h2>
                    
                    <div class="space-y-6">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                            <h3 class="font-semibold text-yellow-900 mb-3">
                                <i class="fas fa-key mr-2"></i>
                                Step 1: Get Gemini API Key
                            </h3>
                            <ol class="list-decimal list-inside space-y-2 text-yellow-800">
                                <li>Go to <a href="https://makersuite.google.com/app/apikey" target="_blank" class="text-blue-600 hover:underline">Google AI Studio</a></li>
                                <li>Sign in with your Google account</li>
                                <li>Click "Create API Key"</li>
                                <li>Copy your API key</li>
                                <li>Open <code class="bg-yellow-100 px-2 py-1 rounded">api/chatbot.php</code></li>
                                <li>Replace <code class="bg-yellow-100 px-2 py-1 rounded">YOUR_GEMINI_API_KEY</code> with your actual API key</li>
                            </ol>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                            <h3 class="font-semibold text-blue-900 mb-3">
                                <i class="fas fa-database mr-2"></i>
                                Step 2: Create Database Table
                            </h3>
                            <p class="text-blue-800 mb-4">Click the button below to create the required database table for logging chatbot conversations.</p>
                            
                            <form method="POST">
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                                    <i class="fas fa-database mr-2"></i>
                                    Create Database Table
                                </button>
                            </form>
                        </div>

                        <div class="bg-green-50 border border-green-200 rounded-lg p-6">
                            <h3 class="font-semibold text-green-900 mb-3">
                                <i class="fas fa-cog mr-2"></i>
                                Step 3: Configure Domain (Optional)
                            </h3>
                            <p class="text-green-800 mb-2">Update the base URL in <code class="bg-green-100 px-2 py-1 rounded">api/chatbot.php</code>:</p>
                            <code class="block bg-green-100 p-3 rounded text-sm">
                                $base_url = 'https://your-domain.com'; // Replace with your actual domain
                            </code>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center">
                    <div class="bg-green-50 border border-green-200 rounded-lg p-8 mb-6">
                        <i class="fas fa-check-circle text-green-500 text-4xl mb-4"></i>
                        <h2 class="text-2xl font-semibold text-green-900 mb-2">Setup Complete!</h2>
                        <p class="text-green-800">Your AI chatbot is now ready to use. Don't forget to:</p>
                        <ul class="list-disc list-inside mt-4 text-green-800 space-y-1">
                            <li>Add your Gemini API key to <code class="bg-green-100 px-2 py-1 rounded">api/chatbot.php</code></li>
                            <li>Update the domain URL if needed</li>
                            <li>Test the chatbot on your dashboards</li>
                        </ul>
                    </div>
                    
                    <div class="flex justify-center space-x-4">
                        <a href="admin/dashboard.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                            <i class="fas fa-tachometer-alt mr-2"></i>
                            Go to Admin Dashboard
                        </a>
                        <a href="api/chatbot.php" target="_blank" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                            <i class="fas fa-code mr-2"></i>
                            Edit API File
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mt-8 pt-8 border-t border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Features Overview</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="flex items-start space-x-3">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-user-shield text-blue-600"></i>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900">Role-Based Responses</h4>
                            <p class="text-sm text-gray-600">Different responses based on user roles (admin, participant, mentor)</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start space-x-3">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-link text-green-600"></i>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900">Smart Link Generation</h4>
                            <p class="text-sm text-gray-600">Automatically provides relevant links to platform features</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start space-x-3">
                        <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-brain text-purple-600"></i>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900">Natural Language Understanding</h4>
                            <p class="text-sm text-gray-600">Powered by Google's Gemini AI for intelligent responses</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start space-x-3">
                        <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-chart-line text-orange-600"></i>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900">Conversation Logging</h4>
                            <p class="text-sm text-gray-600">All conversations are logged for analytics and improvement</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>