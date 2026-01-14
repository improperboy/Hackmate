<?php
// Include session configuration first
require_once 'includes/session_config.php';
require_once 'includes/db.php';
require_once 'includes/auth_check.php';
require_once 'includes/utils.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user = getCurrentUser();
$message = '';
$error = '';

if ($_POST) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = 'Current password is incorrect.';
    } else {
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        
        if ($stmt->execute([$hashed_password, $user['id']])) {
            $message = 'Password changed successfully!';
            // Clear the POST data to prevent accidental resubmission
            $_POST = array();
        } else {
            $error = 'Failed to change password. Please try again.';
        }
    }
}

// Determine the dashboard URL based on user role
$dashboard_url = '';
switch ($user['role']) {
    case 'admin':
        $dashboard_url = 'admin/dashboard.php';
        break;
    case 'mentor':
        $dashboard_url = 'mentor/dashboard.php';
        break;
    case 'volunteer':
        $dashboard_url = 'volunteer/dashboard.php';
        break;
    case 'participant':
        $dashboard_url = 'participant/dashboard.php';
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - HackMate</title>
    
    <!-- Primary Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    
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
    <meta name="description" content="Change your password - HackMate">
    <meta name="keywords" content="hackathon, password, change, security, account">
    <meta name="author" content="HackMate Team">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/icon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/icon-72x72.png">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-lg md:text-xl font-bold text-gray-800">
                        <i class="fas fa-key text-indigo-600"></i>
                        <span class="hidden sm:inline">Change Password</span>
                        <span class="sm:hidden">Password</span>
                    </h1>
                </div>
                <div class="flex items-center space-x-2 md:space-x-4">
                    <!-- <span class="text-gray-600 text-sm md:text-base hidden sm:inline">Welcome, <?php echo $user['name']; ?></span> -->
                    <a href="<?php echo $dashboard_url; ?>" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-arrow-left"></i>
                        <span class="hidden md:inline ml-1">Back</span>
                    </a>
                    <a href="logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="hidden md:inline ml-1">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-2xl mx-auto py-6 px-4">
        <!-- User Info Card -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-<?php echo $user['role'] === 'admin' ? 'red' : ($user['role'] === 'mentor' ? 'green' : ($user['role'] === 'volunteer' ? 'orange' : 'purple')); ?>-100 text-<?php echo $user['role'] === 'admin' ? 'red' : ($user['role'] === 'mentor' ? 'green' : ($user['role'] === 'volunteer' ? 'orange' : 'purple')); ?>-600">
                    <i class="fas fa-<?php echo $user['role'] === 'admin' ? 'user-shield' : ($user['role'] === 'mentor' ? 'chalkboard-teacher' : ($user['role'] === 'volunteer' ? 'hands-helping' : 'laptop-code')); ?> text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-900"><?php echo $user['name']; ?></h3>
                    <p class="text-sm text-gray-600"><?php echo ucfirst($user['role']); ?> • <?php echo $user['email']; ?></p>
                </div>
            </div>
        </div>

        <!-- Change Password Form -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-6">
                <i class="fas fa-shield-alt text-indigo-600"></i>
                Change Your Password
            </h2>

            <!-- Security Notice -->
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>Security Tips:</strong>
                        </p>
                        <ul class="text-sm text-blue-600 mt-2 list-disc list-inside">
                            <li>Use a password that's at least 6 characters long</li>
                            <li>Include a mix of letters, numbers, and special characters</li>
                            <li>Don't reuse passwords from other accounts</li>
                            <li>Keep your password private and secure</li>
                        </ul>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" id="current_password" name="current_password" required
                               class="block w-full pl-10 pr-10 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                               placeholder="Enter your current password">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <button type="button" onclick="togglePassword('current_password')" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye" id="current_password_eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-key text-gray-400"></i>
                        </div>
                        <input type="password" id="new_password" name="new_password" required
                               class="block w-full pl-10 pr-10 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                               placeholder="Enter your new password">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <button type="button" onclick="togglePassword('new_password')" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye" id="new_password_eye"></i>
                            </button>
                        </div>
                    </div>
                    <div id="password_strength" class="mt-2"></div>
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-key text-gray-400"></i>
                        </div>
                        <input type="password" id="confirm_password" name="confirm_password" required
                               class="block w-full pl-10 pr-10 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                               placeholder="Confirm your new password">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <button type="button" onclick="togglePassword('confirm_password')" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye" id="confirm_password_eye"></i>
                            </button>
                        </div>
                    </div>
                    <div id="password_match" class="mt-2"></div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="submit"
                            class="flex-1 flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-save mr-2"></i>
                        Change Password
                    </button>
                    <a href="<?php echo $dashboard_url; ?>"
                       class="flex-1 flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- Security History (Optional Enhancement) -->
        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h3 class="text-lg font-semibold mb-4">
                <i class="fas fa-history text-gray-600"></i>
                Account Security
            </h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <div>
                        <p class="text-sm font-medium text-gray-900">Last Login</p>
                        <p class="text-sm text-gray-600">Session active since login</p>
                    </div>
                    <i class="fas fa-check-circle text-green-500"></i>
                </div>
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <div>
                        <p class="text-sm font-medium text-gray-900">Account Status</p>
                        <p class="text-sm text-gray-600">Active and secure</p>
                    </div>
                    <i class="fas fa-shield-alt text-green-500"></i>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password visibility toggle
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const eye = document.getElementById(fieldId + '_eye');
            
            if (field.type === 'password') {
                field.type = 'text';
                eye.classList.remove('fa-eye');
                eye.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                eye.classList.remove('fa-eye-slash');
                eye.classList.add('fa-eye');
            }
        }

        // Password strength indicator
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('password_strength');
            
            if (password.length === 0) {
                strengthDiv.innerHTML = '';
                return;
            }
            
            let strength = 0;
            let feedback = [];
            
            if (password.length >= 6) strength++;
            else feedback.push('At least 6 characters');
            
            if (password.match(/[a-z]/)) strength++;
            else feedback.push('Lowercase letter');
            
            if (password.match(/[A-Z]/)) strength++;
            else feedback.push('Uppercase letter');
            
            if (password.match(/[0-9]/)) strength++;
            else feedback.push('Number');
            
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            else feedback.push('Special character');
            
            const colors = ['red', 'red', 'yellow', 'yellow', 'green'];
            const labels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
            const color = colors[strength];
            const label = labels[strength];
            
            strengthDiv.innerHTML = `
                <div class="text-xs">
                    <span class="text-${color}-600 font-medium">Password Strength: ${label}</span>
                    ${feedback.length > 0 ? `<span class="text-gray-500 ml-2">Missing: ${feedback.join(', ')}</span>` : ''}
                </div>
                <div class="w-full bg-gray-200 rounded-full h-1 mt-1">
                    <div class="bg-${color}-500 h-1 rounded-full" style="width: ${(strength/5)*100}%"></div>
                </div>
            `;
        });

        // Password match indicator
        function checkPasswordMatch() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('password_match');
            
            if (confirmPassword.length === 0) {
                matchDiv.innerHTML = '';
                return;
            }
            
            if (newPassword === confirmPassword) {
                matchDiv.innerHTML = '<div class="text-xs text-green-600"><i class="fas fa-check mr-1"></i>Passwords match</div>';
            } else {
                matchDiv.innerHTML = '<div class="text-xs text-red-600"><i class="fas fa-times mr-1"></i>Passwords do not match</div>';
            }
        }

        document.getElementById('new_password').addEventListener('input', checkPasswordMatch);
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
    </script>

    <!-- PWA Scripts -->
    <script src="/assets/js/pwa.js"></script>
</body>
</html>
