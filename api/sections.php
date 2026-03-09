<?php
ini_set('display_errors', 0);
error_reporting(0);
require_once __DIR__ . '/../config/db.php';

requireAuth();
$db     = getDB();
$result = $db->query("SELECT section AS name, COUNT(*) AS student_count FROM students WHERE section IS NOT NULL AND section != '' GROUP BY section ORDER BY section ASC");
$sections = [];
while ($row = $result->fetch_assoc()) {
    preg_match('/^(\w+)\s*(\d*)/', $row['name'], $m);
    $row['strand']     = $m[1] ?? 'Other';
    $row['year_level'] = $m[2] ?? '';
    $sections[] = $row;
}
$db->close();
respond(['success' => true, 'sections' => $sections]);
