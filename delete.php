<?php
require_once __DIR__ . '/functions.php'; require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
verify_csrf(); $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT); $stmt = db()->prepare('SELECT imagen, author_id FROM posts WHERE id = ?'); $stmt->execute([$id]); $post = $stmt->fetch();
if (!$post) { http_response_code(404); exit('Artículo no encontrado.'); }
if (!can_edit_post($post)) { http_response_code(403); exit('No tienes permisos para eliminar este artículo.'); }
$stmt = db()->prepare('DELETE FROM posts WHERE id = ?'); $stmt->execute([$id]); delete_image($post['imagen']); flash('Artículo eliminado.'); redirect('index.php');
