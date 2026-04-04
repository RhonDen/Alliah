// Pure CSS smooth animations - no external libs



// Smooth anchor scrolling
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', e => {
    e.preventDefault();
    const target = document.querySelector(anchor.getAttribute('href'));
    if (target) {
      target.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    }
  });
});

// Parallax hero
let ticking = false;
window.addEventListener('scroll', () => {
  if (!ticking) {
    requestAnimationFrame(() => {
      const scrolled = window.pageYOffset;
      const hero = document.querySelector('.hero');
      if (hero) hero.style.transform = `translateY(${scrolled * 0.3}px)`;
      ticking = false;
    });
    ticking = true;
  }
});

// Senior-level scroll-triggered animations (60fps smooth)
const animateElements = document.querySelectorAll('.service-card, .feature-card, .stat, .location-item, .contact-item, .section-title');
const observerOptions = {
  threshold: 0.2,
  rootMargin: '0px 0px -10% 0px'
};

const scrollObserver = new IntersectionObserver((entries, observer) => {
  entries.forEach((entry, index) => {
    if (entry.isIntersecting) {
      // Initial hidden state (set once)
      entry.target.style.opacity = '0';
      entry.target.style.transform = 'translateY(60px) scale(0.92)';
      entry.target.style.transition = 'all 1s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
      
      // Staggered reveal with perfect easing
      setTimeout(() => {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0) scale(1)';
        entry.target.style.transitionDelay = `${Math.min(index * 120, 600)}ms`;
      }, 50);
      
      observer.unobserve(entry.target);
    }
  });
}, observerOptions);

// Initialize and observe
animateElements.forEach(el => scrollObserver.observe(el));

// CTA advanced interactions
document.querySelectorAll('.cta').forEach((btn, index) => {
  btn.style.transitionDelay = `${index * 150}ms`;
  
  btn.addEventListener('mouseenter', () => {
    btn.style.transform = 'translateY(-4px) scale(1.02)';
  });
  
  btn.addEventListener('mouseleave', () => {
    btn.style.transform = '';
  });
  
  // Subtle click feedback
  btn.addEventListener('click', e => {
    btn.style.transform = 'scale(0.96)';
    setTimeout(() => btn.style.transform = '', 150);
  });
});

// Progressive image loading with fade
document.querySelectorAll('img').forEach(img => {
  img.style.opacity = '0';
  img.style.transition = 'opacity 0.5s ease';
  img.addEventListener('load', () => img.style.opacity = '1');
});

// Navbar if exists (future proof)
const nav = document.querySelector('nav');
if (nav) {
  window.addEventListener('scroll', () => {
    if (window.scrollY > 100) {
      nav.style.background = 'rgba(255,255,255,0.95)';
      nav.style.backdropFilter = 'blur(20px)';
    } else {
      nav.style.background = 'transparent';
    }
  });
}

// Performance: RAF loop for scroll effects
let rafId;
window.addEventListener('scroll', () => {
  if (rafId) cancelAnimationFrame(rafId);
  rafId = requestAnimationFrame(() => {
    // Hero parallax already handled above
  });
});

