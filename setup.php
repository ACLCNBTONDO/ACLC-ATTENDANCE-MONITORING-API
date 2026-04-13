<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$url = getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: null;
if ($url) {
    $p    = parse_url($url);
    $host = $p['host'];
    $port = (int)($p['port'] ?? 3306);
    $user = $p['user'];
    $pass = urldecode($p['pass'] ?? '');
    $name = ltrim($p['path'], '/');
} else {
    $host = getenv('MYSQLHOST')     ?: 'localhost';
    $port = (int)(getenv('MYSQLPORT') ?: 3306);
    $user = getenv('MYSQLUSER')     ?: 'root';
    $pass = getenv('MYSQLPASSWORD') ?: '';
    $name = getenv('MYSQLDATABASE') ?: 'railway';
}

$db = new mysqli($host, $user, $pass, $name, $port);
if ($db->connect_error) {
    die("<p style='font-family:Arial;color:red'>❌ Connection failed: " . $db->connect_error . "</p>");
}
$db->set_charset('utf8mb4');

function run($db, $label, $sql) {
    if ($db->query($sql)) {
        echo "<p style='font-family:Arial;color:green'>✅ $label</p>";
    } else {
        echo "<p style='font-family:Arial;color:red'>❌ $label: " . htmlspecialchars($db->error) . "</p>";
    }
}

echo "<h2 style='font-family:Arial;color:#003087'>ACLC Setup</h2>";
echo "<p style='font-family:Arial;color:green'>✅ Connected to database!</p><br/>";

run($db, 'Create users table', "
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(100) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
    name       VARCHAR(200) NOT NULL,
    initials   VARCHAR(10)  DEFAULT NULL,
    section    VARCHAR(100) DEFAULT NULL,
    usn        VARCHAR(50)  DEFAULT NULL,
    auth_token VARCHAR(64)  DEFAULT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

run($db, 'Create students table', "
CREATE TABLE IF NOT EXISTS students (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    usn            VARCHAR(50)  NOT NULL UNIQUE,
    last_name      VARCHAR(100) NOT NULL,
    first_name     VARCHAR(100) NOT NULL,
    middle_name    VARCHAR(100) DEFAULT NULL,
    age            INT          DEFAULT NULL,
    sex            ENUM('Male','Female') DEFAULT NULL,
    lrn            VARCHAR(20)  DEFAULT NULL,
    section        VARCHAR(100) DEFAULT NULL,
    guardian_email VARCHAR(200) DEFAULT NULL,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

run($db, 'Create attendance table', "
CREATE TABLE IF NOT EXISTS attendance (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    usn             VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    attendance_date DATE         NOT NULL DEFAULT (CURRENT_DATE),
    time_in         TIMESTAMP    NULL,
    remarks         VARCHAR(20)  COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PRESENT',
    UNIQUE KEY unique_attendance (usn, attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Seed default users
$users = [
    ['admin',    password_hash('admin123',   PASSWORD_DEFAULT), 'admin',   'Administrator', 'AD', null,              null],
    ['teacher1', password_hash('teacher123', PASSWORD_DEFAULT), 'teacher', 'Teacher One',   'T1', 'Grade 11 - STEM', null],
    ['student1', password_hash('student123', PASSWORD_DEFAULT), 'student', 'Student One',   'S1', 'Grade 11 - STEM', '2024-00101'],
];

$stmt = $db->prepare("INSERT IGNORE INTO users (username,password,role,name,initials,section,usn) VALUES (?,?,?,?,?,?,?)");
foreach ($users as $u) {
    $stmt->bind_param('sssssss', $u[0],$u[1],$u[2],$u[3],$u[4],$u[5],$u[6]);
    $stmt->execute();
}
$stmt->close();
echo "<p style='font-family:Arial;color:green'>✅ Default users seeded (admin / teacher1 / student1)</p>";

// Seed sample student
$db->query("INSERT IGNORE INTO students (usn,last_name,first_name,section,sex,age) VALUES ('2024-00101','One','Student','Grade 11 - STEM','Male',17)");
echo "<p style='font-family:Arial;color:green'>✅ Sample student seeded</p>";

$db->close();
echo "<br/><h3 style='font-family:Arial;color:green'>✅ Setup complete! You can now log in.</h3>";
echo "<p style='font-family:Arial;color:red'><b>Delete or rename setup.php now for security.</b></p>";
