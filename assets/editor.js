document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.editor-shell').forEach((shell) => {
    const editor = shell.querySelector('.rich-editor');
    const value = shell.querySelector('.editor-value');
    const form = shell.closest('form');
    const focus = () => editor.focus();

    shell.querySelectorAll('[data-command]').forEach((button) => {
      button.addEventListener('click', () => {
        focus();
        document.execCommand(button.dataset.command, false);
      });
    });
    shell.querySelectorAll('[data-block]').forEach((button) => {
      button.addEventListener('click', () => {
        focus();
        document.execCommand('formatBlock', false, button.dataset.block);
      });
    });
    shell.querySelector('[data-link]').addEventListener('click', () => {
      const url = window.prompt('Dirección del enlace (https://...)');
      if (url) {
        focus();
        document.execCommand('createLink', false, url);
      }
    });
    form.addEventListener('submit', (event) => {
      value.value = editor.innerHTML.trim();
      if (!editor.textContent.trim()) {
        event.preventDefault();
        editor.focus();
      }
    });
  });
});
