<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (isLoggedIn()) {
    if (currentUserRole() === 'admin') {
        redirectForRole('admin');
    }

    redirect('my-bookings.php');
}

$flash = getFlashMessage();
$error = '';
$email = '';

if (isPostRequest()) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!isValidCsrfToken($_POST['_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid staff email address.';
    } elseif ($password === '') {
        $error = 'Please enter your password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'admin' AND email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !userHasPassword($user) || !password_verify($password, $user['password'])) {
            $error = 'Invalid staff credentials.';
        } else {
            loginUser($user);
            redirectForRole('admin');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login | <?php echo e(APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <style>
        .auth-page-shell {
            min-height: calc(100vh - 180px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .otp-notice {
            margin-top: 1.2rem;
            padding: 1rem 1.1rem;
            border-radius: 20px;
            background: linear-gradient(180deg, rgba(240, 248, 245, 0.95), rgba(255, 255, 255, 0.95));
            border: 1px solid rgba(31, 129, 106, 0.14);
        }

        .otp-notice h3 {
            margin-bottom: 0.45rem;
            color: #18493d;
        }

        .quick-links {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
    </style>
</head>
<body class="auth-page">
<?php include dirname(__DIR__) . '/includes/public/partials/nav-public.php'; ?>

<main class="auth-page-shell">
    <div class="auth-card" style="max-width: 520px;">
        <div class="auth-header">
            <i class="fas fa-user-shield" style="font-size: 2.8rem; color: #1f816a; margin-bottom: 1rem;"></i>
            <h1>Staff Login</h1>
            <p>Admin access still uses email and password. Patient booking now happens with SMS OTP instead.</p>
        </div>

        <?php if ($flash): ?>
            <div class="<?php echo e($flash['type'] === 'success' ? 'success-message' : 'error-message'); ?>">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="error-message"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="POST" class="password-form">
            <?php echo csrfField(); ?>
            <div class="input-group">
                <label for="email">Staff Email</label>
                <input type="email" id="email" name="email" value="<?php echo e($email); ?>" placeholder="admin@clinic.com" required>
            </div>
            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-auth btn-primary">Sign In</button>
        </form>

        <div class="otp-notice">
            <h3>For Patients</h3>
            <p>You no longer need to create an account or remember a password. Use your mobile number and SMS OTP to book or view your appointment history.</p>
            <div class="quick-links">
                <a href="client/book.php" class="btn-solid">Book Appointment</a>
                <a href="my-bookings.php" class="btn-outline">My Bookings</a>
            </div>
        </div>
    </div>
</main>

<?php include dirname(__DIR__) . '/includes/public/partials/footer.php'; ?>
</body>
</html>
