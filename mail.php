<?php
declare(strict_types=1);
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

function send_site_mail(string $to, string $subject, string $html, string $text): void
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = getenv('SMTP_HOST') ?: 'smtp.jrz.wtf';
    $mail->Port = (int)(getenv('SMTP_PORT') ?: 465);
    $mail->SMTPAuth = true;
    $mail->Username = getenv('SMTP_USERNAME') ?: '';
    $mail->Password = getenv('SMTP_PASSWORD') ?: '';
    $mail->SMTPSecure = strtolower(getenv('SMTP_ENCRYPTION') ?: 'ssl') === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
    $from = getenv('SMTP_FROM_EMAIL') ?: $mail->Username;
    if (!$from || !$mail->Password) { throw new RuntimeException('Falta configurar SMTP_USERNAME o SMTP_PASSWORD en .env.'); }
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($from, getenv('SMTP_FROM_NAME') ?: site_setting('site_name'));
    $mail->addAddress($to);
    $mail->isHTML(true); $mail->Subject = $subject; $mail->Body = $html; $mail->AltBody = $text;
    $mail->send();
}

function send_verification_email(array $member): void
{
    $url = absolute_url('/verify.php?token=' . create_member_token((int)$member['id'], 'verify', 24));
    $safe = e($member['display_name']);
    send_site_mail($member['email'], 'Verifica tu correo', "<h2>Hola, {$safe}</h2><p>Confirma tu cuenta para comentar y reaccionar.</p><p><a href=\"" . e($url) . "\">Verificar mi correo</a></p><p>El enlace vence en 24 horas.</p>", "Verifica tu correo: {$url}");
}

function send_reset_email(array $member): void
{
    $url = absolute_url('/reset-password.php?token=' . create_member_token((int)$member['id'], 'reset', 1));
    send_site_mail($member['email'], 'Restablece tu contraseña', '<h2>Restablecer contraseña</h2><p><a href="' . e($url) . '">Crear una contraseña nueva</a></p><p>El enlace vence en una hora.</p>', "Restablece tu contraseña: {$url}");
}
