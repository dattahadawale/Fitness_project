<?php
/**
 * Admin Authentication Guard
 * Apex Fitness Gym Management System
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// Check login session.
if (!is_logged_in()) {
    set_flash('error', 'Please log in to access the Admin Portal.');
    header('Location: ' . base_url('login.php'));
    exit;
}

// Check admin role.
if (!has_role('admin')) {
    set_flash('error', 'Access denied. Admin access is required.');
    header('Location: ' . base_url('member/dashboard.php'));
    exit;
}
