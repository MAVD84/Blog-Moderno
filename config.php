<?php
declare(strict_types=1);

$secure = filter_var(getenv('SESSION_COOKIE_SECURE') ?: 'false', FILTER_VALIDATE_BOOL);
session_set_cookie_params([
    'httponly' => true,
    'secure' => $secure,
    'samesite' => 'Lax',
]);
session_start();

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
