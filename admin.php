<?php
require_once __DIR__ . '/functions.php'; require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $titulo = trim($_POST['titulo'] ?? ''); $contenido = trim($_POST['contenido'] ?? '');
    if ($titulo === '' || $contenido === '') { flash('Título y contenido son obligatorios.', 'error'); }
    else { try { $imagen = upload_image($_FILES['imagen'] ?? []); $stmt = db()->prepare('INSERT INTO posts (titulo, contenido, imagen) VALUES (?, ?, ?)'); $stmt->execute([$titulo, $contenido, $imagen]); flash('Artículo publicado.'); redirect('index.php'); } catch (RuntimeException $e) { flash($e->getMessage(), 'error'); } }
}
render_header('Nueva publicación');
?><div class="panel"><h1>Nueva publicación</h1><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><label>Título<input name="titulo" maxlength="200" required></label><label>Imagen de portada<input type="file" name="imagen" accept="image/png,image/jpeg,image/gif,image/webp"></label><label>Contenido<textarea name="contenido" rows="14" required></textarea></label><button class="button">Publicar</button></form></div><?php render_footer(); ?>
