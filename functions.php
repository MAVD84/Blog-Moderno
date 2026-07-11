<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

const UPLOAD_DIR = __DIR__ . '/uploads';
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function is_admin(): bool
{
    if (($_SESSION['admin'] ?? false) !== true) { return false; }
    $lastActivity = (int)($_SESSION['last_activity'] ?? 0);
    if ($lastActivity === 0 || time() - $lastActivity > 1800) {
        unset($_SESSION['admin'], $_SESSION['last_activity']);
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}
function redirect(string $url): never { header("Location: {$url}"); exit; }
function post_url(array $post): string { return '/' . rawurlencode($post['slug']); }

function site_base_url(): string
{
    $configured = rtrim(getenv('SITE_URL') ?: '', '/');
    if (filter_var($configured, FILTER_VALIDATE_URL)) { return $configured; }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^[a-z0-9.-]+(?::\d+)?$/i', $host)) { $host = 'localhost'; }
    return (request_is_https() ? 'https' : 'http') . '://' . $host;
}

function absolute_url(string $path): string
{
    if (filter_var($path, FILTER_VALIDATE_URL)) { return $path; }
    return site_base_url() . '/' . ltrim($path, '/');
}

function slugify(string $title): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title;
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii) ?? '', '-'));
    return substr($slug ?: 'articulo', 0, 190);
}

function unique_post_slug(string $title, ?int $excludeId = null): string
{
    $base = slugify($title); $slug = $base; $suffix = 2;
    do {
        $sql = 'SELECT 1 FROM posts WHERE slug = ?' . ($excludeId ? ' AND id <> ?' : '') . ' LIMIT 1';
        $stmt = db()->prepare($sql); $stmt->execute($excludeId ? [$slug, $excludeId] : [$slug]);
        if (!$stmt->fetchColumn()) { return $slug; }
        $slug = substr($base, 0, 180) . '-' . $suffix++;
    } while ($suffix < 10000);
    throw new RuntimeException('No se pudo generar una URL única para el artículo.');
}

function ensure_post_slug(array $post): array
{
    if (!empty($post['slug'])) { return $post; }
    $post['slug'] = unique_post_slug($post['titulo'], (int)$post['id']);
    $stmt = db()->prepare('UPDATE posts SET slug = ? WHERE id = ?'); $stmt->execute([$post['slug'], $post['id']]);
    return $post;
}
function flash(string $message, string $type = 'success'): void
{
    $entry = [$type, $message];
    $messages = $_SESSION['flash'] ?? [];
    if (!$messages || end($messages) !== $entry) { $_SESSION['flash'][] = $entry; }
}

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

function login_ip_hash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = getenv('APP_SECRET') ?: env_required('DB_PASSWORD');
    return hash_hmac('sha256', $ip, $key);
}

function login_throttle_seconds(): int
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) AS failures, UNIX_TIMESTAMP(MAX(attempted_at)) AS last_attempt
         FROM login_attempts
         WHERE ip_hash = ? AND successful = 0 AND resolved_at IS NULL
           AND attempted_at >= NOW() - INTERVAL 24 HOUR'
    );
    $stmt->execute([login_ip_hash()]);
    $row = $stmt->fetch();
    $failures = (int)($row['failures'] ?? 0);
    $lastAttempt = (int)($row['last_attempt'] ?? 0);
    $lockSeconds = match (true) {
        $failures >= 12 => 86400,
        $failures >= 8 => 1800,
        $failures >= 5 => 300,
        default => 0,
    };
    return max(0, $lastAttempt + $lockSeconds - time());
}

