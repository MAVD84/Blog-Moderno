# Blog Moderno — PHP y MySQL

Blog con publicaciones, imágenes y comentarios sujetos a aprobación administrativa.

## Requisitos

- PHP 8.1 o posterior con extensiones `pdo_mysql`, `fileinfo` y `mbstring`.
- MySQL 8 o MariaDB 10.5 o posterior.
- Un servidor Apache o Nginx con acceso de escritura al directorio `uploads/`.

## Instalación

1. Crea una base MySQL y ejecuta `schema.sql`.
2. Configura las variables del servidor:

```text
DB_HOST=localhost
DB_PORT=3306
DB_NAME=blog
DB_USER=blog_user
DB_PASSWORD=una-clave-segura
ADMIN_USER=admin
ADMIN_PASSWORD_HASH=<hash de la contraseña>
SESSION_COOKIE_SECURE=true
```

Genera el hash administrativo con:

```bash
php -r "echo password_hash('cambia-esta-clave', PASSWORD_DEFAULT), PHP_EOL;"
```

3. Concede permiso de escritura al usuario del servidor web sobre `uploads/`.
4. Configura el document root apuntando a este directorio.

En Nginx, bloquea expresamente la ejecución de archivos PHP dentro de `/uploads`. Apache aplica automáticamente la regla incluida en `uploads/.htaccess` cuando `AllowOverride` está habilitado.

Para desarrollo local:

```bash
php -S localhost:8000
```

## Moderación

Los visitantes envían comentarios desde cada artículo. El correo es privado y el comentario permanece oculto hasta que el administrador lo aprueba en `comments.php`.

## Despliegue

Usa un hosting compatible con PHP y MySQL, como cPanel, Hostinger, Railway, Render mediante contenedor o un VPS. Vercel no ejecuta esta aplicación PHP de forma nativa.
