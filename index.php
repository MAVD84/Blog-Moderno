<?php
require_once __DIR__ . '/functions.php';
$posts = db()->query('SELECT id, titulo, contenido, imagen, fecha FROM posts ORDER BY fecha DESC')->fetchAll();
render_header('Inicio');
?>
<header class="hero"><h1>Polygon Blockchain</h1><p>Documentando el camino</p></header>
<div class="grid"><?php foreach ($posts as $post): ?><article class="card">
<?php if ($post['imagen']): ?><img src="uploads/<?= e($post['imagen']) ?>" alt="<?= e($post['titulo']) ?>"><?php endif; ?>
<div class="pad"><small><?= e(substr($post['fecha'], 0, 10)) ?></small><h2><a href="post.php?id=<?= (int)$post['id'] ?>"><?= e($post['titulo']) ?></a></h2>
<p><?= e(mb_strimwidth($post['contenido'], 0, 150, '…')) ?></p><a class="more" href="post.php?id=<?= (int)$post['id'] ?>">Leer más →</a></div></article>
<?php endforeach; ?><?php if (!$posts): ?><p class="empty">No hay artículos todavía.</p><?php endif; ?></div>
<?php render_footer(); ?>
