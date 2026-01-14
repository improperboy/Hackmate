<?php
// Include session configuration first
require_once 'includes/session_config.php';
require_once 'includes/db.php';
require_once 'includes/maintenance_check.php';

$message = '';
$error = '';

// Check if user was logged out successfully
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $message = 'You have been successfully logged out.';
}

// Check if user was redirected due to session timeout
if (isset($_GET['timeout']) && $_GET['timeout'] === '1') {
    $error = 'Your session has expired. Please log in again.';
}

// Prevent access if already logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // Redirect based on user role
    $role = $_SESSION['user_role'] ?? '';
    switch ($role) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'mentor':
            header('Location: mentor/dashboard.php');
            break;
        case 'participant':
            header('Location: participant/dashboard.php');
            break;
        case 'volunteer':
            header('Location: volunteer/dashboard.php');
            break;
        default:
            // Clear invalid session
            session_unset();
            session_destroy();
            break;
    }
    if ($role) {
        exit();
    }
}

if ($_POST) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['last_regeneration'] = time();
        
        // Set session fingerprint for security
        $_SESSION['fingerprint'] = generateSessionFingerprint();
        
        // Optional: Update last login time in database
        try {
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
        } catch (Exception $e) {
            error_log("Failed to update last login: " . $e->getMessage());
        }
        
        // Redirect based on role
        switch ($user['role']) {
            case 'admin':
                header('Location: admin/dashboard.php');
                break;
            case 'mentor':
                header('Location: mentor/dashboard.php');
                break;
            case 'participant':
                header('Location: participant/dashboard.php');
                break;
            case 'volunteer':
                header('Location: volunteer/dashboard.php');
                break;
            default:
                $error = 'Unknown user role.';
                session_unset();
                session_destroy();
                break;
        }
        exit();
    } else {
        $error = 'Invalid email or password.';
        
        // Optional: Log failed login attempts
        error_log("Failed login attempt for email: " . $email . " from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - HackMate</title>
    
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
    <meta name="description" content="Login to HackMate - Complete hackathon management system">
    <meta name="keywords" content="hackathon, login, management, system, teams, projects">
    <meta name="author" content="HackMate Team">
    
    <!-- Social Media Meta Tags -->
    <meta property="og:title" content="Login - HackMate">
    <meta property="og:description" content="Login to HackMate - Complete hackathon management system">
    <meta property="og:image" content="/assets/icons/icon-512x512.png">
    <meta property="og:url" content="/login.php">
    <meta property="og:type" content="website">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/icon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/icon-72x72.png">
    
    <!-- Preload critical resources -->
    <link rel="preload" href="/assets/js/pwa.js" as="script">
    <link rel="preload" href="/sw.js" as="script">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
        <div class="text-center mb-6">
            <i class="fas fa-laptop-code text-purple-600 text-5xl mb-4"></i>
            <h2 class="text-2xl font-bold text-gray-800">Hackathon Login</h2>
            <p class="text-gray-600 text-sm">Sign in to your account</p>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-envelope text-gray-400"></i>
                    </div>
                    <input type="email" id="email" name="email" required
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                           placeholder="you@example.com">
                </div>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-lock text-gray-400"></i>
                    </div>
                    <input type="password" id="password" name="password" required
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                           placeholder="••••••••">
                </div>
            </div>

            <div>
                <button type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Sign In
                </button>
            </div>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
                Don't have an account?
                <a href="register.php" class="font-medium text-blue-600 hover:text-blue-500">Register here</a>
            </p>
        </div>
    </div>

    <!-- PWA Scripts -->
    <script src="/assets/js/pwa.js"></script>
    <script>
        // Additional PWA initialization for login page
        document.addEventListener('DOMContentLoaded', function() {
            // Check if PWA is available
            if ('serviceWorker' in navigator) {
                console.log('PWA features available on login page');
            }
        });
    </script>
</body>
</html>
