<?php
/**
 * config/cors.php
 * Sets headers so the frontend HTML file can talk to this PHP backend.
 * Include this at the top of every API file.
 */

// Allow the frontend to call this API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Handle browser preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ─── Helper: send a JSON response and stop ───────────────
function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function respondError($message, $code = 400) {
    respond(['success' => false, 'error' => $message], $code);
}

// ─── Helper: get JSON body from POST requests ────────────
function getBody() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

// ─── Helper: require a valid session ─────────────────────
function requireAuth() {
    session_start();
    if (empty($_SESSION['user'])) {
        respondError('Not authenticated. Please log in.', 401);
    }
    return $_SESSION['user'];
}

// ─── Helper: require a specific role ─────────────────────
function requireRole(...$roles) {
    $user = requireAuth();
    if (!in_array($user['role'], $roles)) {
        respondError('Access denied for your role.', 403);
    }
    return $user;
}
