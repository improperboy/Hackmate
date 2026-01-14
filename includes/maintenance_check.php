<?php
/**
 * Maintenance Mode Check
 * Include this file to check if the system is in maintenance mode
 */

require_once 'system_settings.php';

// Check if maintenance mode is enabled
if (isMaintenanceMode()) {
    // Allow admin users to bypass maintenance mode
    session_start();
    $bypass_maintenance = false;
    
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user && $user['role'] === 'admin') {
                $bypass_maintenance = true;
            }
        } catch (PDOException $e) {
            // If there's a database error, show maintenance page
        }
    }
    
    if (!$bypass_maintenance) {
        $hackathon_info = getHackathonInfo();
        
        http_response_code(503);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Maintenance - <?php echo htmlspecialchars($hackathon_info['name']); ?></title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        </head>
        <body class="bg-gray-100 min-h-screen flex items-center justify-center">
            <div class="max-w-md mx-auto bg-white rounded-lg shadow-lg p-8 text-center">
                <div class="mb-6">
                    <i class="fas fa-tools text-6xl text-yellow-500 mb-4"></i>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">System Maintenance</h1>
                    <p class="text-gray-600">
                        <?php echo htmlspecialchars($hackathon_info['name']); ?> is currently undergoing maintenance.
                    </p>
                </div>
                
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                We're working to improve your experience. Please check back shortly.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="text-sm text-gray-500">
                    <p>For urgent matters, contact us at:</p>
                    <a href="mailto:<?php echo htmlspecialchars($hackathon_info['contact_email']); ?>" 
                       class="text-blue-600 hover:text-blue-800 font-medium">
                        <?php echo htmlspecialchars($hackathon_info['contact_email']); ?>
                    </a>
                </div>
                
                <div class="mt-6">
                    <button onclick="location.reload()" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md transition-colors">
                        <i class="fas fa-refresh mr-2"></i>
                        Try Again
                    </button>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
?>