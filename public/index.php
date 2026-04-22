<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-Content-Type-Options" content="nosniff">
  <meta name="referrer" content="no-referrer">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Dents-City Dental Clinic | Modern Care Redefined</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <script type="module" src="https://unpkg.com/@splinetool/viewer@1.12.73/build/spline-viewer.js"></script>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, sans-serif;
      background-color: #fcfdfd;
      color: #1a2c2a;
      line-height: 1.5;
      scroll-behavior: smooth;
      overflow-x: hidden;
    }

    :root {
      --primary: #1f816a;
      --primary-dark: #166b57;
      --secondary: #48a48f;
      --accent: #73c7b4;
      --light-bg: #b3d9cf;
      --base: #fcfdfd;
      --card-shadow: 0 12px 28px -8px rgba(31, 129, 106, 0.12), 0 4px 12px rgba(0, 0, 0, 0.03);
      --card-hover-shadow: 0 30px 40px -16px rgba(31, 129, 106, 0.2);
      --transition-smooth: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
    }

    h1, h2, h3, h4 {
      font-weight: 700;
      letter-spacing: -0.02em;
      line-height: 1.2;
    }

    h1 {
      font-size: 4.2rem;
      font-weight: 800;
      color: white;
      text-shadow: 0 2px 12px rgba(0, 0, 0, 0.4);
      margin-bottom: 1.25rem;
      letter-spacing: -0.025em;
    }

    h2 {
      font-size: 2.5rem;
      font-weight: 700;
      color: #1a3a34;
      margin-bottom: 1rem;
      position: relative;
      display: inline-block;
    }

    h3 {
      font-size: 1.5rem;
      font-weight: 600;
      color: #1f4e44;
      margin-bottom: 0.75rem;
    }

    .section-title {
      text-align: center;
      margin-bottom: 3rem;
      max-width: 720px;
      margin-left: auto;
      margin-right: auto;
    }

    .section-title h2 {
      margin-bottom: 0.5rem;
    }

    .section-title::after {
      content: '';
      display: block;
      width: 84px;
      height: 4px;
      background: linear-gradient(90deg, var(--primary), var(--accent));
      margin: 1rem auto 0;
      border-radius: 999px;
    }

    p {
      color: #2f4d48;
      font-weight: 400;
      line-height: 1.75;
    }

    .container {
      max-width: 1280px;
      margin: 0 auto;
      padding: 0 32px;
      position: relative;
      z-index: 2;
    }

    .section {
      padding: 120px 0 90px;
    }

    .section-alt {
      background: linear-gradient(145deg, #f5fbf8, #fdfefe);
      position: relative;
      overflow: hidden;
    }

    .section-alt::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: radial-gradient(circle at 20% 20%, rgba(115, 199, 180, 0.08), transparent 32%);
      pointer-events: none;
    }

    .hero {
      position: relative;
      padding: 180px 0 140px;
      isolation: isolate;
      overflow: hidden;
      min-height: 780px;
      display: flex;
      align-items: center;
    }

    .hero::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(180deg, rgba(31, 129, 106, 0.28), rgba(15, 28, 25, 0.4));
      z-index: 1;
      pointer-events: none;
    }

    .hero::after {
      content: '';
      position: absolute;
      top: 8%;
      right: -20%;
      width: 420px;
      height: 420px;
      background: radial-gradient(circle at center, rgba(115, 199, 180, 0.24), transparent 62%);
      z-index: 1;
      pointer-events: none;
    }

    .hero-content {
      position: relative;
      z-index: 3;
      max-width: 780px;
      margin: 0 auto;
      text-align: center;
      padding-top: 2rem;
    }

    .hero-content p {
      font-size: 1.22rem;
      color: rgba(255, 255, 255, 0.95);
      max-width: 640px;
      margin: 0 auto;
      line-height: 1.7;
      text-shadow: 0 1px 15px rgba(0, 0, 0, 0.18);
    }

    .hero-scroll {
      position: relative;
      margin: 2rem auto 0;
      display: inline-flex;
      align-items: center;
      gap: 0.75rem;
      color: rgba(255, 255, 255, 0.95);
      font-size: 0.85rem;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      font-weight: 700;
      text-decoration: none;
      opacity: 0.9;
      transition: opacity 0.25s ease, transform 0.25s ease;
    }

    .hero-scroll:hover {
      opacity: 1;
      transform: translateX(-50%) translateY(-2px);
    }

    .hero-scroll .scroll-icon {
      width: 44px;
      height: 44px;
      border: 2px solid rgba(255, 255, 255, 0.95);
      border-radius: 999px;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
    }

    .hero-scroll .scroll-icon::after {
      content: '';
      width: 10px;
      height: 10px;
      border-right: 2px solid rgba(255, 255, 255, 0.95);
      border-bottom: 2px solid rgba(255, 255, 255, 0.95);
      transform: rotate(45deg);
      display: block;
      animation: scroll-bounce 1.4s infinite ease-in-out;
      margin-top: 4px;
    }

    @keyframes scroll-bounce {
      0%, 20%, 50%, 80%, 100% {
        transform: translateY(0) rotate(45deg);
      }
      40% {
        transform: translateY(6px) rotate(45deg);
      }
      60% {
        transform: translateY(3px) rotate(45deg);
      }
    }

    .hero {
      position: relative;
      padding: 140px 0 130px;
      isolation: isolate;
      overflow: hidden;
    }

    .hero-bg-canvas {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: 0;
      overflow: hidden;
    }

    .hero-bg-canvas spline-viewer {
      position: absolute;
      top: 50%;
      left: 50%;
      width: 100%;
      height: 100%;
      transform: translate(-50%, -50%) scale(1.3);
      display: block;
      pointer-events: none;
    }

    .hero::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1;
      pointer-events: none;
    }

    .hero::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: radial-gradient(circle at 50% 50%, transparent 50%, rgba(0, 0, 0, 0.2) 100%);
      z-index: 1;
      pointer-events: none;
    }

    .hero-content {
      position: relative;
      z-index: 3;
      max-width: 800px;
      margin: 0 auto;
      text-align: center;
    }

    .hero-content p {
      font-size: 1.3rem;
      color: rgba(255, 255, 255, 0.95);
      max-width: 640px;
      margin: 0 auto;
      line-height: 1.5;
      text-shadow: 0 1px 8px rgba(0, 0, 0, 0.4);
    }

    /* Grid layouts */
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 2rem;
    }

    .about-article-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 2rem;
      margin-bottom: 3rem;
    }

    .about-card {
      background: white;
      border-radius: 32px;
      padding: 2rem;
      box-shadow: var(--card-shadow);
      transition: var(--transition-smooth);
      border: 1px solid rgba(115, 199, 180, 0.2);
      height: 100%;
      position: relative;
      overflow: hidden;
    }

    .about-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: linear-gradient(90deg, var(--primary), var(--accent));
      transform: scaleX(0);
      transform-origin: left;
      transition: transform 0.3s ease;
    }

    .about-card:hover::before {
      transform: scaleX(1);
    }

    .about-card:hover {
      transform: translateY(-6px);
      box-shadow: var(--card-hover-shadow);
      border-color: rgba(31, 129, 106, 0.4);
    }

    .about-card h3 {
      font-size: 1.6rem;
      margin-bottom: 1rem;
      position: relative;
      display: inline-block;
    }

    .about-card h3:after {
      content: '';
      display: block;
      width: 40px;
      height: 3px;
      background: var(--accent);
      margin-top: 8px;
      border-radius: 2px;
    }

    .about-card p {
      margin-bottom: 1rem;
    }

    .map-placeholder {
      width: 100%;
      height: 180px;
      background-color: #e2f0ec;
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 1rem 0 0.5rem;
      overflow: hidden;
    }

    .map-placeholder img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.4s ease;
    }

    .map-placeholder:hover img {
      transform: scale(1.02);
    }

    .about-stats-grid {
      margin-top: 3rem;
    }

    .service-grid {
      gap: 1.75rem;
    }

    .location-grid {
      gap: 2rem;
    }

    .contact-grid {
      gap: 2rem;
    }

    .card {
      background: white;
      border-radius: 28px;
      padding: 2rem 1.8rem;
      box-shadow: var(--card-shadow);
      transition: var(--transition-smooth);
      border: 1px solid rgba(115, 199, 180, 0.15);
    }

    .card:hover {
      transform: translateY(-8px);
      box-shadow: var(--card-hover-shadow);
      border-color: rgba(31, 129, 106, 0.25);
    }

    .stat.card {
      text-align: center;
      background: white;
      padding: 2rem 1rem;
    }

    .stat.card h3 {
      font-size: 2.5rem;
      font-weight: 800;
      color: var(--primary);
      margin-bottom: 0.5rem;
    }

    .service-card {
      text-align: center;
      background: linear-gradient(145deg, white, #fefefe);
    }

    .service-card h3 {
      font-size: 1.4rem;
      margin-bottom: 0.75rem;
    }

    .location-item {
      padding: 0;
      overflow: hidden;
      background: white;
    }

    .location-item img {
      width: 100%;
      height: 220px;
      object-fit: cover;
      transition: transform 0.5s ease;
      display: block;
    }

    .location-item:hover img {
      transform: scale(1.05);
    }

    .location-item h3, .location-item p {
      padding: 0 1.5rem;
    }

    .location-item h3 {
      margin-top: 1.25rem;
    }

    .location-item p {
      margin-bottom: 1.5rem;
    }

    .icon, .contact-icon {
      font-size: 2.5rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1rem;
      background: rgba(31, 129, 106, 0.1);
      width: 70px;
      height: 70px;
      border-radius: 60px;
      transition: var(--transition-smooth);
      color: var(--primary);
    }

    .card:hover .icon {
      background: var(--primary);
      color: white;
      transform: scale(1.02);
    }

    .contact-icon {
      background: rgba(72, 164, 143, 0.12);
      margin-bottom: 1.2rem;
      font-size: 2rem;
      width: 64px;
      height: 64px;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 1rem 2rem;
      border-radius: 999px;
      font-weight: 700;
      font-size: 1rem;
      text-decoration: none;
      transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
      cursor: pointer;
      border: 1px solid transparent;
      letter-spacing: -0.01em;
      min-height: 52px;
      box-shadow: 0 12px 28px rgba(15, 28, 25, 0.12);
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white;
    }

    .btn-primary:hover {
      background: linear-gradient(135deg, var(--secondary), var(--accent));
      transform: translateY(-2px);
      box-shadow: 0 18px 36px rgba(15, 28, 25, 0.18);
    }

    .btn-primary:active {
      transform: translateY(0);
    }

    .btn-secondary {
      background: rgba(255, 255, 255, 0.16);
      border: 1.5px solid rgba(255, 255, 255, 0.38);
      color: white;
      backdrop-filter: blur(10px);
    }

    .btn-secondary:hover {
      background: rgba(255, 255, 255, 0.22);
      transform: translateY(-1px);
    }

    .cta-group {
      display: flex;
      gap: 1.2rem;
      flex-wrap: wrap;
      margin-top: 2rem;
      justify-content: center;
    }

    .footer-enhanced {
      background: linear-gradient(180deg, #0f4f43 0%, #0b3a30 100%);
      color: #e5f4ec;
      padding: 3.5rem 0 1.5rem;
      margin-top: 2rem;
      position: relative;
      overflow: hidden;
    }

    .footer-enhanced::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: radial-gradient(circle at 10% 20%, rgba(115, 199, 180, 0.16), transparent 70%);
      pointer-events: none;
    }

    .footer-content {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 2.5rem;
      margin-bottom: 2rem;
      position: relative;
      z-index: 1;
    }

    .footer-section h4 {
      color: white;
      margin-bottom: 1.2rem;
      font-size: 1.25rem;
      font-weight: 600;
      position: relative;
      display: inline-block;
    }

    .footer-section h4::after {
      content: '';
      position: absolute;
      bottom: -6px;
      left: 0;
      width: 32px;
      height: 2px;
      background: var(--accent);
      border-radius: 2px;
    }

    .footer-section p, .footer-section a {
      color: #c8e6df;
      text-decoration: none;
      display: block;
      margin-bottom: 0.6rem;
      transition: color 0.2s ease, transform 0.2s ease;
      font-size: 0.95rem;
    }

    .footer-section a:hover {
      color: var(--accent);
      transform: translateX(4px);
    }

    .social-icons {
      display: flex;
      gap: 1rem;
      margin-top: 0.5rem;
    }

    .social-icons a {
      background: rgba(255,255,255,0.12);
      width: 42px;
      height: 42px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
      transition: all 0.2s ease;
      text-decoration: none;
      color: white;
    }

    .social-icons a:hover {
      background: var(--secondary);
      color: white;
      transform: scale(1.05);
    }

    .footer-bottom {
      text-align: center;
      padding-top: 1.5rem;
      border-top: 1px solid rgba(115, 199, 180, 0.25);
      font-size: 0.85rem;
      color: #b9dad2;
    }

    /* Responsive */
    @media (max-width: 992px) {
      .about-article-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (max-width: 768px) {
      .container {
        padding: 0 24px;
      }
      h1 {
        font-size: 2.8rem;
      }
      h2 {
        font-size: 2rem;
      }
      .section {
        padding: 60px 0;
      }
      .hero {
        padding: 110px 0 70px;
      }
      .hero-content p {
        font-size: 1.1rem;
      }
      .about-article-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
      }
      .grid {
        gap: 1.5rem;
      }
      .card {
        padding: 1.5rem;
      }
      /* Increase zoom on tablet to hide watermark */
      .hero-bg-canvas spline-viewer {
        transform: translate(-50%, -50%) scale(1.6);
      }
    }

    @media (max-width: 480px) {
      h1 {
        font-size: 2.2rem;
      }
      .btn {
        padding: 0.7rem 1.5rem;
      }
      /* Even stronger zoom on small phones */
      .hero-bg-canvas spline-viewer {
        transform: translate(-50%, -50%) scale(1.7);
      }
    }

    /* Scroll animations */
    .fade-up-section {
      opacity: 0;
      transform: translateY(32px);
      transition: opacity 0.7s cubic-bezier(0.2, 0.9, 0.3, 1.1), transform 0.7s ease-out;
      will-change: opacity, transform;
    }

    .fade-up-section.visible {
      opacity: 1;
      transform: translateY(0);
    }

    a:focus-visible, .btn:focus-visible {
      outline: 3px solid var(--accent);
      outline-offset: 2px;
      border-radius: 48px;
    }

    img {
      border-radius: 20px 20px 0 0;
    }

    .contact-item {
      text-align: center;
      background: white;
    }

    .contact-item h3 {
      font-size: 1.3rem;
      font-weight: 600;
      color: var(--primary);
    }
  </style>
</head>
<body>
  <section id="home" class="hero fade-up-section">
    <div class="hero-bg-canvas">
      <spline-viewer url="https://prod.spline.design/9BNhFhTDvHT5pqDj/scene.splinecode"></spline-viewer>
    </div>
    <div class="container">
      <div class="hero-content">
        <h1>Dents-City</h1>
        <p>Modern dental care with seamless appointment booking, comprehensive patient records, and intelligent admin analytics. Experience dentistry redefined.</p>
        <div class="cta-group">
          <a href="client/book.php" class="btn btn-primary">Book Appointment</a>
          <a href="#services" class="btn btn-secondary">View Services</a>
        </div>
        <div class="hero-scroll" role="presentation">
          <span class="scroll-icon" aria-hidden="true"></span>
          <span>Scroll Down</span>
        </div>
      </div>
    </div>
  </section>

  <section class="section features fade-up-section">
    <div class="container">
      <h2 class="section-title">Why Choose Dents-City?</h2>
      <div class="grid">
        <div class="card">
          <div class="icon"><i class="fas fa-calendar-alt"></i></div>
          <h3>Smart Booking</h3>
          <p>30-minute slots with real-time availability. No more double-bookings or scheduling conflicts.</p>
        </div>
        <div class="card">
          <div class="icon"><i class="fas fa-mobile-screen-button"></i></div>
          <h3>Passwordless Patient Access</h3>
          <p>Book and review appointments with SMS OTP. The same mobile number keeps your patient history linked automatically.</p>
        </div>
        <div class="card">
          <div class="icon"><i class="fas fa-chart-line"></i></div>
          <h3>Admin Power</h3>
          <p>Advanced analytics, walk-in management, and real-time appointment oversight for dental practices.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="section about section-alt fade-up-section" id="about">
    <div class="container">
      <h2 class="section-title">About Dents-City</h2>
      <div class="about-article-grid">
        <div class="about-card">
          <h3>Our Mission</h3>
          <p>Dents-City was built to redefine dental care through technology and compassion. Our platform connects patients with top-tier dental services, enabling seamless booking, digital records, and transparent communication. We believe a healthy smile starts with a stress-free experience.</p>
          <p>From routine checkups to complex procedures, we combine modern tools with a human touch, ensuring every visit is efficient, comfortable, and empowering.</p>
        </div>
        <div class="about-card">
          <h3>The Clinic</h3>
          <p>Our state-of-the-art dental clinic is equipped with cutting-edge technology and staffed by a dedicated team of specialists. We offer comprehensive services including preventive care, restorative treatments, orthodontics, and cosmetic dentistry. Patient safety, comfort, and long-term oral health are at the heart of everything we do.</p>
          <p>With years of experience and a commitment to excellence, we've built a reputation for gentle, precise, and personalized care.</p>
        </div>
        <div class="about-card">
          <h3>Office Location</h3>
          <p>123 Dental Street, Cityville, ST 12345<br>Free parking available | Wheelchair accessible</p>
          <div class="map-placeholder">
            <img src="https://placehold.co/600x300/e2f0ec/1f816a?text=Interactive+Map+Preview" alt="Map location placeholder">
          </div>
          <p><i class="fas fa-clock"></i> Mon-Fri: 9:00 AM – 6:00 PM<br><i class="fas fa-phone-alt"></i> (555) 123-4567</p>
        </div>
      </div>
      <div class="grid about-stats-grid">
        <div class="stat card">
          <h3>500+</h3>
          <p>Happy Patients</p>
        </div>
        <div class="stat card">
          <h3>10+</h3>
          <p>Years Experience</p>
        </div>
        <div class="stat card">
          <h3>50+</h3>
          <p>Services Offered</p>
        </div>
      </div>
    </div>
  </section>

  <section class="section services fade-up-section" id="services">
    <div class="container">
      <h2 class="section-title">Comprehensive Dental Services</h2>
      <div class="grid service-grid">
        <div class="card service-card"><h3>Dental Checkup</h3><p>Comprehensive oral examination and preventive care.</p></div>
        <div class="card service-card"><h3>Teeth Cleaning</h3><p>Professional deep cleaning and polishing.</p></div>
        <div class="card service-card"><h3>Fillings</h3><p>Durable composite fillings for cavities.</p></div>
        <div class="card service-card"><h3>Root Canal</h3><p>Save your natural tooth with endodontic treatment.</p></div>
        <div class="card service-card"><h3>Tooth Extraction</h3><p>Safe removal when necessary.</p></div>
        <div class="card service-card"><h3>Teeth Whitening</h3><p>Professional brightening for a dazzling smile.</p></div>
        <div class="card service-card"><h3>Orthodontics</h3><p>Braces and aligners for perfect alignment.</p></div>
      </div>
    </div>
  </section>

  <section class="section location section-alt fade-up-section" id="location">
    <div class="container">
      <h2 class="section-title">Our Clinic Inside</h2>
      <div class="grid location-grid">
        <div class="card location-item"><img src="assets/images/facility.jpeg" alt="Modern facility"><h3>Modern Facility</h3><p>Welcome to our contemporary dental clinic.</p></div>
        <div class="card location-item"><img src="assets/images/waitroom.jfif" alt="Patient waiting area"><h3>Comfortable Waiting</h3><p>Relax in our cozy reception area.</p></div>
        <div class="card location-item"><img src="assets/images/room.jpg" alt="Treatment rooms"><h3>State-of-the-Art Rooms</h3><p>Equipped for all your dental needs.</p></div>
      </div>
    </div>
  </section>

  <section class="section contacts fade-up-section" id="contact">
    <div class="container">
      <h2 class="section-title">Get In Touch</h2>
      <div class="grid contact-grid">
        <div class="card contact-item"><div class="contact-icon"><i class="fas fa-phone-alt"></i></div><h3>(555) 123-4567</h3><p>Mon-Fri 9AM-6PM</p></div>
        <div class="card contact-item"><div class="contact-icon"><i class="fas fa-envelope"></i></div><h3>info@dentscity.com</h3><p>Reply within 24 hours</p></div>
        <div class="card contact-item"><div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div><h3>123 Dental St, City, ST 12345</h3><p>Free parking available</p></div>
      </div>
    </div>
  </section>

  <footer class="footer-enhanced">
    <div class="container">
      <div class="footer-content grid">
        <div class="footer-section"><h4>Dents-City Dental</h4><p>Your trusted partner for modern dental care and seamless appointment management.</p></div>
        <div class="footer-section"><h4>Quick Links</h4><a href="#home">Home</a><a href="#about">About</a><a href="#services">Services</a><a href="#location">Location</a><a href="#contact">Contact</a></div>
        <div class="footer-section"><h4>Patient Access</h4><a href="client/book.php">Book Appointment</a><a href="my-bookings.php">My Bookings</a><a href="login.php">Staff Login</a></div>
        <div class="footer-section"><h4>Follow Us</h4><div class="social-icons"><a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a><a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a><a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a></div></div>
      </div>
      <div class="footer-bottom"><p>&copy; <?php echo date('Y'); ?> Dents-City Dental Clinic. All rights reserved.</p></div>
    </div>
  </footer>

  <script>
    (function() {
      const fadeElements = document.querySelectorAll('.fade-up-section');
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.1, rootMargin: "0px 0px -20px 0px" });
      fadeElements.forEach(el => observer.observe(el));
      window.addEventListener('load', () => {
        fadeElements.forEach(el => {
          const rect = el.getBoundingClientRect();
          if (rect.top < window.innerHeight - 100) {
            el.classList.add('visible');
            observer.unobserve(el);
          }
        });
      });
      document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
          const targetId = this.getAttribute('href');
          if (targetId === "#" || targetId === "") return;
          const targetElement = document.querySelector(targetId);
          if (targetElement) {
            e.preventDefault();
            targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        });
      });
    })();
  </script>
</body>
</html>
