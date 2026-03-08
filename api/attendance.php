<?php
/**
 * api/attendance.php
 *
 * POST /api/attendance.php
 *   → Save/update attendance records for a batch of students.
 *     Body: { date: "2026-03-05", records: [ { usn, status, time_in, remarks }, ... ] }
 *
 * GET /api/attendance.php?usn=2024-00142&days=30
 *   → Get attendance history for a specific student (used by student self-view).
 *
 * GET /api/attendance.php?section_id=1&date=2026-03-05
 *   → Get summary stats for a section on a date (used by dashboard).
 */

require_once '../config/cors.php';
require_once '../config/db.php';

$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

// ── POST: Save attendance ─────────────────────────────────────────────
if ($method === 'POST') {
    requireRole('admin', 'teacher');

    $body    = getBody();
    $records = $body['records'] ?? [];
    $date    = $body['date']    ?? date('Y-m-d');

    if (empty($records)) {
        respondError('No records provided.');
    }

    $saved = 0;
    $stmt  = $db->prepare("
        INSERT INTO attendance (usn, date, status, time_in, remarks, marked_by)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            status     = VALUES(status),
            time_in    = VALUES(time_in),
            remarks    = VALUES(remarks),
            marked_by  = VALUES(marked_by),
            updated_at = CURRENT_TIMESTAMP
    ");

    foreach ($records as $rec) {
        $usn     = $rec['usn']     ?? '';
        $status  = in_array($rec['status'], ['present','absent','late']) ? $rec['status'] : 'absent';
        $time_in = ($rec['time_in'] && $rec['time_in'] !== '—') ? $rec['time_in'] : null;
        $remarks = $rec['remarks'] ?? null;
        $uid     = $user['id'];   // null for students (but students can't POST)

        if (!$usn) continue;
        $stmt->bind_param('sssssi', $usn, $date, $status, $time_in, $remarks, $uid);
        if ($stmt->execute()) $saved++;
    }
    $stmt->close();

    respond(['success' => true, 'saved' => $saved, 'date' => $date]);
}

// ── GET: Student's own attendance history ─────────────────────────────
if ($method === 'GET' && isset($_GET['usn'])) {
    $usn  = $_GET['usn'];
    $days = min(intval($_GET['days'] ?? 30), 365);

    // Students can only view their own record
    if ($user['role'] === 'student' && $user['usn'] !== $usn) {
        respondError('You can only view your own attendance.', 403);
    }

    $stmt = $db->prepare("
        SELECT date, status, time_in, remarks
        FROM attendance
        WHERE usn = ?
        ORDER BY date DESC
        LIMIT ?
    ");
    $stmt->bind_param('si', $usn, $days);
    $stmt->execute();
    $result  = $stmt->get_result();
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();

    // Summary
    $present = count(array_filter($history, fn($r) => $r['status'] === 'present'));
    $absent  = count(array_filter($history, fn($r) => $r['status'] === 'absent'));
    $late    = count(array_filter($history, fn($r) => $r['status'] === 'late'));
    $total   = count($history);
    $rate    = $total > 0 ? round(($present / $total) * 100) : 0;

    respond([
        'success' => true,
        'history' => $history,
        'summary' => compact('present', 'absent', 'late', 'total', 'rate')
    ]);
}

// ── GET: Section stats for a date ─────────────────────────────────────
if ($method === 'GET' && isset($_GET['section_id'])) {
    requireRole('admin', 'teacher');

    $section_id = intval($_GET['section_id']);
    $date       = $_GET['date'] ?? date('Y-m-d');

    // Resolve section name
    $sStmt = $db->prepare("SELECT name FROM sections WHERE id = ? LIMIT 1");
    $sStmt->bind_param('i', $section_id);
    $sStmt->execute();
    $sRow = $sStmt->get_result()->fetch_assoc();
    $sStmt->close();
    if (!$sRow) respondError('Section not found.', 404);

    $sectionName = $sRow['name'];

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
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    respond(['success' => true, 'stats' => $stats, 'date' => $date]);
}

respondError('Invalid request.');
