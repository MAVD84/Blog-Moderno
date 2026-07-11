<?php
require_once __DIR__ . '/functions.php'; require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $titulo = trim($_POST['titulo'] ?? ''); $contenido = trim($_POST['contenido'] ?? '');
    if ($titulo === '' || $contenido === '') { flash('Título y contenido son obligatorios.', 'error'); }
    else { try { $contenido = sanitize_html($contenido); if (trim(strip_tags($contenido)) === '') throw new RuntimeException('El contenido no puede estar vacío.'); $imagen = upload_image($_FILES['imagen'] ?? []); $slug = unique_post_slug($titulo); $stmt = db()->prepare('INSERT INTO posts (titulo, slug, contenido, imagen) VALUES (?, ?, ?, ?)'); $stmt->execute([$titulo, $slug, $contenido, $imagen]); flash('Artículo publicado.'); redirect(post_url(['slug' => $slug])); } catch (RuntimeException $e) { flash($e->getMessage(), 'error'); } }
}
render_header('Nueva publicación');
?><div class="panel"><h1>Nueva publicación</h1><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><label>Título<input name="titulo" maxlength="200" required></label><label>Imagen de portada<input type="file" name="imagen" accept="image/png,image/jpeg,image/gif,image/webp"></label><?php render_editor($_POST['contenido'] ?? ''); ?><button class="button">Publicar</button></form></div><?php render_footer(); ?>
