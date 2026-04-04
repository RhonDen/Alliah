<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirectForRole();
}

$flash = getFlashMessage();
$error = '';
$form = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'mobile' => '',
    'age' => '',
];

if (isPostRequest()) {
    $form['first_name'] = trim($_POST['first_name'] ?? '');
    $form['last_name'] = trim($_POST['last_name'] ?? '');
    $form['email'] = trim($_POST['email'] ?? '');
    $raw_mobile = trim($_POST['mobile'] ?? '');
    $form['mobile'] = normalizeMobile($raw_mobile);
    $form['age'] = trim($_POST['age'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $age = filter_var($form['age'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 120]]);

    $errors = [];
    if (!isValidCsrfToken($_POST['_token'] ?? null)) {
        $errors[] = 'Your session expired. Please submit the form again.';
    }
    if ($form['first_name'] === '' || $form['last_name'] === '') {
        $errors[] = 'Please provide first and last name.';
    }
    if (!empty($form['email']) && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email.';
    }
    if (empty($form['mobile'])) {
        $errors[] = 'Invalid mobile number.';
    }
    if ($age === false) {
        $errors[] = 'Valid age (1-120) required.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be 8+ chars.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords mismatch.';
    }
    $error = implode(' ', $errors);

    if (!empty($errors)) {
        $error = implode("\n", $errors);
    } else {

        $emailCondition = !empty($form['email']) ? 'email = ? OR ' : '';
        $params = [];
        if (!empty($form['email'])) {
            $params[] = $form['email'];
        }
        $params[] = $form['mobile'];

        $existingUser = findUserByIdentifier($pdo, $form['mobile']) ?: ($form['email'] ? findUserByIdentifier($pdo, $form['email']) : null);
        
        if ($existingUser) {
            if (empty($existingUser['password'])) {
                // Existing guest - redirect to set-password
                setFlashMessage('info', 'Account exists! Set your password to activate.');
                redirect('set-password.php?identifier=' . urlencode($form['mobile'] ?: $form['email']));
            } else {
                $error = 'Account already exists with this mobile/email. Please log in.';
            }
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (role, first_name, last_name, email, mobile, age, password)
                 VALUES ('client', ?, ?, ?, ?, ?, ?)"
            );

            if ($stmt->execute([$form['first_name'], $form['last_name'], $form['email'], $form['mobile'], $age, $hashed])) {
                setFlashMessage('success', 'Registration successful. Please log in.');
                redirect('login.php');
            }
            $error = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Create Account | Dents-City Dental Clinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Copy the exact same styles from login.php (navbar, auth, footer) */
        /* (I'll paste them here, but to save space, assume you copy them from above) */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, sans-serif;
            background: linear-gradient(135deg, #1f816a, #166b57);
            min-height: 100vh;
            color: #1a202c;
        }
        .navbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(252, 253, 253, 0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(115, 199, 180, 0.3);
            padding: 0.8rem 2rem;
        }
        .nav-container {
            max-width: 1280px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .logo a {
            font-size: 1.8rem;
            font-weight: 800;
            text-decoration: none;
            background: linear-gradient(135deg, #1f816a, #48a48f);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            letter-spacing: -0.02em;
        }
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .nav-links a {
            text-decoration: none;
            color: #2c554b;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        .nav-links a:hover {
            color: #1f816a;
        }
        .btn-outline {
            border: 1.5px solid #1f816a;
            background: transparent;
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-weight: 600;
            color: #1f816a;
            transition: all 0.2s ease;
        }
        .btn-outline:hover {
            background: #1f816a;
            color: white;
            transform: scale(1.02);
        }
        .btn-solid {
            background: #1f816a;
            color: white;
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-solid:hover {
            background: #48a48f;
            transform: scale(1.02);
        }
        .navbar-hamburger {
            display: none;
            flex-direction: column;
            cursor: pointer;
        }
        .hamburger-line {
            width: 25px;
            height: 3px;
            background: #1f816a;
            margin: 3px 0;
            border-radius: 3px;
        }
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 1rem;
            }
            .navbar-hamburger {
                display: flex;
            }
            .nav-links {
                display: none;
                width: 100%;
                flex-direction: column;
                gap: 1rem;
                text-align: center;
                padding: 1rem 0;
            }
            .nav-links.open {
                display: flex;
            }
        }
        .auth-section {
            min-height: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1.5rem;
            background: linear-gradient(145deg, #f0f8f5, #fafefc);
        }
        .auth-card {
            max-width: 520px;
            width: 100%;
            background: white;
            border-radius: 32px;
            padding: 2.5rem 2rem;
            box-shadow: 0 12px 28px -8px rgba(31, 129, 106, 0.12), 0 4px 12px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(115, 199, 180, 0.3);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .auth-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 45px -12px rgba(31, 129, 106, 0.25);
        }
        .auth-card h2 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #1a3a34;
        }
        .auth-card p {
            color: #5c7a72;
            margin-bottom: 1.8rem;
        }
        .input-group {
            margin-bottom: 1.5rem;
        }
        .input-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #2c554b;
        }
        .input-group input {
            width: 100%;
            padding: 0.9rem 1rem;
            border: 1px solid #d0e2dc;
            border-radius: 24px;
            font-family: inherit;
            font-size: 1rem;
            transition: all 0.2s ease;
            background: #fefefe;
        }
        .input-group input:focus {
            outline: none;
            border-color: #1f816a;
            box-shadow: 0 0 0 3px rgba(31, 129, 106, 0.2);
        }
        .btn-auth {
            width: 100%;
            background: #1f816a;
            color: white;
            border: none;
            padding: 0.9rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 1.5rem;
        }
        .btn-auth:hover {
            background: #48a48f;
            transform: scale(1.02);
        }
        .auth-link {
            text-align: center;
            color: #5c7a72;
        }
        .auth-link a {
            color: #1f816a;
            text-decoration: none;
            font-weight: 600;
        }
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0;
            color: #b3d9cf;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e2f0ec;
        }
        .divider span {
            padding: 0 0.8rem;
        }
        .error-message {
            background: #fed7d7;
            color: #c53030;
            padding: 0.75rem 1rem;
            border-radius: 20px;
            margin-bottom: 1.5rem;
            border: 1px solid #fc8181;
        }
        .terms {
            font-size: 0.8rem;
            text-align: center;
            margin-top: 1.2rem;
            color: #7a9a8e;
        }
        .footer-enhanced {
            background: #0e3f36;
            color: #e0f0ec;
            padding: 3rem 0 1.5rem;
            margin-top: 2rem;
            position: relative;
            overflow: hidden;
        }
        .footer-enhanced .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 32px;
        }
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 2.5rem;
            margin-bottom: 2rem;
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
            background: #73c7b4;
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
            color: #73c7b4;
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
            background: #48a48f;
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
        @media (max-width: 768px) {
            .auth-card {
                padding: 1.8rem;
            }
            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
        }
    </style>

    <script src="assets/js/phone-format.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.querySelector('.navbar-hamburger');
            const nav = document.querySelector('.nav-links');
            if (hamburger && nav) {
                hamburger.addEventListener('click', () => nav.classList.toggle('open'));
                document.querySelectorAll('.nav-link').forEach(link => {
                    link.addEventListener('click', () => nav.classList.remove('open'));
                });
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') nav.classList.remove('open');
                });
            }
        });
    </script>

