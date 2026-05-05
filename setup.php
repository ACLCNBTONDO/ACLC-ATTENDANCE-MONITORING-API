


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

// Force collation fix on existing tables first
run($db, 'Fix collation: students.usn',    "ALTER TABLE students  MODIFY usn VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL");
run($db, 'Fix collation: users.usn',       "ALTER TABLE users     MODIFY usn VARCHAR(50)  COLLATE utf8mb4_unicode_ci DEFAULT NULL");
run($db, 'Fix collation: users.username',  "ALTER TABLE users     MODIFY username VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL");
run($db, 'Fix collation: users.section',   "ALTER TABLE users     MODIFY section VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL");
run($db, 'Fix collation: students table',  "ALTER TABLE students  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
run($db, 'Fix collation: users table',     "ALTER TABLE users     CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
run($db, 'Fix collation: attendance table',"ALTER TABLE attendance CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");


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
    usn            VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
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
    time_out        TIMESTAMP    NULL,
    remarks         ENUM('PRESENT','LATE','TARDY','MISSING') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PRESENT',
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

// ── Add MISSING status to attendance.remarks ─────────────────────────────────
$col = $db->query("SHOW COLUMNS FROM attendance LIKE 'remarks'");
if ($col) {
    $colData = $col->fetch_assoc();
    run($db, 'Ensure MISSING in attendance.remarks column',
        "ALTER TABLE attendance MODIFY remarks ENUM('PRESENT','LATE','TARDY','MISSING')
         COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PRESENT'"
    );
}

echo "<h3 style='font-family:Arial;color:#003087;margin-top:24px;'>📧 Email Notification Variables</h3>";
echo "<table style='font-family:monospace;border-collapse:collapse;font-size:13px;border:1px solid #ddd;'>
  <tr style='background:#f3f4f6;'><th style='padding:6px 14px;text-align:left;'>Variable</th><th style='padding:6px 14px;text-align:left;'>Example</th><th style='padding:6px 14px;text-align:left;'>Required</th></tr>
  <tr><td style='padding:6px 14px;'>MAIL_HOST</td><td style='padding:6px 14px;'>smtp.gmail.com</td><td style='padding:6px 14px;'>Yes</td></tr>
  <tr><td style='padding:6px 14px;'>MAIL_PORT</td><td style='padding:6px 14px;'>587</td><td style='padding:6px 14px;'>Yes</td></tr>
  <tr><td style='padding:6px 14px;'>MAIL_USERNAME</td><td style='padding:6px 14px;'>school@gmail.com</td><td style='padding:6px 14px;'>Yes</td></tr>
  <tr><td style='padding:6px 14px;'>MAIL_PASSWORD</td><td style='padding:6px 14px;'>your-app-password</td><td style='padding:6px 14px;'>Yes</td></tr>
  <tr><td style='padding:6px 14px;'>MAIL_FROM_NAME</td><td style='padding:6px 14px;'>ACLC Attendance System</td><td style='padding:6px 14px;'>No</td></tr>
  <tr><td style='padding:6px 14px;'>MAIL_ENCRYPTION</td><td style='padding:6px 14px;'>tls</td><td style='padding:6px 14px;'>No</td></tr>
</table>
<p style='font-family:Arial;font-size:13px;color:#6b7280;margin-top:8px;'>
  For Gmail: Google Account → Security → 2-Step Verification → App passwords.
</p>";
