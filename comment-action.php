<?php
require_once __DIR__ . '/functions.php'; require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
verify_csrf(); $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT); $action = $_POST['action'] ?? '';
if (!$id) { http_response_code(400); exit('Comentario inválido.'); }
if ($action === 'approve') { $stmt = db()->prepare('UPDATE comments SET aprobado = 1, fecha_aprobacion = NOW() WHERE id = ? AND aprobado = 0'); $stmt->execute([$id]); flash($stmt->rowCount() ? 'Comentario aprobado.' : 'El comentario ya estaba aprobado.'); }
elseif ($action === 'delete') { $stmt = db()->prepare('DELETE FROM comments WHERE id = ?'); $stmt->execute([$id]); flash($stmt->rowCount() ? 'Comentario eliminado.' : 'El comentario ya no existe.'); }
else { http_response_code(400); exit('Acción inválida.'); }
redirect('comments.php');
