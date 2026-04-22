<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Accounts Removed | <?php echo e(APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/auth.css">
    <style>
        .notice-shell {
            min-height: calc(100vh - 180px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .notice-card {
            max-width: 620px;
            width: 100%;
            background: #fff;
            border-radius: 28px;
            padding: 2.5rem 2rem;
            box-shadow: 0 20px 60px rgba(17, 70, 56, 0.12);
            border: 1px solid rgba(31, 129, 106, 0.12);
        }

        .notice-card h1 {
            margin-bottom: 0.75rem;
            color: #17493c;
        }

        .notice-card p {
            color: #4a655b;
            line-height: 1.7;
        }

        .cta-row {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body class="auth-page">
<?php include dirname(__DIR__) . '/includes/public/partials/nav-public.php'; ?>

<main class="notice-shell">
    <div class="notice-card">
        <h1>Patient Passwords Have Been Removed</h1>
        <p>Patients no longer create accounts on this page. Booking now uses SMS OTP, which is faster for patients and keeps appointment history tied to the same verified mobile number.</p>
        <p>Staff/admin login is still available with email and password.</p>
        <div class="cta-row">
            <a href="client/book.php" class="btn-solid">Book Appointment</a>
            <a href="my-bookings.php" class="btn-outline">My Bookings</a>
            <a href="login.php" class="btn-outline">Staff Login</a>
        </div>
    </div>
</main>

<?php include dirname(__DIR__) . '/includes/public/partials/footer.php'; ?>
</body>
</html>
