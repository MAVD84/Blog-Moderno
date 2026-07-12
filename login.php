<?php
require_once __DIR__ . '/functions.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $waitSeconds = login_throttle_seconds();
    if ($waitSeconds > 0) {
        flash('Demasiados intentos. Espera ' . max(1, (int)ceil($waitSeconds / 60)) . ' minuto(s).', 'error');
        render_header('Acceso', ['robots' => 'noindex,nofollow']);
        ?><div class="panel narrow"><h1>Acceso administrativo</h1><p class="muted">El acceso está temporalmente limitado.</p></div><?php render_footer(); exit;
    }
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $account = null;
    try {
        $stmt = db()->prepare('SELECT id, username, display_name, role, password_hash FROM users WHERE username = ? AND active = 1 LIMIT 1');
        $stmt->execute([$username]); $account = $stmt->fetch() ?: null;
    } catch (PDOException $error) {
        if (!str_contains($error->getMessage(), 'users')) { throw $error; }
    }
    $valid = $account && password_verify($password, $account['password_hash']);
    if (!$valid && !$account) {
        $envUser = getenv('ADMIN_USER') ?: '';
        $hash = getenv('ADMIN_PASSWORD_HASH') ?: '';
        $plainPassword = getenv('ADMIN_PASSWORD') ?: '';
        if ($hash === '' && $plainPassword !== '') { $hash = password_hash($plainPassword, PASSWORD_DEFAULT); }
        if ($envUser && $hash && hash_equals($envUser, $username) && password_verify($password, $hash)) {
            $valid = true; $account = ['id' => null, 'display_name' => $envUser, 'role' => 'admin'];
        }
    }
    record_login_attempt((bool)$valid);
    if ($valid) {
        if (!empty($account['id']) && password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)) {
            $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?'); $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $account['id']]);
        }
        session_regenerate_id(true); $_SESSION['authenticated'] = true; $_SESSION['admin'] = true; $_SESSION['user_id'] = $account['id']; $_SESSION['display_name'] = $account['display_name']; $_SESSION['role'] = $account['role']; $_SESSION['last_activity'] = time(); $_SESSION['csrf'] = bin2hex(random_bytes(32)); redirect('admin.php');
    }
    usleep(random_int(250000, 500000));
    flash('Usuario o contraseña incorrectos.', 'error');
}
render_header('Acceso', ['robots' => 'noindex,nofollow']);
?><div class="panel narrow"><h1>Acceso administrativo</h1><form method="post"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><label>Usuario<input name="username" required></label><label>Contraseña<input type="password" name="password" required></label><button class="button">Ingresar</button></form></div><?php render_footer(); ?>
