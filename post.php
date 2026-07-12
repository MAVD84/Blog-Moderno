<?php
require_once __DIR__ . '/functions.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug !== '') { $stmt = db()->prepare('SELECT p.*,COALESCE(m.avatar,u.avatar) author_avatar,COALESCE(m.profile_slug,u.profile_slug) author_profile_slug,COALESCE(m.profile_public,u.profile_public) author_profile_public FROM posts p LEFT JOIN members m ON m.id=p.member_author_id LEFT JOIN users u ON u.id=p.author_id WHERE p.slug=?'); $stmt->execute([$slug]); }
else { $stmt = db()->prepare('SELECT p.*,COALESCE(m.avatar,u.avatar) author_avatar,COALESCE(m.profile_slug,u.profile_slug) author_profile_slug,COALESCE(m.profile_public,u.profile_public) author_profile_public FROM posts p LEFT JOIN members m ON m.id=p.member_author_id LEFT JOIN users u ON u.id=p.author_id WHERE p.id=?'); $stmt->execute([$id]); }
$post = $stmt->fetch();
if (!$post) { http_response_code(404); exit('Artículo no encontrado.'); }
if (($post['status'] ?? 'published') !== 'published' && !is_logged_in() && !can_member_edit_post($post)) { http_response_code(404); exit('Artículo no encontrado.'); }
$post = ensure_post_slug($post);
if ($slug === '') { header('Location: ' . post_url($post), true, 301); exit; }
$id = (int)$post['id'];
record_post_view($id);
$member = current_member();
$stats = post_stats($id, $member ? (int)$member['id'] : null);
$imageSize = $post['imagen'] ? @getimagesize(UPLOAD_DIR . '/' . $post['imagen']) : false;
$stmt = db()->prepare('SELECT c.id,c.parent_id,c.nombre,c.contenido,c.fecha,c.member_id,c.staff_author_id,COALESCE(m.avatar,u.avatar) avatar,COALESCE(m.profile_slug,u.profile_slug) profile_slug,COALESCE(m.profile_public,u.profile_public) profile_public,COALESCE(SUM(cr.reaction=1),0) likes,COALESCE(SUM(cr.reaction=-1),0) dislikes,COALESCE(MAX(CASE WHEN cr.member_id=? THEN cr.reaction ELSE 0 END),0) my_reaction FROM comments c LEFT JOIN members m ON m.id=c.member_id LEFT JOIN users u ON u.id=c.staff_author_id LEFT JOIN comment_reactions cr ON cr.comment_id=c.id WHERE c.post_id=? AND c.aprobado=1 GROUP BY c.id,c.parent_id,c.nombre,c.contenido,c.fecha,c.member_id,c.staff_author_id,m.avatar,u.avatar,m.profile_slug,u.profile_slug,m.profile_public,u.profile_public ORDER BY c.fecha');
$stmt->execute([$member ? (int)$member['id'] : 0, $id]); $comments = $stmt->fetchAll();
$commentsByParent = [];
foreach ($comments as $comment) { $commentsByParent[(int)($comment['parent_id'] ?? 0)][] = $comment; }
$canComment = is_logged_in() || ($member && is_member_verified());
$renderComments = function (int $parentId = 0, int $depth = 0) use (&$renderComments, $commentsByParent, $canComment, $id): void {
    foreach ($commentsByParent[$parentId] ?? [] as $comment) { $replyId = 'reply-' . (int)$comment['id']; ?>
    <article id="comment-<?=(int)$comment['id']?>" class="comment <?= $depth ? 'comment-reply' : '' ?>" style="--reply-depth:<?= min($depth, 4) ?>"><div class="comment-author"><?php $commentProfile=public_profile_url($comment);if($commentProfile):?><a class="author-profile-link" href="<?=e($commentProfile)?>"><?php endif;?><?php if($comment['avatar']):?><img class="avatar" src="/uploads/<?=e($comment['avatar'])?>" alt=""><?php endif;?><strong><?=e($comment['nombre'])?></strong><?php if($commentProfile):?></a><?php endif;?><?php if(!$comment['member_id']):?><span class="author-badge">Autor</span><?php endif;?><small><?=e(format_date($comment['fecha'],true))?></small></div><p><?=nl2br(e($comment['contenido']))?></p><div class="comment-tools"><form method="post" action="/comment-reaction.php"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="comment_id" value="<?=(int)$comment['id']?>"><button class="reaction <?= (int)$comment['my_reaction']===1?'active':'' ?>" name="reaction" value="1" aria-label="Me gusta este comentario">👍 <?=(int)$comment['likes']?></button><button class="reaction <?= (int)$comment['my_reaction']===-1?'active':'' ?>" name="reaction" value="-1" aria-label="No me gusta este comentario">👎 <?=(int)$comment['dislikes']?></button></form></div>
    <?php if($canComment):?><button type="button" class="reply-toggle" data-reply-toggle aria-controls="<?=$replyId?>" aria-expanded="false">Responder</button><form class="reply-form" id="<?=$replyId?>" method="post" action="/comment-submit.php" hidden><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="post_id" value="<?=$id?>"><input type="hidden" name="parent_id" value="<?=(int)$comment['id']?>"><label>Responder a <?=e($comment['nombre'])?><textarea name="contenido" maxlength="3000" rows="3" required></textarea></label><button class="button">Enviar respuesta</button></form><?php endif;?>
    <?php $renderComments((int)$comment['id'], $depth + 1); ?></article><?php }
};
$plainContent = trim(preg_replace('/\s+/', ' ', strip_tags($post['contenido'])) ?? '');
$description = mb_strimwidth($plainContent, 0, 200, '…');
$socialImage = $post['imagen'] ? '/uploads/' . rawurlencode($post['imagen']) : site_setting('og_image');
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
$commentsVersion = (string) (@filemtime(__DIR__ . '/assets/comments.js') ?: '1');
?>
<article class="article"><?php if ($post['imagen']): ?><div class="cover-wrap"><img class="cover" src="uploads/<?= e($post['imagen']) ?>" alt="<?= e($post['titulo']) ?>" loading="eager" decoding="async"<?= $imageSize ? ' width="' . (int)$imageSize[0] . '" height="' . (int)$imageSize[1] . '"' : '' ?> style="max-width:100%;height:auto;max-height:72vh;object-fit:contain"></div><?php endif; ?>
<div class="article-body"><h1><?= e($post['titulo']) ?></h1><div class="post-author"><?php $authorProfile=public_profile_url(['profile_public'=>$post['author_profile_public'],'profile_slug'=>$post['author_profile_slug']]);if($authorProfile):?><a class="author-profile-link" href="<?=e($authorProfile)?>"><?php endif;?><?php if($post['author_avatar']):?><img class="avatar" src="/uploads/<?=e($post['author_avatar'])?>" alt="Avatar de <?=e($post['author_name'])?>"><?php endif;?><p class="muted">Publicado el <?= e(format_date($post['fecha'], true)) ?> · Por <?= e($post['author_name'] ?: 'Administrador') ?></p><?php if($authorProfile):?></a><?php endif;?></div>
<div class="post-stats"><span class="reaction stat-pill" title="Vistas" aria-label="<?= $stats['views'] ?> vistas">👁 <?= $stats['views'] ?></span><form method="post" action="/reaction.php"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="post_id" value="<?= $id ?>"><button name="reaction" value="1" class="reaction <?= $stats['reaction']===1?'active':'' ?>">👍 <?= $stats['likes'] ?></button><button name="reaction" value="-1" class="reaction <?= $stats['reaction']===-1?'active':'' ?>">👎 <?= $stats['dislikes'] ?></button></form><a class="reaction stat-pill" href="#comentarios" title="Comentarios" aria-label="<?= $stats['comments'] ?> comentarios">💬 <?= $stats['comments'] ?></a><button type="button" class="reaction share-icon-button" data-share data-post-id="<?=$id?>" data-token="<?=csrf_token()?>" data-title="<?=e($post['titulo'])?>" data-text="Mira este artículo: <?=e($post['titulo'])?>" aria-label="Compartir publicación">↗ <span data-share-count><?= $stats['shares'] ?></span></button><span class="share-status" role="status" aria-live="polite"></span></div>
<?php if (can_edit_post($post)): ?><div class="actions"><a class="button warn" href="edit.php?id=<?= (int)$id ?>">Editar</a><form method="post" action="delete.php" onsubmit="return confirm('¿Eliminar este artículo?')"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= (int)$id ?>"><button class="button danger-bg">Eliminar</button></form></div><?php endif; ?>
<div class="content rich-content"><?= sanitize_html($post['contenido']) ?></div>
</div></article>
<section class="columns" id="comentarios"><div class="panel"><h2>Comentarios</h2><?php $renderComments(); ?><?php if (!$comments): ?><p class="muted">Todavía no hay comentarios aprobados.</p><?php endif; ?></div>
<div class="panel"><h2>Deja un comentario</h2><?php if($canComment):?><p class="muted">Publicas como <strong><?=e($member['display_name'] ?? current_author_name())?></strong>. Aparecerá después de ser aprobado.</p><form method="post" action="/comment-submit.php"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="post_id" value="<?= $id ?>"><label>Comentario<textarea name="contenido" maxlength="3000" rows="6" required></textarea></label><button class="button">Enviar para aprobación</button></form><?php elseif($member):?><p>Verifica tu correo desde <a class="text-link" href="/profile.php">tu perfil</a> para comentar y responder.</p><?php else:?><p>Necesitas una cuenta con correo verificado.</p><div class="actions"><a class="button" href="/member-login.php">Acceder</a><a class="button secondary" href="/register.php">Registrarme</a></div><?php endif;?></div></section>
<script src="/assets/share.js?v=<?= e($shareVersion) ?>" defer></script>
<script src="/assets/comments.js?v=<?= e($commentsVersion) ?>" defer></script>
<?php render_footer(); ?>
