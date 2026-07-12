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
  const textInput = document.querySelector('[data-text-color]');
  const textToggle = document.querySelector('[data-text-color-toggle]');
  const updateText = () => {
    const custom = textToggle?.checked;
    document.documentElement.style.setProperty('--text', custom ? textInput.value : '#172033');
    document.documentElement.style.setProperty('--muted', custom ? textInput.value : '#667085');
  };
  textInput?.addEventListener('input', () => {
    const textControl = textInput.closest('.color-control');
    const textOutput = textControl?.querySelector('output');
    const textSwatch = textControl?.querySelector('.color-swatch');
    if (textOutput) textOutput.textContent = textInput.value.toUpperCase();
    if (textSwatch) textSwatch.style.background = textInput.value;
    updateText();
  });
  textToggle?.addEventListener('change', updateText);
});
