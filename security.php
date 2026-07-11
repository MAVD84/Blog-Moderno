<?php
require_once __DIR__ . '/functions.php';
require_admin();

$attempts = db()->query(
    'SELECT successful, attempted_at, resolved_at, LEFT(ip_hash, 12) AS source
     FROM login_attempts ORDER BY attempted_at DESC LIMIT 100'
)->fetchAll();
$failed24h = (int)db()->query(
    'SELECT COUNT(*) FROM login_attempts WHERE successful = 0 AND attempted_at >= NOW() - INTERVAL 24 HOUR'
)->fetchColumn();

render_header('Seguridad', ['robots' => 'noindex,nofollow']);
?>
<div class="panel">
    <div class="moderation-title"><div><h1>Registro de seguridad</h1><p class="muted">Últimos 100 intentos de acceso. Las direcciones IP no se guardan en claro.</p></div><span class="count-badge"><?= $failed24h ?> fallo<?= $failed24h === 1 ? '' : 's' ?> / 24 h</span></div>
    <div class="security-table-wrap"><table class="security-table"><thead><tr><th>Fecha</th><th>Origen protegido</th><th>Resultado</th></tr></thead><tbody>
    <?php foreach ($attempts as $attempt): ?><tr><td><?= e($attempt['attempted_at']) ?></td><td><code><?= e($attempt['source']) ?>…</code></td><td><span class="status-badge <?= $attempt['successful'] ? 'approved' : 'pending' ?>"><?= $attempt['successful'] ? 'Correcto' : 'Fallido' ?></span></td></tr><?php endforeach; ?>
    <?php if (!$attempts): ?><tr><td colspan="3" class="empty">Todavía no hay intentos registrados.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?php render_footer(); ?>
