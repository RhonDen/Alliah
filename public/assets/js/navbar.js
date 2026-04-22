document.addEventListener('DOMContentLoaded', function() {
  const hamburger = document.querySelector('.navbar-hamburger');
  const nav = document.querySelector('.nav-links');
  const navLinks = document.querySelectorAll('.nav-links a');

  function setMenuState(open) {
    if (!hamburger || !nav) {
      return;
    }
    hamburger.classList.toggle('active', open);
    nav.classList.toggle('open', open);
    hamburger.setAttribute('aria-expanded', open ? 'true' : 'false');
    hamburger.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  }

  if (hamburger && nav) {
    hamburger.addEventListener('click', () => {
      setMenuState(!nav.classList.contains('open'));
    });
  }

  navLinks.forEach(link => {
    link.addEventListener('click', () => {
      setMenuState(false);
    });
  });

  document.addEventListener('click', (event) => {
    if (!nav.classList.contains('open')) {
      return;
    }
    if (!nav.contains(event.target) && !hamburger.contains(event.target)) {
      setMenuState(false);
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      setMenuState(false);
    }
  });
});
