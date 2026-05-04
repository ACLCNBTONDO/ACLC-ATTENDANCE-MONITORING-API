
<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(0);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Requested-With");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    echo '{}';
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = ltrim($uri, '/');

if ($uri === '' || $uri === 'index.php') {
    ob_end_clean();
    echo json_encode(['status' => 'ACLC Monitor API is running!', 'version' => '2.0']);
    exit;
}

if (preg_match('/^[\w\-]+\.php$/', $uri)) {
    $file = __DIR__ . '/' . $uri;
    if (file_exists($file)) {
        ob_end_clean();
        require $file;
        exit;
    }
}

if (strpos($uri, 'api/') === 0) {
    $file = __DIR__ . '/' . $uri;
    if (file_exists($file)) {
        ob_end_clean();
        require $file;
        exit;
    }
    ob_end_clean();
    http_response_code(404);
    echo json_encode(['error' => "Not found: $uri"]);
    exit;
}

ob_end_clean();
http_response_code(404);
echo json_encode(['error' => 'Unknown route: ' . $uri]);
