<?php
// ============================================================
//  D6 — Notification Config
//
//  INSTRUCTIONS:
//  1. Copy this file:   cp notify_config.example.php notify_config.php
//  2. Fill in your real credentials in notify_config.php
//  3. notify_config.php is in .gitignore — it will NEVER be pushed to GitHub
// ============================================================

// --- EMAIL (PHPMailer via Gmail SMTP) -----------------------
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'your_gmail@gmail.com');      // your Gmail address
define('MAIL_PASSWORD', 'your_16_char_app_password'); // Gmail App Password (NOT your login password)
define('MAIL_FROM',     'your_gmail@gmail.com');
define('MAIL_FROM_NAME','Vehicle Recall System');

// --- SMS (Twilio) — optional, set NOTIFY_SMS_ENABLED=false to skip ---
define('TWILIO_SID',   'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('TWILIO_TOKEN', 'your_auth_token');
define('TWILIO_FROM',  '+1XXXXXXXXXX');               // Your Twilio phone number

// --- DATABASE (used when integrating with team DB) ----------
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'recall_db');
define('DB_USER', 'root');
define('DB_PASS', 'your_db_password');

// --- FEATURE FLAGS ------------------------------------------
// Set to false to run in log-only mode (safe for testing without real credentials)
define('NOTIFY_EMAIL_ENABLED', false);   // change to true when you have Gmail App Password
define('NOTIFY_SMS_ENABLED',   false);   // change to true when you have Twilio set up

// Log file path (relative to this file)
define('NOTIFY_LOG', __DIR__ . '/logs/notifications.log');