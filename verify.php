<?php
require_once __DIR__ . '/functions.php';
$id=consume_member_token((string)($_GET['token']??''),'verify'); if (!$id) { flash('El enlace es inválido o ya venció.','error'); redirect('/member-login.php'); }
db()->prepare('UPDATE members SET email_verified_at=COALESCE(email_verified_at,NOW()) WHERE id=?')->execute([$id]); $_SESSION['member_id']=$id; flash('Correo verificado. Ya puedes comentar y reaccionar.'); redirect('/profile.php');
