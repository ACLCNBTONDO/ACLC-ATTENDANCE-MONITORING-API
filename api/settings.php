<?php
<<<<<<< HEAD
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

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

=======
require_once __DIR__.'/../config/init.php';
if($_SERVER['REQUEST_METHOD']!=='POST') respondError('Method not allowed',405);
$user=requireAuth();$b=getBody();$action=$b['action']??'';$db=getDB();
if($action==='update_name'){$name=trim($b['name']??'');if(!$name)respondError('Name required.');$parts=explode(' ',$name);$ini=strtoupper(substr($parts[0],0,1).substr(end($parts),0,1));$s=$db->prepare("UPDATE users SET name=?,initials=? WHERE id=?");$s->bind_param('ssi',$name,$ini,$user['id']);$s->execute();$s->close();$db->close();respond(['success'=>true]);}
if($action==='change_password'){$old=$b['old_password']??'';$new=$b['new_password']??'';if(!$old||!$new)respondError('Both required.');if(strlen($new)<6)respondError('Min 6 chars.');$s=$db->prepare("SELECT password FROM users WHERE id=? LIMIT 1");$s->bind_param('i',$user['id']);$s->execute();$row=$s->get_result()->fetch_assoc();$s->close();if(!password_verify($old,$row['password'])&&$old!==$row['password'])respondError('Wrong password.');$h=password_hash($new,PASSWORD_DEFAULT);$u=$db->prepare("UPDATE users SET password=? WHERE id=?");$u->bind_param('si',$h,$user['id']);$u->execute();$u->close();$db->close();respond(['success'=>true]);}
>>>>>>> 25b772fa27ed306d9e6d113effd16aaaba91ccff
respondError('Invalid action.');
