<?php
/**
 * api/students.php
 *
 * GET /api/students.php?section_id=1&date=2026-03-05
 *   → Returns student list for a section with their attendance for that date.
 *     Used by the Mark Attendance table.
 *
 * GET /api/students.php?usn=2024-00142
 *   → Returns a single student's full profile.
 *     Used by the student's own profile page.
 */

require_once '../config/cors.php';
require_once '../config/db.php';

$user = requireAuth();
$db   = getDB();

// ── Single student profile (for student self-view) ────────────────────
if (isset($_GET['usn'])) {
    $usn = $_GET['usn'];

    // Students can only view their own profile
    if ($user['role'] === 'student' && $user['usn'] !== $usn) {
        respondError('Access denied.', 403);
    }

    $stmt = $db->prepare("
        SELECT
            usn, first_name, last_name, middle_name,
            age, sex, lrn, section, guardian_email
        FROM students
        WHERE usn = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $usn);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$student) respondError('Student not found.', 404);
    respond(['success' => true, 'student' => $student]);
}

// ── Student list for a section (for attendance table) ─────────────────
$section_id = intval($_GET['section_id'] ?? 0);
$date       = $_GET['date'] ?? date('Y-m-d');

if (!$section_id) {
    respondError('section_id is required.');
}

// Teachers can only access their own section
if ($user['role'] === 'teacher' && $user['section_id'] != $section_id) {
    respondError('You can only view your assigned section.', 403);
}

// Get section name from section ID
$sStmt = $db->prepare("SELECT name FROM sections WHERE id = ? LIMIT 1");
$sStmt->bind_param('i', $section_id);
$sStmt->execute();
$sRow = $sStmt->get_result()->fetch_assoc();
$sStmt->close();

if (!$sRow) respondError('Section not found.', 404);
$sectionName = $sRow['name'];

// Fetch students in section + their attendance for the given date
$stmt = $db->prepare("
    SELECT
        st.usn,
        st.last_name,
        st.first_name,
        st.middle_name,
        st.sex,
        st.lrn,
        CONCAT(st.last_name, ', ', st.first_name,
               IF(st.middle_name IS NOT NULL AND st.middle_name != '',
                  CONCAT(' ', LEFT(st.middle_name, 1), '.'), ''))  AS full_name,
        COALESCE(a.status,  'absent')  AS status,
        COALESCE(a.time_in, '—')       AS time_in,
        COALESCE(a.remarks, '')        AS remarks
    FROM students st
    LEFT JOIN attendance a
        ON a.usn = st.usn AND a.date = ?
    WHERE st.section = ?
    ORDER BY st.last_name ASC, st.first_name ASC
");
$stmt->bind_param('ss', $date, $sectionName);
$stmt->execute();
$result   = $stmt->get_result();
$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();

respond([
    'success'      => true,
    'students'     => $students,
    'section_name' => $sectionName,
    'date'         => $date,
    'count'        => count($students)
]);
