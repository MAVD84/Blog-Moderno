<?php
require_once __DIR__ . '/functions.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $waitSeconds = login_throttle_seconds();
    if ($waitSeconds > 0) {
        flash('Demasiados intentos. Espera ' . max(1, (int)ceil($waitSeconds / 60)) . ' minuto(s).', 'error');
        render_header('Acceso');
        ?><div class="panel narrow"><h1>Acceso administrativo</h1><p class="muted">El acceso está temporalmente limitado.</p></div><?php render_footer(); exit;
    }
    $user = getenv('ADMIN_USER') ?: '';
    $hash = getenv('ADMIN_PASSWORD_HASH') ?: '';
    $plainPassword = getenv('ADMIN_PASSWORD') ?: '';
    if ($hash === '' && $plainPassword !== '') {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    }
    $valid = $user && $hash && hash_equals($user, $_POST['username'] ?? '') && password_verify($_POST['password'] ?? '', $hash);
    record_login_attempt((bool)$valid);
    if ($valid) {
        session_regenerate_id(true); $_SESSION['admin'] = true; $_SESSION['last_activity'] = time(); $_SESSION['csrf'] = bin2hex(random_bytes(32)); redirect('admin.php');
    }
    usleep(random_int(250000, 500000));
    flash('Usuario o contraseña incorrectos.', 'error');
}
render_header('Acceso');
?><div class="panel narrow"><h1>Acceso administrativo</h1><form method="post"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><label>Usuario<input name="username" required></label><label>Contraseña<input type="password" name="password" required></label><button class="button">Ingresar</button></form></div><?php render_footer(); ?>
