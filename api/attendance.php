<?php
ini_set('display_errors', 0);
error_reporting(0);
if (!defined('ROOT')) define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';

$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

// ── POST: Save attendance records ─────────────────────────────────────
if ($method === 'POST') {
    if (!in_array($user['role'], ['admin','teacher'])) respondError('Forbidden', 403);
    $b       = getBody();
    $records = $b['records'] ?? [];
    $date    = $b['date']    ?? date('Y-m-d');
    if (!$records) respondError('No records provided.');
    $stmt = $db->prepare("INSERT INTO attendance (usn, scanned_at, attendance_date, remarks) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE scanned_at=VALUES(scanned_at), remarks=VALUES(remarks)");
    $saved = 0;
    foreach ($records as $r) {
        $usn  = $r['usn']             ?? '';
        $scan = $r['scanned_at']      ?? null;
        $dt   = $r['attendance_date'] ?? $date;
        $rem  = $r['remarks']         ?? null;
        if (!$usn) continue;
        $stmt->bind_param('ssss', $usn, $scan, $dt, $rem);
        if ($stmt->execute()) $saved++;
    }
    $stmt->close(); $db->close();
    respond(['success' => true, 'saved' => $saved]);
}

// ── GET: Student attendance history ──────────────────────────────────
if (isset($_GET['usn'])) {
    $usn  = $_GET['usn'];
    $days = min((int)($_GET['days'] ?? 30), 365);
    // Students can only view their own history
    if ($user['role'] === 'student' && $user['usn'] !== $usn) respondError('Access denied.', 403);
    $stmt = $db->prepare("SELECT attendance_date AS date, scanned_at, remarks FROM attendance WHERE usn=? ORDER BY attendance_date DESC LIMIT ?");
    $stmt->bind_param('si', $usn, $days);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close(); $db->close();
    // Derive status from remarks
    foreach ($rows as &$row) {
        $r = strtolower($row['remarks'] ?? '');
        if (!$row['remarks'] && !$row['scanned_at'])                       $row['status'] = 'absent';
        elseif (str_contains($r, 'tardy') || str_contains($r, 'late'))    $row['status'] = 'late';
        else                                                                $row['status'] = 'present';
    }
    $present = count(array_filter($rows, fn($r) => $r['status'] === 'present'));
    $absent  = count(array_filter($rows, fn($r) => $r['status'] === 'absent'));
    $late    = count(array_filter($rows, fn($r) => $r['status'] === 'late'));
    $total   = count($rows);
    $rate    = $total > 0 ? round(($present / $total) * 100) : 0;
    respond(['success' => true, 'history' => $rows, 'summary' => compact('present','absent','late','total','rate')]);
}

respondError('Invalid request.');
