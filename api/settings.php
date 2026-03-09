<?php
ini_set('display_errors', 0);
error_reporting(0);
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respondError('Method not allowed', 405);
$user   = requireAuth();
$b      = getBody();
$action = $b['action'] ?? '';
$db     = getDB();

if ($action === 'update_name') {
    $name     = trim($b['name'] ?? '');
    if (!$name) respondError('Name required.');
    $initials = strtoupper(substr($name,0,1) . (strpos($name,' ')!==false ? substr(strrchr($name,' '),1,1) : ''));
    $stmt = $db->prepare("UPDATE users SET name=?,initials=? WHERE id=?");
    $stmt->bind_param('ssi', $name, $initials, $user['id']);
    $stmt->execute(); $stmt->close(); $db->close();
    respond(['success' => true]);
}

if ($action === 'change_password') {
    $old = $b['old_password'] ?? '';
    $new = $b['new_password'] ?? '';
    if (!$old || !$new) respondError('Both passwords required.');
    if (strlen($new) < 6) respondError('Min 6 characters.');
    $stmt = $db->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!password_verify($old, $row['password']) && $old !== $row['password']) respondError('Current password incorrect.');
    $hashed = password_hash($new, PASSWORD_DEFAULT);
    $upd = $db->prepare("UPDATE users SET password=? WHERE id=?");
    $upd->bind_param('si', $hashed, $user['id']);
    $upd->execute(); $upd->close(); $db->close();
    respond(['success' => true]);
}

respondError('Invalid action.');
