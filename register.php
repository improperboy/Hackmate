<?php
require_once 'includes/db.php';
require_once 'includes/system_settings.php';
require_once 'includes/maintenance_check.php';

// Check if registration is being accessed by admin
$is_admin_access = false;
if (isset($_GET['admin']) && $_GET['admin'] === '1') {
    session_start();
    require_once 'includes/auth_check.php';
    checkAuth('admin');
    $is_admin_access = true;
    $admin_user = getCurrentUser();
}

// Check if registration is open (unless admin access)
if (!$is_admin_access && !isRegistrationOpen()) {
    $hackathon_info = getHackathonInfo();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Registration Closed - <?php echo htmlspecialchars($hackathon_info['name']); ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    </head>
    <body class="bg-gray-100 min-h-screen flex items-center justify-center">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-lg p-8 text-center">
            <div class="mb-6">
                <i class="fas fa-user-times text-6xl text-red-500 mb-4"></i>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Registration Closed</h1>
                <p class="text-gray-600">
                    Registration for <?php echo htmlspecialchars($hackathon_info['name']); ?> is currently closed.
                </p>
            </div>
            
            <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700">
                            New registrations are not being accepted at this time.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="text-sm text-gray-500 mb-6">
                <p>For questions about registration, contact us at:</p>
                <a href="mailto:<?php echo htmlspecialchars($hackathon_info['contact_email']); ?>" 
                   class="text-blue-600 hover:text-blue-800 font-medium">
                    <?php echo htmlspecialchars($hackathon_info['contact_email']); ?>
                </a>
            </div>
            
            <div class="space-y-3">
                <a href="login.php" 
                   class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md transition-colors">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Login Instead
                </a>
                <a href="index.php" 
                   class="block w-full bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-md transition-colors">
                    <i class="fas fa-home mr-2"></i>
                    Go Home
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$message = '';
$error = '';

if ($_POST) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $tech_stack = trim($_POST['tech_stack'] ?? '');

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error = 'All required fields must be filled.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!in_array($role, ['participant', 'mentor', 'volunteer'])) {
        $error = 'Invalid role selected.';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Email already registered. Please use a different email or login.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, tech_stack) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $email, $hashed_password, $role, $tech_stack])) {
                $message = 'Registration successful! You can now login.';
                // Clear form fields after successful registration
                $_POST = array(); 
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Hackathon Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
        <div class="text-center mb-6">
            <i class="fas fa-user-plus text-green-600 text-5xl mb-4"></i>
            <h2 class="text-2xl font-bold text-gray-800">Register for Hackathon</h2>
            <p class="text-gray-600 text-sm">Create your account</p>
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
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-user text-gray-400"></i>
                    </div>
                    <input type="text" id="name" name="name" required
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                           placeholder="John Doe" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-envelope text-gray-400"></i>
                    </div>
                    <input type="email" id="email" name="email" required
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                           placeholder="you@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
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
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-lock text-gray-400"></i>
                    </div>
                    <input type="password" id="confirm_password" name="confirm_password" required
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                           placeholder="••••••••">
                </div>
            </div>

            <div>
                <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Register As</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-user-tag text-gray-400"></i>
                    </div>
                    <select id="role" name="role" required
                            class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <option value="">Select Role</option>
                        <option value="participant" <?php echo (isset($_POST['role']) && $_POST['role'] == 'participant') ? 'selected' : ''; ?>>Participant</option>
                        <option value="mentor" <?php echo (isset($_POST['role']) && $_POST['role'] == 'mentor') ? 'selected' : ''; ?>>Mentor</option>
                        <option value="volunteer" <?php echo (isset($_POST['role']) && $_POST['role'] == 'volunteer') ? 'selected' : ''; ?>>Volunteer</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="tech_stack" class="block text-sm font-medium text-gray-700 mb-1">Tech Stack <span class="text-gray-500">(Optional)</span></label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-code text-gray-400"></i>
                    </div>
                    <textarea id="tech_stack" name="tech_stack" rows="3"
                              class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                              placeholder="e.g., React, Node.js, Python, MongoDB, AWS..."><?php echo htmlspecialchars($_POST['tech_stack'] ?? ''); ?></textarea>
                </div>
                <p class="text-xs text-gray-500 mt-1">List your technical skills and preferred technologies (comma-separated)</p>
            </div>

            <div>
                <button type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Register
                </button>
            </div>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
                Already have an account?
                <a href="login.php" class="font-medium text-blue-600 hover:text-blue-500">Login here</a>
            </p>
        </div>
    </div>
</body>
</html>
