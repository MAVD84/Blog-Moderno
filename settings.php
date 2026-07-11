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
        'favicon_image' => $settings['favicon_image'],
        'logo_image' => $settings['logo_image'],
    ];
    if (isset($_POST['reset_favicon'])) { $values['favicon_image'] = '/assets/favicon.png'; }
    if (isset($_POST['remove_logo'])) { $values['logo_image'] = ''; }
    $limits = ['site_name' => 50, 'site_title' => 120, 'site_tagline' => 160, 'site_description' => 300, 'footer_text' => 160];
    foreach ($limits as $key => $limit) {
        if ($values[$key] === '' || mb_strlen($values[$key]) > $limit) {
            flash('Revisa los campos de configuración y sus longitudes.', 'error');
            redirect('settings.php');
        }
    }

    $newFiles = [];
    try {
        foreach (['og_image', 'favicon_image', 'logo_image'] as $imageKey) {
            $uploaded = upload_image($_FILES[$imageKey] ?? []);
            if ($uploaded) { $newFiles[$imageKey] = $uploaded; $values[$imageKey] = '/uploads/' . $uploaded; }
        }
        save_site_settings($values);
        foreach (['og_image', 'favicon_image', 'logo_image'] as $imageKey) {
            if ($values[$imageKey] !== $settings[$imageKey] && !empty($settings[$imageKey]) && str_starts_with($settings[$imageKey], '/uploads/')) { delete_image(basename($settings[$imageKey])); }
        }
        flash('Configuración actualizada.');
        redirect('settings.php');
    } catch (Throwable $error) {
        foreach ($newFiles as $uploaded) { delete_image($uploaded); }
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
        <div class="settings-grid settings-brand-assets">
            <label>Favicon<input type="file" name="favicon_image" accept="image/png,image/jpeg,image/webp"><small>Recomendado: imagen cuadrada de al menos 192 × 192 px.</small><img class="settings-icon-preview" src="<?= e($settings['favicon_image']) ?>" alt="Favicon actual"><span class="check-row"><input type="checkbox" name="reset_favicon" value="1"> Restaurar favicon original</span></label>
            <label>Logo de navegación<input type="file" name="logo_image" accept="image/png,image/jpeg,image/webp"><small>Si lo subes reemplazará el texto del nombre en la navegación.</small><?php if ($settings['logo_image']): ?><img class="settings-logo-preview" src="<?= e($settings['logo_image']) ?>" alt="Logo actual"><span class="check-row"><input type="checkbox" name="remove_logo" value="1"> Quitar logo y mostrar el nombre</span><?php else: ?><span class="settings-no-logo">Actualmente se muestra “<?= e($settings['site_name']) ?>”.</span><?php endif; ?></label>
        </div>
        <div class="settings-actions"><button class="button" type="submit">Guardar configuración</button></div>
    </form>
</div>
<?php render_footer(); ?>
