<?php
/**
 * api/sections.php
 * GET /api/sections.php
 *
 * Reads distinct sections from students.section column.
 * Groups them by strand (ICT / GAS) and year level (11 / 12).
 *
 * Admins see all sections.
 * Teachers see only their own assigned section.
 *
 * Response: { success, sections: [ { name, strand, year_level, student_count } ] }
 */

require_once '../config/cors.php';
require_once '../config/db.php';

$user = requireAuth();
$db   = getDB();

if ($user['role'] === 'teacher') {
    $stmt = $db->prepare("
        SELECT
            section                              AS name,
            COUNT(*)                             AS student_count
        FROM students
        WHERE section = ?
        GROUP BY section
    ");
    $stmt->bind_param('s', $user['section']);
} else {
    // Admin: all sections ordered by strand then year level then section letter
    $stmt = $db->prepare("
        SELECT
            section                              AS name,
            COUNT(*)                             AS student_count
        FROM students
        WHERE section IS NOT NULL AND section != ''
        GROUP BY section
        ORDER BY
            CASE WHEN section LIKE 'GAS%' THEN 1 ELSE 0 END ASC,
            section ASC
    ");
}

$stmt->execute();
$result   = $stmt->get_result();
$sections = [];

while ($row = $result->fetch_assoc()) {
    // Parse strand and year level from section name (e.g. 'ICT 11-A')
    $parts = explode(' ', $row['name']);
    $row['strand']     = $parts[0] ?? '';      // ICT or GAS
    $row['year_level'] = isset($parts[1]) ? substr($parts[1], 0, 2) : '';  // 11 or 12
    $sections[] = $row;
}
$stmt->close();

respond(['success' => true, 'sections' => $sections]);
