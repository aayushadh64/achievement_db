<?php
/**
 * Database Layer — PDO Singleton
 * Uses prepared statements to prevent SQL injection.
 */

require_once __DIR__ . '/app.php';

class Database
{
    private static ?PDO $instance = null;

    /**
     * Get the PDO instance (creates one if it doesn't exist).
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci",
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                if (DEBUG_MODE) {
                    die(json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]));
                } else {
                    die(json_encode(['success' => false, 'message' => 'Database connection failed. Please try again later.']));
                }
            }
        }

        return self::$instance;
    }

    // Prevent instantiation and cloning
    private function __construct() {}
    private function __clone() {}
}

/**
 * Convenience wrapper — returns a PDO instance.
 */
function db(): PDO
{
    return Database::getInstance();
}
