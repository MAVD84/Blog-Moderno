<?php
require_once __DIR__ . '/functions.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$stmt = db()->prepare('SELECT * FROM posts WHERE id = ?'); $stmt->execute([$id]); $post = $stmt->fetch();
if (!$post) { http_response_code(404); exit('Artículo no encontrado.'); }
$stmt = db()->prepare('SELECT nombre, contenido, fecha FROM comments WHERE post_id = ? AND aprobado = 1 ORDER BY fecha');
$stmt->execute([$id]); $comments = $stmt->fetchAll();
render_header($post['titulo']);
?>
<article class="article"><?php if ($post['imagen']): ?><img class="cover" src="uploads/<?= e($post['imagen']) ?>" alt="<?= e($post['titulo']) ?>"><?php endif; ?>
<div class="article-body"><h1><?= e($post['titulo']) ?></h1><p class="muted">Publicado el <?= e(substr($post['fecha'], 0, 16)) ?></p>
<?php if (is_admin()): ?><div class="actions"><a class="button warn" href="edit.php?id=<?= (int)$id ?>">Editar</a><form method="post" action="delete.php" onsubmit="return confirm('¿Eliminar este artículo?')"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= (int)$id ?>"><button class="button danger-bg">Eliminar</button></form></div><?php endif; ?>
<div class="content"><?= nl2br(e($post['contenido'])) ?></div></div></article>
<section class="columns"><div class="panel"><h2>Comentarios</h2><?php foreach ($comments as $comment): ?><article class="comment"><strong><?= e($comment['nombre']) ?></strong><small><?= e(substr($comment['fecha'], 0, 10)) ?></small><p><?= nl2br(e($comment['contenido'])) ?></p></article><?php endforeach; ?><?php if (!$comments): ?><p class="muted">Todavía no hay comentarios aprobados.</p><?php endif; ?></div>
<div class="panel"><h2>Deja un comentario</h2><p class="muted">Tu correo será privado. El comentario aparecerá después de ser aprobado.</p>
<form method="post" action="comment-submit.php"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="post_id" value="<?= (int)$id ?>"><input class="honeypot" name="website" tabindex="-1" autocomplete="off">
<label>Nombre<input name="nombre" maxlength="80" required></label><label>Correo electrónico<input type="email" name="email" maxlength="254" required></label><label>Comentario<textarea name="contenido" maxlength="2000" rows="5" required></textarea></label><button class="button">Enviar para aprobación</button></form></div></section>
<?php render_footer(); ?>
