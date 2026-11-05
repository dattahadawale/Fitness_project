<?php
/**
 * Authentication Middleware & Session Helper
 * Gym Management System
 */

require_once __DIR__ . '/functions.php';

// Check inactivity timeout (60 minutes)
$timeout_duration = 3600;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    session_start();
    set_flash('warning', 'Session expired due to inactivity. Please log in again.');
    header('Location: ' . base_url('login.php'));
    exit;
}
$_SESSION['last_activity'] = time();

/**
 * Check if a user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Get current logged in user object
 */
function current_user() {
    return $_SESSION['user'] ?? null;
}

/**
 * Check user role
 */
function has_role($role) {
    return is_logged_in() && ($_SESSION['user']['role'] ?? '') === $role;
}
