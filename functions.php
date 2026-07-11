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

function sanitize_html(string $html): string
{
    $allowedTags = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'a'];
    $document = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $document->loadHTML(
        '<?xml encoding="utf-8" ?><div id="editor-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    $root = $document->getElementById('editor-root');
    if (!$root) { return ''; }

    $sanitizeNode = function (DOMNode $node) use (&$sanitizeNode, $allowedTags): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed'], true)) {
                    $child->parentNode?->removeChild($child);
                    continue;
                }
                $sanitizeNode($child);
                if (!in_array($tag, $allowedTags, true)) {
                    while ($child->firstChild) { $child->parentNode?->insertBefore($child->firstChild, $child); }
                    $child->parentNode?->removeChild($child);
                    continue;
                }
                foreach (iterator_to_array($child->attributes) as $attribute) {
                    if ($tag !== 'a' || !in_array(strtolower($attribute->name), ['href', 'title'], true)) {
                        $child->removeAttribute($attribute->name);
                    }
                }
                if ($tag === 'a') {
                    $href = trim($child->getAttribute('href'));
                    if ($href !== '' && !preg_match('#^(https?://|mailto:|/|\#)#i', $href)) {
                        $child->removeAttribute('href');
                    }
                    $child->setAttribute('rel', 'noopener noreferrer');
                }
            }
        }
    };
    $sanitizeNode($root);

    $result = '';
    foreach ($root->childNodes as $child) { $result .= $document->saveHTML($child); }
    return trim($result);
}

function render_editor(string $content = ''): void
{
    ?>
    <label>Contenido</label>
    <div class="editor-shell">
        <div class="editor-toolbar" role="toolbar" aria-label="Formato del contenido">
            <button type="button" data-command="bold" title="Negrita"><strong>B</strong></button>
            <button type="button" data-command="italic" title="Cursiva"><em>I</em></button>
            <button type="button" data-command="underline" title="Subrayado"><u>U</u></button>
            <button type="button" data-block="h2" title="Título">H2</button>
            <button type="button" data-block="h3" title="Subtítulo">H3</button>
            <button type="button" data-block="p" title="Párrafo">P</button>
            <button type="button" data-command="insertUnorderedList" title="Lista">• Lista</button>
            <button type="button" data-command="insertOrderedList" title="Lista numerada">1. Lista</button>
            <button type="button" data-block="blockquote" title="Cita">❝</button>
            <button type="button" data-link title="Enlace">Enlace</button>
            <button type="button" data-command="removeFormat" title="Quitar formato">Limpiar</button>
        </div>
        <div class="rich-editor" contenteditable="true" role="textbox" aria-multiline="true"><?= sanitize_html($content) ?></div>
        <textarea class="editor-value" name="contenido" required><?= e($content) ?></textarea>
    </div>
    <script src="assets/editor.js" defer></script>
    <?php
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
