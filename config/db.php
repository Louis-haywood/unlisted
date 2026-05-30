<?php
// Database credentials — fill these in before deploying
define('DB_HOST', 'localhost');
define('DB_NAME', 'louventory');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// Admin panel credentials
// Generate ADMIN_PASSWORD with: echo password_hash('your_password', PASSWORD_BCRYPT);
define('ADMIN_EMAIL',    'admin@louventory.uk');
define('ADMIN_PASSWORD', '$2y$12$CHANGE_ME_RUN_PASSWORD_HASH_TO_GENERATE');

// Cron job secret — change this to a random string
define('CRON_TOKEN', 'change_me_to_a_long_random_secret');

// Application
define('APP_DOMAIN',      'louventory.uk');
define('UPLOAD_MAX_SIZE', 5242880); // 5 MB
