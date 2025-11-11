<?php
// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'bdms');
define('DB_USER', 'root');
define('DB_PASS', ''); // XAMPP default no password

// Application URL (because index.php is inside /public)
define('APP_URL', 'http://localhost/bloodconnect/public');

// App settings
define('APP_NAME', 'Bloodconnect');
define('ENV', 'local');

// Session config
ini_set('session.cookie_httponly', 1);
session_name('bdms_session');
session_start();
