<?php
require_once __DIR__.'/../config/init.php';
$user=requireAuth();if(!in_array($user['role'],['admin','teacher'])) respondError('Forbidden',403);
$db=getDB();$date=$_GET['date']??date('Y-m-d');$section=$user['role']==='teacher'?$user['section']:null;
$where=$section?"AND st.section=?":"";
$sql="SELECT COUNT(st.usn) AS total,SUM(CASE WHEN a.remarks IS NOT NULL AND a.remarks!='' AND a.remarks NOT LIKE '%tardy%' AND a.remarks NOT LIKE '%late%' THEN 1 ELSE 0 END) AS present,SUM(CASE WHEN a.usn IS NULL OR(a.remarks IS NULL AND a.scanned_at IS NULL) THEN 1 ELSE 0 END) AS absent,SUM(CASE WHEN a.remarks LIKE '%tardy%' OR a.remarks LIKE '%late%' THEN 1 ELSE 0 END) AS late FROM students st LEFT JOIN attendance a ON a.usn=st.usn AND a.attendance_date=? $where";
$s=$db->prepare($sql);if($section)$s->bind_param('ss',$date,$section);else$s->bind_param('s',$date);
$s->execute();$stats=$s->get_result()->fetch_assoc();$s->close();
$sc=$db->query("SELECT COUNT(DISTINCT section) AS cnt FROM students WHERE section IS NOT NULL AND section!=''")->fetch_assoc();
$weekly=[];
for($i=6;$i>=0;$i--){$d=date('Y-m-d',strtotime("-$i days",strtotime($date)));
    if($section){$w=$db->prepare("SELECT COUNT(a.usn) AS present FROM attendance a JOIN students st ON st.usn=a.usn WHERE a.attendance_date=? AND a.remarks IS NOT NULL AND a.remarks!='' AND st.section=?");$w->bind_param('ss',$d,$section);}
    else{$w=$db->prepare("SELECT COUNT(usn) AS present FROM attendance WHERE attendance_date=? AND remarks IS NOT NULL AND remarks!=''");$w->bind_param('s',$d);}
    $w->execute();$wRow=$w->get_result()->fetch_assoc();$w->close();
    $weekly[]=['date'=>$d,'day'=>date('D',strtotime($d)),'present'=>(int)$wRow['present']];
}
$db->close();
respond(['success'=>true,'stats'=>['total'=>(int)$stats['total'],'present'=>(int)$stats['present'],'absent'=>(int)$stats['absent'],'late'=>(int)$stats['late']],'section_count'=>(int)$sc['cnt'],'weekly'=>$weekly]);
