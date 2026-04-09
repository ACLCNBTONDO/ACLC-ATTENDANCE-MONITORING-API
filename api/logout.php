<?php
ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';
$token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
if ($token) {
    $db   = getDB();
    $stmt = $db->prepare("UPDATE users SET auth_token = NULL WHERE auth_token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $stmt->close();
    $db->close();
}
ob_end_clean();
respond(['success' => true]);
