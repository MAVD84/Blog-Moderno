<?php
require_once __DIR__ . '/functions.php'; require_admin();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT); $stmt = db()->prepare('SELECT * FROM posts WHERE id = ?'); $stmt->execute([$id]); $post = $stmt->fetch();
if (!$post) { http_response_code(404); exit('Artículo no encontrado.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $titulo = trim($_POST['titulo'] ?? ''); $contenido = trim($_POST['contenido'] ?? '');
    if ($titulo === '' || $contenido === '') { flash('Título y contenido son obligatorios.', 'error'); }
    else { try { $contenido = sanitize_html($contenido); if (trim(strip_tags($contenido)) === '') throw new RuntimeException('El contenido no puede estar vacío.'); $new = upload_image($_FILES['imagen'] ?? []); $imagen = $new ?: $post['imagen']; $stmt = db()->prepare('UPDATE posts SET titulo = ?, contenido = ?, imagen = ? WHERE id = ?'); $stmt->execute([$titulo, $contenido, $imagen, $id]); if ($new) delete_image($post['imagen']); flash('Artículo actualizado.'); redirect("post.php?id={$id}"); } catch (RuntimeException $e) { flash($e->getMessage(), 'error'); } }
}
render_header('Editar publicación', ['robots' => 'noindex,nofollow']);
?><div class="panel"><h1>Editar publicación</h1><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><label>Título<input name="titulo" maxlength="200" value="<?= e($post['titulo']) ?>" required></label><label>Cambiar imagen<input type="file" name="imagen" accept="image/png,image/jpeg,image/gif,image/webp"></label><?php render_editor($_POST['contenido'] ?? $post['contenido']); ?><button class="button warn">Guardar cambios</button></form></div><?php render_footer(); ?>
