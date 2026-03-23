<?php
ini_set('display_errors', 0);
error_reporting(0);
if (!defined('ROOT')) define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';

$token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
if ($token) {
    $db   = getDB();
    $stmt = $db->prepare("UPDATE users SET auth_token = NULL WHERE auth_token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $stmt->close();
    $db->close();
}
respond(['success' => true]);
