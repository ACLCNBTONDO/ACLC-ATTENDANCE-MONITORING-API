<?php
ini_set('display_errors', 0);
error_reporting(0);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['https://aclc-attendance-monitoring-web.vercel.app','http://localhost','http://127.0.0.1'];
header("Access-Control-Allow-Origin: " . (in_array($origin, $allowed) ? $origin : $allowed[0]));
header("Content-Type: application/json");

$result = [];

// 1. Check env vars
$mysqlUrl    = getenv('MYSQL_URL')        ?: null;
$mysqlHost   = getenv('MYSQLHOST')        ?: null;
$mysqlPort   = getenv('MYSQLPORT')        ?: null;
$mysqlUser   = getenv('MYSQLUSER')        ?: null;
$mysqlPass   = getenv('MYSQLPASSWORD')    ?: null;
$mysqlDb     = getenv('MYSQLDATABASE')    ?: null;

$result['env'] = [
    'MYSQL_URL_set'      => !empty($mysqlUrl),
    'MYSQLHOST_set'      => !empty($mysqlHost),
    'MYSQLPORT_set'      => !empty($mysqlPort),
    'MYSQLUSER_set'      => !empty($mysqlUser),
    'MYSQLPASSWORD_set'  => !empty($mysqlPass),
    'MYSQLDATABASE_set'  => !empty($mysqlDb),
];

// 2. Try parsing MYSQL_URL
if ($mysqlUrl) {
    $parts = parse_url($mysqlUrl);
    $result['url_parse'] = [
        'host'   => $parts['host'] ?? 'MISSING',
        'port'   => $parts['port'] ?? 'MISSING',
        'user'   => $parts['user'] ?? 'MISSING',
        'pass'   => !empty($parts['pass']) ? 'SET' : 'MISSING',
        'dbname' => ltrim($parts['path'] ?? '', '/') ?: 'MISSING',
    ];
}

// 3. Try connecting
try {
    require_once __DIR__ . '/../config/db.php';
    $db = getDB();
    $result['db_connect'] = 'SUCCESS';
    
    // 4. Check users table
    $r = $db->query("SELECT COUNT(*) as cnt FROM users");
    $result['users_count'] = $r ? $r->fetch_assoc()['cnt'] : 'query failed: ' . $db->error;
    $db->close();
} catch (Exception $e) {
    $result['db_connect'] = 'FAILED: ' . $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);
