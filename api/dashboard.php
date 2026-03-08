<?php
/**
 * api/dashboard.php
 * GET /api/dashboard.php?date=2026-03-05
 *
 * Returns:
 *   - stats: total, present, absent, late for today
 *   - weekly: array of { date, day, present } for last 7 days
 *
 * Admin → all sections combined
 * Teacher → their section only (matched via students.section = section name)
 */

require_once '../config/cors.php';
require_once '../config/db.php';

$user = requireRole('admin', 'teacher');
$db   = getDB();
$date = $_GET['date'] ?? date('Y-m-d');

// Resolve section name for teachers
$sectionName = null;
if ($user['role'] === 'teacher' && $user['section_id']) {
    $sStmt = $db->prepare("SELECT name FROM sections WHERE id = ? LIMIT 1");
    $sStmt->bind_param('i', $user['section_id']);
    $sStmt->execute();
    $sRow = $sStmt->get_result()->fetch_assoc();
    $sStmt->close();
    $sectionName = $sRow['name'] ?? null;
}

// ── Today's stats ─────────────────────────────────────────────────────
if ($user['role'] === 'teacher' && $sectionName) {
    $stmt = $db->prepare("
        SELECT
            COUNT(st.usn)                                            AS total,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END)   AS present,
            SUM(CASE WHEN a.status = 'absent'  THEN 1 ELSE 0 END)   AS absent,
            SUM(CASE WHEN a.status = 'late'    THEN 1 ELSE 0 END)   AS late
        FROM students st
        LEFT JOIN attendance a ON a.usn = st.usn AND a.date = ?
        WHERE st.section = ?
    ");
    $stmt->bind_param('ss', $date, $sectionName);
} else {
    // Admin: all students
    $stmt = $db->prepare("
        SELECT
            COUNT(st.usn)                                            AS total,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END)   AS present,
            SUM(CASE WHEN a.status = 'absent'  THEN 1 ELSE 0 END)   AS absent,
            SUM(CASE WHEN a.status = 'late'    THEN 1 ELSE 0 END)   AS late
        FROM students st
        LEFT JOIN attendance a ON a.usn = st.usn AND a.date = ?
    ");
    $stmt->bind_param('s', $date);
}
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Weekly data (last 7 days) ──────────────────────────────────────────
$weekly = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days", strtotime($date)));

    if ($user['role'] === 'teacher' && $sectionName) {
        $wStmt = $db->prepare("
            SELECT COUNT(a.usn) AS present
            FROM attendance a
            JOIN students st ON st.usn = a.usn
            WHERE a.date = ? AND a.status = 'present' AND st.section = ?
        ");
        $wStmt->bind_param('ss', $d, $sectionName);
    } else {
        $wStmt = $db->prepare("
            SELECT COUNT(usn) AS present
            FROM attendance
            WHERE date = ? AND status = 'present'
        ");
        $wStmt->bind_param('s', $d);
    }

    $wStmt->execute();
    $wRow = $wStmt->get_result()->fetch_assoc();
    $wStmt->close();

    $weekly[] = [
        'date'    => $d,
        'day'     => date('D', strtotime($d)),
        'present' => intval($wRow['present'])
    ];
}

respond([
    'success' => true,
    'stats'   => [
        'total'   => intval($stats['total']),
        'present' => intval($stats['present']),
        'absent'  => intval($stats['absent']),
        'late'    => intval($stats['late']),
    ],
    'weekly' => $weekly
]);
