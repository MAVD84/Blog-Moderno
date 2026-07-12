<?php
declare(strict_types=1);

function load_env_file(string $path): void
{
    if (!is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $name) || getenv($name) !== false) {
            continue;
        }
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
                $value = $first === '"' ? stripcslashes($value) : str_replace(["\\'", "\\\\"], ["'", "\\"], $value);
            }
        }
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

load_env_file(__DIR__ . '/.env');

if (getenv('DB_HOST') === false && PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
    header('Location: /install.php');
    exit;
}

$secure = filter_var(getenv('SESSION_COOKIE_SECURE') ?: 'false', FILTER_VALIDATE_BOOL);
function request_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

if ($secure && !request_is_https() && PHP_SAPI !== 'cli') {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host !== '' && preg_match('/^[a-z0-9.-]+(?::\d+)?$/i', $host)) {
        header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
$sessionLifetime = max(1800, min(86400, (int) (getenv('SESSION_LIFETIME') ?: 7200)));
ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
session_name($secure ? '__Host-blog_session' : 'blog_session');
session_set_cookie_params([
    'httponly' => true,
    'secure' => $secure,
    'samesite' => 'Strict',
    'path' => '/',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('Cache-Control: no-store');
if (request_is_https()) { header('Strict-Transport-Security: max-age=31536000; includeSubDomains'); }

function env_required(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        throw new RuntimeException("Falta la variable de entorno {$name}.");
    }
    return $value;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env_required('DB_HOST');
    $port = getenv('DB_PORT') ?: '3306';
    $name = env_required('DB_NAME');
    $user = env_required('DB_USER');
    $password = env_required('DB_PASSWORD');
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
