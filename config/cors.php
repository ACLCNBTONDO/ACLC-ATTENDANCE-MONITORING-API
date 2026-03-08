<?php

$allowed_origins = [
    'https://aclc-attendance-monitoring-web.vercel.app',
    'http://localhost',
    'http://127.0.0.1',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: https://aclc-attendance-monitoring-web.vercel.app");
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');

// ── Helpers ──────────────────────────────────────────
function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function respondError($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

function getBody() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function requireAuth() {
    session_start();
    if (empty($_SESSION['user_id'])) {
        respondError('Unauthorized', 401);
    }
    return $_SESSION;
}

function requireRole($role) {
    $session = requireAuth();
    if ($session['role'] !== $role) {
        respondError('Forbidden', 403);
    }
    return $session;
}
