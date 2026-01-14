<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - HackMate Admin' : 'HackMate Admin'; ?></title>
    
    <!-- Primary Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- PWA Configuration -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#4F46E5">
    <meta name="background-color" content="#4F46E5">
    
    <!-- iOS PWA Support -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="HackMate">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
    
    <!-- Android PWA Support -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="HackMate">
    
    <!-- Windows PWA Support -->
    <meta name="msapplication-TileImage" content="/assets/icons/icon-144x144.png">
    <meta name="msapplication-TileColor" content="#4F46E5">
    <meta name="msapplication-navbutton-color" content="#4F46E5">
    
    <!-- General Meta Tags -->
    <meta name="description" content="<?php echo isset($page_description) ? $page_description : 'HackMate Admin Panel - Complete hackathon management system'; ?>">
    <meta name="keywords" content="hackathon, admin, dashboard, management, system, teams, projects">
    <meta name="author" content="HackMate Team">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/icon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/icon-72x72.png">
    
    <!-- Preload critical resources -->
    <link rel="preload" href="/assets/js/pwa.js" as="script">
    <link rel="preload" href="/sw.js" as="script">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-arrow-left"></i>
                        Back to Dashboard
                    </a>
                    <h1 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-<?php echo isset($page_icon) ? $page_icon : 'cog'; ?> text-indigo-600 mr-2"></i>
                        <?php echo isset($page_title) ? $page_title : 'Admin Panel'; ?>
                    </h1>
                </div>
                <div class="flex items-center space-x-4">
                    
                    <a href="../change_password.php" class="text-indigo-600 hover:text-indigo-800" title="Change Password">
                  
                    <a href="../logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt"></i>
                      
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4">
        <?php echo $content; ?>
    </div>

    <!-- PWA Scripts -->
    <script src="/assets/js/pwa.js"></script>
    <script>
        // Additional PWA initialization
        document.addEventListener('DOMContentLoaded', function() {
            // Check if PWA is available
            if ('serviceWorker' in navigator) {
                console.log('PWA features available');
                
                // Show notifications
                if (window.pwaManager) {
                    window.pwaManager.requestNotificationPermission = true;
                }
            }
        });
    </script>
</body>
</html>