function record_login_attempt(bool $successful): void
{
    $pdo = db();
    $hash = login_ip_hash();
    $pdo->beginTransaction();
    try {
        if ($successful) {
            $stmt = $pdo->prepare('UPDATE login_attempts SET resolved_at = NOW() WHERE ip_hash = ? AND successful = 0 AND resolved_at IS NULL');
            $stmt->execute([$hash]);
        }
        $stmt = $pdo->prepare('INSERT INTO login_attempts (ip_hash, successful) VALUES (?, ?)');
        $stmt->execute([$hash, $successful ? 1 : 0]);
        if (random_int(1, 100) === 1) { $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 30 DAY'); }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $error;
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
    $editorVersion = (string) (@filemtime(__DIR__ . '/assets/editor.js') ?: '1');
    ?>
    <label class="field-label" for="rich-editor">Contenido</label>
    <div class="editor-shell">
        <div class="editor-toolbar" role="toolbar" aria-label="Formato del contenido">
            <div class="editor-group"><button type="button" data-command="bold" title="Negrita"><strong>B</strong></button><button type="button" data-command="italic" title="Cursiva"><em>I</em></button><button type="button" data-command="underline" title="Subrayado"><u>U</u></button></div>
            <div class="editor-group"><button type="button" data-block="h2">Título</button><button type="button" data-block="h3">Subtítulo</button><button type="button" data-block="p">Párrafo</button></div>
            <div class="editor-group"><button type="button" data-command="insertUnorderedList">• Lista</button><button type="button" data-command="insertOrderedList">1. Lista</button><button type="button" data-block="blockquote">❝ Cita</button></div>
            <div class="editor-group"><button type="button" data-link>🔗 Enlace</button><button type="button" data-command="removeFormat">Limpiar</button></div>
        </div>
        <div id="rich-editor" class="rich-editor" contenteditable="true" role="textbox" aria-multiline="true" data-placeholder="Escribe aquí el contenido de tu publicación..."><?= sanitize_html($content) ?></div>
        <textarea class="editor-value" name="contenido" hidden><?= e($content) ?></textarea>
        <div class="editor-status"><span>Formato seguro activado</span><span class="editor-count">0 palabras</span></div>
    </div>
    <script src="/assets/editor.js?v=<?= e($editorVersion) ?>" defer></script>
    <?php
}

function render_header(string $title, array $metadata = []): void
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    $styleVersion = (string) (@filemtime(__DIR__ . '/assets/style.css') ?: '1');
    $siteName = 'Polygon Blockchain';
    $pageTitle = $metadata['title'] ?? ($title === 'Inicio' ? $siteName . ' · Blog' : $title . ' · ' . $siteName);
    $description = $metadata['description'] ?? 'Artículos, análisis y aprendizaje sobre Polygon, Ethereum y tecnología blockchain.';
    $canonical = absolute_url($metadata['canonical'] ?? (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
    $socialImage = absolute_url($metadata['image'] ?? '/assets/og-image.png');
    $type = $metadata['type'] ?? 'website';
    $imageWidth = (int)($metadata['image_width'] ?? 1320);
    $imageHeight = (int)($metadata['image_height'] ?? 682);
    ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="robots" content="<?= e($metadata['robots'] ?? 'index,follow,max-image-preview:large') ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<link rel="icon" type="image/png" href="/assets/favicon.png">
<link rel="apple-touch-icon" href="/assets/favicon.png">
<meta property="og:locale" content="es_MX">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:type" content="<?= e($type) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($socialImage) ?>">
<meta property="og:image:alt" content="<?= e($metadata['image_alt'] ?? $pageTitle) ?>">
<meta property="og:image:width" content="<?= $imageWidth ?>"><meta property="og:image:height" content="<?= $imageHeight ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($pageTitle) ?>">
<meta name="twitter:description" content="<?= e($description) ?>">
<meta name="twitter:image" content="<?= e($socialImage) ?>">
<?php if (!empty($metadata['published_time'])): ?><meta property="article:published_time" content="<?= e($metadata['published_time']) ?>"><?php endif; ?>
<?php if ($type === 'article'): ?><script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $title, 'description' => $description, 'image' => [$socialImage], 'datePublished' => $metadata['published_time'] ?? null, 'mainEntityOfPage' => $canonical, 'author' => ['@type' => 'Person', 'name' => 'Miguel Vega'], 'publisher' => ['@type' => 'Organization', 'name' => $siteName, 'logo' => ['@type' => 'ImageObject', 'url' => absolute_url('/assets/favicon.png')]]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script><?php endif; ?>
<link rel="stylesheet" href="/assets/style.css?v=<?= e($styleVersion) ?>"></head><body>
<nav><a class="brand" href="index.php">Blog.</a><div><?php if (is_admin()): ?>
<a href="admin.php">Escribir</a><a href="comments.php">Comentarios</a><a href="security.php">Seguridad</a>
<form class="inline" method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><button class="link danger">Salir</button></form>
<?php endif; ?></div></nav>
<main><?php foreach ($messages as [$type, $message]): ?><div class="flash <?= e($type) ?>"><?= e($message) ?></div><?php endforeach; ?>
<?php
}

function render_footer(): void { ?></main><footer>&copy; <?= date('Y') ?> Blog Personal.</footer></body></html><?php }
