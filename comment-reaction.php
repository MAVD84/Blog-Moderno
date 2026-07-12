<?php
require_once __DIR__.'/functions.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
verify_csrf();
$commentId=filter_input(INPUT_POST,'comment_id',FILTER_VALIDATE_INT);$reaction=filter_input(INPUT_POST,'reaction',FILTER_VALIDATE_INT);
if(!$commentId||!in_array($reaction,[-1,1],true)){http_response_code(400);exit('Reacción inválida.');}
$stmt=db()->prepare('SELECT c.post_id,p.slug FROM comments c JOIN posts p ON p.id=c.post_id WHERE c.id=? AND c.aprobado=1');$stmt->execute([$commentId]);$comment=$stmt->fetch();
if(!$comment){http_response_code(404);exit('Comentario no encontrado.');}
if(is_logged_in()&&current_user_id()){$table='comment_staff_reactions';$column='user_id';$actor=current_user_id();}else{$member=require_member();$table='comment_reactions';$column='member_id';$actor=(int)$member['id'];}
$stmt=db()->prepare("SELECT reaction FROM {$table} WHERE comment_id=? AND {$column}=?");$stmt->execute([$commentId,$actor]);$current=(int)($stmt->fetchColumn()?:0);
if($current===$reaction){db()->prepare("DELETE FROM {$table} WHERE comment_id=? AND {$column}=?")->execute([$commentId,$actor]);}
else{db()->prepare("INSERT INTO {$table}(comment_id,{$column},reaction) VALUES(?,?,?) ON DUPLICATE KEY UPDATE reaction=VALUES(reaction),updated_at=NOW()")->execute([$commentId,$actor,$reaction]);}
redirect('/'.rawurlencode($comment['slug']).'#comment-'.(int)$commentId);
