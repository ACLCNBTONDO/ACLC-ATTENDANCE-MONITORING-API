<?php
ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';
$user = requireAuth();
$db   = getDB();
if ($user['role'] === 'teacher') {
    if (empty($user['section'])) { ob_end_clean(); respond(['success' => true, 'sections' => []]); }
    $stmt = $db->prepare("SELECT section AS name, COUNT(*) AS student_count FROM students WHERE section = ? GROUP BY section");
    $stmt->bind_param('s', $user['section']);
} else {
    $stmt = $db->prepare("SELECT section AS name, COUNT(*) AS student_count FROM students WHERE section IS NOT NULL AND section != '' GROUP BY section ORDER BY section ASC");
}
$stmt->execute();
$result = $stmt->get_result();
$sections = [];
while ($row = $result->fetch_assoc()) {
    if (empty($row['name'])) continue; // skip any null/empty section rows
    // Parse "Grade 11 - STEM" → strand="STEM", year_level="Gr. 11"
    // Pattern: optional "Grade N -" prefix then strand name
    $name = $row['name'];
    if (preg_match('/Grade\s+(\d+)\s*-\s*(.+)/i', $name, $m)) {
        $row['year_level'] = 'Gr.'  . trim($m[1]);
        $row['strand']     = trim($m[2]);
    } else {
        $parts = explode(' ', $name);
        $row['strand']     = $parts[0] ?? $name;
        $row['year_level'] = isset($parts[1]) ? substr($parts[1], 0, 2) : '';
    }
    $sections[] = $row;
}
$stmt->close();
ob_end_clean();
respond(['success' => true, 'sections' => $sections]);
