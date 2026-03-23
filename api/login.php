<?php
require_once __DIR__.'/../config/init.php';
if($_SERVER['REQUEST_METHOD']!=='POST') respondError('Method not allowed',405);
$b=getBody(); $username=trim($b['username']??''); $password=trim($b['password']??''); $role=trim($b['role']??'');
if(!$username||!$password||!$role) respondError('All fields required.');
$db=getDB();
$s=$db->prepare("SELECT id,username,password,role,name,initials,section,usn FROM users WHERE username=? AND role=? LIMIT 1");
$s->bind_param('ss',$username,$role);$s->execute();
$user=$s->get_result()->fetch_assoc();$s->close();
if(!$user) respondError('Incorrect username or password.',401);
$ok=password_verify($password,$user['password'])||($password===$user['password']);
if(!$ok) respondError('Incorrect username or password.',401);
$token=md5(uniqid('',true)).md5(uniqid('',true));
$u=$db->prepare("UPDATE users SET auth_token=? WHERE id=?");
$u->bind_param('si',$token,$user['id']);$u->execute();$u->close();$db->close();
respond(['success'=>true,'token'=>$token,'user'=>['id'=>(int)$user['id'],'name'=>$user['name'],'role'=>$user['role'],'initials'=>$user['initials'],'section_name'=>$user['section'],'section'=>$user['section'],'usn'=>$user['usn']]]);
