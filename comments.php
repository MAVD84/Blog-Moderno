<?php
require_once __DIR__ . '/functions.php';
require_admin();

$comments = db()->query(
    'SELECT c.*, p.titulo, p.slug
     FROM comments c
     JOIN posts p ON p.id = c.post_id
     ORDER BY c.aprobado ASC, c.fecha DESC'
)->fetchAll();
foreach ($comments as &$comment) {
    if (empty($comment['slug'])) {
        $article = ensure_post_slug(['id' => (int)$comment['post_id'], 'titulo' => $comment['titulo'], 'slug' => null]);
        $comment['slug'] = $article['slug'];
    }
}
unset($comment);
$pendingCount = count(array_filter($comments, fn(array $comment): bool => !(bool)$comment['aprobado']));

render_header('Administrar comentarios', ['robots' => 'noindex,nofollow']);
?>
<div class="panel">
    <div class="moderation-title">
        <div>
            <h1>Administrar comentarios</h1>
            <p class="muted">El correo solo es visible para ti.</p>
        </div>
        <span class="count-badge"><?= $pendingCount ?> pendiente<?= $pendingCount === 1 ? '' : 's' ?></span>
    </div>

    <?php foreach ($comments as $comment): ?>
        <article class="moderation">
            <div class="moderation-meta">
                <div>
                    <strong><?= e($comment['nombre']) ?></strong>
                    <span class="muted">&lt;<?= e($comment['email']) ?>&gt;</span>
                </div>
                <span class="status-badge <?= $comment['aprobado'] ? 'approved' : 'pending' ?>">
                    <?= $comment['aprobado'] ? 'Aprobado' : 'Pendiente' ?>
                </span>
            </div>
            <small>En: <a href="<?= e('/' . rawurlencode($comment['slug'])) ?>"><?= e($comment['titulo']) ?></a></small>
            <p><?= nl2br(e($comment['contenido'])) ?></p>
            <div class="actions">
                <?php if (!$comment['aprobado']): ?>
                    <form method="post" action="comment-action.php">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="id" value="<?= (int)$comment['id'] ?>">
                        <button class="button" name="action" value="approve">Aprobar</button>
                    </form>
                <?php endif; ?>
                <form method="post" action="comment-action.php" onsubmit="return confirm('¿Eliminar este comentario? Esta acción no se puede deshacer.')">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="id" value="<?= (int)$comment['id'] ?>">
                    <button class="button danger-bg" name="action" value="delete">Eliminar</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>

    <?php if (!$comments): ?><p class="empty">No hay comentarios.</p><?php endif; ?>
</div>
<?php render_footer(); ?>
