<?php
require_once __DIR__ . '/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = strtolower(trim($_POST['username'] ?? ''));
    $displayName = trim($_POST['display_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'editor';
    $password = (string)($_POST['password'] ?? '');
    if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username) || $displayName === '' || mb_strlen($displayName) > 100 || !in_array($role, ['admin', 'editor'], true) || strlen($password) < 12 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
        flash('Revisa los datos. El usuario debe tener 3 caracteres y la contraseña al menos 12.', 'error');
    } else {
        try {
            $stmt = db()->prepare('INSERT INTO users (username, display_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$username, $displayName, $email ?: null, password_hash($password, PASSWORD_DEFAULT), $role]);
            flash('Usuario creado correctamente.');
        } catch (PDOException $error) {
            flash($error->getCode() === '23000' ? 'El usuario o correo ya existe.' : 'No se pudo crear el usuario.', 'error');
        }
    }
    redirect('users.php');
}

$users = db()->query('SELECT id, username, display_name, email, role, active, created_at FROM users ORDER BY active DESC, display_name')->fetchAll();
render_header('Usuarios', ['robots' => 'noindex,nofollow']);
?>
<div class="users-layout">
<div class="panel"><h1>Agregar usuario</h1><p class="muted">Los administradores tienen acceso completo. Los editores administran únicamente sus propios posts.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><label>Nombre público<input name="display_name" maxlength="100" required></label><label>Usuario<input name="username" pattern="[a-zA-Z0-9._-]{3,50}" maxlength="50" required></label><label>Correo electrónico<input type="email" name="email" maxlength="254"></label><label>Rol<select name="role"><option value="editor">Editor</option><option value="admin">Administrador</option></select></label><label>Contraseña temporal<input type="password" name="password" minlength="12" required></label><button class="button" type="submit">Crear usuario</button></form></div>
<div class="panel"><h1>Usuarios</h1><div class="user-list"><?php foreach ($users as $user): ?><article class="user-card"><div class="user-card-head"><div><strong><?= e($user['display_name']) ?></strong><small>@<?= e($user['username']) ?> · <?= e($user['email'] ?: 'Sin correo') ?></small></div><span class="status-badge <?= $user['active'] ? 'approved' : 'pending' ?>"><?= $user['active'] ? e(ucfirst($user['role'])) : 'Inactivo' ?></span></div><?php if((int)$user['id']===current_user_id()):?><div class="user-actions"><a class="button" href="/account.php">Cambiar mi contraseña</a></div><?php else:?><div class="user-actions"><form method="post" action="user-action.php"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= (int)$user['id'] ?>"><button class="button <?= $user['active'] ? 'danger-bg' : '' ?>" name="action" value="toggle"><?= $user['active'] ? 'Desactivar' : 'Activar' ?></button></form><form class="password-reset" method="post" action="user-action.php"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= (int)$user['id'] ?>"><input type="password" name="password" minlength="12" placeholder="Nueva contraseña" required><button class="button" name="action" value="password">Cambiar</button></form></div><?php endif;?></article><?php endforeach; ?><?php if (!$users): ?><p class="empty">Aún no hay usuarios en MySQL. Tu acceso actual proviene de `.env`.</p><?php endif; ?></div></div>
</div>
<?php render_footer(); ?>
