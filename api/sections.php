<?php
ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';
$user = requireAuth();
$db   = getDB();
if ($user['role'] === 'teacher') {
    $stmt = $db->prepare("SELECT section AS name, COUNT(*) AS student_count FROM students WHERE section = ? GROUP BY section");
    $stmt->bind_param('s', $user['section']);
} else {
    $stmt = $db->prepare("SELECT section AS name, COUNT(*) AS student_count FROM students WHERE section IS NOT NULL AND section != '' GROUP BY section ORDER BY section ASC");
}
$stmt->execute();
$result = $stmt->get_result();
$sections = [];
while ($row = $result->fetch_assoc()) {
    $parts = explode(' ', $row['name']);
    $row['strand']     = $parts[0] ?? '';
    $row['year_level'] = isset($parts[1]) ? substr($parts[1], 0, 2) : '';
    $sections[] = $row;
}
$stmt->close();
ob_end_clean();
respond(['success' => true, 'sections' => $sections]);
