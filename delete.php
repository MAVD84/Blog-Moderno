<?php
require_once __DIR__ . '/functions.php'; require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
verify_csrf(); $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT); $stmt = db()->prepare('SELECT imagen FROM posts WHERE id = ?'); $stmt->execute([$id]); $post = $stmt->fetch();
if (!$post) { http_response_code(404); exit('Artículo no encontrado.'); }
$stmt = db()->prepare('DELETE FROM posts WHERE id = ?'); $stmt->execute([$id]); delete_image($post['imagen']); flash('Artículo eliminado.'); redirect('index.php');
