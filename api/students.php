<?php
ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';

$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

if ($method === 'POST') {
    if ($user['role'] !== 'admin') { ob_end_clean(); respondError('Only admins can add students.', 403); }
    $body = getBody();
    $last  = trim($body['last_name']      ?? '');
    $first = trim($body['first_name']     ?? '');
    $mid   = trim($body['middle_name']    ?? '');
    $age   = trim($body['age']            ?? '');
    $sex   = trim($body['sex']            ?? '');
    $usn   = trim($body['usn']            ?? '');
    $lrn   = trim($body['lrn']            ?? '');
    $sec   = trim($body['section']        ?? '');
    $email = trim($body['guardian_email'] ?? '');
    if (!$last || !$first || !$usn || !$sec) { ob_end_clean(); respondError('Last name, first name, USN and section required.'); }
    $stmt = $db->prepare("INSERT INTO students (last_name,first_name,middle_name,age,sex,usn,lrn,section,guardian_email) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('sssssssss', $last,$first,$mid,$age,$sex,$usn,$lrn,$sec,$email);
    if (!$stmt->execute()) { ob_end_clean(); respondError('Failed: USN may already exist.'); }
    $stmt->close();
    ob_end_clean();
    respond(['success' => true, 'message' => 'Student added.']);
}

// Single student by USN
if (isset($_GET['usn'])) {
    $usn = $_GET['usn'];
    if ($user['role'] === 'student' && $user['usn'] !== $usn) { ob_end_clean(); respondError('Access denied.', 403); }
    $stmt = $db->prepare("SELECT usn,first_name,last_name,middle_name,age,sex,lrn,section,guardian_email FROM students WHERE usn=? LIMIT 1");
    $stmt->bind_param('s', $usn);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$student) { ob_end_clean(); respondError('Student not found.', 404); }
    ob_end_clean();
    respond(['success' => true, 'student' => $student]);
}

// All students (admin only)
if (isset($_GET['all'])) {
    if ($user['role'] !== 'admin') { ob_end_clean(); respondError('Access denied.', 403); }
    $result = $db->query("SELECT usn,first_name,last_name,middle_name,age,sex,lrn,section,guardian_email FROM students ORDER BY section,last_name ASC");
    $students = [];
    while ($row = $result->fetch_assoc()) $students[] = $row;
    ob_end_clean();
    respond(['success' => true, 'students' => $students]);
}

// Students by section + date
$section = $_GET['section'] ?? '';
$date    = $_GET['date']    ?? date('Y-m-d');
if (!$section) { ob_end_clean(); respondError('section is required.'); }
if ($user['role'] === 'teacher' && $user['section'] !== $section) { ob_end_clean(); respondError('Access denied.', 403); }

$stmt = $db->prepare("SELECT st.usn, st.last_name, st.first_name, st.middle_name, st.sex, st.age, st.lrn, st.section, CONCAT(st.last_name,', ',st.first_name,IF(st.middle_name IS NOT NULL AND st.middle_name!='',CONCAT(' ',LEFT(st.middle_name,1),'.'),'' )) AS full_name, COALESCE(a.status,'absent') AS status, COALESCE(a.time_in,'') AS time_in, COALESCE(a.scanned_at,'') AS scanned_at, COALESCE(a.remarks,'') AS remarks FROM students st LEFT JOIN attendance a ON a.usn=st.usn AND a.date=? WHERE st.section=? ORDER BY st.last_name ASC,st.first_name ASC");
$stmt->bind_param('ss', $date, $section);
$stmt->execute();
$result = $stmt->get_result();
$students = [];
while ($row = $result->fetch_assoc()) $students[] = $row;
$stmt->close();
ob_end_clean();
respond(['success'=>true,'students'=>$students,'section'=>$section,'date'=>$date,'count'=>count($students)]);
