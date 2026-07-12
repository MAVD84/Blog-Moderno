<?php
require_once __DIR__.'/functions.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
verify_csrf();$member=require_member();
$commentId=filter_input(INPUT_POST,'comment_id',FILTER_VALIDATE_INT);$reaction=filter_input(INPUT_POST,'reaction',FILTER_VALIDATE_INT);
if(!$commentId||!in_array($reaction,[-1,1],true)){http_response_code(400);exit('Reacción inválida.');}
$stmt=db()->prepare('SELECT c.post_id,p.slug FROM comments c JOIN posts p ON p.id=c.post_id WHERE c.id=? AND c.aprobado=1');$stmt->execute([$commentId]);$comment=$stmt->fetch();
if(!$comment){http_response_code(404);exit('Comentario no encontrado.');}
$stmt=db()->prepare('SELECT reaction FROM comment_reactions WHERE comment_id=? AND member_id=?');$stmt->execute([$commentId,$member['id']]);$current=(int)($stmt->fetchColumn()?:0);
if($current===$reaction){db()->prepare('DELETE FROM comment_reactions WHERE comment_id=? AND member_id=?')->execute([$commentId,$member['id']]);}
else{db()->prepare('INSERT INTO comment_reactions(comment_id,member_id,reaction) VALUES(?,?,?) ON DUPLICATE KEY UPDATE reaction=VALUES(reaction),updated_at=NOW()')->execute([$commentId,$member['id'],$reaction]);}
redirect('/'.rawurlencode($comment['slug']).'#comment-'.(int)$commentId);
