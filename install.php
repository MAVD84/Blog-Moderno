<?php
declare(strict_types=1);

if (is_file(__DIR__ . '/.installed') || is_file(__DIR__ . '/.env')) {
    http_response_code(403);
    exit('El sitio ya está configurado. El instalador está bloqueado.');
}

session_start();
if (empty($_SESSION['install_csrf'])) { $_SESSION['install_csrf'] = bin2hex(random_bytes(32)); }
$requirements = [
    'PHP 8.1+' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'Fileinfo' => extension_loaded('fileinfo'),
    'Mbstring' => extension_loaded('mbstring'),
    'DOM' => extension_loaded('dom'),
    'Iconv' => extension_loaded('iconv'),
    'OpenSSL' => extension_loaded('openssl'),
];
$error = '';
$success = false;

function installer_e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function env_value(string $value): string { return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'"; }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!hash_equals($_SESSION['install_csrf'], (string)($_POST['csrf_token'] ?? ''))) { throw new RuntimeException('La sesión expiró. Recarga la página.'); }
        if (in_array(false, $requirements, true)) { throw new RuntimeException('El servidor no cumple todos los requisitos de PHP.'); }

        $fields = [];
        foreach (['db_host', 'db_port', 'db_name', 'db_user', 'db_password', 'admin_user', 'admin_password', 'admin_confirm', 'site_url', 'site_name', 'site_title', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password'] as $field) {
            $fields[$field] = trim((string)($_POST[$field] ?? ''));
            if ($fields[$field] === '' || str_contains($fields[$field], "\n") || str_contains($fields[$field], "\r")) { throw new RuntimeException('Completa todos los campos correctamente.'); }
        }
        if ($fields['admin_password'] !== $fields['admin_confirm']) { throw new RuntimeException('Las contraseñas administrativas no coinciden.'); }
        if (strlen($fields['admin_password']) < 12) { throw new RuntimeException('La contraseña administrativa debe tener al menos 12 caracteres.'); }
        if (!filter_var($fields['site_url'], FILTER_VALIDATE_URL) || !str_starts_with($fields['site_url'], 'https://')) { throw new RuntimeException('La URL del sitio debe ser HTTPS y válida.'); }
        if (!ctype_digit($fields['db_port'])) { throw new RuntimeException('El puerto MySQL no es válido.'); }
        if (!ctype_digit($fields['smtp_port']) || !filter_var($fields['smtp_username'], FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Revisa el puerto y el correo SMTP.'); }

        $dsn = "mysql:host={$fields['db_host']};port={$fields['db_port']};dbname={$fields['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $fields['db_user'], $fields['db_password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $schema = file_get_contents(__DIR__ . '/schema.sql');
        if ($schema === false) { throw new RuntimeException('No se pudo leer schema.sql.'); }
        foreach (preg_split('/;\s*(?:\r?\n|$)/', $schema) ?: [] as $statement) {
            if (trim($statement) !== '') { $pdo->exec($statement); }
        }

        $settings = [
            'site_name' => $fields['site_name'], 'site_title' => $fields['site_title'],
            'site_tagline' => 'Documentando el camino',
            'site_description' => 'Artículos, análisis y aprendizaje sobre Polygon, Ethereum y tecnología blockchain.',
            'footer_text' => $fields['site_name'], 'og_image' => '/assets/og-image.png',
            'favicon_image' => '/assets/favicon.png', 'logo_image' => '',
        ];
        $stmt = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($settings as $key => $value) { $stmt->execute([$key, $value]); }

        $adminHash = password_hash($fields['admin_password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, display_name, password_hash, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([$fields['admin_user'], $fields['admin_user'], $adminHash]);

        $env = [
            'DB_HOST' => $fields['db_host'], 'DB_PORT' => $fields['db_port'], 'DB_NAME' => $fields['db_name'],
            'DB_USER' => $fields['db_user'], 'DB_PASSWORD' => $fields['db_password'],
            'ADMIN_USER' => $fields['admin_user'], 'ADMIN_PASSWORD_HASH' => $adminHash,
            'APP_SECRET' => bin2hex(random_bytes(32)), 'SITE_URL' => rtrim($fields['site_url'], '/'),
            'SESSION_COOKIE_SECURE' => 'true',
            'SMTP_HOST' => $fields['smtp_host'], 'SMTP_PORT' => $fields['smtp_port'],
            'SMTP_ENCRYPTION' => 'ssl', 'SMTP_USERNAME' => $fields['smtp_username'],
            'SMTP_PASSWORD' => $fields['smtp_password'], 'SMTP_FROM_EMAIL' => $fields['smtp_username'],
            'SMTP_FROM_NAME' => $fields['site_name'],
        ];
        $envContents = "# Generado automáticamente por install.php\n";
        foreach ($env as $key => $value) { $envContents .= $key . '=' . env_value((string)$value) . "\n"; }
        if (file_put_contents(__DIR__ . '/.env', $envContents, LOCK_EX) === false) { throw new RuntimeException('No se pudo crear .env. Revisa los permisos del directorio.'); }
        @chmod(__DIR__ . '/.env', 0600);
        if (!is_dir(__DIR__ . '/uploads')) { mkdir(__DIR__ . '/uploads', 0755, true); }
        file_put_contents(__DIR__ . '/.installed', date(DATE_ATOM), LOCK_EX);
        @chmod(__DIR__ . '/.installed', 0600);
        unset($_SESSION['install_csrf']);
        $success = true;
    } catch (Throwable $exception) {
        $error = $exception instanceof PDOException ? 'No se pudo conectar o preparar MySQL. Verifica las credenciales y permisos.' : $exception->getMessage();
    }
}

$defaultUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'tudominio.com');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Instalar Blog Moderno</title><link rel="icon" href="/assets/favicon.png"><link rel="stylesheet" href="/assets/style.css"></head><body class="installer-body"><main class="installer-wrap">
<div class="panel installer-card"><div class="installer-heading"><img src="/assets/favicon.png" alt="" width="64" height="64"><div><h1>Instalar Blog Moderno</h1><p class="muted">Configuración guiada de PHP y MySQL</p></div></div>
<?php if ($success): ?><div class="install-success"><h2>Instalación completada</h2><p>La base de datos, el administrador y el sitio están configurados. El instalador ha quedado bloqueado.</p><a class="button" href="/login.php">Abrir acceso administrativo</a></div>
<?php else: ?>
<?php if ($error): ?><div class="flash error"><?= installer_e($error) ?></div><?php endif; ?>
<div class="requirements"><?php foreach ($requirements as $name => $valid): ?><span class="requirement <?= $valid ? 'ok' : 'bad' ?>"><?= $valid ? '✓' : '×' ?> <?= installer_e($name) ?></span><?php endforeach; ?></div>
<form method="post"><input type="hidden" name="csrf_token" value="<?= installer_e($_SESSION['install_csrf']) ?>">
<fieldset><legend>Base de datos MySQL</legend><div class="settings-grid"><label>Servidor<input name="db_host" value="<?= installer_e($_POST['db_host'] ?? 'localhost') ?>" required></label><label>Puerto<input name="db_port" inputmode="numeric" value="<?= installer_e($_POST['db_port'] ?? '3306') ?>" required></label><label>Base de datos<input name="db_name" value="<?= installer_e($_POST['db_name'] ?? '') ?>" required></label><label>Usuario MySQL<input name="db_user" value="<?= installer_e($_POST['db_user'] ?? '') ?>" required></label></div><label>Contraseña MySQL<input type="password" name="db_password" required></label></fieldset>
<fieldset><legend>Administrador</legend><div class="settings-grid"><label>Usuario<input name="admin_user" value="<?= installer_e($_POST['admin_user'] ?? 'admin') ?>" required></label><span></span><label>Contraseña<input type="password" name="admin_password" minlength="12" required></label><label>Confirmar contraseña<input type="password" name="admin_confirm" minlength="12" required></label></div></fieldset>
<fieldset><legend>Identidad del sitio</legend><label>URL HTTPS<input type="url" name="site_url" value="<?= installer_e($_POST['site_url'] ?? $defaultUrl) ?>" required></label><div class="settings-grid"><label>Nombre del sitio<input name="site_name" maxlength="50" value="<?= installer_e($_POST['site_name'] ?? 'Blog.') ?>" required></label><label>Título principal<input name="site_title" maxlength="120" value="<?= installer_e($_POST['site_title'] ?? 'Polygon Blockchain') ?>" required></label></div></fieldset>
<fieldset><legend>Correo de verificación</legend><p class="muted">Datos del buzón que enviará verificaciones y recuperaciones.</p><div class="settings-grid"><label>Servidor SMTP<input name="smtp_host" value="<?= installer_e($_POST['smtp_host'] ?? 'smtp.jrz.wtf') ?>" required></label><label>Puerto SSL<input name="smtp_port" inputmode="numeric" value="<?= installer_e($_POST['smtp_port'] ?? '465') ?>" required></label><label>Usuario SMTP<input type="email" name="smtp_username" value="<?= installer_e($_POST['smtp_username'] ?? 'no-reply@jrz.wtf') ?>" required></label><label>Contraseña del correo<input type="password" name="smtp_password" required></label></div></fieldset>
<button class="button installer-submit" type="submit" <?= in_array(false, $requirements, true) ? 'disabled' : '' ?>>Instalar sitio</button></form>
<?php endif; ?></div></main></body></html>
