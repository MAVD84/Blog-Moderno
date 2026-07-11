<?php
require_once __DIR__ . '/functions.php';
require_admin();

$settings = site_settings();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = [
        'site_name' => trim($_POST['site_name'] ?? ''),
        'site_title' => trim($_POST['site_title'] ?? ''),
        'site_tagline' => trim($_POST['site_tagline'] ?? ''),
        'site_description' => trim($_POST['site_description'] ?? ''),
        'footer_text' => trim($_POST['footer_text'] ?? ''),
        'og_image' => $settings['og_image'],
    ];
    $limits = ['site_name' => 50, 'site_title' => 120, 'site_tagline' => 160, 'site_description' => 300, 'footer_text' => 160];
    foreach ($limits as $key => $limit) {
        if ($values[$key] === '' || mb_strlen($values[$key]) > $limit) {
            flash('Revisa los campos de configuración y sus longitudes.', 'error');
            redirect('settings.php');
        }
    }

    $newImage = null;
    try {
        $newImage = upload_image($_FILES['og_image'] ?? []);
        if ($newImage) { $values['og_image'] = '/uploads/' . $newImage; }
        save_site_settings($values);
        if ($newImage && str_starts_with($settings['og_image'], '/uploads/')) { delete_image(basename($settings['og_image'])); }
        flash('Configuración actualizada.');
        redirect('settings.php');
    } catch (Throwable $error) {
        if ($newImage) { delete_image($newImage); }
        flash('No se pudo guardar la configuración: ' . $error->getMessage(), 'error');
    }
}

render_header('Configuración', ['robots' => 'noindex,nofollow']);
?>
<div class="panel settings-panel">
    <h1>Configuración del sitio</h1>
    <p class="muted">Personaliza la identidad y la información que aparece al compartir el blog.</p>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="settings-grid">
            <label>Nombre del sitio<input name="site_name" maxlength="50" value="<?= e($settings['site_name']) ?>" required><small>Se muestra en la navegación y la metadata.</small></label>
            <label>Título principal<input name="site_title" maxlength="120" value="<?= e($settings['site_title']) ?>" required><small>Encabezado grande de la portada.</small></label>
        </div>
        <label>Subtítulo<input name="site_tagline" maxlength="160" value="<?= e($settings['site_tagline']) ?>" required></label>
        <label>Descripción SEO<textarea name="site_description" maxlength="300" rows="4" required><?= e($settings['site_description']) ?></textarea><small>Se utiliza en Google y al compartir la página principal.</small></label>
        <label>Texto del footer<input name="footer_text" maxlength="160" value="<?= e($settings['footer_text']) ?>" required></label>
        <label>Imagen social predeterminada<input type="file" name="og_image" accept="image/png,image/jpeg,image/webp"><small>Recomendado: 1200 × 630 px. Los posts con portada usan su propia imagen.</small></label>
        <img class="settings-og-preview" src="<?= e($settings['og_image']) ?>" alt="Imagen social actual">
        <div class="settings-actions"><button class="button" type="submit">Guardar configuración</button></div>
    </form>
</div>
<?php render_footer(); ?>
