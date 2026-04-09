<?php
ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';
$user=$method=$db=null;
$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

if ($method==='POST') {
    if (!in_array($user['role'],['admin','teacher'])) { ob_end_clean(); respondError('Forbidden',403); }
    $body=getBody(); $records=$body['records']??[]; $date=$body['date']??date('Y-m-d');
    if (empty($records)) { ob_end_clean(); respondError('No records provided.'); }
    $stmt=$db->prepare("INSERT INTO attendance (usn,date,status,time_in,remarks,marked_by) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),time_in=VALUES(time_in),remarks=VALUES(remarks),marked_by=VALUES(marked_by),updated_at=CURRENT_TIMESTAMP");
    $saved=0;
    foreach($records as $rec) {
        $usn=$rec['usn']??'';
        $status=in_array($rec['status'],['present','absent','late'])?$rec['status']:'absent';
        $tin=(!empty($rec['time_in'])&&$rec['time_in']!=='—')?$rec['time_in']:null;
        $rem=$rec['remarks']??null; $uid=$user['id'];
        if(!$usn) continue;
        $stmt->bind_param('sssssi',$usn,$date,$status,$tin,$rem,$uid);
        if($stmt->execute()) $saved++;
    }
    $stmt->close(); ob_end_clean();
    respond(['success'=>true,'saved'=>$saved]);
}

if ($method==='GET'&&isset($_GET['usn'])) {
    $usn=$_GET['usn']; $days=min(intval($_GET['days']??30),365);
    if ($user['role']==='student'&&$user['usn']!==$usn) { ob_end_clean(); respondError('Access denied.',403); }
    $stmt=$db->prepare("SELECT date,status,time_in,scanned_at,remarks FROM attendance WHERE usn=? ORDER BY date DESC LIMIT ?");
    $stmt->bind_param('si',$usn,$days); $stmt->execute();
    $result=$stmt->get_result(); $history=[];
    while($row=$result->fetch_assoc()) $history[]=$row;
    $stmt->close();
    $present=count(array_filter($history,fn($r)=>$r['status']==='present'));
    $absent=count(array_filter($history,fn($r)=>$r['status']==='absent'));
    $late=count(array_filter($history,fn($r)=>$r['status']==='late'));
    $total=count($history); $rate=$total>0?round(($present/$total)*100):0;
    ob_end_clean();
    respond(['success'=>true,'history'=>$history,'summary'=>compact('present','absent','late','total','rate')]);
}
ob_end_clean();
respondError('Invalid request.');
