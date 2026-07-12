<?php
require_once __DIR__ . '/functions.php';
$member = require_member();
$stmt = db()->prepare('SELECT c.id,c.nombre,c.contenido,c.fecha,c.aprobado,c.staff_author_id,p.titulo,p.slug,COALESCE(m.avatar,u.avatar) author_avatar,COALESCE(m.profile_slug,u.profile_slug) author_profile_slug,COALESCE(m.profile_public,u.profile_public) author_profile_public FROM comments c JOIN posts p ON p.id=c.post_id LEFT JOIN members m ON m.id=c.member_id LEFT JOIN users u ON u.id=c.staff_author_id WHERE p.member_author_id=? ORDER BY c.aprobado ASC,c.fecha DESC');
$stmt->execute([$member['id']]);
$comments = $stmt->fetchAll();
$pendingCount = count(array_filter($comments, fn(array $comment): bool => !(bool) $comment['aprobado']));
render_header('Comentarios de mis publicaciones', ['robots' => 'noindex,nofollow']);
?>
<section class="panel">
    <div class="moderation-title"><div><h1>Comentarios de mis publicaciones</h1><p class="muted">Aprueba o elimina únicamente los comentarios recibidos en tus posts.</p></div><span class="count-badge"><?= $pendingCount ?> pendiente<?= $pendingCount === 1 ? '' : 's' ?></span></div>
    <?php foreach ($comments as $comment): ?>
        <article class="moderation">
            <div class="moderation-meta">
                <?php $profileData=['profile_slug'=>$comment['author_profile_slug'],'profile_public'=>$comment['author_profile_public']];$profileUrl=$comment['staff_author_id']?public_author_url($profileData):public_profile_url($profileData); ?>
                <div class="moderation-user"><?php if($profileUrl):?><a class="author-profile-link" href="<?=e($profileUrl)?>"><?php endif;?><?php if($comment['author_avatar']):?><img class="avatar member-admin-avatar" src="/uploads/<?=e($comment['author_avatar'])?>" alt="Avatar de <?=e($comment['nombre'])?>"><?php else:?><span class="avatar member-admin-avatar avatar-fallback"><?=e(mb_strtoupper(mb_substr($comment['nombre'],0,1)))?></span><?php endif;?><strong><?=e($comment['nombre'])?></strong><?php if($profileUrl):?></a><?php endif;?></div>
                <span class="status-badge <?= $comment['aprobado'] ? 'approved' : 'pending' ?>"><?= $comment['aprobado'] ? 'Aprobado' : 'Pendiente' ?></span>
            </div>
            <small>En: <a href="/<?= e(rawurlencode($comment['slug'])) ?>"><?= e($comment['titulo']) ?></a></small>
            <p><?= nl2br(e($comment['contenido'])) ?></p>
            <div class="actions">
                <?php if (!$comment['aprobado']): ?><form method="post" action="/comment-owner-action.php"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="comment_id" value="<?=(int)$comment['id']?>"><button class="button" name="action" value="approve">Aprobar</button></form><?php endif; ?>
                <form method="post" action="/comment-owner-action.php" onsubmit="return confirm('¿Eliminar este comentario?')"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="comment_id" value="<?=(int)$comment['id']?>"><button class="button danger-bg" name="action" value="delete">Eliminar</button></form>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$comments): ?><p class="empty">Tus publicaciones todavía no tienen comentarios.</p><?php endif; ?>
</section>
<?php render_footer();
