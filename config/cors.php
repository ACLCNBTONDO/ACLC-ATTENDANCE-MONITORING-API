<?php
// Global handlers — ensures every fatal/exception returns valid JSON instead of empty body
set_exception_handler(function(Throwable $e) {
    if (ob_get_level()) ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
});
set_error_handler(function($errno, $errstr) {
    throw new ErrorException($errstr, $errno);
});

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (str_contains($origin, 'vercel.app') || str_contains($origin, 'localhost') || str_contains($origin, '127.0.0.1')) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: *");
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
