<?php
require_once '../config/cors.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respondError('Method not allowed.', 405);

$body     = getBody();
$role     = trim($body['role']     ?? '');
$name     = trim($body['name']     ?? '');
$username = trim($body['username'] ?? '');
$password = trim($body['password'] ?? '');
$usn      = trim($body['usn']      ?? '');
$section  = trim($body['section']  ?? '');

if (!$role || !$name || !$username || !$password) respondError('All fields are required.');
if (!in_array($role, ['admin','teacher','student'])) respondError('Invalid role.');
if (strlen($password) < 6) respondError('Password must be at least 6 characters.');

// Generate initials from name
$parts    = explode(' ', $name);
$initials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr(end($parts) ?? '', 0, 1));

$db = getDB();

// Check if username exists
$check = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$check->bind_param('s', $username);
$check->execute();
if ($check->get_result()->fetch_assoc()) {
    $check->close();
    respondError('Username already taken. Please choose another.');
}
$check->close();

// Hash password
$hashed = password_hash($password, PASSWORD_DEFAULT);

// Insert user
$stmt = $db->prepare("INSERT INTO users (username, password, role, name, initials, section, usn) VALUES (?, ?, ?, ?, ?, ?, ?)");
$usnVal = $role === 'student' ? $usn : null;
$secVal = in_array($role, ['student','teacher']) ? $section : null;
$stmt->bind_param('sssssss', $username, $hashed, $role, $name, $initials, $secVal, $usnVal);

if (!$stmt->execute()) {
    respondError('Failed to create account: ' . $db->error);
}
$stmt->close();

// If student, also link USN to students table
if ($role === 'student' && $usn) {
    $chk = $db->prepare("SELECT usn FROM students WHERE usn = ? LIMIT 1");
    $chk->bind_param('s', $usn);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        // USN not in students table — insert a placeholder
        $ins = $db->prepare("INSERT IGNORE INTO students (usn, first_name, last_name, section) VALUES (?, ?, ?, ?)");
        $nameParts = explode(',', $name);
        $lastName  = trim($nameParts[0] ?? $name);
        $firstName = trim($nameParts[1] ?? '');
        $ins->bind_param('ssss', $usn, $firstName, $lastName, $section);
        $ins->execute();
        $ins->close();
    }
    $chk->close();
}

$db->close();
respond(['success' => true, 'message' => 'Account created successfully!']);
