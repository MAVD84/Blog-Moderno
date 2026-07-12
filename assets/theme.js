document.addEventListener('DOMContentLoaded', () => {
  const input = document.querySelector('input[name="theme_color"]');
  if (!input) return;
  const control = input.closest('.color-control');
  const output = control?.querySelector('output');
  const swatch = control?.querySelector('.color-swatch');
  input.addEventListener('input', () => {
    document.documentElement.style.setProperty('--primary', input.value);
    if (output) output.textContent = input.value.toUpperCase();
    if (swatch) swatch.style.background = input.value;
  });
});
