<?php
ini_set('display_errors', 0);
error_reporting(0);
if (!defined('ROOT')) define('ROOT', dirname(__DIR__));

function getDB() {
    $url = getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: null;
    if ($url) {
        $p    = parse_url($url);
        $host = $p['host']               ?? 'localhost';
        $port = (int)($p['port']         ?? 3306);
        $user = $p['user']               ?? 'root';
        $pass = urldecode($p['pass']     ?? '');
        $name = ltrim($p['path']         ?? '/railway', '/');
    } else {
        $host = getenv('MYSQLHOST')      ?: 'localhost';
        $port = (int)(getenv('MYSQLPORT')?: 3306);
        $user = getenv('MYSQLUSER')      ?: 'root';
        $pass = getenv('MYSQLPASSWORD')  ?: '';
        $name = getenv('MYSQLDATABASE')  ?: 'railway';
    }
    $db = @new mysqli($host, $user, $pass, $name, $port);
    if ($db->connect_error) {
        http_response_code(500);
        echo json_encode(['error' => 'DB connection failed: ' . $db->connect_error]);
        exit;
    }
    $db->set_charset('utf8mb4');
    return $db;
}

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function respondError($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function getBody() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function requireAuth() {
    $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if (!$token) respondError('Unauthorized', 401);
    $db   = getDB();
    $stmt = $db->prepare("SELECT id, username, role, name, initials, section, usn FROM users WHERE auth_token = ? LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $db->close();
    if (!$user) respondError('Session expired', 401);
    return $user;
}
