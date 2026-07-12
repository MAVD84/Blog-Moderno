<?php
require_once __DIR__ . '/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action = (string) ($_POST['action'] ?? '');
if (!$id || !in_array($action, ['approve', 'delete'], true)) {
    http_response_code(400);
    exit('Acción inválida.');
}

$stmt = db()->prepare('SELECT c.id,c.aprobado FROM comments c JOIN posts p ON p.id=c.post_id WHERE c.id=? AND p.author_id=?');
$stmt->execute([$id, current_user_id()]);
$comment = $stmt->fetch();
if (!$comment) {
    http_response_code(403);
    exit('Solo puedes moderar comentarios de tus publicaciones.');
}

if ($action === 'approve') {
    $stmt = db()->prepare('UPDATE comments SET aprobado=1,fecha_aprobacion=NOW() WHERE id=? AND aprobado=0');
    $stmt->execute([$id]);
    flash($stmt->rowCount() ? 'Comentario aprobado.' : 'El comentario ya estaba aprobado.');
} else {
    db()->prepare('DELETE FROM comments WHERE id=?')->execute([$id]);
    flash('Comentario eliminado.');
}
redirect('/comments.php');
