<?php
require_once '../config/cors.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respondError('Method not allowed.', 405);

$user = requireAuth();
$body = getBody();
$action = $body['action'] ?? '';
$db = getDB();

if ($action === 'update_name') {
    $name     = trim($body['name'] ?? '');
    $initials = strtoupper(substr($name, 0, 1) . (strpos($name,' ')!==false ? substr(strrchr($name,' '),1,1) : ''));
    if (!$name) respondError('Name is required.');
    $stmt = $db->prepare("UPDATE users SET name=?, initials=? WHERE id=?");
    $stmt->bind_param('ssi', $name, $initials, $user['id']);
    $stmt->execute();
    $stmt->close();
    respond(['success' => true, 'message' => 'Name updated.']);
}

if ($action === 'change_password') {
    $oldPass = $body['old_password'] ?? '';
    $newPass = $body['new_password'] ?? '';
    if (!$oldPass || !$newPass) respondError('Both passwords are required.');
    if (strlen($newPass) < 6) respondError('New password must be at least 6 characters.');

    $stmt = $db->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $ok = password_verify($oldPass, $row['password']) || $oldPass === $row['password'];
    if (!$ok) respondError('Current password is incorrect.');

    $hashed = password_hash($newPass, PASSWORD_DEFAULT);
    $upd = $db->prepare("UPDATE users SET password=? WHERE id=?");
    $upd->bind_param('si', $hashed, $user['id']);
    $upd->execute();
    $upd->close();
    respond(['success' => true, 'message' => 'Password changed.']);
}

respondError('Invalid action.');
