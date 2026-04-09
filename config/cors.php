<?php
// Allow the Vercel frontend
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = [
    'https://aclc-attendance-monitoring-web-git-main-aclnbtondos-projects.vercel.app',
    'https://aclc-attendance-monitoring-fkrmhw592-aclnbtondos-projects.vercel.app',
    'http://localhost',
    'http://localhost:3000',
    'http://127.0.0.1',
];
if (in_array($origin, $allowed) || str_contains($origin, 'vercel.app')) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Requested-With");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo '{}';
    exit;
}

function respond($data, $code = 200) {
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function respondError($msg, $code = 400) {
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function getBody() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function requireAuth() {
    $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? $_GET['token'] ?? '';
    if (!$token) respondError('Unauthorized — please log in.', 401);
    $db   = getDB();
    $stmt = $db->prepare("SELECT id, username, role, name, initials, section, usn FROM users WHERE auth_token = ? LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) respondError('Session expired — please log in again.', 401);
    return $user;
}

function requireRole(...$roles) {
    $user = requireAuth();
    if (!in_array($user['role'], $roles)) respondError('You do not have permission.', 403);
    return $user;
}
