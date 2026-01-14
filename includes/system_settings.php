<?php
/**
 * System Settings Management Utility
 * Handles reading and writing system configuration settings
 */

/**
 * Get a system setting value
 * @param string $key The setting key
 * @param mixed $default Default value if setting not found
 * @return mixed The setting value
 */
function getSystemSetting($key, $default = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value, setting_type FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return $default;
        }
        
        $value = $result['setting_value'];
        $type = $result['setting_type'];
        
        // Convert value based on type
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int) $value;
            case 'json':
                return json_decode($value, true);
            case 'string':
            default:
                return $value;
        }
    } catch (PDOException $e) {
        error_log("Error getting system setting '$key': " . $e->getMessage());
        return $default;
    }
}

/**
 * Set a system setting value
 * @param string $key The setting key
 * @param mixed $value The setting value
 * @param string $type The setting type (string, integer, boolean, json)
 * @param string $description Optional description
 * @param bool $is_public Whether the setting is public
 * @return bool Success status
 */
function setSystemSetting($key, $value, $type = 'string', $description = null, $is_public = true) {
    global $pdo;
    
    try {
        // Convert value based on type
        switch ($type) {
            case 'boolean':
                $value = $value ? '1' : '0';
                break;
            case 'integer':
                $value = (string) (int) $value;
                break;
            case 'json':
                $value = json_encode($value);
                break;
            case 'string':
            default:
                $value = (string) $value;
                break;
        }
        
        // Check if setting exists
        $stmt = $pdo->prepare("SELECT id FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        
        if ($stmt->fetch()) {
            // Update existing setting
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ?, setting_type = ?, description = ?, is_public = ?, updated_at = NOW() WHERE setting_key = ?");
            return $stmt->execute([$value, $type, $description, $is_public, $key]);
        } else {
            // Insert new setting
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES (?, ?, ?, ?, ?)");
            return $stmt->execute([$key, $value, $type, $description, $is_public]);
        }
    } catch (PDOException $e) {
        error_log("Error setting system setting '$key': " . $e->getMessage());
        return false;
    }
}

/**
 * Get all public system settings
 * @return array Array of public settings
 */
function getPublicSettings() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value, setting_type FROM system_settings WHERE is_public = 1");
        $settings = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $value = $row['setting_value'];
            $type = $row['setting_type'];
            
            // Convert value based on type
            switch ($type) {
                case 'boolean':
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'integer':
                    $value = (int) $value;
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            
            $settings[$row['setting_key']] = $value;
        }
        
        return $settings;
    } catch (PDOException $e) {
        error_log("Error getting public settings: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if maintenance mode is enabled
 * @return bool
 */
function isMaintenanceMode() {
    return getSystemSetting('maintenance_mode', false);
}

/**
 * Check if registration is open
 * @return bool
 */
function isRegistrationOpen() {
    return getSystemSetting('registration_open', true);
}

/**
 * Get hackathon information
 * @return array
 */
function getHackathonInfo() {
    return [
        'name' => getSystemSetting('hackathon_name', 'HackMate'),
        'description' => getSystemSetting('hackathon_description', 'Hackathon Management System'),
        'start_date' => getSystemSetting('hackathon_start_date'),
        'end_date' => getSystemSetting('hackathon_end_date'),
        'contact_email' => getSystemSetting('contact_email', 'support@hackathon.com'),
        'timezone' => getSystemSetting('timezone', 'UTC')
    ];
}

/**
 * Get team size limits
 * @return array
 */
function getTeamSizeLimits() {
    return [
        'min' => getSystemSetting('min_team_size', 1),
        'max' => getSystemSetting('max_team_size', 4)
    ];
}

/**
 * Format datetime according to system timezone
 * @param string $datetime
 * @param string $format
 * @return string
 */
function formatSystemDateTime($datetime, $format = 'Y-m-d H:i:s') {
    if (empty($datetime)) return '';
    
    try {
        $timezone = getSystemSetting('timezone', 'UTC');
        $dt = new DateTime($datetime);
        $dt->setTimezone(new DateTimeZone($timezone));
        return $dt->format($format);
    } catch (Exception $e) {
        return $datetime; // Fallback to original if conversion fails
    }
}
?>