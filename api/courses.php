<?php
ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';

$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

// Ensure the course_materials table exists
$db->query("CREATE TABLE IF NOT EXISTS course_materials (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    subject     VARCHAR(100) DEFAULT '',
    description TEXT,
    section     VARCHAR(100) DEFAULT '',
    file_url    TEXT,
    file_type   VARCHAR(20) DEFAULT 'other',
    uploader    VARCHAR(150) DEFAULT '',
    user_id     INT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// ─────────────────────────────────────────────────────────────────────────────
// GET — list materials (filtered by section for students/teachers)
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $subject = $_GET['subject'] ?? '';
    $search  = $_GET['search']  ?? '';

    $conditions = [];
    $params     = [];
    $types      = '';

    // Students only see materials for their section (or all-section ones)
    if ($user['role'] === 'student' && !empty($user['section'])) {
        $conditions[] = "(section = '' OR section = ?)";
        $params[]     = $user['section'];
        $types       .= 's';
    }

    // Teachers see materials for their section and all-section ones
    if ($user['role'] === 'teacher' && !empty($user['section'])) {
        $conditions[] = "(section = '' OR section = ?)";
        $params[]     = $user['section'];
        $types       .= 's';
    }

    if (!empty($subject)) {
        $conditions[] = "subject = ?";
        $params[]     = $subject;
        $types       .= 's';
    }

    if (!empty($search)) {
        $conditions[] = "(title LIKE ? OR description LIKE ?)";
        $like         = '%' . $search . '%';
        $params[]     = $like;
        $params[]     = $like;
        $types       .= 'ss';
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $sql   = "SELECT * FROM course_materials $where ORDER BY created_at DESC";

    if ($params) {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $result = $db->query($sql);
    }

    $materials = [];
    while ($row = $result->fetch_assoc()) {
        $materials[] = $row;
    }

    ob_end_clean();
    respond(['success' => true, 'materials' => $materials]);
}

// ─────────────────────────────────────────────────────────────────────────────
// POST — upload / create a material (admin and teacher only)
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    if (!in_array($user['role'], ['admin', 'teacher'])) {
        ob_end_clean();
        respondError('Forbidden', 403);
    }

    $body = getBody();

    $title       = trim($body['title']       ?? '');
    $subject     = trim($body['subject']     ?? '');
    $description = trim($body['description'] ?? '');
    $section     = trim($body['section']     ?? '');
    $file_url    = trim($body['file_url']    ?? '');
    $file_type   = trim($body['file_type']   ?? 'other');

    if (!$title) {
        ob_end_clean();
        respondError('Title is required.');
    }

    // Teachers can only post to their own section (or all sections if admin)
    if ($user['role'] === 'teacher' && !empty($section) && $section !== ($user['section'] ?? '')) {
        ob_end_clean();
        respondError('You can only upload materials for your own section.');
    }

    $stmt = $db->prepare(
        "INSERT INTO course_materials (title, subject, description, section, file_url, file_type, uploader, user_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'sssssssi',
        $title, $subject, $description, $section, $file_url, $file_type,
        $user['name'], $user['id']
    );
    $stmt->execute();
    $insertId = $db->insert_id;
    $stmt->close();

    ob_end_clean();
    respond(['success' => true, 'id' => $insertId, 'message' => 'Material uploaded successfully.']);
}

// ─────────────────────────────────────────────────────────────────────────────
// DELETE — remove a material (admin or the teacher who uploaded it)
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!in_array($user['role'], ['admin', 'teacher'])) {
        ob_end_clean();
        respondError('Forbidden', 403);
    }

    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        ob_end_clean();
        respondError('Material ID is required.');
    }

    // Teachers may only delete their own materials
    if ($user['role'] === 'teacher') {
        $chk = $db->prepare("SELECT user_id FROM course_materials WHERE id = ? LIMIT 1");
        $chk->bind_param('i', $id);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        $chk->close();
        if (!$row || (int)$row['user_id'] !== (int)$user['id']) {
            ob_end_clean();
            respondError('You can only delete your own materials.', 403);
        }
    }

    $stmt = $db->prepare("DELETE FROM course_materials WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    ob_end_clean();
    respond(['success' => true, 'message' => 'Material deleted.']);
}

ob_end_clean();
respondError('Method not allowed.', 405);
