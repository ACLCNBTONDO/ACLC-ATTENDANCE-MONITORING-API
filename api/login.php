<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(0);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    respondError('Method not allowed.', 405);
}

$body     = getBody();
$username = trim($body['username'] ?? '');
$password = $body['password'] ?? ''; // Don't trim — passwords can have spaces
$role     = trim($body['role']     ?? '');

if (!$username || !$password || !$role) {
    ob_end_clean();
    respondError('All fields are required.');
}
if (!in_array($role, ['admin','teacher','student'])) {
    ob_end_clean();
    respondError('Invalid role.');
}

$db = getDB();

// Find user by username AND role
$stmt = $db->prepare("SELECT id, username, password, role, name, initials, section, usn FROM users WHERE username = ? AND role = ? LIMIT 1");
$stmt->bind_param('ss', $username, $role);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// User not found
if (!$user) {
    ob_end_clean();
    http_response_code(401);
    respond(['error' => 'Incorrect username or password.']);
}

// Strictly check password
$passwordOk = false;
if (password_verify($password, $user['password'])) {
    $passwordOk = true;
} elseif ($password === $user['password']) {
    // Plain text match (for dev accounts)
    $passwordOk = true;
}

if (!$passwordOk) {
    ob_end_clean();
    http_response_code(401);
    respond(['error' => 'Incorrect username or password.']);
}

// Generate new token — invalidates any previous session
$token = bin2hex(random_bytes(32));
$upd = $db->prepare("UPDATE users SET auth_token = ? WHERE id = ?");
$upd->bind_param('si', $token, $user['id']);
$upd->execute();
$upd->close();
$db->close();

ob_end_clean();
respond([
    'success' => true,
    'token'   => $token,
    'user'    => [
        'id'           => (int)$user['id'],
        'name'         => $user['name'],
        'role'         => $user['role'],
        'initials'     => $user['initials'],
        'section_name' => $user['section'],
        'section'      => $user['section'],
        'usn'          => $user['usn'],
    ]
]);
