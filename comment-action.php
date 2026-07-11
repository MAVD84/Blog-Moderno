<?php
require_once __DIR__ . '/functions.php'; require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
verify_csrf(); $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT); $action = $_POST['action'] ?? '';
if ($action === 'approve') { $stmt = db()->prepare('UPDATE comments SET aprobado = 1, fecha_aprobacion = NOW() WHERE id = ?'); $stmt->execute([$id]); flash('Comentario aprobado.'); }
elseif ($action === 'delete') { $stmt = db()->prepare('DELETE FROM comments WHERE id = ?'); $stmt->execute([$id]); flash('Comentario eliminado.'); }
else { http_response_code(400); exit('Acción inválida.'); }
redirect('comments.php');
