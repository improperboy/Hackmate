<?php
/**
 * Session Configuration for InfinityFree Hosting
 * This file contains optimized session settings for shared hosting environments
 */

// Prevent multiple includes
if (defined('SESSION_CONFIG_LOADED')) {
    return;
}
define('SESSION_CONFIG_LOADED', true);

/**
 * Configure session settings before session_start()
 * These settings are optimized for InfinityFree and other shared hosting providers
 */
function configureSession() {
    // Don't start if session is already active
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }
    
    // Session configuration optimized for InfinityFree
    // Use @ini_set to suppress errors if settings aren't allowed
    
    // Set session name (optional, but good practice)
    @ini_set('session.name', 'HACKMATE_SESSID');
    
    // Use only cookies for sessions (more secure)
    @ini_set('session.use_cookies', 1);
    @ini_set('session.use_only_cookies', 1);
    
    // Cookie settings
    @ini_set('session.cookie_lifetime', 0); // Session cookies (expire when browser closes)
    @ini_set('session.cookie_path', '/');
    
    // Disable cookie domain to avoid subdomain issues on InfinityFree
    @ini_set('session.cookie_domain', '');
    
    // Security settings
    @ini_set('session.cookie_httponly', 1); // Prevent XSS
    
    // Set secure cookies only if HTTPS is available
    $isHTTPS = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
               (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
               
    @ini_set('session.cookie_secure', $isHTTPS ? 1 : 0);
    
    // Session lifetime and garbage collection
    @ini_set('session.gc_maxlifetime', 7200); // 2 hours
    @ini_set('session.gc_probability', 1);
    @ini_set('session.gc_divisor', 1000);
    
    // Save handler settings for shared hosting
    // InfinityFree uses 'files' by default, which is fine
    @ini_set('session.save_handler', 'files');
    
    // Try to set a custom session save path if possible
    $session_path = __DIR__ . '/../tmp/sessions';
    if (!is_dir($session_path)) {
        @mkdir($session_path, 0755, true);
    }
    
    if (is_dir($session_path) && is_writable($session_path)) {
        @ini_set('session.save_path', $session_path);
    }
    // If custom path fails, let PHP use system default
    
    // Additional security measures (suppress errors with @)
    @ini_set('session.entropy_length', 32);
    @ini_set('session.hash_function', 'sha256');
    @ini_set('session.hash_bits_per_character', 5);
    
    // Prevent session fixation
    @ini_set('session.use_trans_sid', 0);
    
    return true;
}

/**
 * Start session with proper error handling
 */
function startSecureSession() {
    // Configure session before starting
    configureSession();
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        // Set custom session start parameters
        $started = @session_start([
            'use_cookies' => true,
            'use_only_cookies' => true,
            'cookie_httponly' => true,
            'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'cookie_samesite' => 'Lax'
        ]);
        
        if (!$started) {
            // Fallback to basic session start
            $started = @session_start();
        }
        
        if ($started) {
            // Regenerate session ID periodically for security
            if (!isset($_SESSION['last_regeneration'])) {
                $_SESSION['last_regeneration'] = time();
                session_regenerate_id(true);
            } elseif (time() - $_SESSION['last_regeneration'] > 300) { // Every 5 minutes
                $_SESSION['last_regeneration'] = time();
                session_regenerate_id(true);
            }
            
            return true;
        } else {
            // Log error if possible
            error_log("Session start failed: " . error_get_last()['message'] ?? 'Unknown error');
            return false;
        }
    }
    
    return true;
}

/**
 * Validate session data integrity
 */
function validateSession() {
    // If no user is logged in, session is "valid" but empty
    if (!isset($_SESSION['user_id'])) {
        return true; // Not an error, just no user logged in
    }
    
    // Check if session has required fields
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || !isset($_SESSION['login_time'])) {
        return false;
    }
    
    // Check session age (max 8 hours)
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 28800) {
        return false;
    }
    
    // Check session fingerprint to prevent hijacking
    $fingerprint = generateSessionFingerprint();
    
    if (!isset($_SESSION['fingerprint'])) {
        $_SESSION['fingerprint'] = $fingerprint;
    } elseif ($_SESSION['fingerprint'] !== $fingerprint) {
        // Session might be hijacked, destroy it
        error_log("Session fingerprint mismatch for user ID: " . ($_SESSION['user_id'] ?? 'unknown'));
        return false;
    }
    
    return true;
}

/**
 * Generate a session fingerprint for security
 */
function generateSessionFingerprint() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    
    return hash('sha256', $userAgent . $acceptLanguage . $acceptEncoding);
}

/**
 * Enhanced session cleanup
 */
function cleanupSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
        
        // Also clear the session cookie
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
}

// Auto-start session when this file is included
if (!startSecureSession()) {
    // If session start fails, try to provide useful error info
    if (function_exists('error_log')) {
        error_log("Failed to start session in session_config.php");
    }
}

?>
