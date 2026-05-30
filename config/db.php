<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'u463907152_louventory');
define('DB_USER', 'u463907152_louventory');
define('DB_PASS', 'g@kyqH^X5B5=');

// Admin panel credentials — change ADMIN_PASSWORD before going live.
// Generate a new hash: php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT);"
define('ADMIN_EMAIL',    'admin@louventory.uk');
define('ADMIN_PASSWORD', '$2a$12$xvJSJTAGBmUyrpnGqkV/9uDBtatuVznK5TtkT.e6P0.MfJazeeHwO');

// Cron job secret — change this to a long random string
define('CRON_TOKEN', 'change_me_to_a_long_random_secret');

// Application
define('APP_DOMAIN',      'louventory.uk');
define('UPLOAD_MAX_SIZE', 5242880); // 5 MB
