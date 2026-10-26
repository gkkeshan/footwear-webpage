<?php
// Database Configuration and Connection
// Uses PDO with prepared statements for SQL injection prevention

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'girls_footwear');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Create PDO database connection
function getDBConnection() {
    static $db = null;

    if ($db === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $db = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check configuration.");
        }
    }

    return $db;
}

// Close database connection
function closeDBConnection() {
    global $db;
    $db = null;
}
