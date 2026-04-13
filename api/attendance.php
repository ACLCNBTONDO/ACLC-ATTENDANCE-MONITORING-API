<?php
ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';
$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

if ($method==='POST') {
    if (!in_array($user['role'],['admin','teacher'])) { ob_end_clean(); respondError('Forbidden',403); }
    $body=getBody(); $records=$body['records']??[]; $date=$body['date']??date('Y-m-d');
    if (empty($records)) { ob_end_clean(); respondError('No records provided.'); }
    $stmt=$db->prepare("INSERT INTO attendance (usn,attendance_date,time_in,remarks) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE time_in=VALUES(time_in),remarks=VALUES(remarks),time_out=VALUES(time_out)");
    $saved=0;
    foreach($records as $rec) {
        $usn=$rec['usn']??'';
        $status=$rec['status']??'absent';
        if($status!=='present') continue; // Only save present records
        $tin=(!empty($rec['time_in'])&&$rec['time_in']!=='—')?$rec['time_in']:date('H:i');
        $rem=$rec['remarks']??null;
        if(!$usn) continue;
        $stmt->bind_param('ssss',$usn,$date,$tin,$rem);
        if($stmt->execute()) $saved++;
    }
    $stmt->close(); ob_end_clean();
    respond(['success'=>true,'saved'=>$saved]);
}

if ($method==='GET'&&isset($_GET['usn'])) {
    $usn=$_GET['usn']; $days=min(intval($_GET['days']??30),365);
    if ($user['role']==='student'&&$user['usn']!==$usn) { ob_end_clean(); respondError('Access denied.',403); }
    $stmt=$db->prepare("SELECT attendance_date AS date, remarks AS status, time_in, time_out AS scanned_at, remarks FROM attendance WHERE usn=? ORDER BY attendance_date DESC LIMIT ?");
    $stmt->bind_param('si',$usn,$days); $stmt->execute();
    $result=$stmt->get_result(); $history=[];
    while($row=$result->fetch_assoc()) $history[]=$row;
    $stmt->close();
    $present=count($history); $total=$present;
    $rate=$total>0?100:0;
    ob_end_clean();
    respond(['success'=>true,'history'=>$history,'summary'=>['present'=>$present,'absent'=>0,'late'=>0,'total'=>$total,'rate'=>$rate]]);
}
ob_end_clean();
respondError('Invalid request.');
