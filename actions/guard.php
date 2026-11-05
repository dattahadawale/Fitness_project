<?php
/**
 * Admin Role Guard
 * Restricts admin access and requires OTP verification.
 */

require_once __DIR__ . '/auth.php';

if (!is_logged_in()) {
  set_flash('error', 'Please log in to access the Admin Portal.');
  header('Location: ' . base_url('login.php'));
  exit;
}

$authorizedAdminEmail = 'dattahadawale673@gmail.com';

$loggedInEmail = strtolower(
  trim($_SESSION['user']['email'] ?? '')
);

$loggedInRole = $_SESSION['user']['role'] ?? '';

if (
  $loggedInRole !== 'admin' ||
  $loggedInEmail !== $authorizedAdminEmail
) {
  set_flash('error', 'Access denied. This account is not authorized for the Admin Portal.');
  header('Location: ' . base_url('login.php'));
  exit;
}

// Redirect to OTP verification if not yet verified.
if (empty($_SESSION['admin_otp_verified'])) {
  header('Location: ' . base_url('admin/verify-otp.php'));
  exit;
}

