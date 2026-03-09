<?php
ini_set('display_errors', 0);
error_reporting(0);
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { respondError('Method not allowed', 405); }

$body     = getBody();
$username = trim($body['username'] ?? '');
$password = trim($body['password'] ?? '');
$role     = trim($body['role']     ?? '');

if (!$username || !$password || !$role) respondError('All fields are required.');

$db   = getDB();
$stmt = $db->prepare("SELECT id, username, password, role, name, initials, section, usn FROM users WHERE username = ? AND role = ? LIMIT 1");
$stmt->bind_param('ss', $username, $role);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) { http_response_code(401); respondError('Incorrect username or password.', 401); }

$ok = password_verify($password, $user['password']) || ($password === $user['password']);
if (!$ok) respondError('Incorrect username or password.', 401);

$token = md5(uniqid('', true)) . md5(uniqid('', true));
$upd   = $db->prepare("UPDATE users SET auth_token = ? WHERE id = ?");
$upd->bind_param('si', $token, $user['id']);
$upd->execute();
$upd->close();
$db->close();

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
