<?php
/**
 * ============================================================================
 * DATABASE CONFIGURATION & CONNECTION (PDO SINGLETON)
 * ============================================================================
 * Purpose: Provides a secure, persistent PDO connection to the MySQL database.
 * Security: Uses native prepared statements (disables emulated prepares)
 *           to guarantee protection against SQL Injection attacks.
 * Pattern:  Singleton pattern via static variable to reuse the connection.
 */

// Database connection parameters
define('DB_HOST', 'localhost');        // Hostname of the MySQL server
define('DB_NAME', 'girls_footwear');   // Name of the e-commerce database
define('DB_USER', 'root');             // Database username (default XAMPP: root)
define('DB_PASS', '');                 // Database password (default XAMPP: empty)
define('DB_CHARSET', 'utf8mb4');       // Character set (utf8mb4 supports full Unicode / emojis)

/**
 * Returns the singleton PDO database connection instance.
 *
 * Why Singleton?
 * - Reuses an existing connection instead of establishing a new TCP/socket
 *   connection on every function call or query, saving server CPU and memory.
 *
 * @return PDO The active PDO connection object
 */
function getDBConnection() {
    // Static variable persists across multiple function calls in the same request
    static $db = null;

    if ($db === null) {
        try {
            // Data Source Name (DSN) specifies the driver, host, database, and charset
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            // Instantiate PDO with security and error-handling options
            $db = new PDO($dsn, DB_USER, DB_PASS, [
                // Throw PDOException on errors so we can catch them cleanly
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                
                // Return query results as associative arrays ($row['column_name'])
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                
                // CRITICAL FOR SECURITY: Disable emulated prepares.
                // Forces MySQL server to prepare statements natively, preventing SQL injection.
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // Log the error internally for server administrators
            error_log("Database connection failed: " . $e->getMessage());
            
            // Display a generic message to the client without exposing credentials or stack trace
            die("Database connection failed. Please check configuration.");
        }
    }

    return $db;
}

/**
 * Closes the active database connection by resetting the reference.
 */
function closeDBConnection() {
    global $db;
    $db = null;
}

