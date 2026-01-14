<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';
require_once '../includes/system_settings.php';

checkAuth('admin');
$user = getCurrentUser();

// Get counts for sidebar notifications
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_teams = $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
$pending_teams = $pdo->query("SELECT COUNT(*) FROM teams WHERE status = 'pending'")->fetchColumn();
$total_submissions = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
$open_support_requests = $pdo->query("SELECT COUNT(*) FROM support_messages WHERE status = 'open'")->fetchColumn();

$message = '';
$error = '';

// Handle form submission
if ($_POST && isset($_POST['update_settings'])) {
    try {
        $pdo->beginTransaction();
        
        // Update each setting
        $settings_to_update = [
            'hackathon_name' => ['value' => $_POST['hackathon_name'], 'type' => 'string'],
            'hackathon_description' => ['value' => $_POST['hackathon_description'], 'type' => 'string'],
            'max_team_size' => ['value' => (int)$_POST['max_team_size'], 'type' => 'integer'],
            'min_team_size' => ['value' => (int)$_POST['min_team_size'], 'type' => 'integer'],
            'registration_open' => ['value' => isset($_POST['registration_open']), 'type' => 'boolean'],
            'hackathon_start_date' => ['value' => $_POST['hackathon_start_date'], 'type' => 'string'],
            'hackathon_end_date' => ['value' => $_POST['hackathon_end_date'], 'type' => 'string'],
            'contact_email' => ['value' => $_POST['contact_email'], 'type' => 'string'],
            'timezone' => ['value' => $_POST['timezone'], 'type' => 'string'],
            'maintenance_mode' => ['value' => isset($_POST['maintenance_mode']), 'type' => 'boolean'],
            'show_mentoring_scores_to_participants' => ['value' => isset($_POST['show_mentoring_scores_to_participants']), 'type' => 'boolean']
        ];
        
        // Validate inputs
        if (empty($_POST['hackathon_name'])) {
            throw new Exception('Hackathon name is required');
        }
        
        if (!filter_var($_POST['contact_email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid contact email format');
        }
        
        if ((int)$_POST['min_team_size'] >= (int)$_POST['max_team_size']) {
            throw new Exception('Maximum team size must be greater than minimum team size');
        }
        
        if (!empty($_POST['hackathon_start_date']) && !empty($_POST['hackathon_end_date'])) {
            if (strtotime($_POST['hackathon_start_date']) >= strtotime($_POST['hackathon_end_date'])) {
                throw new Exception('Hackathon end date must be after start date');
            }
        }
        
        // Update all settings
        foreach ($settings_to_update as $key => $setting) {
            $description = '';
            $is_public = true;
            
            switch ($key) {
                case 'hackathon_name':
                    $description = 'Name of the hackathon event';
                    break;
                case 'hackathon_description':
                    $description = 'Description of the hackathon';
                    break;
                case 'max_team_size':
                    $description = 'Maximum number of members per team';
                    break;
                case 'min_team_size':
                    $description = 'Minimum number of members per team';
                    break;
                case 'registration_open':
                    $description = 'Whether registration is currently open';
                    break;
                case 'hackathon_start_date':
                    $description = 'Hackathon start date and time';
                    break;
                case 'hackathon_end_date':
                    $description = 'Hackathon end date and time';
                    break;
                case 'contact_email':
                    $description = 'Contact email for support';
                    break;
                case 'timezone':
                    $description = 'System timezone';
                    $is_public = false;
                    break;
                case 'maintenance_mode':
                    $description = 'Enable maintenance mode';
                    $is_public = false;
                    break;
                case 'show_mentoring_scores_to_participants':
                    $description = 'Whether participants can see actual mentoring scores or just feedback and status';
                    $is_public = false;
                    break;
            }
            
            if (!setSystemSetting($key, $setting['value'], $setting['type'], $description, $is_public)) {
                throw new Exception("Failed to update setting: $key");
            }
        }
        
        $pdo->commit();
        $message = 'System settings updated successfully!';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Get current settings
$current_settings = [
    'hackathon_name' => getSystemSetting('hackathon_name', 'HackMate'),
    'hackathon_description' => getSystemSetting('hackathon_description', 'Hackathon Management System'),
    'max_team_size' => getSystemSetting('max_team_size', 4),
    'min_team_size' => getSystemSetting('min_team_size', 1),
    'registration_open' => getSystemSetting('registration_open', true),
    'hackathon_start_date' => getSystemSetting('hackathon_start_date', ''),
    'hackathon_end_date' => getSystemSetting('hackathon_end_date', ''),
    'contact_email' => getSystemSetting('contact_email', 'support@hackathon.com'),
    'timezone' => getSystemSetting('timezone', 'UTC'),
    'maintenance_mode' => getSystemSetting('maintenance_mode', false),
    'show_mentoring_scores_to_participants' => getSystemSetting('show_mentoring_scores_to_participants', false)
];

// Get available timezones
$timezones = [
    'UTC' => 'UTC (Coordinated Universal Time)',
    'America/New_York' => 'Eastern Time (US & Canada)',
    'America/Chicago' => 'Central Time (US & Canada)',
    'America/Denver' => 'Mountain Time (US & Canada)',
    'America/Los_Angeles' => 'Pacific Time (US & Canada)',
    'Europe/London' => 'London (GMT/BST)',
    'Europe/Paris' => 'Paris (CET/CEST)',
    'Europe/Berlin' => 'Berlin (CET/CEST)',
    'Asia/Tokyo' => 'Tokyo (JST)',
    'Asia/Shanghai' => 'Shanghai (CST)',
    'Asia/Kolkata' => 'India (IST)',
    'Australia/Sydney' => 'Sydney (AEST/AEDT)'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - <?php echo htmlspecialchars(getSystemSetting('hackathon_name', 'HackMate')); ?></title>
    
    <!-- Primary Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden lg:ml-0">
            <!-- Top Header -->
            <header class="bg-white shadow-sm border-b border-gray-200 lg:hidden">
                <div class="flex items-center justify-between px-4 py-3">
                    <button onclick="toggleSidebar()" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-semibold text-gray-900">System Settings</h1>
                    <div class="w-6"></div>
                </div>
            </header>
            
            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- Page Header -->
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">
                                <i class="fas fa-cogs text-blue-600 mr-3"></i>
                                System Settings
                            </h1>
                            <p class="text-gray-600 mt-1">Configure hackathon system settings</p>
                        </div>
                        
                        <!-- Settings Actions -->
                        <div class="flex items-center space-x-3">
                            <span class="bg-blue-100 text-blue-800 px-4 py-2 rounded-lg text-sm font-medium">
                                <i class="fas fa-shield-alt mr-2"></i>
                                Admin Only
                            </span>
                        </div>
                    </div>
                </div>
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Warning about maintenance mode -->
        <?php if ($current_settings['maintenance_mode']): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Warning:</strong> Maintenance mode is currently enabled. Only admins can access the system.
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <!-- Hackathon Information -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">
                    <i class="fas fa-info-circle text-blue-600"></i>
                    Hackathon Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label for="hackathon_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Hackathon Name *
                        </label>
                        <input type="text" id="hackathon_name" name="hackathon_name" required
                               value="<?php echo htmlspecialchars($current_settings['hackathon_name']); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label for="hackathon_description" class="block text-sm font-medium text-gray-700 mb-2">
                            Hackathon Description
                        </label>
                        <textarea id="hackathon_description" name="hackathon_description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($current_settings['hackathon_description']); ?></textarea>
                    </div>
                    
                    <div>
                        <label for="hackathon_start_date" class="block text-sm font-medium text-gray-700 mb-2">
                            Start Date & Time
                        </label>
                        <input type="datetime-local" id="hackathon_start_date" name="hackathon_start_date"
                               value="<?php echo $current_settings['hackathon_start_date'] ? str_replace(' ', 'T', $current_settings['hackathon_start_date']) : ''; ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label for="hackathon_end_date" class="block text-sm font-medium text-gray-700 mb-2">
                            End Date & Time
                        </label>
                        <input type="datetime-local" id="hackathon_end_date" name="hackathon_end_date"
                               value="<?php echo $current_settings['hackathon_end_date'] ? str_replace(' ', 'T', $current_settings['hackathon_end_date']) : ''; ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label for="contact_email" class="block text-sm font-medium text-gray-700 mb-2">
                            Contact Email *
                        </label>
                        <input type="email" id="contact_email" name="contact_email" required
                               value="<?php echo htmlspecialchars($current_settings['contact_email']); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label for="timezone" class="block text-sm font-medium text-gray-700 mb-2">
                            System Timezone
                        </label>
                        <select id="timezone" name="timezone"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <?php foreach ($timezones as $tz => $label): ?>
                                <option value="<?php echo $tz; ?>" <?php echo $current_settings['timezone'] === $tz ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Team Configuration -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">
                    <i class="fas fa-users text-green-600"></i>
                    Team Configuration
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="min_team_size" class="block text-sm font-medium text-gray-700 mb-2">
                            Minimum Team Size *
                        </label>
                        <input type="number" id="min_team_size" name="min_team_size" required min="1" max="10"
                               value="<?php echo $current_settings['min_team_size']; ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label for="max_team_size" class="block text-sm font-medium text-gray-700 mb-2">
                            Maximum Team Size *
                        </label>
                        <input type="number" id="max_team_size" name="max_team_size" required min="2" max="20"
                               value="<?php echo $current_settings['max_team_size']; ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                    <p class="text-sm text-blue-700">
                        <i class="fas fa-info-circle mr-2"></i>
                        These settings control how many members can be in a team. Changes will affect new team formations and invitations.
                    </p>
                </div>
            </div>

            <!-- System Controls -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">
                    <i class="fas fa-toggle-on text-purple-600"></i>
                    System Controls
                </h3>
                
                <div class="mb-4 p-4 bg-blue-50 rounded-lg">
                    <p class="text-sm text-blue-700">
                        <i class="fas fa-info-circle mr-2"></i>
                        These settings control system-wide behavior and participant access to information.
                    </p>
                </div>
                
                <div class="space-y-4">
                    <div class="flex items-center">
                        <input type="checkbox" id="registration_open" name="registration_open" 
                               <?php echo $current_settings['registration_open'] ? 'checked' : ''; ?>
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="registration_open" class="ml-2 block text-sm font-medium text-gray-900">
                            Enable User Registration
                        </label>
                    </div>
                    <p class="text-sm text-gray-600 ml-6">
                        When disabled, new users cannot register (except through admin panel)
                    </p>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="show_mentoring_scores_to_participants" name="show_mentoring_scores_to_participants" 
                               <?php echo $current_settings['show_mentoring_scores_to_participants'] ? 'checked' : ''; ?>
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="show_mentoring_scores_to_participants" class="ml-2 block text-sm font-medium text-gray-900">
                            Show Mentoring Scores to Participants
                        </label>
                    </div>
                    <p class="text-sm text-gray-600 ml-6">
                        When enabled, participants can see actual numerical scores. When disabled, they only see feedback and "Scored"/"Not Scored" status.
                    </p>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                               <?php echo $current_settings['maintenance_mode'] ? 'checked' : ''; ?>
                               class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded">
                        <label for="maintenance_mode" class="ml-2 block text-sm font-medium text-gray-900">
                            Enable Maintenance Mode
                        </label>
                    </div>
                    <p class="text-sm text-gray-600 ml-6">
                        <i class="fas fa-exclamation-triangle text-yellow-500 mr-1"></i>
                        <strong>Warning:</strong> Only admins can access the system when maintenance mode is enabled
                    </p>
                </div>
            </div>

            <!-- Save Button -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center">
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-save mr-2"></i>
                        Changes will take effect immediately after saving
                    </div>
                    <button type="submit" name="update_settings" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-md transition-colors">
                        <i class="fas fa-save mr-2"></i>Save Settings
                    </button>
                </div>
            </div>
        </form>
            </main>
        </div>
    </div>

    <script>
        // Validate team size inputs
        document.getElementById('min_team_size').addEventListener('change', function() {
            const minSize = parseInt(this.value);
            const maxSizeInput = document.getElementById('max_team_size');
            const maxSize = parseInt(maxSizeInput.value);
            
            if (minSize >= maxSize) {
                maxSizeInput.value = minSize + 1;
            }
        });
        
        document.getElementById('max_team_size').addEventListener('change', function() {
            const maxSize = parseInt(this.value);
            const minSizeInput = document.getElementById('min_team_size');
            const minSize = parseInt(minSizeInput.value);
            
            if (maxSize <= minSize) {
                minSizeInput.value = maxSize - 1;
            }
        });
        
        // Validate date inputs
        document.getElementById('hackathon_start_date').addEventListener('change', function() {
            const startDate = new Date(this.value);
            const endDateInput = document.getElementById('hackathon_end_date');
            const endDate = new Date(endDateInput.value);
            
            if (endDateInput.value && startDate >= endDate) {
                // Set end date to 1 day after start date
                const newEndDate = new Date(startDate);
                newEndDate.setDate(newEndDate.getDate() + 1);
                endDateInput.value = newEndDate.toISOString().slice(0, 16);
            }
        });
    </script>
</body>
</html>