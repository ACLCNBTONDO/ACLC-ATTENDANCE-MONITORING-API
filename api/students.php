<?php
ini_set('display_errors', 0);
error_reporting(0);
if (!defined('ROOT')) define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';

$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

// ── POST: Add student (admin only) ────────────────────────────────────
if ($method === 'POST') {
    if ($user['role'] !== 'admin') respondError('Admins only.', 403);
    $b       = getBody();
    $last    = trim($b['last_name']      ?? '');
    $first   = trim($b['first_name']     ?? '');
    $mid     = trim($b['middle_name']    ?? '');
    $age     = trim($b['age']            ?? '');
    $sex     = trim($b['sex']            ?? '');
    $usn     = trim($b['usn']            ?? '');
    $lrn     = trim($b['lrn']            ?? '');
    $section = trim($b['section']        ?? '');
    $email   = trim($b['guardian_email'] ?? '');
    if (!$last || !$first || !$usn || !$section) respondError('Last name, first name, USN and section are required.');
    $s = $db->prepare("INSERT INTO students (last_name,first_name,middle_name,age,sex,usn,lrn,section,guardian_email) VALUES (?,?,?,?,?,?,?,?,?)");
    $s->bind_param('sssssssss', $last, $first, $mid, $age, $sex, $usn, $lrn, $section, $email);
    if (!$s->execute()) respondError('Failed to add student. USN may already exist.');
    $s->close(); $db->close();
    respond(['success' => true, 'message' => 'Student added.']);
}

// ── GET all students (students/classes pages) ─────────────────────────
if (isset($_GET['all'])) {
    if ($user['role'] === 'teacher') {
        // Teacher sees their section only
        $s = $db->prepare("SELECT usn, first_name, last_name, middle_name, age, sex, lrn, section, CONCAT(last_name,', ',first_name) AS full_name FROM students WHERE section=? ORDER BY last_name,first_name");
        $s->bind_param('s', $user['section']);
    } else {
        $s = $db->prepare("SELECT usn, first_name, last_name, middle_name, age, sex, lrn, section, CONCAT(last_name,', ',first_name) AS full_name FROM students ORDER BY section,last_name,first_name");
    }
    $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close(); $db->close();
    respond(['success' => true, 'students' => $rows]);
}

// ── GET single student by USN ─────────────────────────────────────────
if (isset($_GET['usn'])) {
    $usn = $_GET['usn'];
    // Students can only view their own record
    if ($user['role'] === 'student' && $user['usn'] !== $usn) respondError('Access denied.', 403);
    $s = $db->prepare("SELECT usn, first_name, last_name, middle_name, age, sex, lrn, section, guardian_email FROM students WHERE usn=? LIMIT 1");
    $s->bind_param('s', $usn);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close(); $db->close();
    if (!$row) respondError('Student not found.', 404);
    respond(['success' => true, 'student' => $row]);
}

// ── GET students for attendance table ─────────────────────────────────
$section = $_GET['section'] ?? '';
$date    = $_GET['date']    ?? date('Y-m-d');
if (!$section) respondError('section is required.');
// Teacher can only access their own section
if ($user['role'] === 'teacher' && $user['section'] !== $section) respondError('Access denied.', 403);

$s = $db->prepare("
    SELECT
        st.usn, st.last_name, st.first_name, st.middle_name, st.sex, st.lrn, st.section,
        CONCAT(st.last_name, ', ', st.first_name) AS full_name,
        a.scanned_at,
        COALESCE(a.remarks, '') AS remarks
    FROM students st
    LEFT JOIN attendance a ON a.usn = st.usn AND a.attendance_date = ?
    WHERE st.section = ?
    ORDER BY st.last_name, st.first_name
");
$s->bind_param('ss', $date, $section);
$s->execute();
$rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$s->close(); $db->close();
respond(['success' => true, 'students' => $rows, 'section' => $section, 'date' => $date]);
