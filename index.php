<?php
ini_set('display_errors', 0);
error_reporting(0);

// ── CORS ──────────────────────────────────────────────────────────────
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['https://aclc-attendance-monitoring-web.vercel.app', 'http://localhost', 'http://127.0.0.1'];

if (in_array($origin, $allowed)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: https://aclc-attendance-monitoring-web.vercel.app");
}
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Auth-Token");
header("Content-Type: application/json");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo '{}';
    exit;
}

// ── Route ─────────────────────────────────────────────────────────────
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri  = trim($uri, '/');

if ($uri === '' || $uri === 'index.php') {
    echo json_encode(['status' => 'ACLC Monitor API is running!']);
    exit;
}

$file = __DIR__ . '/' . $uri . (pathinfo($uri, PATHINFO_EXTENSION) ? '' : '.php');
if (!file_exists(__DIR__ . '/' . $uri)) {
    // try with .php
    $uri2 = __DIR__ . '/' . $uri;
    if (file_exists($uri2)) { require $uri2; exit; }
}

$path = __DIR__ . '/' . $uri;
if (file_exists($path)) {
    require $path;
    exit;
}

http_response_code(404);
echo json_encode(['error' => "Not found: $uri"]);
