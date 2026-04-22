document.addEventListener('DOMContentLoaded', function() {
  // 4-3-4 format: e.g., 0999-999-9999
  const phoneFields = document.querySelectorAll('input[type="tel"]');
  const identifierField = document.querySelector('input[id="identifier"]');

  phoneFields.forEach(input => {
    input.setAttribute('maxlength', '14');
    input.setAttribute('inputmode', 'numeric');

    input.addEventListener('input', function(e) {
      const currentValue = e.target.value;
      let digits = currentValue.replace(/\D/g, '');
      if (digits.length > 11) digits = digits.substring(0, 11);
      e.target.value = formatPhone(digits);
    });

    input.addEventListener('keydown', function(e) {
      const allowedKeys = ['Backspace', 'Tab', 'Enter', 'Escape', 'Home', 'End', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Delete'];
      if (allowedKeys.includes(e.key)) return;
      if (e.ctrlKey || e.metaKey) return;
      if (e.key >= '0' && e.key <= '9') return;
      e.preventDefault();
    });

    input.addEventListener('paste', function(e) {
      e.preventDefault();
      const clipboard = (e.clipboardData || window.clipboardData).getData('text');
      const digits = clipboard.replace(/\D/g, '').substring(0, 11);
      e.target.value = formatPhone(digits);
      input.dispatchEvent(new Event('input'));
    });
  });

  if (identifierField) {
    identifierField.addEventListener('input', function(e) {
      const currentValue = e.target.value;
      if (currentValue.includes('@')) return; // Allow email typing
      // Only format if it looks like a phone (digits only)
      if (/^\d+$/.test(currentValue.replace(/\D/g, ''))) {
        let digits = currentValue.replace(/\D/g, '');
        if (digits.length > 11) digits = digits.substring(0, 11);
        e.target.value = formatPhone(digits);
      }
    });
  }

  function formatPhone(value) {
    if (value.length <= 4) return value;
    if (value.length <= 7) return value.replace(/(\d{4})(\d+)/, '$1-$2');
    return value.replace(/(\d{4})(\d{3})(\d+)/, '$1-$2-$3');
  }
});
