<?php
require_once '../config/cors.php';
require_once '../config/db.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondError('Method not allowed.', 405);
}

$body     = getBody();
$username = trim($body['username'] ?? '');
$password = trim($body['password'] ?? '');
$role     = trim($body['role']     ?? '');

if (!$username || !$password || !$role) {
    respondError('Username, password, and role are required.');
}
if (!in_array($role, ['admin', 'teacher', 'student'])) {
    respondError('Invalid role.');
}

$db = getDB();

// ── Student login ─────────────────────────────────────────────────────
if ($role === 'student') {
    $stmt = $db->prepare("SELECT u.id, u.username, u.password, u.role, u.name, u.initials, u.section, u.usn FROM users u WHERE u.username = ? AND u.role = 'student' LIMIT 1");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) { respondError('Student not found.', 401); }

    $passwordOk = password_verify($password, $user['password']) || $password === $user['password'];
    if (!$passwordOk) { respondError('Incorrect password.', 401); }

    $_SESSION['user'] = [
        'id'           => $user['id'],
        'usn'          => $user['usn'],
        'name'         => $user['name'],
        'role'         => 'student',
        'initials'     => $user['initials'],
        'section_name' => $user['section'],
    ];
    respond(['success' => true, 'user' => $_SESSION['user']]);
}

// ── Admin / Teacher login ─────────────────────────────────────────────
$stmt = $db->prepare("SELECT id, username, password, role, name, initials, section FROM users WHERE username = ? AND role = ? LIMIT 1");
$stmt->bind_param('ss', $username, $role);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) { respondError('Incorrect username or password.', 401); }

$passwordOk = password_verify($password, $user['password']) || $password === $user['password'];
if (!$passwordOk) { respondError('Incorrect username or password.', 401); }

$_SESSION['user'] = [
    'id'           => $user['id'],
    'name'         => $user['name'],
    'role'         => $user['role'],
    'initials'     => $user['initials'],
    'section_name' => $user['section'],
    'usn'          => null,
];

respond(['success' => true, 'user' => $_SESSION['user']]);
