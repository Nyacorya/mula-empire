<?php
// ==========================================
// Configuration
// ==========================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'chat');

// Site identifier – this is the numeric ID of the site
define('SITE_ID', 1);

// Ably key
define('ABLY_KEY', 'T1Kgvw.xxXxCA:xqMiZRtQtCCgLE6vKMS7mGj1xYgXTPsak2LROpUTgMM');

// VAPID keys for Web Push
define('VAPID_PUBLIC_KEY', 'BKYqVXzMUXHAldHKIqOPznSOPQ0pUMrf13z_VUb_MIKtkIHAV-qMOzoYywButfDyUZGVA6ZfN7V82ZFn0gR9cMY');
define('VAPID_PRIVATE_KEY', 'OYFiwPgUsX3Vx-Xjf9d9eEutfrnSwGs7EX97BloiEcU');
define('VAPID_SUBJECT', 'mailto:nyacoryans@gmail.com');

// Maintenance mode (true = on)
define('ENABLE_MAINTENANCE', false);

// Session / cookie lifetime in seconds (1 year default)
define('SESSION_LIFETIME', 31536000);