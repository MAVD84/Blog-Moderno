# Blog Moderno — PHP y MySQL

Blog con publicaciones, imágenes y comentarios sujetos a aprobación administrativa.
El panel incluye un editor visual seguro para aplicar títulos, estilos, listas, citas y enlaces al contenido.
Cada artículo ofrece el menú nativo para compartir en celulares y copia el enlace como alternativa en escritorio.

## Requisitos

- PHP 8.1 o posterior con extensiones `pdo_mysql`, `fileinfo`, `mbstring` y `dom`.
- MySQL 8 o MariaDB 10.5 o posterior.
- Un servidor Apache o Nginx con acceso de escritura al directorio `uploads/`.

## Instalación

En una instalación nueva abre `/install.php` y sigue el asistente. Este comprueba PHP, crea las tablas, genera `.env`, guarda la contraseña administrativa como hash y se bloquea al terminar.

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
SITE_URL=https://jrz.wtf
SESSION_COOKIE_SECURE=true
```

También puedes guardarlas en un archivo `.env` ubicado junto a `config.php`. El archivo está excluido de Git y no debe subirse al repositorio. En Linux aplica permisos restrictivos con `chmod 600 .env`.

La aplicación convierte `ADMIN_PASSWORD` en un hash antes de verificar el inicio de sesión. Para mayor seguridad también puedes omitir `ADMIN_PASSWORD` y configurar directamente `ADMIN_PASSWORD_HASH`. Genera el hash con:

```bash
php -r "echo password_hash('cambia-esta-clave', PASSWORD_DEFAULT), PHP_EOL;"
```

En una instalación existente ejecuta también `migrations/002_login_security.sql`. Esta migración activa el registro y bloqueo progresivo de intentos. Las sesiones administrativas expiran después de 30 minutos de inactividad.
Después ejecuta `migrations/003_pretty_urls.sql` para activar URLs como `/polygon-blockchain`. Apache debe permitir reglas `.htaccess` (`AllowOverride FileInfo` o `AllowOverride All`).
Ejecuta `migrations/004_site_settings.sql` para habilitar la personalización de nombre, textos, metadata, favicon y logo desde `settings.php`.
Ejecuta `migrations/005_users_and_authors.sql` para habilitar administradores, editores y autores de publicaciones.

3. Concede permiso de escritura al usuario del servidor web sobre `uploads/`.
4. Configura el document root apuntando a este directorio.

En Nginx, bloquea expresamente la ejecución de archivos PHP dentro de `/uploads`. Apache aplica automáticamente la regla incluida en `uploads/.htaccess` cuando `AllowOverride` está habilitado.

Para desarrollo local:

```bash
php -S localhost:8000
```

## Moderación

Los visitantes envían comentarios desde cada artículo. El correo es privado y el comentario permanece oculto hasta que el administrador lo aprueba en `comments.php`.

## Lectores, correo y reacciones

Ejecuta `migrations/006_members_reactions.sql` en phpMyAdmin. Los lectores se registran, verifican su correo y entonces pueden comentar, dar like o dislike y subir un avatar. Las vistas cuentan una vez por visitante y día. Los comentarios siguen sujetos a aprobación.

Para habilitar respuestas en hilos en una instalación existente, ejecuta también `migrations/007_comment_replies.sql`. Los lectores verificados y el personal del blog pueden responder; cada respuesta permanece pendiente hasta ser aprobada.

Ejecuta `migrations/008_comment_reactions.sql` para habilitar likes y dislikes en comentarios. Cada lector puede moderar desde `my-comments.php` las respuestas directas recibidas en sus propios comentarios; la administración mantiene control global.

Ejecuta `migrations/009_community_posts.sql` para habilitar la comunidad. Los lectores verificados pueden crear y editar temas con portada; estos quedan pendientes hasta que un administrador los aprueba desde `community-moderation.php`.

Las instalaciones nuevas comienzan sin logo, favicon, imagen social, descripción, eslogan ni contenido SEO de ejemplo. Configura la identidad y metadata después desde el panel administrativo.

El instalador solicita usuario, correo y contraseña del administrador. En instalaciones existentes, el correo administrativo se puede completar desde el perfil de autor.

Ejecuta `migrations/015_email_reverification.sql` para exigir una nueva verificación cada vez que lectores, administradores o editores cambien su correo electrónico.

El color principal global se puede cambiar desde Configuración. El selector actualiza botones, enlaces, estados activos y detalles del tema sin modificar CSS manualmente.

El color del texto también es configurable mediante un interruptor independiente. Al desactivarlo, el sitio restaura los colores tipográficos originales.

Ejecuta `migrations/010_public_profiles.sql` para habilitar perfiles públicos opcionales y seguidores. Cada lector decide la privacidad desde su perfil; solo los perfiles públicos exponen su fecha de registro y publicaciones recientes.

Ejecuta `migrations/011_staff_profiles.sql` para dar perfiles sociales a administradores y editores. El perfil de autor mantiene separados los permisos del panel e incorpora avatar, biografía, privacidad, publicaciones y seguidores.

Ejecuta `migrations/012_post_shares.sql` para mostrar un contador de veces compartido junto a las reacciones. Se registra como máximo una compartida por visitante y día.

La zona horaria se selecciona desde Configuración y se aplica tanto a PHP como a la sesión MySQL. El valor inicial es `America/Matamoros`.

Ejecuta `migrations/013_staff_reactions.sql` para que administradores y editores puedan reaccionar con su cuenta del panel, sin crear una cuenta adicional de lector.

Ejecuta `migrations/014_image_credits.sql` para agregar autor y enlace de atribución opcionales debajo de las imágenes de portada.

Configura en `.env`: `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_FROM_EMAIL` y `SMTP_FROM_NAME`. Para HostVerge: servidor `smtp.jrz.wtf`, puerto `465`, cifrado `ssl` y usuario `no-reply@jrz.wtf`. Nunca subas la contraseña del buzón a Git.

## Despliegue

Usa un hosting compatible con PHP y MySQL, como cPanel, Hostinger, Railway, Render mediante contenedor o un VPS. Vercel no ejecuta esta aplicación PHP de forma nativa.
