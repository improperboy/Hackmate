<?php
// Include session configuration first
require_once __DIR__ . '/session_config.php';
require_once 'db.php'; // Ensure db.php is included for PDO connection

function checkAuth($required_role = null) {
    // Prevent caching of authenticated pages
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
    
    // Check if session exists and is valid
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        redirectToLogin();
    }
    
    // Validate session integrity
    if (!validateSession()) {
        redirectToLogin();
    }
    
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // User not found in DB, session might be stale
            clearSessionAndRedirect();
        }
        
        // Check if user account is still active (if you have an active field)
        // if (isset($user['is_active']) && !$user['is_active']) {
        //     clearSessionAndRedirect();
        // }
        
        // Update session data
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['last_activity'] = time();
        
        // Check session timeout (30 minutes of inactivity)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            clearSessionAndRedirectWithTimeout();
        }
        
        // Role-based access control
        if ($required_role) {
            if (is_array($required_role)) {
                if (!in_array($user['role'], $required_role)) {
                    header('Location: ../unauthorized.php');
                    exit();
                }
            } else {
                if ($user['role'] !== $required_role) {
                    header('Location: ../unauthorized.php');
                    exit();
                }
            }
        }
        
        // Regenerate session ID periodically for security
        if (!isset($_SESSION['last_regeneration']) || (time() - $_SESSION['last_regeneration']) > 300) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
        
    } catch (Exception $e) {
        error_log("Auth check failed: " . $e->getMessage());
        clearSessionAndRedirect();
    }
}

function redirectToLogin() {
    // Determine the correct path to login.php based on current location
    $loginPath = '../login.php';
    
    // Check if we're already in the root directory
    if (basename(dirname($_SERVER['SCRIPT_NAME'])) === basename($_SERVER['DOCUMENT_ROOT']) || 
        strpos($_SERVER['SCRIPT_NAME'], '/admin/') === false && 
        strpos($_SERVER['SCRIPT_NAME'], '/participant/') === false && 
        strpos($_SERVER['SCRIPT_NAME'], '/mentor/') === false && 
        strpos($_SERVER['SCRIPT_NAME'], '/volunteer/') === false) {
        $loginPath = 'login.php';
    }
    
    header("Location: $loginPath");
    exit();
}

function clearSessionAndRedirect() {
    // Clear session data
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
    
    redirectToLogin();
}

function clearSessionAndRedirectWithTimeout() {
    // Clear session data
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
    
    // Redirect with timeout parameter
    $loginPath = '../login.php?timeout=1';
    
    // Check if we're already in the root directory
    if (basename(dirname($_SERVER['SCRIPT_NAME'])) === basename($_SERVER['DOCUMENT_ROOT']) || 
        strpos($_SERVER['SCRIPT_NAME'], '/admin/') === false && 
        strpos($_SERVER['SCRIPT_NAME'], '/participant/') === false && 
        strpos($_SERVER['SCRIPT_NAME'], '/mentor/') === false && 
        strpos($_SERVER['SCRIPT_NAME'], '/volunteer/') === false) {
        $loginPath = 'login.php?timeout=1';
    }
    
    header("Location: $loginPath");
    exit();
}

function getCurrentUser() {
    global $pdo;
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return null;
}
?>
