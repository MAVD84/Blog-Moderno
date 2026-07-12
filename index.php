<?php
require_once __DIR__ . '/functions.php';
$posts = db()->query("SELECT p.id,p.titulo,p.slug,p.contenido,p.imagen,p.author_name,p.fecha,p.post_type,m.avatar FROM posts p LEFT JOIN members m ON m.id=p.member_author_id WHERE p.status='published' ORDER BY p.fecha DESC")->fetchAll();
$posts = array_map('ensure_post_slug', $posts);
render_header('Inicio', ['canonical' => '/']);
?>
<header class="hero"><h1><?= e(site_setting('site_title')) ?></h1><p><?= e(site_setting('site_tagline')) ?></p></header>
<div class="grid"><?php foreach ($posts as $post): ?><article class="card">
<?php if ($post['imagen']): ?><img src="uploads/<?= e($post['imagen']) ?>" alt="<?= e($post['titulo']) ?>"><?php endif; ?>
<div class="pad"><div class="author-meta"><?php if($post['avatar']):?><img class="avatar avatar-sm" src="/uploads/<?=e($post['avatar'])?>" alt=""><?php endif;?><small><?= e(format_date($post['fecha'])) ?> · <?= e($post['author_name'] ?: 'Administrador') ?></small></div><h2><a href="<?= e(post_url($post)) ?>"><?= e($post['titulo']) ?></a></h2>
<p><?= e(mb_strimwidth(strip_tags($post['contenido']), 0, 150, '…')) ?></p><a class="more" href="<?= e(post_url($post)) ?>">Leer más →</a></div></article>
<?php endforeach; ?><?php if (!$posts): ?><p class="empty">No hay artículos todavía.</p><?php endif; ?></div>
<?php render_footer(); ?>
