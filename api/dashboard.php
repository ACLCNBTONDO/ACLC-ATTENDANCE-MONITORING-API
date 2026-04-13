<?php
ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';
$user    = requireRole('admin', 'teacher');
$db      = getDB();
$date    = $_GET['date'] ?? date('Y-m-d');
$section = $user['role'] === 'teacher' ? ($user['section'] ?? null) : null;

// Stats
if ($section) {
    $stmt = $db->prepare("
        SELECT COUNT(st.usn) AS total,
               SUM(CASE WHEN a.id IS NOT NULL AND (a.remarks NOT LIKE '%TARDY%' AND a.remarks NOT LIKE '%LATE%') THEN 1 ELSE 0 END) AS present,
               SUM(CASE WHEN a.id IS NULL THEN 1 ELSE 0 END) AS absent,
               SUM(CASE WHEN a.id IS NOT NULL AND (a.remarks LIKE '%TARDY%' OR a.remarks LIKE '%LATE%') THEN 1 ELSE 0 END) AS late
        FROM students st
        LEFT JOIN attendance a ON a.usn = st.usn AND a.attendance_date = ?
        WHERE st.section = ?
    ");
    $stmt->bind_param('ss', $date, $section);
} else {
    $stmt = $db->prepare("
        SELECT COUNT(st.usn) AS total,
               SUM(CASE WHEN a.id IS NOT NULL AND (a.remarks NOT LIKE '%TARDY%' AND a.remarks NOT LIKE '%LATE%') THEN 1 ELSE 0 END) AS present,
               SUM(CASE WHEN a.id IS NULL THEN 1 ELSE 0 END) AS absent,
               SUM(CASE WHEN a.id IS NOT NULL AND (a.remarks LIKE '%TARDY%' OR a.remarks LIKE '%LATE%') THEN 1 ELSE 0 END) AS late
        FROM students st
        LEFT JOIN attendance a ON a.usn = st.usn AND a.attendance_date = ?
    ");
    $stmt->bind_param('s', $date);
}
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Section count
$scStmt = $db->prepare("SELECT COUNT(DISTINCT section) AS cnt FROM students WHERE section IS NOT NULL AND section != ''");
$scStmt->execute();
$scRow = $scStmt->get_result()->fetch_assoc();
$scStmt->close();

// Weekly chart
$weekly = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days", strtotime($date)));
    if ($section) {
        $wStmt = $db->prepare("SELECT COUNT(a.id) AS present FROM attendance a JOIN students st ON st.usn = a.usn WHERE a.attendance_date = ? AND st.section = ?");
        $wStmt->bind_param('ss', $d, $section);
    } else {
        $wStmt = $db->prepare("SELECT COUNT(id) AS present FROM attendance WHERE attendance_date = ?");
        $wStmt->bind_param('s', $d);
    }
    $wStmt->execute();
    $wRow = $wStmt->get_result()->fetch_assoc();
    $wStmt->close();
    $weekly[] = ['date' => $d, 'day' => date('D', strtotime($d)), 'present' => intval($wRow['present'])];
}

ob_end_clean();
respond([
    'success'       => true,
    'stats'         => [
        'total'   => intval($stats['total']),
        'present' => intval($stats['present']),
        'absent'  => intval($stats['absent']),
        'late'    => intval($stats['late']),
    ],
    'section_count' => intval($scRow['cnt']),
    'weekly'        => $weekly,
]);
