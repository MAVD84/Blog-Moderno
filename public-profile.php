<?php
require_once __DIR__ . '/functions.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$stmt = db()->prepare('SELECT id,display_name,bio,avatar,profile_slug,profile_public,created_at FROM members WHERE profile_slug=? AND profile_public=1 AND active=1');
$stmt->execute([$slug]);
$profile = $stmt->fetch();

if (!$profile) {
    $stmt = db()->prepare('SELECT 1 FROM users WHERE profile_slug=? AND profile_public=1 AND active=1');
    $stmt->execute([$slug]);
    if ($stmt->fetchColumn()) {
        redirect('/autor/' . rawurlencode($slug));
    }
    http_response_code(404);
    exit('Perfil no encontrado o privado.');
}

$stmt = db()->prepare("SELECT id,titulo,slug,contenido,imagen,image_credit,image_credit_url,fecha FROM posts WHERE member_author_id=? AND post_type='community' AND status='published' ORDER BY fecha DESC LIMIT 12");
$stmt->execute([$profile['id']]);
$posts = $stmt->fetchAll();

$stmt = db()->prepare('SELECT (SELECT COUNT(*) FROM member_follows WHERE followed_id=?) followers,(SELECT COUNT(*) FROM member_follows WHERE follower_id=?) following');
$stmt->execute([$profile['id'], $profile['id']]);
$counts = $stmt->fetch();
$viewer = current_member();
$following = false;
if ($viewer && $viewer['email_verified_at'] && (int) $viewer['id'] !== (int) $profile['id']) {
    $stmt = db()->prepare('SELECT 1 FROM member_follows WHERE follower_id=? AND followed_id=?');
    $stmt->execute([$viewer['id'], $profile['id']]);
    $following = (bool) $stmt->fetchColumn();
}
$shareVersion = (string) (@filemtime(__DIR__ . '/assets/share.js') ?: '1');

$profileMetadata = [
    'description' => $profile['bio'] ?: 'Perfil de ' . $profile['display_name'],
    'canonical' => public_profile_url($profile),
    'type' => 'profile',
    'image_alt' => 'Foto de perfil de ' . $profile['display_name'],
];
if ($profile['avatar']) {
    $profileMetadata['image'] = '/uploads/' . ltrim($profile['avatar'], '/');
}
render_header($profile['display_name'], $profileMetadata);
?>
<section class="panel public-profile-head">
    <div class="public-profile-identity">
        <?php if ($profile['avatar']): ?>
            <img class="avatar public-avatar" src="/uploads/<?= e($profile['avatar']) ?>" alt="Avatar de <?= e($profile['display_name']) ?>">
        <?php else: ?>
            <span class="avatar public-avatar avatar-fallback"><?= e(mb_strtoupper(mb_substr($profile['display_name'], 0, 1))) ?></span>
        <?php endif; ?>
        <div>
            <h1><?= e($profile['display_name']) ?></h1>
            <p class="muted">Miembro desde <?= e(format_date($profile['created_at'])) ?></p>
        </div>
    </div>
    <?php if ($profile['bio']): ?><p class="profile-bio"><?= nl2br(e($profile['bio'])) ?></p><?php endif; ?>
    <div class="profile-social">
        <span><strong><?= (int) $counts['followers'] ?></strong> seguidores</span>
        <span><strong><?= (int) $counts['following'] ?></strong> siguiendo</span>
        <button type="button" class="button secondary profile-share-button" data-share data-title="<?= e($profile['display_name']) ?>" data-text="Mira el perfil de <?= e($profile['display_name']) ?>" aria-label="Compartir perfil">↗ Compartir</button>
        <span class="share-status" role="status" aria-live="polite"></span>
        <?php if ($viewer && (int) $viewer['id'] !== (int) $profile['id'] && $viewer['email_verified_at']): ?>
            <form method="post" action="/follow.php">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="member_id" value="<?= (int) $profile['id'] ?>">
                <button class="button <?= $following ? 'secondary' : '' ?>"><?= $following ? 'Dejar de seguir' : 'Seguir' ?></button>
            </form>
        <?php elseif (!$viewer): ?>
            <a class="button secondary" href="/member-login.php">Accede para seguir</a>
        <?php endif; ?>
    </div>
</section>

<section>
    <div class="moderation-title"><h2>Publicaciones recientes</h2><span class="count-badge"><?= count($posts) ?></span></div>
    <div class="grid profile-posts">
        <?php foreach ($posts as $post): ?>
            <article class="card">
                <?php if ($post['imagen']): ?>
                    <img src="/uploads/<?= e($post['imagen']) ?>" alt="<?= e($post['titulo']) ?>">
                    <?php if ($post['image_credit']): ?>
                        <p class="image-credit">Imagen:
                            <?php if ($post['image_credit_url']): ?><a href="<?= e($post['image_credit_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($post['image_credit']) ?></a><?php else: ?><?= e($post['image_credit']) ?><?php endif; ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="pad">
                    <small><?= e(format_date($post['fecha'])) ?></small>
                    <h2><a href="<?= e(post_url($post)) ?>"><?= e($post['titulo']) ?></a></h2>
                    <p><?= e(mb_strimwidth(strip_tags($post['contenido']), 0, 140, '…')) ?></p>
                    <a class="more" href="<?= e(post_url($post)) ?>">Leer →</a>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$posts): ?><p class="empty">Aún no tiene publicaciones públicas.</p><?php endif; ?>
    </div>
</section>
<script src="/assets/share.js?v=<?= e($shareVersion) ?>" defer></script>
<?php render_footer();
