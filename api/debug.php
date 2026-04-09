<?php
ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';

$db     = getDB();
$date   = date('Y-m-d');
$errors = [];

try {
    $stmt = $db->prepare("SELECT COUNT(st.usn) AS total, SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) AS present FROM students st LEFT JOIN attendance a ON a.usn=st.usn AND a.date=?");
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $errors[] = "✅ Stats query OK: total=" . $stats['total'];
} catch (Exception $e) {
    $errors[] = "❌ Stats failed: " . $e->getMessage();
}

try {
    $result = $db->query("DESCRIBE attendance");
    $cols = [];
    while ($row = $result->fetch_assoc()) $cols[] = $row['Field'];
    $errors[] = "Attendance columns: " . implode(', ', $cols);
} catch (Exception $e) {
    $errors[] = "❌ DESCRIBE failed: " . $e->getMessage();
}

try {
    $wStmt = $db->prepare("SELECT COUNT(usn) AS present FROM attendance WHERE date=? AND status='present'");
    $wStmt->bind_param('s', $date);
    $wStmt->execute();
    $wRow = $wStmt->get_result()->fetch_assoc();
    $wStmt->close();
    $errors[] = "✅ Weekly query OK";
} catch (Exception $e) {
    $errors[] = "❌ Weekly failed: " . $e->getMessage();
}

ob_end_clean();
respond(['debug' => $errors]);