</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo"><a href="index.php">Dents-City</a></div>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="index.php#services">Services</a>
                <a href="index.php#about">About</a>
                <a href="login.php" class="btn-outline">Sign In</a>
                <a href="register.php" class="btn-solid">Register</a>
            </div>
            <div class="navbar-hamburger">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </div>
        </div>
    </nav>

    <div class="auth-section">
        <div class="auth-card">
            <h2>Create an account</h2>
            <p>Join Dents-City for a seamless dental experience</p>

            <?php if ($flash): ?>
                <div class="error-message"><?php echo e($flash['message']); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error-message"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <?php echo csrfField(); ?>
                <div class="input-group">
                    <label for="first_name">Full name</label>
                    <input type="text" id="first_name" name="first_name" placeholder="First name" value="<?php echo e($form['first_name']); ?>" required>
                </div>
                <div class="input-group">
                    <input type="text" name="last_name" placeholder="Last name" value="<?php echo e($form['last_name']); ?>" required>
                </div>
                <div class="input-group">
                    <label for="email">Email (optional)</label>
                    <input type="email" id="email" name="email" placeholder="hello@dentscity.com" value="<?php echo e($form['email']); ?>">
                </div>
                <div class="input-group">
                    <label for="mobile">Mobile Number <span style="color: #1f816a;">63+</span></label>

                    <div style="position: relative;">
                        <input type="tel" id="mobile" name="mobile" placeholder="0999-999-9999" value="<?php echo e($form['mobile']); ?>" required style="padding-left: 20px;" class="phone-field">
                    </div>

                </div>
                <div class="input-group">
                    <label for="age">Age</label>
                    <input type="number" id="age" name="age" placeholder="25" value="<?php echo e($form['age']); ?>" min="1" max="120" required>
                </div>
                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="At least 8 characters" required>
                </div>
                <div class="input-group">
                    <label for="confirm_password">Confirm password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn-auth">Get Started →</button>
            </form>

            <p class="auth-link">Already have an account? <a href="login.php">Sign in</a></p>
            <div class="terms">By registering, you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.</div>
        </div>
    </div>

    <footer class="footer-enhanced">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>Dents-City Dental</h4>
                    <p>Your trusted partner for modern dental care.</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <a href="index.php">Home</a>
                    <a href="index.php#services">Services</a>
                    <a href="index.php#about">About</a>
                    <a href="login.php">Login</a>
                </div>
                <div class="footer-section">
                    <h4>Contact</h4>
                    <p>(555) 123-4567</p>
                    <p>info@dentscity.com</p>
                </div>
                <div class="footer-section">
                    <h4>Follow Us</h4>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Dents-City Dental Clinic. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>