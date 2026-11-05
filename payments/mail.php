<?php
/**
 * Mail (SMTP) settings for payment-proof emails.
 *
 * IMPORTANT: replace the two YOUR_... placeholders. For Gmail you must use an
 * "App Password" (Google Account -> Security -> 2-Step Verification -> App passwords),
 * NOT your normal Gmail password. Never share or publish this file.
 */
return [
  'host' => 'smtp.gmail.com',
  'port' => 587,
  'secure' => 'tls',                         // STARTTLS on port 587
  'username' => 'YOUR_SENDING_GMAIL@gmail.com',
  'password' => 'YOUR_GMAIL_APP_PASSWORD',
  'from_email' => 'YOUR_SENDING_GMAIL@gmail.com', // must match the SMTP account for Gmail
  'from_name' => 'Apex Fitness Club',
  'admin_email' => 'dattahadawale9322@gmail.com', // payment proofs are sent here
];