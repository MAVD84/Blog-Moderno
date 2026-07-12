<?php
require_once __DIR__ . '/functions.php'; require_once __DIR__ . '/mail.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { verify_csrf(); if (!member_auth_allowed('register')) { flash('Demasiados intentos. Espera 15 minutos.', 'error'); redirect('/register.php'); } record_member_auth('register');
    $name = trim((string)($_POST['display_name'] ?? '')); $email = strtolower(trim((string)($_POST['email'] ?? ''))); $password = (string)($_POST['password'] ?? '');
    try { if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) { throw new RuntimeException('Usa un nombre válido, correo válido y una contraseña de al menos 10 caracteres.'); }
        $stmt = db()->prepare('INSERT INTO members (email,password_hash,display_name,profile_slug) VALUES (?,?,?,?)'); $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $name, unique_member_slug($name)]);
        $member = ['id' => (int)db()->lastInsertId(), 'email' => $email, 'display_name' => $name]; send_verification_email($member);
        flash('Cuenta creada. Revisa tu correo para verificarla.'); redirect('/member-login.php');
    } catch (PDOException) { flash('Ese correo ya está registrado.', 'error'); } catch (Throwable $e) { flash($e->getMessage(), 'error'); }
}
render_header('Crear cuenta', ['robots'=>'noindex,nofollow']); ?>
<section class="panel narrow"><h1>Crear cuenta</h1><p class="muted">Verifica tu correo para comentar, dar like o dislike.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><label>Nombre visible<input name="display_name" maxlength="80" required></label><label>Correo electrónico<input type="email" name="email" autocomplete="email" required></label><label>Contraseña<input type="password" name="password" minlength="10" autocomplete="new-password" required></label><button class="button">Registrarme</button></form><p><a href="/member-login.php">Ya tengo cuenta</a></p></section><?php render_footer();
