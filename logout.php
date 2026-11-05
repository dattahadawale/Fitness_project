<?php
/**
 * User Logout Action
 * Gym Management System
 */

require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(405);
    exit('Invalid logout request.');
}

// Unset all session variables
$_SESSION = [];

// Destroy session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Start fresh session for the flash notification
session_start();
session_regenerate_id(true);
set_flash('info', 'You have been logged out securely. See you soon!');
header('Location: ' . base_url('login.php'));
exit;
