<?php
require_once __DIR__.'/../config/init.php';
$user=requireAuth(); $db=getDB();
if($user['role']==='teacher'){
    $s=$db->prepare("SELECT section AS name,COUNT(*) AS student_count FROM students WHERE section=? GROUP BY section");
    $s->bind_param('s',$user['section']);$s->execute();$res=$s->get_result();$s->close();
}else{$res=$db->query("SELECT section AS name,COUNT(*) AS student_count FROM students WHERE section IS NOT NULL AND section!='' GROUP BY section ORDER BY section ASC");}
$sections=[];
while($row=$res->fetch_assoc()){preg_match('/^(\w+)\s*(\d*)/',$row['name'],$m);$row['strand']=$m[1]??'Other';$row['year_level']=$m[2]??'';$sections[]=$row;}
$db->close();respond(['success'=>true,'sections'=>$sections]);
