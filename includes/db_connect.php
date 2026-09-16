<?php
// includes/db_connect.php

require_once __DIR__ . '/database_config.php';

$databaseConfig = currentDatabaseConfig();

define('APP_ENVIRONMENT', $databaseConfig['environment']);
define('DB_HOST', $databaseConfig['host']);
define('DB_NAME', $databaseConfig['name']);
define('DB_USER', $databaseConfig['user']);
define('DB_PASS', $databaseConfig['password']);
define('DB_CHARSET', $databaseConfig['charset']);
unset($databaseConfig);

// DSN (Data Source Name)
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

// PDO options for security and performance
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,       // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,             // Fetch associative arrays by default
    PDO::ATTR_EMULATE_PREPARES   => false,                        // Use real prepared statements (disables emulation)
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET     // Ensure correct charset encoding
];

try {
    // Create PDO instance
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // Log the error securely instead of displaying it to the user
    error_log("Database Connection Error: " . $e->getMessage());
    
    // Generic error message for users
    header('HTTP/1.1 500 Internal Server Error');
    die("A database error occurred. Please try again later.");
}

// Ensure session starts securely. Public return pages may opt out so a cross-site
// redirect cannot replace an existing SameSite=Strict clinic session cookie.
if (!defined('EASYIMPLANT_SKIP_SESSION') && session_status() === PHP_SESSION_NONE) {
    // Session security settings
    ini_set('session.cookie_httponly', 1); // Prevent JavaScript access to session cookie
    ini_set('session.use_only_cookies', 1); // Only use cookies for sessions
    ini_set('session.cookie_samesite', 'Strict'); // Prevent CSRF attacks
    // ini_set('session.cookie_secure', 1); // Enable this in production with HTTPS
    
    session_start();
}
?>
