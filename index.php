<?php
$origin=$_SERVER['HTTP_ORIGIN']??'';
$isOk=preg_match('/^https:\/\/aclc-attendance-monitoring[a-z0-9\-]*\.vercel\.app$/',$origin)||in_array($origin,['http://localhost','http://127.0.0.1']);
header("Access-Control-Allow-Origin: ".($isOk?$origin:'https://aclc-attendance-monitoring-bzbv751sz.vercel.app'));
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Auth-Token");
header("Content-Type: application/json");
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(200);echo'{}';exit;}
echo json_encode(['status'=>'ACLC Monitor API running!']);
