<?php
/**
 * Secure Logout System
 * This file handles secure user logout with proper session cleanup
 */

// Include session configuration
require_once 'includes/session_config.php';
require_once 'includes/db.php';

// Function to perform secure logout
function performSecureLogout() {
    global $pdo;
    
    // Log the logout activity if user is logged in
    if (isset($_SESSION['user_id'])) {
        try {
            // Optional: Log logout activity in database
            $stmt = $pdo->prepare("UPDATE users SET last_logout = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        } catch (Exception $e) {
            // Continue with logout even if logging fails
            error_log("Logout logging failed: " . $e->getMessage());
        }
    }
    
    // Clear all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Clear any additional cookies that might be set
    $cookiesToClear = ['HACKMATE_SESSID', 'PHPSESSID', 'remember_me'];
    foreach ($cookiesToClear as $cookieName) {
        if (isset($_COOKIE[$cookieName])) {
            setcookie($cookieName, '', time() - 3600, '/');
            setcookie($cookieName, '', time() - 3600, '/', $_SERVER['HTTP_HOST']);
        }
    }
}

// Perform the logout
performSecureLogout();

// Prevent caching of this page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

// Redirect to login page with logout confirmation
header('Location: login.php?logout=success');
exit();
?>
