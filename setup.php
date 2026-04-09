<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

echo "<h2 style='font-family:Arial;color:#003087'>AttendEase Setup</h2>";

$db = getDB();
echo "<p style='color:green'>✅ Connected to database!</p>";

// Add auth_token column if not exists
$result = $db->query("DESCRIBE users");
$cols = [];
while ($row = $result->fetch_assoc()) $cols[] = $row['Field'];

if (!in_array('auth_token', $cols)) {
    $db->query("ALTER TABLE users ADD COLUMN auth_token VARCHAR(64) DEFAULT NULL");
    echo "<p style='color:green'>✅ Added auth_token column</p>";
} else {
    echo "<p style='color:green'>✅ auth_token column already exists</p>";
}

// Check tables exist
foreach (['students', 'users', 'attendance'] as $table) {
    $r = $db->query("SHOW TABLES LIKE '$table'");
    if ($r->num_rows > 0) {
        echo "<p style='color:green'>✅ Table '$table' exists</p>";
    } else {
        echo "<p style='color:red'>❌ Table '$table' MISSING — please import database.sql</p>";
    }
}

$db->close();
echo "<h3 style='color:green'>Done! Delete setup.php after running.</h3>";
