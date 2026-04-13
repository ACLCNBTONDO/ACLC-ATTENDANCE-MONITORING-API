<?php
function getDB() {
    $url = getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: null;
    if ($url) {
        $p    = parse_url($url);
        $host = $p['host']  ?? '';
        $port = (int)($p['port'] ?? 3306);
        $user = $p['user']  ?? '';
        $pass = isset($p['pass']) ? urldecode($p['pass']) : '';
        $name = ltrim($p['path'] ?? '', '/');
    } else {
        $host = getenv('MYSQLHOST')     ?: 'localhost';
        $port = (int)(getenv('MYSQLPORT') ?: 3306);
        $user = getenv('MYSQLUSER')     ?: 'root';
        $pass = getenv('MYSQLPASSWORD') ?: '';
        $name = getenv('MYSQLDATABASE') ?: 'railway';
    }
    $conn = @new mysqli($host, $user, $pass, $name, $port);
    if ($conn->connect_error) {
        if (ob_get_level()) ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'DB connection failed: ' . $conn->connect_error]);
        exit;
    }
    $conn->set_charset('utf8mb4');
    // Throw exceptions on mysqli errors so they get caught by the global handler
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    return $conn;
}
