<?php
// index.php — Entry point for Railway PHP server

// Handle CORS preflight OPTIONS at the top level before any routing
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = [
    'https://aclc-attendance-monitoring-web.vercel.app',
    'http://localhost',
    'http://localhost:3000',
    'http://127.0.0.1',
];
$allowOrigin = in_array($origin, $allowed)
    ? $origin
    : 'https://aclc-attendance-monitoring-web.vercel.app';

header("Access-Control-Allow-Origin: $allowOrigin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Requested-With");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = ltrim($uri, '/');

// Route to setup.php
if ($uri === 'setup.php') {
    require __DIR__ . '/setup.php';
    exit;
}

// Route api/* requests
if (strpos($uri, 'api/') === 0) {
    $file = __DIR__ . '/' . $uri;
    if (file_exists($file)) {
        require $file;
        exit;
    }
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['error' => "Endpoint not found: $uri"]);
    exit;
}

// Default — API status
header('Content-Type: application/json');
echo json_encode(['status' => 'ACLC Monitor API is running!', 'version' => '2.0']);
