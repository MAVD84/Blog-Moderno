<?php
require_once __DIR__ . '/functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
verify_csrf(); $postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
if (!empty($_POST['website'])) { redirect("post.php?id={$postId}"); }
$nombre = trim($_POST['nombre'] ?? ''); $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL); $contenido = trim($_POST['contenido'] ?? '');
if (!$postId || !$email || $nombre === '' || mb_strlen($nombre) > 80 || $contenido === '' || mb_strlen($contenido) > 2000) { flash('Revisa los datos del comentario.', 'error'); redirect("post.php?id={$postId}"); }
$exists = db()->prepare('SELECT 1 FROM posts WHERE id = ?'); $exists->execute([$postId]);
if (!$exists->fetchColumn()) { http_response_code(404); exit('Artículo no encontrado.'); }
$stmt = db()->prepare('INSERT INTO comments (post_id, nombre, email, contenido) VALUES (?, ?, ?, ?)'); $stmt->execute([$postId, $nombre, $email, $contenido]); flash('Comentario recibido. Aparecerá cuando sea aprobado.'); redirect("post.php?id={$postId}");
