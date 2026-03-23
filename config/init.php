<?php
ini_set('display_errors', 0);
error_reporting(0);

// CORS
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
$isOk = preg_match('/^https:\/\/aclc-attendance-monitoring[a-z0-9\-]*\.vercel\.app$/', $origin)
     || in_array($origin, ['http://localhost','http://localhost:3000','http://127.0.0.1']);
header("Access-Control-Allow-Origin: " . ($isOk ? $origin : 'https://aclc-attendance-monitoring-bzbv751sz.vercel.app'));
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Auth-Token");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); echo '{}'; exit;
}

// DB
function getDB() {
    $url = getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: null;
    if ($url) {
        $p=$p=parse_url($url);
        $host=$p['host']; $port=(int)($p['port']??3306);
        $user=$p['user']; $pass=urldecode($p['pass']??'');
        $name=ltrim($p['path'],'/');
    } else {
        $host=getenv('MYSQLHOST')?:'localhost'; $port=(int)(getenv('MYSQLPORT')?:3306);
        $user=getenv('MYSQLUSER')?:'root';      $pass=getenv('MYSQLPASSWORD')?:'';
        $name=getenv('MYSQLDATABASE')?:'railway';
    }
    $db=@new mysqli($host,$user,$pass,$name,$port);
    if($db->connect_error){echo json_encode(['error'=>'DB: '.$db->connect_error]);exit;}
    $db->set_charset('utf8mb4');
    return $db;
}

function respond($d,$c=200){http_response_code($c);echo json_encode($d);exit;}
function respondError($m,$c=400){http_response_code($c);echo json_encode(['success'=>false,'error'=>$m]);exit;}
function getBody(){return json_decode(file_get_contents('php://input'),true)??[];}

function requireAuth(){
    $token=$_SERVER['HTTP_X_AUTH_TOKEN']??'';
    if(!$token) respondError('Unauthorized',401);
    $db=getDB();
    $s=$db->prepare("SELECT id,username,role,name,initials,section,usn FROM users WHERE auth_token=? LIMIT 1");
    $s->bind_param('s',$token);$s->execute();
    $u=$s->get_result()->fetch_assoc();$s->close();$db->close();
    if(!$u) respondError('Session expired',401);
    return $u;
}
