<?php
/**
 * Member Role Guard
 * Restricts access to member accounts only
 */

require_once __DIR__ . '/auth.php';

if (!is_logged_in()) {
    set_flash('error', 'Please log in to access your Member Portal.');
    header('Location: ' . base_url('login.php'));
    exit;
}

if ($_SESSION['user']['role'] !== 'member') {
    // If admin accidentally visits member portal, redirect to admin dashboard
    header('Location: ' . base_url('admin/dashboard.php'));
    exit;
}
