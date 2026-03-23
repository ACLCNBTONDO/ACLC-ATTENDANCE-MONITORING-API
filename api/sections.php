<?php
ini_set('display_errors', 0);
error_reporting(0);
if (!defined('ROOT')) define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';

$user = requireAuth();
$db   = getDB();

// Teacher only sees their own section
if ($user['role'] === 'teacher') {
    $stmt = $db->prepare("SELECT section AS name, COUNT(*) AS student_count FROM students WHERE section = ? GROUP BY section");
    $stmt->bind_param('s', $user['section']);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();
} else {
    $res = $db->query("SELECT section AS name, COUNT(*) AS student_count FROM students WHERE section IS NOT NULL AND section != '' GROUP BY section ORDER BY section ASC");
}

$sections = [];
while ($row = $res->fetch_assoc()) {
    preg_match('/^(\w+)\s*(\d*)/', $row['name'], $m);
    $row['strand']     = $m[1] ?? 'Other';
    $row['year_level'] = $m[2] ?? '';
    $sections[] = $row;
}
$db->close();
respond(['success' => true, 'sections' => $sections]);
