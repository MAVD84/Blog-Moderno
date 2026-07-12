<?php
require_once __DIR__.'/functions.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
verify_csrf();
$member=current_member();
if($member && empty($member['email_verified_at'])){flash('Primero verifica tu correo electrónico.','error');redirect('/profile.php');}
if(!$member && !is_logged_in()){flash('Inicia sesión para comentar.','error');redirect('/member-login.php');}
$postId=filter_input(INPUT_POST,'post_id',FILTER_VALIDATE_INT);
$parentId=filter_input(INPUT_POST,'parent_id',FILTER_VALIDATE_INT) ?: null;
$content=trim((string)($_POST['contenido']??''));
if(!$postId||$content===''||mb_strlen($content)>3000){flash('Escribe un comentario válido.','error');redirect('/');}
$stmt=db()->prepare('SELECT slug FROM posts WHERE id=?');$stmt->execute([$postId]);$slug=$stmt->fetchColumn();
if(!$slug){http_response_code(404);exit('Artículo no encontrado.');}
if($parentId){$stmt=db()->prepare('SELECT 1 FROM comments WHERE id=? AND post_id=? AND aprobado=1');$stmt->execute([$parentId,$postId]);if(!$stmt->fetchColumn()){http_response_code(400);exit('El comentario al que respondes no es válido.');}}
if($member){$memberId=(int)$member['id'];$name=$member['display_name'];$email=$member['email'];}
else{$memberId=null;$name=current_author_name();$email=getenv('SMTP_FROM_EMAIL')?:'admin@localhost.invalid';}
$isStaff=!$member&&is_logged_in();
db()->prepare('INSERT INTO comments(post_id,member_id,staff_author_id,parent_id,nombre,email,contenido,aprobado,fecha_aprobacion) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$postId,$memberId,$isStaff?current_user_id():null,$parentId,$name,$email,$content,$isStaff?1:0,$isStaff?date('Y-m-d H:i:s'):null]);
flash($isStaff?($parentId?'Respuesta publicada.':'Comentario publicado.'):($parentId?'Respuesta recibida. Aparecerá cuando sea aprobada.':'Comentario recibido. Aparecerá cuando sea aprobado.'));
redirect('/'.rawurlencode((string)$slug).'#comentarios');
