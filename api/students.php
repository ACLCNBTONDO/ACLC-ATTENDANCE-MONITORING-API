<?php
ini_set('display_errors', 0);
error_reporting(0);
require_once __DIR__ . '/../config/db.php';

$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

// POST: Add student
if ($method === 'POST') {
    if ($user['role'] !== 'admin') respondError('Admins only.', 403);
    $b = getBody();
    $last    = trim($b['last_name']      ?? '');
    $first   = trim($b['first_name']     ?? '');
    $middle  = trim($b['middle_name']    ?? '');
    $age     = trim($b['age']            ?? '');
    $sex     = trim($b['sex']            ?? '');
    $usn     = trim($b['usn']            ?? '');
    $lrn     = trim($b['lrn']            ?? '');
    $section = trim($b['section']        ?? '');
    $email   = trim($b['guardian_email'] ?? '');
    if (!$last || !$first || !$usn || !$section) respondError('Last name, first name, USN, and section are required.');
    $stmt = $db->prepare("INSERT INTO students (last_name, first_name, middle_name, age, sex, usn, lrn, section, guardian_email) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('sssssssss', $last, $first, $middle, $age, $sex, $usn, $lrn, $section, $email);
    if (!$stmt->execute()) respondError('Failed: USN may already exist.');
    $stmt->close();
    $db->close();
    respond(['success' => true, 'message' => 'Student added.']);
}

// GET all students
if (isset($_GET['all'])) {
    if ($user['role'] === 'teacher') {
        $stmt = $db->prepare("SELECT usn, first_name, last_name, middle_name, age, sex, lrn, section, guardian_email, CONCAT(last_name,', ',first_name) AS full_name FROM students WHERE section=? ORDER BY last_name,first_name");
        $stmt->bind_param('s', $user['section']);
    } else {
        $stmt = $db->prepare("SELECT usn, first_name, last_name, middle_name, age, sex, lrn, section, guardian_email, CONCAT(last_name,', ',first_name) AS full_name FROM students ORDER BY section,last_name,first_name");
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close(); $db->close();
    respond(['success' => true, 'students' => $rows]);
}

// GET single student by USN
if (isset($_GET['usn'])) {
    $usn  = $_GET['usn'];
    $stmt = $db->prepare("SELECT usn, first_name, last_name, middle_name, age, sex, lrn, section, guardian_email FROM students WHERE usn=? LIMIT 1");
    $stmt->bind_param('s', $usn);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close(); $db->close();
    if (!$student) respondError('Student not found.', 404);
    respond(['success' => true, 'student' => $student]);
}

// GET students for attendance table
$section = $_GET['section'] ?? '';
$date    = $_GET['date']    ?? date('Y-m-d');
if (!$section) respondError('section is required.');
if ($user['role'] === 'teacher' && $user['section'] !== $section) respondError('Access denied.', 403);

$stmt = $db->prepare("
    SELECT st.usn, st.last_name, st.first_name, st.middle_name, st.sex, st.lrn, st.section,
        CONCAT(st.last_name,', ',st.first_name) AS full_name,
        a.scanned_at, COALESCE(a.remarks,'') AS remarks
    FROM students st
    LEFT JOIN attendance a ON a.usn=st.usn AND a.attendance_date=?
    WHERE st.section=?
    ORDER BY st.last_name, st.first_name
");
$stmt->bind_param('ss', $date, $section);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close(); $db->close();
respond(['success' => true, 'students' => $rows, 'section' => $section, 'date' => $date]);
