<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-In Follow Up | <?php echo e(APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page">
<?php include dirname(__DIR__) . '/includes/public/partials/nav-public.php'; ?>

<main class="auth-section">
    <div class="auth-card" style="max-width: 600px;">
        <div class="auth-header">
            <h1>Walk-In Accounts No Longer Need Passwords</h1>
            <p>If the clinic created your record during a walk-in visit, use the same mobile number for future OTP booking and history lookup.</p>
        </div>
        <div class="success-message">
            Your patient history is preserved by mobile number, so there is no separate account activation step anymore.
        </div>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <a href="client/book.php" class="btn-auth" style="text-decoration: none; text-align: center;">Book Appointment</a>
            <a href="my-bookings.php" class="btn-outline" style="text-decoration: none; text-align: center; padding: 1rem 1.2rem;">View My Bookings</a>
            <a href="login.php" class="btn-outline" style="text-decoration: none; text-align: center; padding: 1rem 1.2rem;">Staff Login</a>
        </div>
    </div>
</main>

<?php include dirname(__DIR__) . '/includes/public/partials/footer.php'; ?>
</body>
</html>
