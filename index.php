<?php
// Include session configuration first
require_once 'includes/session_config.php';

// Always show splash screen first, unless coming from splash
if (!isset($_GET['from_splash'])) {
    header('Location: splash.php');
    exit();
}

if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['user_role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            exit();
        case 'mentor':
            header('Location: mentor/dashboard.php');
            exit();
        case 'participant':
            header('Location: participant/dashboard.php');
            exit();
        case 'volunteer':
            header('Location: volunteer/dashboard.php');
            exit();
        default:
            header('Location: login.php');
            exit();
    }
} else {
    header('Location: login.php');
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HackMate - Hackathon Management System</title>
    
    <!-- Primary Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    
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
    <meta name="description" content="Complete hackathon management system for organizers, mentors, and participants">
    <meta name="keywords" content="hackathon, management, system, teams, projects, coding, competition">
    <meta name="author" content="HackMate Team">
    
    <!-- Social Media Meta Tags -->
    <meta property="og:title" content="HackMate - Hackathon Management System">
    <meta property="og:description" content="Complete hackathon management system for organizers, mentors, and participants">
    <meta property="og:image" content="/assets/icons/icon-512x512.png">
    <meta property="og:url" content="/">
    <meta property="og:type" content="website">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="HackMate - Hackathon Management System">
    <meta name="twitter:description" content="Complete hackathon management system for organizers, mentors, and participants">
    <meta name="twitter:image" content="/assets/icons/icon-512x512.png">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/icon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/icon-72x72.png">
    
    <!-- Preload critical resources -->
    <link rel="preload" href="/assets/js/pwa.js" as="script">
    <link rel="preload" href="/sw.js" as="script">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <div class="mx-auto h-20 w-20 bg-indigo-600 rounded-full flex items-center justify-center mb-6">
                    <i class="fas fa-laptop-code text-white text-3xl"></i>
                </div>
                <h2 class="text-3xl font-extrabold text-gray-900 mb-2">
                    Hackathon Management System
                </h2>
                <p class="text-gray-600 mb-8">
                    Manage your hackathon events with ease
                </p>
            </div>

            <div class="bg-white rounded-lg shadow-xl p-8">
                <div class="space-y-6">
                    <div class="text-center">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">Welcome!</h3>
                        <p class="text-gray-600 mb-6">
                            Please login to access your dashboard
                        </p>
                    </div>

                    <div class="space-y-4">
                        <a href="login.php" 
                           class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Login to Dashboard
                        </a>
                        
                        <div class="text-center">
                            <p class="text-sm text-gray-600">
                                New user? Contact your administrator for account creation.
                            </p>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-6">
                        <div class="text-center">
                            <h4 class="text-lg font-medium text-gray-900 mb-4">System Features</h4>
                            <div class="grid grid-cols-2 gap-4 text-sm text-gray-600">
                                <div class="flex items-center">
                                    <i class="fas fa-users text-indigo-600 mr-2"></i>
                                    Team Management
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-file-upload text-indigo-600 mr-2"></i>
                                    Project Submissions
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-star text-indigo-600 mr-2"></i>
                                    Mentor Scoring
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-chart-bar text-indigo-600 mr-2"></i>
                                    Analytics Dashboard
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center">
                <p class="text-sm text-gray-500">
                    © 2025 Hackathon Management System. All rights reserved.
                </p>
            </div>
        </div>
    </div>

    <!-- PWA Scripts -->
    <script src="/assets/js/pwa.js"></script>
    <script>
        // Additional PWA initialization for index page
        document.addEventListener('DOMContentLoaded', function() {
            // Check if PWA is available
            if ('serviceWorker' in navigator) {
                console.log('PWA features available');
                
                // Show PWA install prompt after 5 seconds if not installed
                setTimeout(() => {
                    if (window.pwaManager && !window.pwaManager.isInstalled) {
                        const installHint = document.createElement('div');
                        installHint.className = 'fixed top-4 left-4 bg-indigo-600 text-white px-4 py-2 rounded-lg shadow-lg text-sm z-50';
                        installHint.innerHTML = '<i class="fas fa-mobile-alt mr-2"></i>Install as app for better experience!';
                        document.body.appendChild(installHint);
                        
                        setTimeout(() => {
                            if (installHint.parentNode) {
                                installHint.style.opacity = '0';
                                setTimeout(() => installHint.remove(), 300);
                            }
                        }, 5000);
                    }
                }, 5000);
            }
        });
    </script>
</body>
</html>
