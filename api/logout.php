<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(0);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';

// Clear the token from database so it can never be reused
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
