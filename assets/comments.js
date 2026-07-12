document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-reply-toggle]');
  if (!button) return;
  const form = document.getElementById(button.getAttribute('aria-controls'));
  if (!form) return;
  const opening = form.hidden;
  document.querySelectorAll('.reply-form').forEach((item) => { item.hidden = true; });
  form.hidden = !opening;
  button.setAttribute('aria-expanded', opening ? 'true' : 'false');
  if (opening) form.querySelector('textarea')?.focus();
});
