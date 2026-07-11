# Blog Moderno — PHP y MySQL

Blog con publicaciones, imágenes y comentarios sujetos a aprobación administrativa.
El panel incluye un editor visual seguro para aplicar títulos, estilos, listas, citas y enlaces al contenido.
Cada artículo ofrece el menú nativo para compartir en celulares y copia el enlace como alternativa en escritorio.

## Requisitos

- PHP 8.1 o posterior con extensiones `pdo_mysql`, `fileinfo`, `mbstring` y `dom`.
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
ADMIN_PASSWORD=tu-contraseña-normal
APP_SECRET=una-clave-aleatoria-larga
SESSION_COOKIE_SECURE=true
```

También puedes guardarlas en un archivo `.env` ubicado junto a `config.php`. El archivo está excluido de Git y no debe subirse al repositorio. En Linux aplica permisos restrictivos con `chmod 600 .env`.

La aplicación convierte `ADMIN_PASSWORD` en un hash antes de verificar el inicio de sesión. Para mayor seguridad también puedes omitir `ADMIN_PASSWORD` y configurar directamente `ADMIN_PASSWORD_HASH`. Genera el hash con:

```bash
php -r "echo password_hash('cambia-esta-clave', PASSWORD_DEFAULT), PHP_EOL;"
```

En una instalación existente ejecuta también `migrations/002_login_security.sql`. Esta migración activa el registro y bloqueo progresivo de intentos. Las sesiones administrativas expiran después de 30 minutos de inactividad.
Después ejecuta `migrations/003_pretty_urls.sql` para activar URLs como `/polygon-blockchain`. Apache debe permitir reglas `.htaccess` (`AllowOverride FileInfo` o `AllowOverride All`).

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
