<?php
require_once __DIR__ . '/functions.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $user = getenv('ADMIN_USER') ?: ''; $hash = getenv('ADMIN_PASSWORD_HASH') ?: '';
    if ($user && $hash && hash_equals($user, $_POST['username'] ?? '') && password_verify($_POST['password'] ?? '', $hash)) {
        session_regenerate_id(true); $_SESSION['admin'] = true; redirect('admin.php');
    }
    flash('Usuario o contraseña incorrectos.', 'error');
}
render_header('Acceso');
?><div class="panel narrow"><h1>Acceso administrativo</h1><form method="post"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><label>Usuario<input name="username" required></label><label>Contraseña<input type="password" name="password" required></label><button class="button">Ingresar</button></form></div><?php render_footer(); ?>
