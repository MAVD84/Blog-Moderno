document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.menu-toggle');
  const menu = document.querySelector('.nav-menu');
  if (!toggle || !menu) return;

  const closeItems = document.querySelectorAll('[data-menu-close]');
  const openMenu = () => {
    document.body.classList.add('menu-open');
    toggle.setAttribute('aria-expanded', 'true');
    menu.querySelector('a, button')?.focus();
  };
  const closeMenu = () => {
    document.body.classList.remove('menu-open');
    toggle.setAttribute('aria-expanded', 'false');
  };

  toggle.addEventListener('click', () => {
    if (document.body.classList.contains('menu-open')) closeMenu();
    else openMenu();
  });
  closeItems.forEach((item) => item.addEventListener('click', closeMenu));
  menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && document.body.classList.contains('menu-open')) {
      closeMenu();
      toggle.focus();
    }
  });
  window.matchMedia('(min-width: 761px)').addEventListener('change', closeMenu);
});
