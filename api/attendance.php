<?php
require_once __DIR__.'/../config/init.php';
$user=requireAuth();$method=$_SERVER['REQUEST_METHOD'];$db=getDB();
if($method==='POST'){
    if(!in_array($user['role'],['admin','teacher'])) respondError('Forbidden',403);
    $b=getBody();$records=$b['records']??[];$date=$b['date']??date('Y-m-d');
    if(!$records) respondError('No records.');
    $s=$db->prepare("INSERT INTO attendance (usn,scanned_at,attendance_date,remarks) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE scanned_at=VALUES(scanned_at),remarks=VALUES(remarks)");
    $saved=0;
    foreach($records as $r){$usn=$r['usn']??'';$scan=$r['scanned_at']??null;$dt=$r['attendance_date']??$date;$rem=$r['remarks']??null;if(!$usn)continue;$s->bind_param('ssss',$usn,$scan,$dt,$rem);if($s->execute())$saved++;}
    $s->close();$db->close();respond(['success'=>true,'saved'=>$saved]);
}
if(isset($_GET['usn'])){
    $usn=$_GET['usn'];$days=min((int)($_GET['days']??30),365);
    if($user['role']==='student'&&$user['usn']!==$usn) respondError('Access denied.',403);
    $s=$db->prepare("SELECT attendance_date AS date,scanned_at,remarks FROM attendance WHERE usn=? ORDER BY attendance_date DESC LIMIT ?");
    $s->bind_param('si',$usn,$days);$s->execute();$rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();$db->close();
    foreach($rows as &$row){$r=strtolower($row['remarks']??'');if(!$row['remarks']&&!$row['scanned_at'])$row['status']='absent';elseif(str_contains($r,'tardy')||str_contains($r,'late'))$row['status']='late';else$row['status']='present';}
    $p=count(array_filter($rows,fn($r)=>$r['status']==='present'));$a=count(array_filter($rows,fn($r)=>$r['status']==='absent'));$l=count(array_filter($rows,fn($r)=>$r['status']==='late'));$t=count($rows);
    respond(['success'=>true,'history'=>$rows,'summary'=>['present'=>$p,'absent'=>$a,'late'=>$l,'total'=>$t,'rate'=>$t>0?round(($p/$t)*100):0]]);
}
respondError('Invalid request.');
