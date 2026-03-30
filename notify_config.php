<?php

define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'ldx9651@gmail.com');      // your Gmail address
define('MAIL_PASSWORD', 'hgmd kutw kmcl qvzo'); // Gmail App Password (NOT your login password)
define('MAIL_FROM',     'ldx9651@gmail.com');
define('MAIL_FROM_NAME','Vehicle Recall System');

define('TWILIO_SID',   'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('TWILIO_TOKEN', '9872e0b196d89e42a7243203ff956dbd');
define('TWILIO_FROM',  '+18882827892');               // Your Twilio phone number

define('DB_HOST', '10.246.134.26');
define('DB_NAME', 'vehicleRecall');
define('DB_USER', 'admin');      
define('DB_PASS', '123456');     

define('NOTIFY_EMAIL_ENABLED', false);  
define('NOTIFY_SMS_ENABLED',   false);

define('NOTIFY_LOG', __DIR__ . '/logs/notifications.log');
