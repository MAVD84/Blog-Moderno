<?php
require_once __DIR__ . '/functions.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug !== '') { $stmt = db()->prepare('SELECT * FROM posts WHERE slug = ?'); $stmt->execute([$slug]); }
else { $stmt = db()->prepare('SELECT * FROM posts WHERE id = ?'); $stmt->execute([$id]); }
$post = $stmt->fetch();
if (!$post) { http_response_code(404); exit('Artículo no encontrado.'); }
$post = ensure_post_slug($post);
if ($slug === '') { header('Location: ' . post_url($post), true, 301); exit; }
$id = (int)$post['id'];
record_post_view($id);
$member = current_member();
$stats = post_stats($id, $member ? (int)$member['id'] : null);
$imageSize = $post['imagen'] ? @getimagesize(UPLOAD_DIR . '/' . $post['imagen']) : false;
$stmt = db()->prepare('SELECT c.nombre, c.contenido, c.fecha, m.avatar FROM comments c LEFT JOIN members m ON m.id=c.member_id WHERE c.post_id = ? AND c.aprobado = 1 ORDER BY c.fecha');
$stmt->execute([$id]); $comments = $stmt->fetchAll();
$plainContent = trim(preg_replace('/\s+/', ' ', strip_tags($post['contenido'])) ?? '');
$description = mb_strimwidth($plainContent, 0, 200, '…');
$socialImage = $post['imagen'] ? '/uploads/' . rawurlencode($post['imagen']) : '/assets/og-image.png';
render_header($post['titulo'], [
    'description' => $description,
    'canonical' => post_url($post),
    'image' => $socialImage,
    'image_alt' => $post['titulo'],
    'image_width' => $imageSize ? $imageSize[0] : 1320,
    'image_height' => $imageSize ? $imageSize[1] : 682,
    'type' => 'article',
    'published_time' => date(DATE_ATOM, strtotime($post['fecha'])),
    'author' => $post['author_name'] ?: 'Administrador',
]);
$shareVersion = (string) (@filemtime(__DIR__ . '/assets/share.js') ?: '1');
?>
<article class="article"><?php if ($post['imagen']): ?><div class="cover-wrap"><img class="cover" src="uploads/<?= e($post['imagen']) ?>" alt="<?= e($post['titulo']) ?>" loading="eager" decoding="async"<?= $imageSize ? ' width="' . (int)$imageSize[0] . '" height="' . (int)$imageSize[1] . '"' : '' ?> style="max-width:100%;height:auto;max-height:72vh;object-fit:contain"></div><?php endif; ?>
<div class="article-body"><h1><?= e($post['titulo']) ?></h1><p class="muted">Publicado el <?= e(format_date($post['fecha'], true)) ?> · Por <?= e($post['author_name'] ?: 'Administrador') ?></p>
<div class="post-stats"><span>👁 <?= $stats['views'] ?> vistas</span><form method="post" action="/reaction.php"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="post_id" value="<?= $id ?>"><button name="reaction" value="1" class="reaction <?= $stats['reaction']===1?'active':'' ?>">👍 <?= $stats['likes'] ?></button><button name="reaction" value="-1" class="reaction <?= $stats['reaction']===-1?'active':'' ?>">👎 <?= $stats['dislikes'] ?></button></form></div>
<?php if (can_edit_post($post)): ?><div class="actions"><a class="button warn" href="edit.php?id=<?= (int)$id ?>">Editar</a><form method="post" action="delete.php" onsubmit="return confirm('¿Eliminar este artículo?')"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= (int)$id ?>"><button class="button danger-bg">Eliminar</button></form></div><?php endif; ?>
<div class="content rich-content"><?= sanitize_html($post['contenido']) ?></div>
<div class="post-tools post-tools-end"><button type="button" class="button share-button" data-share data-title="<?= e($post['titulo']) ?>" data-text="Mira este artículo: <?= e($post['titulo']) ?>">Compartir</button><span class="share-status" role="status" aria-live="polite"></span></div></div></article>
<section class="columns"><div class="panel"><h2>Comentarios</h2><?php foreach ($comments as $comment): ?><article class="comment"><div class="comment-author"><?php if($comment['avatar']):?><img class="avatar" src="/uploads/<?=e($comment['avatar'])?>" alt=""><?php endif;?><strong><?= e($comment['nombre']) ?></strong><small><?= e(format_date($comment['fecha'],true)) ?></small></div><p><?= nl2br(e($comment['contenido'])) ?></p></article><?php endforeach; ?><?php if (!$comments): ?><p class="muted">Todavía no hay comentarios aprobados.</p><?php endif; ?></div>
<div class="panel"><h2>Deja un comentario</h2><?php if($member && is_member_verified()):?><p class="muted">Publicas como <strong><?=e($member['display_name'])?></strong>. Aparecerá después de ser aprobado.</p><form method="post" action="/comment-submit.php"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="post_id" value="<?= $id ?>"><label>Comentario<textarea name="contenido" maxlength="3000" rows="6" required></textarea></label><button class="button">Enviar para aprobación</button></form><?php elseif($member):?><p>Verifica tu correo desde <a class="text-link" href="/profile.php">tu perfil</a> para comentar y reaccionar.</p><?php else:?><p>Necesitas una cuenta con correo verificado.</p><div class="actions"><a class="button" href="/member-login.php">Acceder</a><a class="button secondary" href="/register.php">Registrarme</a></div><?php endif;?></div></section>
<script src="/assets/share.js?v=<?= e($shareVersion) ?>" defer></script>
<?php render_footer(); ?>
