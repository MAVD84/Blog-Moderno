<?php
require_once __DIR__.'/functions.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
verify_csrf();$member=require_member();$commentId=filter_input(INPUT_POST,'comment_id',FILTER_VALIDATE_INT);$action=(string)($_POST['action']??'');
$stmt=db()->prepare('SELECT c.id FROM comments c JOIN comments parent ON parent.id=c.parent_id WHERE c.id=? AND parent.member_id=? AND c.aprobado=0');$stmt->execute([$commentId,$member['id']]);
if(!$stmt->fetchColumn()){http_response_code(403);exit('No puedes moderar esta respuesta.');}
if($action==='approve'){db()->prepare('UPDATE comments SET aprobado=1,fecha_aprobacion=NOW() WHERE id=?')->execute([$commentId]);flash('Respuesta aprobada.');}
elseif($action==='delete'){db()->prepare('DELETE FROM comments WHERE id=?')->execute([$commentId]);flash('Respuesta eliminada.');}
else{http_response_code(400);exit('Acción inválida.');}
redirect('/my-comments.php');
