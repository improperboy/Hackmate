<?php
// Authentication helper functions

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireRole($required_role) {
    requireLogin();
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        header('Location: unauthorized.php');
        exit;
    }
}

function requireRoles($required_roles) {
    requireLogin();
    
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $required_roles)) {
        header('Location: unauthorized.php');
        exit;
    }
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

function getCurrentUserName() {
    return $_SESSION['user_name'] ?? null;
}

function isAdmin() {
    return getCurrentUserRole() === 'admin';
}

function isMentor() {
    return getCurrentUserRole() === 'mentor';
}

function isParticipant() {
    return getCurrentUserRole() === 'participant';
}

function isVolunteer() {
    return getCurrentUserRole() === 'volunteer';
}

function logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>