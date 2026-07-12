<?php
require_once __DIR__ . '/functions.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$stmt = db()->prepare('SELECT id,display_name,role,bio,avatar,profile_slug,profile_public,created_at FROM users WHERE profile_slug=? AND profile_public=1 AND active=1');
$stmt->execute([$slug]);
$author = $stmt->fetch();
if (!$author) {
    http_response_code(404);
    exit('Perfil no encontrado o privado.');
}

$stmt = db()->prepare("SELECT id,titulo,slug,contenido,imagen,image_credit,image_credit_url,fecha FROM posts WHERE author_id=? AND status='published' ORDER BY fecha DESC LIMIT 12");
$stmt->execute([$author['id']]);
$posts = $stmt->fetchAll();
$stmt = db()->prepare('SELECT COUNT(*) FROM user_follows WHERE followed_user_id=?');
$stmt->execute([$author['id']]);
$followers = (int) $stmt->fetchColumn();
$viewer = current_member();
$following = false;
if ($viewer && $viewer['email_verified_at']) {
    $stmt = db()->prepare('SELECT 1 FROM user_follows WHERE follower_member_id=? AND followed_user_id=?');
    $stmt->execute([$viewer['id'], $author['id']]);
    $following = (bool) $stmt->fetchColumn();
}
$shareVersion = (string) (@filemtime(__DIR__ . '/assets/share.js') ?: '1');

$profileMetadata = [
    'description' => $author['bio'] ?: 'Perfil de ' . $author['display_name'],
    'canonical' => public_author_url($author),
    'type' => 'profile',
    'image_alt' => 'Foto de perfil de ' . $author['display_name'],
];
if ($author['avatar']) {
    $profileMetadata['image'] = '/uploads/' . ltrim($author['avatar'], '/');
}
render_header($author['display_name'], $profileMetadata);
?>
<section class="panel public-profile-head">
    <div class="public-profile-identity">
        <?php if ($author['avatar']): ?>
            <img class="avatar public-avatar" src="/uploads/<?= e($author['avatar']) ?>" alt="Avatar de <?= e($author['display_name']) ?>">
        <?php else: ?>
            <span class="avatar public-avatar avatar-fallback"><?= e(mb_strtoupper(mb_substr($author['display_name'], 0, 1))) ?></span>
        <?php endif; ?>
        <div>
            <h1><?= e($author['display_name']) ?></h1>
            <p class="muted"><?= e(ucfirst($author['role'])) ?> · Miembro desde <?= e(format_date($author['created_at'])) ?></p>
        </div>
    </div>
    <?php if ($author['bio']): ?><p class="profile-bio"><?= nl2br(e($author['bio'])) ?></p><?php endif; ?>
    <div class="profile-social">
        <span><strong><?= $followers ?></strong> seguidores</span>
        <button type="button" class="button secondary profile-share-button" data-share data-title="<?= e($author['display_name']) ?>" data-text="Mira el perfil de <?= e($author['display_name']) ?>" aria-label="Compartir perfil">↗ Compartir</button>
        <span class="share-status" role="status" aria-live="polite"></span>
        <?php if ($viewer && $viewer['email_verified_at']): ?>
            <form method="post" action="/follow-author.php">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="user_id" value="<?= (int) $author['id'] ?>">
                <button class="button <?= $following ? 'secondary' : '' ?>"><?= $following ? 'Dejar de seguir' : 'Seguir' ?></button>
            </form>
        <?php elseif (!$viewer): ?>
            <a class="button secondary" href="/member-login.php">Accede para seguir</a>
        <?php endif; ?>
    </div>
</section>

<div class="grid profile-posts">
    <?php foreach ($posts as $post): ?>
        <article class="card">
            <?php if ($post['imagen']): ?>
                <img src="/uploads/<?= e($post['imagen']) ?>" alt="<?= e($post['titulo']) ?>">
                <?php if ($post['image_credit']): ?>
                    <p class="image-credit card-image-credit">Imagen: <?php if ($post['image_credit_url']): ?><a href="<?= e($post['image_credit_url']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e($post['image_credit']) ?></a><?php else: ?><?= e($post['image_credit']) ?><?php endif; ?></p>
                <?php endif; ?>
            <?php endif; ?>
            <div class="pad">
                <small><?= e(format_date($post['fecha'])) ?></small>
                <h2><a href="<?= e(post_url($post)) ?>"><?= e($post['titulo']) ?></a></h2>
                <p><?= e(mb_strimwidth(strip_tags($post['contenido']), 0, 140, '…')) ?></p>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<script src="/assets/share.js?v=<?= e($shareVersion) ?>" defer></script>
<?php render_footer();
