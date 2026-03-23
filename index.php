<?php
ini_set('display_errors', 0);
error_reporting(0);
define('ROOT', __DIR__);

// ── CORS ──────────────────────────────────────────────────────────────
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = [
    'https://aclc-attendance-monitoring-web.vercel.app',
    'http://localhost',
    'http://127.0.0.1',
];
header("Access-Control-Allow-Origin: " . (in_array($origin, $allowed) ? $origin : $allowed[0]));
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Auth-Token");
header("Content-Type: application/json");

// Answer preflight immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo '{}';
    exit;
}

// ── Router ────────────────────────────────────────────────────────────
$uri  = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// Root ping
if ($uri === '' || $uri === 'index.php') {
    echo json_encode(['status' => 'ACLC Monitor API running!']);
    exit;
}

// Serve file
$file = ROOT . '/' . $uri;
if (file_exists($file) && is_file($file)) {
    require $file;
    exit;
}

http_response_code(404);
echo json_encode(['error' => "Not found: /$uri"]);
