<?php
ini_set('display_errors', 0);
error_reporting(0);
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respondError('Method not allowed', 405);

$b        = getBody();
$role     = trim($b['role']     ?? '');
$name     = trim($b['name']     ?? '');
$username = trim($b['username'] ?? '');
$password = trim($b['password'] ?? '');
$usn      = trim($b['usn']      ?? '');
$section  = trim($b['section']  ?? '');

if (!$role || !$name || !$username || !$password) respondError('All fields required.');
if (!in_array($role, ['admin','teacher','student'])) respondError('Invalid role.');
if (strlen($password) < 6) respondError('Password must be at least 6 characters.');

$parts    = explode(' ', $name);
$initials = strtoupper(substr($parts[0],0,1) . substr(end($parts),0,1));

$db = getDB();
$chk = $db->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
$chk->bind_param('s', $username);
$chk->execute();
if ($chk->get_result()->fetch_assoc()) respondError('Username already taken.');
$chk->close();

$hashed = password_hash($password, PASSWORD_DEFAULT);
$secVal = in_array($role, ['student','teacher']) ? $section : null;
$usnVal = $role === 'student' ? $usn : null;

$stmt = $db->prepare("INSERT INTO users (username,password,role,name,initials,section,usn) VALUES (?,?,?,?,?,?,?)");
$stmt->bind_param('sssssss', $username, $hashed, $role, $name, $initials, $secVal, $usnVal);
if (!$stmt->execute()) respondError('Failed to create account.');
$stmt->close();
$db->close();
respond(['success' => true, 'message' => 'Account created!']);
