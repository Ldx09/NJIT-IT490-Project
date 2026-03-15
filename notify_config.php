<?php

// --- EMAIL (PHPMailer via Gmail SMTP) -----------------------
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'ldx9651@gmail.com');      // your Gmail address
define('MAIL_PASSWORD', 'hgmd kutw kmcl qvzo'); // Gmail App Password (NOT your login password)
define('MAIL_FROM',     'ldx9651@gmail.com');
define('MAIL_FROM_NAME','Vehicle Recall System');

// --- SMS (Twilio) — optional, set NOTIFY_SMS_ENABLED=false to skip ---
define('TWILIO_SID',   'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('TWILIO_TOKEN', '9872e0b196d89e42a7243203ff956dbd');
define('TWILIO_FROM',  '+18882827892');               // Your Twilio phone number

// --- DATABASE (used when integrating with team DB) ----------
define('DB_HOST', '10.246.134.26');
define('DB_NAME', 'vehicleRecall');
define('DB_USER', 'admin');      
define('DB_PASS', '123456');     

// --- FEATURE FLAGS ------------------------------------------
// Set to false to run in log-only mode (safe for testing without real credentials)
define('NOTIFY_EMAIL_ENABLED', false);   // change to true when you have Gmail App Password
define('NOTIFY_SMS_ENABLED',   false);   // change to true when you have Twilio set up

// Log file path (relative to this file)
define('NOTIFY_LOG', __DIR__ . '/logs/notifications.log');
