document.addEventListener('DOMContentLoaded', () => {
  const input = document.querySelector('[data-avatar-input]');
  const preview = document.querySelector('[data-avatar-preview]');
  const status = document.querySelector('[data-avatar-status]');
  if (!input || !preview) return;
  input.addEventListener('change', () => {
    const file = input.files?.[0];
    if (!file) return;
    if (!file.type.startsWith('image/') || file.size > 2 * 1024 * 1024) {
      input.value = '';
      if (status) status.textContent = 'Selecciona una imagen válida de hasta 2 MB';
      return;
    }
    const url = URL.createObjectURL(file);
    const image = document.createElement('img');
    image.className = 'avatar avatar-lg'; image.alt = 'Vista previa del avatar'; image.src = url;
    image.addEventListener('load', () => URL.revokeObjectURL(url), { once: true });
    preview.replaceChildren(image);
    if (status) status.textContent = file.name;
  });
});
