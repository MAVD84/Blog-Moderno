document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-share]').forEach((button) => {
    const status = button.parentElement.querySelector('.share-status');
    button.addEventListener('click', async () => {
      const shareData = {
        title: button.dataset.title || document.title,
        text: button.dataset.text || '',
        url: window.location.href,
      };
      try {
        if (navigator.share) {
          await navigator.share(shareData);
          status.textContent = '';
          return;
        }
        if (navigator.clipboard?.writeText) {
          await navigator.clipboard.writeText(shareData.url);
          status.textContent = 'Enlace copiado';
          setTimeout(() => { status.textContent = ''; }, 3000);
          return;
        }
        window.prompt('Copia este enlace:', shareData.url);
      } catch (error) {
        if (error.name !== 'AbortError') { status.textContent = 'No se pudo compartir'; }
      }
    });
  });
});
