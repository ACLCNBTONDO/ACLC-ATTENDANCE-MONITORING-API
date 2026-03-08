<?php
/**
 * config/db.php
 * ─────────────────────────────────────────
 * On Railway: credentials are auto-injected as environment variables.
 * Locally (XAMPP): falls back to the defaults below.
 *
 * Railway env vars it reads automatically:
 *   MYSQLHOST, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE, MYSQLPORT
 */

define('DB_HOST', getenv('MYSQLHOST')     ?: 'localhost');
define('DB_PORT', getenv('MYSQLPORT')     ?: '3306');
define('DB_USER', getenv('MYSQLUSER')     ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'attendease_db');

function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
        exit;
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
