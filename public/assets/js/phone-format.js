document.addEventListener('DOMContentLoaded', function() {
  // 4-3-4 format: e.g., 0999 999 9999
  const phoneInputs = document.querySelectorAll('input[type="tel"][name="mobile"], input[id="mobile"], input[name="emergency_contact"]');
  

  phoneInputs.forEach(input => {
    input.addEventListener('input', function(e) {
      let val = e.target.value.replace(/\D/g, ''); // Keep digits only
      if (val.length > 11) val = val.substring(0, 11); // PH 11 digits max

      val = val.replace(/(\d{4})(\d{3})(\d{4})/, '$1-$2-$3'); // 4-3-4 with dashes

      e.target.value = val;
    });
    
    input.addEventListener('keydown', function(e) {
      // Allow backspace, delete, arrows, tab
      if ([8, 46, 37, 39, 9].includes(e.keyCode)) return;
      // Block non-digit
      if (e.keyCode < 48 || e.keyCode > 57) e.preventDefault();
    });
  });

});
