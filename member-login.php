<?php
require_once __DIR__ . '/functions.php'; require_once __DIR__ . '/mail.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { verify_csrf(); if (!member_auth_allowed('login')) { flash('Demasiados intentos. Espera 15 minutos.', 'error'); redirect('/member-login.php'); } record_member_auth('login');
    $stmt=db()->prepare('SELECT * FROM members WHERE email=? AND active=1'); $stmt->execute([strtolower(trim((string)($_POST['email']??'')))]); $member=$stmt->fetch();
    if (!$member || !password_verify((string)($_POST['password']??''),$member['password_hash'])) { flash('Correo o contraseña incorrectos.','error'); }
    else { session_regenerate_id(true); $_SESSION['member_id']=(int)$member['id']; flash(empty($member['email_verified_at'])?'Accediste. Aún debes verificar tu correo desde Mi perfil.':'Bienvenido, '.$member['display_name'].'.'); redirect('/community.php'); }
}
render_header('Acceder', ['robots'=>'noindex,nofollow']); ?><section class="panel narrow"><h1>Acceder</h1><form method="post"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><label>Correo electrónico<input type="email" name="email" autocomplete="email" required></label><label>Contraseña<input type="password" name="password" autocomplete="current-password" required></label><button class="button">Entrar</button></form><p><a href="/forgot-password.php">¿Olvidaste tu contraseña?</a></p><p><a href="/register.php">Crear una cuenta</a></p></section><?php render_footer();
