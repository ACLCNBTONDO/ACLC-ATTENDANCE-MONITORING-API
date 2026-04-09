<?php
ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { ob_end_clean(); respondError('Method not allowed.', 405); }

$body     = getBody();
$username = trim($body['username'] ?? '');
$password = $body['password'] ?? '';
$role     = trim($body['role'] ?? '');

if (!$username || !$password || !$role) { ob_end_clean(); respondError('All fields are required.'); }
if (!in_array($role, ['admin','teacher','student'])) { ob_end_clean(); respondError('Invalid role.'); }

$db   = getDB();
$stmt = $db->prepare("SELECT id, username, password, role, name, initials, section, usn FROM users WHERE username = ? AND role = ? LIMIT 1");
$stmt->bind_param('ss', $username, $role);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) { ob_end_clean(); http_response_code(401); respond(['error' => 'Incorrect username or password.']); }

$ok = password_verify($password, $user['password']) || ($password === $user['password']);
if (!$ok) { ob_end_clean(); http_response_code(401); respond(['error' => 'Incorrect username or password.']); }

$token = bin2hex(random_bytes(32));
$upd   = $db->prepare("UPDATE users SET auth_token = ? WHERE id = ?");
$upd->bind_param('si', $token, $user['id']);
$upd->execute();
$upd->close();
$db->close();

ob_end_clean();
respond(['success' => true, 'token' => $token, 'user' => [
    'id'           => (int)$user['id'],
    'name'         => $user['name'],
    'role'         => $user['role'],
    'initials'     => $user['initials'],
    'section_name' => $user['section'],
    'section'      => $user['section'],
    'usn'          => $user['usn'],
]]);
