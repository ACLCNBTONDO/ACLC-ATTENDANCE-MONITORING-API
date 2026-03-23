<?php
require_once __DIR__.'/../config/init.php';
$token=$_SERVER['HTTP_X_AUTH_TOKEN']??'';
if($token){$db=getDB();$s=$db->prepare("UPDATE users SET auth_token=NULL WHERE auth_token=?");$s->bind_param('s',$token);$s->execute();$s->close();$db->close();}
respond(['success'=>true]);
