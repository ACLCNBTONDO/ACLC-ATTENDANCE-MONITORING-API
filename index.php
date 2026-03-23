<?php
ini_set('display_errors', 0);
error_reporting(0);
define('ROOT', __DIR__);

// ── CORS ──────────────────────────────────────────────────────────────
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = [
    'https://aclc-attendance-monitoring-web.vercel.app',
    'https://aclc-attendance-monitoring-bzbv751sz.vercel.app',
    'http://localhost',
    'http://localhost:3000',
    'http://127.0.0.1',
];

// Allow any vercel.app subdomain for this project
$isAllowed = in_array($origin, $allowed) || preg_match('/^https:\/\/aclc-attendance-monitoring[a-z0-9\-]*\.vercel\.app$/', $origin);

header("Access-Control-Allow-Origin: " . ($isAllowed ? $origin : $allowed[0]));
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Auth-Token");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo '{}';
    exit;
}

// ── Router ────────────────────────────────────────────────────────────
$uri  = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

if ($uri === '' || $uri === 'index.php') {
    echo json_encode(['status' => 'ACLC Monitor API running!']);
    exit;
}

$file = ROOT . '/' . $uri;
if (file_exists($file) && is_file($file)) {
    require $file;
    exit;
}

http_response_code(404);
echo json_encode(['error' => "Not found: /$uri"]);
