<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

const UPLOAD_DIR = __DIR__ . '/uploads';
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function is_admin(): bool { return ($_SESSION['admin'] ?? false) === true; }
function redirect(string $url): never { header("Location: {$url}"); exit; }
function flash(string $message, string $type = 'success'): void { $_SESSION['flash'][] = [$type, $message]; }

function require_admin(): void
{
    if (!is_admin()) {
        flash('Por favor inicia sesión.', 'error');
        redirect('login.php');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(400);
        exit('Solicitud inválida. Actualiza la página e inténtalo nuevamente.');
    }
}

function upload_image(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return null; }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('La imagen no pudo subirse o supera 5 MB.');
    }
    if (!is_uploaded_file($file['tmp_name'])) { throw new RuntimeException('Archivo de subida inválido.'); }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime]) || @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Usa una imagen PNG, JPG, GIF o WEBP válida.');
    }
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
        throw new RuntimeException('No se pudo crear el directorio de imágenes.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        throw new RuntimeException('No se pudo guardar la imagen.');
    }
    return $name;
}

function delete_image(?string $name): void
{
    if ($name && preg_match('/^[a-f0-9]{32}\.(png|jpg|gif|webp)$/', $name)) {
        $path = UPLOAD_DIR . '/' . $name;
        if (is_file($path)) { unlink($path); }
    }
}

function render_header(string $title): void
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> · Blog</title><link rel="stylesheet" href="assets/style.css"></head><body>
<nav><a class="brand" href="index.php">Blog.</a><div><?php if (is_admin()): ?>
<a href="admin.php">Escribir</a><a href="comments.php">Comentarios</a>
<form class="inline" method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><button class="link danger">Salir</button></form>
<?php else: ?><a href="login.php">Entrar</a><?php endif; ?></div></nav>
<main><?php foreach ($messages as [$type, $message]): ?><div class="flash <?= e($type) ?>"><?= e($message) ?></div><?php endforeach; ?>
<?php
}

function render_footer(): void { ?></main><footer>&copy; <?= date('Y') ?> Blog Personal.</footer></body></html><?php }
