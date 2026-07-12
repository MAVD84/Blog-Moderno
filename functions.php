<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

const UPLOAD_DIR = __DIR__ . '/uploads';
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function format_date(?string $value, bool $includeTime = false): string
{
    if (!$value) { return ''; }
    try { return (new DateTime($value))->format($includeTime ? 'd/m/Y h:i A' : 'd/m/Y'); }
    catch (Exception) { return $value; }
}
function is_logged_in(): bool
{
    if (($_SESSION['authenticated'] ?? $_SESSION['admin'] ?? false) !== true) { return false; }
    $lastActivity = (int)($_SESSION['last_activity'] ?? 0);
    if ($lastActivity === 0 || time() - $lastActivity > 1800) {
        unset($_SESSION['authenticated'], $_SESSION['admin'], $_SESSION['role'], $_SESSION['user_id'], $_SESSION['display_name'], $_SESSION['last_activity']);
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}
function is_admin(): bool { return is_logged_in() && ($_SESSION['role'] ?? 'admin') === 'admin'; }
function current_user_id(): ?int { return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null; }
function current_author_name(): string
{
    $name = $_SESSION['display_name'] ?? getenv('ADMIN_USER') ?: 'Administrador';
    return (string)$name;
}
function can_edit_post(array $post): bool { return is_admin() || (is_logged_in() && current_user_id() && (int)($post['author_id'] ?? 0) === current_user_id()); }
function can_member_edit_post(array $post): bool { $member=current_member(); return $member && (int)($post['member_author_id']??0)===(int)$member['id']; }
function unique_member_slug(string $name): string
{
    $base=slugify($name);$slug=$base;$suffix=2;
    while(true){$stmt=db()->prepare('SELECT 1 FROM members WHERE profile_slug=? LIMIT 1');$stmt->execute([$slug]);if(!$stmt->fetchColumn())return $slug;$slug=substr($base,0,175).'-'.$suffix++;}
}
function public_profile_url(array $member): ?string { return !empty($member['profile_public'])&&!empty($member['profile_slug'])?'/usuario/'.rawurlencode($member['profile_slug']):null; }
function public_author_url(array $user): ?string { return !empty($user['profile_public'])&&!empty($user['profile_slug'])?'/autor/'.rawurlencode($user['profile_slug']):null; }
function current_staff_profile(): ?array { $id=current_user_id();if(!$id)return null;$stmt=db()->prepare('SELECT * FROM users WHERE id=? AND active=1');$stmt->execute([$id]);return $stmt->fetch()?:null; }
function unique_staff_slug(string $name): string { $base=slugify($name);$slug=$base;$n=2;while(true){$stmt=db()->prepare('SELECT 1 FROM users WHERE profile_slug=?');$stmt->execute([$slug]);if(!$stmt->fetchColumn())return $slug;$slug=substr($base,0,175).'-'.$n++;} }
function current_member(): ?array
{
    static $member = false;
    if ($member !== false) { return $member ?: null; }
    $id = (int)($_SESSION['member_id'] ?? 0);
    if (!$id) { $member = null; return null; }
    $stmt = db()->prepare('SELECT * FROM members WHERE id = ? AND active = 1');
    $stmt->execute([$id]);
    $member = $stmt->fetch() ?: null;
    if (!$member) { unset($_SESSION['member_id']); }
    return $member;
}
function is_member_logged_in(): bool { return current_member() !== null; }
function is_member_verified(): bool { return !empty(current_member()['email_verified_at']); }
function require_member(bool $verified = true): array
{
    $member = current_member();
    if (!$member) { flash('Inicia sesión para continuar.', 'error'); redirect('/member-login.php'); }
    if ($verified && empty($member['email_verified_at'])) { flash('Primero verifica tu correo electrónico.', 'error'); redirect('/profile.php'); }
    return $member;
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

function site_settings(): array
{
    static $settings = null;
    if (is_array($settings)) { return $settings; }
    $settings = [
        'site_name' => 'Mi sitio',
        'site_title' => 'Mi sitio',
        'site_tagline' => '',
        'site_description' => '',
        'footer_text' => 'Mi sitio',
        'og_image' => '',
        'favicon_image' => '',
        'logo_image' => '',
        'theme_color' => '#5546e8',
        'custom_text_color' => '0',
        'text_color' => '#172033',
        'site_timezone' => 'America/Matamoros',
    ];
    try {
        foreach (db()->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll() as $row) {
            if (array_key_exists($row['setting_key'], $settings)) { $settings[$row['setting_key']] = $row['setting_value']; }
        }
    } catch (PDOException $error) {
        if (!str_contains($error->getMessage(), 'site_settings')) { throw $error; }
    }
    return $settings;
}

function site_setting(string $key): string { return site_settings()[$key] ?? ''; }

function apply_site_timezone(): void
{
    static $applied=false;if($applied)return;
    $timezone=site_setting('site_timezone')?:'America/Matamoros';
    if(!in_array($timezone,DateTimeZone::listIdentifiers(),true)){$timezone='America/Matamoros';}
    date_default_timezone_set($timezone);
    $pdo=db();$pdo->exec('SET time_zone='.$pdo->quote(date('P')));
    $applied=true;
}

function save_site_settings(array $values): void
{
    $stmt = db()->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    foreach ($values as $key => $value) { $stmt->execute([$key, $value]); }
}

apply_site_timezone();

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

function require_login(): void
{
    if (!is_logged_in()) {
        flash('Por favor inicia sesión.', 'error');
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) { http_response_code(403); exit('No tienes permisos para realizar esta acción.'); }
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

function visitor_hash(string $scope = 'visitor'): string
{
    $source = ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    return hash_hmac('sha256', $scope . '|' . $source, getenv('APP_SECRET') ?: env_required('DB_PASSWORD'));
}

function create_member_token(int $memberId, string $type, int $hours): string
{
    $raw = bin2hex(random_bytes(32));
    $pdo = db();
    $pdo->prepare('DELETE FROM member_tokens WHERE member_id = ? AND token_type = ?')->execute([$memberId, $type]);
    $expires = (new DateTimeImmutable("+{$hours} hours"))->format('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO member_tokens (member_id, token_hash, token_type, expires_at) VALUES (?, ?, ?, ?)')
        ->execute([$memberId, hash('sha256', $raw), $type, $expires]);
    return $raw;
}

function consume_member_token(string $raw, string $type): ?int
{
    if (!preg_match('/^[a-f0-9]{64}$/', $raw)) { return null; }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, member_id FROM member_tokens WHERE token_hash = ? AND token_type = ? AND used_at IS NULL AND expires_at > NOW()');
    $stmt->execute([hash('sha256', $raw), $type]); $token = $stmt->fetch();
    if (!$token) { return null; }
    $pdo->prepare('UPDATE member_tokens SET used_at = NOW() WHERE id = ?')->execute([$token['id']]);
    return (int)$token['member_id'];
}

function member_auth_allowed(string $action): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM member_auth_attempts WHERE ip_hash = ? AND action_name = ? AND attempted_at > NOW() - INTERVAL 15 MINUTE');
    $stmt->execute([visitor_hash('member-auth'), $action]);
    return (int)$stmt->fetchColumn() < 10;
}
function record_member_auth(string $action): void
{
    db()->prepare('INSERT INTO member_auth_attempts (ip_hash, action_name) VALUES (?, ?)')->execute([visitor_hash('member-auth'), $action]);
}

function upload_avatar(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return null; }
    if (($file['size'] ?? 0) > 2 * 1024 * 1024) { throw new RuntimeException('El avatar no puede superar 2 MB.'); }
    return upload_image($file);
}

function record_post_view(int $postId): void
{
    $stmt = db()->prepare('INSERT IGNORE INTO post_views (post_id, visitor_hash, viewed_on) VALUES (?, ?, CURRENT_DATE)');
    $stmt->execute([$postId, visitor_hash('post-view')]);
}

function post_stats(int $postId, ?int $memberId = null): array
{
    $stmt = db()->prepare('SELECT (SELECT COUNT(*) FROM post_views WHERE post_id=?) views,(SELECT COUNT(*) FROM post_shares WHERE post_id=?) shares,(SELECT COUNT(*) FROM comments WHERE post_id=? AND aprobado=1) comments,SUM(reaction=1) likes,SUM(reaction=-1) dislikes FROM post_reactions WHERE post_id=?');
    $stmt->execute([$postId,$postId,$postId,$postId]); $stats = $stmt->fetch() ?: [];
    $reaction = 0;
    if ($memberId) { $stmt = db()->prepare('SELECT reaction FROM post_reactions WHERE post_id = ? AND member_id = ?'); $stmt->execute([$postId, $memberId]); $reaction = (int)($stmt->fetchColumn() ?: 0); }
    return ['views'=>(int)($stats['views']??0),'shares'=>(int)($stats['shares']??0),'comments'=>(int)($stats['comments']??0),'likes'=>(int)($stats['likes']??0),'dislikes'=>(int)($stats['dislikes']??0),'reaction'=>$reaction];
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
    $menuVersion = (string) (@filemtime(__DIR__ . '/assets/menu.js') ?: '1');
    $siteName = site_setting('site_name');
    $pageTitle = $metadata['title'] ?? ($title === 'Inicio' ? site_setting('site_title') . ' · ' . $siteName : $title . ' · ' . $siteName);
    $description = $metadata['description'] ?? site_setting('site_description');
    $canonical = absolute_url($metadata['canonical'] ?? (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
    $socialImagePath = $metadata['image'] ?? site_setting('og_image');
    $socialImage = $socialImagePath !== '' ? absolute_url($socialImagePath) : '';
    $type = $metadata['type'] ?? 'website';
    $localImage = __DIR__ . '/' . ltrim((string)(parse_url($socialImagePath, PHP_URL_PATH) ?: ''), '/');
    $detectedSize = is_file($localImage) ? @getimagesize($localImage) : false;
    $imageWidth = (int)($metadata['image_width'] ?? ($detectedSize[0] ?? 1320));
    $imageHeight = (int)($metadata['image_height'] ?? ($detectedSize[1] ?? 682));
    $articleSchema = ['@context'=>'https://schema.org','@type'=>'BlogPosting','headline'=>$title,'mainEntityOfPage'=>$canonical,'author'=>['@type'=>'Person','name'=>$metadata['author']??'Administrador'],'publisher'=>['@type'=>'Organization','name'=>$siteName]];
    if ($description !== '') { $articleSchema['description'] = $description; }
    if ($socialImage !== '') { $articleSchema['image'] = [$socialImage]; }
    if (!empty($metadata['published_time'])) { $articleSchema['datePublished'] = $metadata['published_time']; }
    if (site_setting('favicon_image') !== '') { $articleSchema['publisher']['logo'] = ['@type'=>'ImageObject','url'=>absolute_url(site_setting('favicon_image'))]; }
    ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?></title>
<?php if ($description !== ''): ?><meta name="description" content="<?= e($description) ?>"><?php endif; ?>
<meta name="robots" content="<?= e($metadata['robots'] ?? 'index,follow,max-image-preview:large') ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<?php if (site_setting('favicon_image') !== ''): ?><link rel="icon" href="<?= e(site_setting('favicon_image')) ?>"><link rel="apple-touch-icon" href="<?= e(site_setting('favicon_image')) ?>"><?php endif; ?>
<meta property="og:locale" content="es_MX">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:type" content="<?= e($type) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<?php if ($description !== ''): ?><meta property="og:description" content="<?= e($description) ?>"><?php endif; ?>
<meta property="og:url" content="<?= e($canonical) ?>">
<?php if ($socialImage !== ''): ?><meta property="og:image" content="<?= e($socialImage) ?>">
<meta property="og:image:alt" content="<?= e($metadata['image_alt'] ?? $pageTitle) ?>">
<meta property="og:image:width" content="<?= $imageWidth ?>"><meta property="og:image:height" content="<?= $imageHeight ?>"><?php endif; ?>
<meta name="twitter:card" content="<?= $socialImage !== '' ? 'summary_large_image' : 'summary' ?>">
<meta name="twitter:title" content="<?= e($pageTitle) ?>">
<?php if ($description !== ''): ?><meta name="twitter:description" content="<?= e($description) ?>"><?php endif; ?>
<?php if ($socialImage !== ''): ?><meta name="twitter:image" content="<?= e($socialImage) ?>"><?php endif; ?>
<?php if (!empty($metadata['published_time'])): ?><meta property="article:published_time" content="<?= e($metadata['published_time']) ?>"><?php endif; ?>
<?php if ($type === 'article'): ?><script type="application/ld+json"><?= json_encode($articleSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script><?php endif; ?>
<?php $customText=site_setting('custom_text_color')==='1'&&preg_match('/^#[0-9a-f]{6}$/i',site_setting('text_color'));$globalText=$customText?site_setting('text_color'):'#172033'; ?><link rel="stylesheet" href="/assets/style.css?v=<?= e($styleVersion) ?>"><style>:root{--primary:<?= e(preg_match('/^#[0-9a-f]{6}$/i',site_setting('theme_color'))?site_setting('theme_color'):'#5546e8') ?>;--text:<?=e($globalText)?>;<?php if($customText):?>--muted:<?=e($globalText)?>;<?php endif;?>}</style></head><body>
<nav><a class="brand" href="index.php"><?php if (site_setting('logo_image')): ?><img class="brand-logo" src="<?= e(site_setting('logo_image')) ?>" alt="<?= e(site_setting('site_name')) ?>"><?php else: ?><?= e(site_setting('site_name')) ?><?php endif; ?></a>
<button class="menu-toggle" type="button" aria-label="Abrir menú" aria-controls="site-menu" aria-expanded="false"><span></span><span></span><span></span></button>
<div class="menu-overlay" data-menu-close></div><div class="nav-menu" id="site-menu"><div class="menu-header"><strong>Menú</strong><button type="button" class="menu-close" data-menu-close aria-label="Cerrar menú">×</button></div>
<a href="/index.php">Inicio</a><a href="/community.php">Comunidad</a><?php if (is_logged_in()): ?><a href="/admin.php">Escribir</a><?php if (is_admin()): ?><details class="nav-dropdown"><summary>Administrar</summary><div><a href="/community-moderation.php">Publicaciones</a><a href="/comments.php">Comentarios</a><a href="/users.php">Usuarios</a><a href="/members.php">Lectores</a><a href="/security.php">Seguridad</a><a href="/settings.php">Configuración</a></div></details><?php endif; ?><a href="/account.php">Mi cuenta</a><form class="inline logout-form" method="post" action="/logout.php"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><button class="link danger">Salir del panel</button></form><?php elseif (is_member_logged_in()): ?><a href="/community-write.php">Publicar</a><a href="/member-posts.php">Mis publicaciones</a><a href="/my-comments.php">Respuestas</a><a class="profile-link" href="/profile.php"><?php if(current_member()['avatar']):?><img class="nav-avatar" src="/uploads/<?=e(current_member()['avatar'])?>" alt=""><?php endif;?>Mi perfil</a><form class="inline logout-form" method="post" action="/member-logout.php"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><button class="link danger">Salir</button></form><?php else: ?><a href="/member-login.php">Acceder</a><a href="/register.php">Registrarse</a><?php endif; ?></div>
</nav><script src="/assets/menu.js?v=<?= e($menuVersion) ?>" defer></script>
<main><?php foreach ($messages as [$type, $message]): ?><div class="flash <?= e($type) ?>"><?= e($message) ?></div><?php endforeach; ?>
<?php
}

function render_footer(): void { ?></main><footer>&copy; <?= date('Y') ?> <?= e(site_setting('footer_text')) ?></footer></body></html><?php }
