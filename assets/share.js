document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-share]').forEach((button) => {
    const status = button.parentElement.querySelector('.share-status');
    const count = button.querySelector('[data-share-count]');
    const recordShare = async () => {
      if (!button.dataset.postId || !button.dataset.token) return;
      const response = await fetch('/share.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ post_id: button.dataset.postId, csrf_token: button.dataset.token }),
      });
      if (response.ok && count) count.textContent = (await response.json()).count;
    };
    button.addEventListener('click', async () => {
      const shareData = {
        title: button.dataset.title || document.title,
        text: button.dataset.text || '',
        url: window.location.href,
      };
      try {
        if (navigator.share) {
          await navigator.share(shareData);
          await recordShare();
          status.textContent = '';
          return;
        }
        if (navigator.clipboard?.writeText) {
          await navigator.clipboard.writeText(shareData.url);
          await recordShare();
          status.textContent = 'Enlace copiado';
          setTimeout(() => { status.textContent = ''; }, 3000);
          return;
        }
        window.prompt('Copia este enlace:', shareData.url);
        await recordShare();
      } catch (error) {
        if (error.name !== 'AbortError') { status.textContent = 'No se pudo compartir'; }
      }
    });
  });
});
