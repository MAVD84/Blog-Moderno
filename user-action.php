<?php
require_once __DIR__ . '/functions.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';
if (!$id || $id === current_user_id()) { flash('No puedes modificar tu propia cuenta desde esta acción.', 'error'); redirect('users.php'); }

if ($action === 'toggle') {
    $stmt = db()->prepare('SELECT role, active FROM users WHERE id = ?'); $stmt->execute([$id]); $user = $stmt->fetch();
    if (!$user) { flash('Usuario no encontrado.', 'error'); redirect('users.php'); }
    if ($user['role'] === 'admin' && $user['active']) {
        $activeAdmins = (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1")->fetchColumn();
        if ($activeAdmins <= 1) { flash('Debe permanecer al menos un administrador activo.', 'error'); redirect('users.php'); }
    }
    $stmt = db()->prepare('UPDATE users SET active = NOT active WHERE id = ?'); $stmt->execute([$id]); flash('Estado del usuario actualizado.');
} elseif ($action === 'password') {
    $password = (string)($_POST['password'] ?? '');
    if (strlen($password) < 12) { flash('La contraseña debe tener al menos 12 caracteres.', 'error'); redirect('users.php'); }
    $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?'); $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]); flash('Contraseña actualizada.');
} else { http_response_code(400); exit('Acción inválida.'); }
redirect('users.php');
