<?php
ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/mailer.php';

$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Normalise a remarks string into one of: present | late | missing | absent
 */
function resolveStatus(string $remarks): string {
    $r = strtolower(trim($remarks));
    if ($r === 'missing')                                                    return 'missing';
    if (strpos($r, 'tardy') !== false || strpos($r, 'late') !== false)      return 'late';
    if ($r !== '')                                                           return 'present';
    return 'absent';
}

/**
 * Lookup student name + guardian email for a given USN.
 */
function getStudentContact(mysqli $db, string $usn): ?array {
    $stmt = $db->prepare(
        "SELECT CONCAT(first_name,' ',last_name) AS name, guardian_email
         FROM students WHERE usn = ? LIMIT 1"
    );
    $stmt->bind_param('s', $usn);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || empty($row['guardian_email'])) return null;
    return ['name' => $row['name'], 'email' => $row['guardian_email']];
}

// ─────────────────────────────────────────────────────────────────────────────
// POST — bulk save attendance (batch from teacher's attendance sheet)
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    if (!in_array($user['role'], ['admin', 'teacher'])) {
        ob_end_clean(); respondError('Forbidden', 403);
    }

    $body    = getBody();
    $records = $body['records'] ?? [];
    $date    = $body['date']    ?? date('Y-m-d');

    if (empty($records)) { ob_end_clean(); respondError('No records provided.'); }

    $stmtUpsert = $db->prepare(
        "INSERT INTO attendance (usn, attendance_date, time_in, remarks)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE time_in = VALUES(time_in), remarks = VALUES(remarks)"
    );

    // Fetch previous remarks for all USNs in one query so we can detect changes
    $usns = array_values(array_filter(array_column($records, 'usn')));
    $prevMap = [];
    if ($usns) {
        $placeholders = implode(',', array_fill(0, count($usns), '?'));
        $types        = 's' . str_repeat('s', count($usns));
        $stmtPrev     = $db->prepare(
            "SELECT usn, remarks FROM attendance
             WHERE attendance_date = ? AND usn IN ($placeholders)"
        );
        $params = array_merge([$date], $usns);
        $stmtPrev->bind_param($types, ...$params);
        $stmtPrev->execute();
        $res = $stmtPrev->get_result();
        while ($row = $res->fetch_assoc()) {
            $prevMap[$row['usn']] = $row['remarks'];
        }
        $stmtPrev->close();
    }

    $saved        = 0;
    $notified     = 0;
    $notifyErrors = [];

    foreach ($records as $rec) {
        $usn = $rec['usn'] ?? '';
        if (!$usn) continue;
        $rem = $rec['remarks'] ?? null;
        if (!$rem) continue;

        $tin = (!empty($rec['time_in']) && $rec['time_in'] !== '—')
             ? $rec['time_in']
             : date('H:i');

        $stmtUpsert->bind_param('ssss', $usn, $date, $tin, $rem);
        if (!$stmtUpsert->execute()) continue;
        $saved++;

        // Detect status change & notify guardian
        $prevStatus = resolveStatus($prevMap[$usn] ?? '');
        $newStatus  = resolveStatus($rem);

        if ($newStatus !== $prevStatus) {
            $contact = getStudentContact($db, $usn);
            if ($contact) {
                $sent = sendAttendanceNotification(
                    $contact['email'], $contact['name'],
                    $newStatus, $date, $tin, $rem
                );
                $sent ? $notified++ : ($notifyErrors[] = $usn);
            }
        }
    }

    $stmtUpsert->close();
    ob_end_clean();
    respond([
        'success'       => true,
        'saved'         => $saved,
        'notified'      => $notified,
        'notify_errors' => $notifyErrors,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// PUT — update a single student's status (triggers notification on change)
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    if (!in_array($user['role'], ['admin', 'teacher'])) {
        ob_end_clean(); respondError('Forbidden', 403);
    }

    $body    = getBody();
    $usn     = trim($body['usn']     ?? '');
    $date    = trim($body['date']    ?? date('Y-m-d'));
    $remarks = trim($body['remarks'] ?? '');
    $timeIn  = trim($body['time_in'] ?? date('H:i'));

    if (!$usn || !$remarks) {
        ob_end_clean(); respondError('usn and remarks are required.');
    }

    // Fetch previous status
    $stmtGet = $db->prepare(
        "SELECT remarks FROM attendance WHERE usn = ? AND attendance_date = ? LIMIT 1"
    );
    $stmtGet->bind_param('ss', $usn, $date);
    $stmtGet->execute();
    $prev        = $stmtGet->get_result()->fetch_assoc();
    $stmtGet->close();

    $prevStatus = resolveStatus($prev['remarks'] ?? '');
    $newStatus  = resolveStatus($remarks);

    // Upsert
    $stmtUpsert = $db->prepare(
        "INSERT INTO attendance (usn, attendance_date, time_in, remarks)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE time_in = VALUES(time_in), remarks = VALUES(remarks)"
    );
    $stmtUpsert->bind_param('ssss', $usn, $date, $timeIn, $remarks);
    if (!$stmtUpsert->execute()) {
        ob_end_clean(); respondError('Failed to update attendance.');
    }
    $stmtUpsert->close();

    $notified      = false;
    $statusChanged = ($newStatus !== $prevStatus);

    if ($statusChanged) {
        $contact = getStudentContact($db, $usn);
        if ($contact) {
            $notified = sendAttendanceNotification(
                $contact['email'], $contact['name'],
                $newStatus, $date, $timeIn, $remarks
            );
        }
    }

    ob_end_clean();
    respond([
        'success'        => true,
        'status_changed' => $statusChanged,
        'prev_status'    => $prevStatus,
        'new_status'     => $newStatus,
        'notified'       => $notified,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// GET — attendance history for a student
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET' && isset($_GET['usn'])) {
    $usn  = $_GET['usn'];
    $days = min(intval($_GET['days'] ?? 30), 365);

    if ($user['role'] === 'student' && $user['usn'] !== $usn) {
        ob_end_clean(); respondError('Access denied.', 403);
    }

    $stmt = $db->prepare(
        "SELECT attendance_date AS date, time_in, time_out AS scanned_at, remarks
         FROM attendance WHERE usn = ? ORDER BY attendance_date DESC LIMIT ?"
    );
    $stmt->bind_param('si', $usn, $days);
    $stmt->execute();
    $result  = $stmt->get_result();
    $history = [];
    $present = $late = $absent = $missing = 0;

    while ($row = $result->fetch_assoc()) {
        $status        = resolveStatus($row['remarks'] ?? '');
        $row['status'] = $status;
        switch ($status) {
            case 'present': $present++; break;
            case 'late':    $late++;    break;
            case 'missing': $missing++; break;
            default:        $absent++;
        }
        $history[] = $row;
    }
    $stmt->close();

    $total = count($history);
    $rate  = $total > 0 ? round(($present / $total) * 100) : 0;

    ob_end_clean();
    respond([
        'success' => true,
        'history' => $history,
        'summary' => [
            'present' => $present,
            'absent'  => $absent,
            'late'    => $late,
            'missing' => $missing,
            'total'   => $total,
            'rate'    => $rate,
        ],
    ]);
}

ob_end_clean();
respondError('Invalid request.');
