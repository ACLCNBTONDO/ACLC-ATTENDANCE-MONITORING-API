<?php
require_once __DIR__.'/../config/init.php';
$user=requireAuth();$method=$_SERVER['REQUEST_METHOD'];$db=getDB();
if($method==='POST'){
    if($user['role']!=='admin') respondError('Admins only.',403);
    $b=getBody();$last=trim($b['last_name']??'');$first=trim($b['first_name']??'');
    $mid=trim($b['middle_name']??'');$age=trim($b['age']??'');$sex=trim($b['sex']??'');
    $usn=trim($b['usn']??'');$lrn=trim($b['lrn']??'');$sec=trim($b['section']??'');$email=trim($b['guardian_email']??'');
    if(!$last||!$first||!$usn||!$sec) respondError('Last name, first name, USN and section required.');
    $s=$db->prepare("INSERT INTO students (last_name,first_name,middle_name,age,sex,usn,lrn,section,guardian_email) VALUES (?,?,?,?,?,?,?,?,?)");
    $s->bind_param('sssssssss',$last,$first,$mid,$age,$sex,$usn,$lrn,$sec,$email);
    if(!$s->execute()) respondError('Failed. USN may already exist.');
    $s->close();$db->close();respond(['success'=>true]);
}
if(isset($_GET['all'])){
    if($user['role']==='teacher'){$s=$db->prepare("SELECT usn,first_name,last_name,middle_name,age,sex,lrn,section,CONCAT(last_name,', ',first_name) AS full_name FROM students WHERE section=? ORDER BY last_name,first_name");$s->bind_param('s',$user['section']);}
    else{$s=$db->prepare("SELECT usn,first_name,last_name,middle_name,age,sex,lrn,section,CONCAT(last_name,', ',first_name) AS full_name FROM students ORDER BY section,last_name,first_name");}
    $s->execute();$rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();$db->close();
    respond(['success'=>true,'students'=>$rows]);
}
if(isset($_GET['usn'])){
    $usn=$_GET['usn'];
    if($user['role']==='student'&&$user['usn']!==$usn) respondError('Access denied.',403);
    $s=$db->prepare("SELECT * FROM students WHERE usn=? LIMIT 1");$s->bind_param('s',$usn);$s->execute();
    $row=$s->get_result()->fetch_assoc();$s->close();$db->close();
    if(!$row) respondError('Not found.',404);respond(['success'=>true,'student'=>$row]);
}
$section=$_GET['section']??'';$date=$_GET['date']??date('Y-m-d');
if(!$section) respondError('section required.');
if($user['role']==='teacher'&&$user['section']!==$section) respondError('Access denied.',403);
$s=$db->prepare("SELECT st.usn,st.last_name,st.first_name,st.sex,st.lrn,st.section,CONCAT(st.last_name,', ',st.first_name) AS full_name,a.scanned_at,COALESCE(a.remarks,'') AS remarks FROM students st LEFT JOIN attendance a ON a.usn=st.usn AND a.attendance_date=? WHERE st.section=? ORDER BY st.last_name,st.first_name");
$s->bind_param('ss',$date,$section);$s->execute();$rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();$db->close();
respond(['success'=>true,'students'=>$rows,'section'=>$section,'date'=>$date]);
