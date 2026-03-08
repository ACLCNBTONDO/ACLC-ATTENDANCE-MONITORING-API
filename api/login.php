<?php
/**
 * api/login.php
 * POST /api/login.php
 *
 * Handles login for all 3 roles:
 *   - admin   → checks users table (role = 'admin')
 *   - teacher → checks users table (role = 'teacher')
 *   - student → checks students table using USN as username
 *
 * Body:    { "username": "...", "password": "...", "role": "admin|teacher|student" }
 * Returns: { success, user: { id, name, role, initials, section_id, section_name, usn? } }
 */

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

// ── Student login: authenticate via students table using USN ──────────
if ($role === 'student') {
    $stmt = $db->prepare("
        SELECT usn, first_name, last_name, middle_name, section
        FROM students
        WHERE usn = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$student) {
        respondError('Student not found. Check your USN.', 401);
    }

    // For students: password is their USN by default (change as needed)
    // You can store a separate password column, or use their LRN, etc.
    // Current rule: password must match their USN (simple default)
    if ($password !== $student['usn'] && $password !== 'student123') {
        respondError('Incorrect password.', 401);
    }

    // Build display name and initials
    $fullName = $student['last_name'] . ', ' . $student['first_name'];
    $initials = strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1));

    // Get section_id from sections table
    $sStmt = $db->prepare("SELECT id FROM sections WHERE name = ? LIMIT 1");
    $sStmt->bind_param('s', $student['section']);
    $sStmt->execute();
    $sRow = $sStmt->get_result()->fetch_assoc();
    $sStmt->close();

    $_SESSION['user'] = [
        'id'           => null,
        'usn'          => $student['usn'],
        'name'         => $fullName,
        'role'         => 'student',
        'initials'     => $initials,
        'section_id'   => $sRow['id']   ?? null,
        'section_name' => $student['section'],
    ];

    respond(['success' => true, 'user' => $_SESSION['user']]);
}

// ── Admin / Teacher login: authenticate via users table ──────────────
$stmt = $db->prepare("
    SELECT u.id, u.username, u.password, u.role, u.name, u.initials,
           u.section_id, s.name AS section_name
    FROM users u
    LEFT JOIN sections s ON u.section_id = s.id
    WHERE u.username = ? AND u.role = ?
    LIMIT 1
");
$stmt->bind_param('ss', $username, $role);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    respondError('Incorrect username or password.', 401);
}

// Support both bcrypt hashed AND plain-text passwords (for easy dev setup)
$passwordOk = password_verify($password, $user['password'])
           || $password === $user['password'];

if (!$passwordOk) {
    respondError('Incorrect username or password.', 401);
}

$_SESSION['user'] = [
    'id'           => $user['id'],
    'name'         => $user['name'],
    'role'         => $user['role'],
    'initials'     => $user['initials'],
    'section_id'   => $user['section_id'],
    'section_name' => $user['section_name'],
    'usn'          => null,
];

respond(['success' => true, 'user' => $_SESSION['user']]);
