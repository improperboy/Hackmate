/**
 * Client-side Security Enhancements
 * This file provides additional security measures for the application
 */

(function() {
    'use strict';
    
    // Prevent back button after logout
    function preventBackButton() {
        window.history.forward();
        
        // Disable back button
        window.addEventListener('popstate', function(event) {
            window.history.forward();
        });
        
        // Clear browser cache on logout
        if (window.location.href.includes('login.php') && 
            (window.location.href.includes('logout=success') || window.location.href.includes('timeout=1'))) {
            
            // Clear various caches
            if ('caches' in window) {
                caches.keys().then(function(names) {
                    names.forEach(function(name) {
                        caches.delete(name);
                    });
                });
            }
            
            // Clear session storage
            if (typeof(Storage) !== "undefined") {
                sessionStorage.clear();
            }
        }
    }
    
    // Session timeout warning
    let sessionTimeout;
    let warningTimeout;
    let lastActivity = Date.now();
    
    function resetSessionTimer() {
        lastActivity = Date.now();
        
        // Clear existing timeouts
        clearTimeout(sessionTimeout);
        clearTimeout(warningTimeout);
        
        // Only set timers if user is logged in (not on login page)
        if (!window.location.href.includes('login.php') && 
            !window.location.href.includes('register.php')) {
            
            // Show warning 5 minutes before timeout (25 minutes)
            warningTimeout = setTimeout(function() {
                showSessionWarning();
            }, 25 * 60 * 1000); // 25 minutes
            
            // Auto logout after 30 minutes of inactivity
            sessionTimeout = setTimeout(function() {
                autoLogout();
            }, 30 * 60 * 1000); // 30 minutes
        }
    }
    
    function showSessionWarning() {
        if (confirm('Your session will expire in 5 minutes due to inactivity. Click OK to stay logged in.')) {
            // User wants to stay logged in, make a keep-alive request
            fetch(window.location.href, {
                method: 'HEAD',
                cache: 'no-cache'
            }).then(function() {
                resetSessionTimer();
            }).catch(function() {
                // If request fails, logout anyway
                autoLogout();
            });
        } else {
            autoLogout();
        }
    }
    
    function autoLogout() {
        // Redirect to logout
        window.location.href = '../logout.php';
    }
    
    // Track user activity
    function trackActivity() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        
        events.forEach(function(event) {
            document.addEventListener(event, function() {
                const now = Date.now();
                // Only reset if more than 1 minute has passed since last activity
                if (now - lastActivity > 60000) {
                    resetSessionTimer();
                }
            }, true);
        });
    }
    
    // Disable right-click context menu on production (optional)
    function disableContextMenu() {
        // Only disable in production, not during development
        if (window.location.hostname !== 'localhost' && 
            window.location.hostname !== '127.0.0.1') {
            
            document.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                return false;
            });
        }
    }
    
    // Disable certain keyboard shortcuts (optional)
    function disableKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Disable F12 (Developer Tools)
            if (e.keyCode === 123) {
                e.preventDefault();
                return false;
            }
            
            // Disable Ctrl+Shift+I (Developer Tools)
            if (e.ctrlKey && e.shiftKey && e.keyCode === 73) {
                e.preventDefault();
                return false;
            }
            
            // Disable Ctrl+U (View Source)
            if (e.ctrlKey && e.keyCode === 85) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    // Initialize security measures
    function init() {
        preventBackButton();
        trackActivity();
        resetSessionTimer();
        
        // Uncomment these if you want additional security (may affect user experience)
        // disableContextMenu();
        // disableKeyboardShortcuts();
        
        // Clear sensitive data on page unload
        window.addEventListener('beforeunload', function() {
            // Clear any sensitive data from memory
            if (typeof(Storage) !== "undefined") {
                // Don't clear localStorage as it might contain user preferences
                // Only clear sessionStorage
                sessionStorage.clear();
            }
        });
        
        // Detect if page is loaded from cache (back button)
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                // Page was loaded from cache, reload it
                window.location.reload();
            }
        });
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();