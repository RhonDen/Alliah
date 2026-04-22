<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (isset($_GET['reset'])) {
    unset($_SESSION['history_otp']);
    redirect('my-bookings.php');
}

$errors = [];
$step = empty($_SESSION['history_otp']) ? 1 : 2;
$mobileInput = '';
$patient = null;
$appointments = [];
$stats = null;

if (!empty($_SESSION['history_otp']['mobile'])) {
    $mobileInput = formatMobileForDisplay($_SESSION['history_otp']['mobile']);
}

if (isPostRequest() && isset($_POST['request_otp'])) {
    $step = 1;
    $mobileInput = trim($_POST['mobile'] ?? '');

    if (!isValidCsrfToken($_POST['_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $patient = findClientByMobile($pdo, $mobileInput);
        if (!$patient) {
            $errors[] = 'No patient record was found for that mobile number yet.';
        } else {
            $otp = generateOtp();
            $_SESSION['history_otp'] = [
                'otp' => $otp,
                'expires' => time() + OTP_EXPIRY_SECONDS,
                'user_id' => (int) $patient['id'],
                'mobile' => $patient['mobile'],
            ];

            if (!sendOtpViaSms($patient['mobile'], $otp)) {
                unset($_SESSION['history_otp']);
                $errors[] = 'Failed to send OTP. Please try again.';
            } else {
                $step = 2;
                $mobileInput = formatMobileForDisplay($patient['mobile']);
            }
        }
    }
}

if (isPostRequest() && isset($_POST['verify_otp'])) {
    $step = 2;
    $lookupData = $_SESSION['history_otp'] ?? null;
    $submittedOtp = trim($_POST['otp'] ?? '');

    if (!isValidCsrfToken($_POST['_token'] ?? null)) {
        $errors[] = 'Your session expired. Please start again.';
    } elseif (!$lookupData) {
        $errors[] = 'Session expired. Please request a new OTP.';
        $step = 1;
    } elseif (time() > (int) ($lookupData['expires'] ?? 0)) {
        unset($_SESSION['history_otp']);
        $errors[] = 'OTP expired. Please request a new one.';
        $step = 1;
    } elseif ($submittedOtp !== (string) ($lookupData['otp'] ?? '')) {
        $errors[] = 'Invalid OTP. Please try again.';
    } else {
        $patient = getUser($pdo, (int) $lookupData['user_id']);
        if (!$patient || $patient['role'] !== 'client') {
            unset($_SESSION['history_otp']);
            $errors[] = 'Patient record not found.';
            $step = 1;
        } else {
            $appointments = getUserAppointments($pdo, (int) $patient['id']);
            $stats = getUserAppointmentStats($pdo, (int) $patient['id']);
            $mobileInput = formatMobileForDisplay($patient['mobile']);
            unset($_SESSION['history_otp']);
            $step = 3;
        }
    }
}

$lookupData = $_SESSION['history_otp'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings | <?php echo e(APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/client.css">
    <style>
        .history-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .history-stat {
            background: linear-gradient(180deg, rgba(240, 248, 245, 0.95), rgba(255, 255, 255, 0.95));
            border: 1px solid rgba(31, 129, 106, 0.15);
            border-radius: 20px;
            padding: 1rem;
        }

        .history-stat strong {
            display: block;
            font-size: 1.6rem;
            color: #1b5445;
            margin-bottom: 0.2rem;
        }
    </style>
</head>
<body>
<?php include dirname(__DIR__) . '/includes/public/partials/nav-public.php'; ?>

<div class="client-container">
    <main>
        <?php if (!empty($errors)): ?>
            <div class="error-message"><?php echo nl2br(e(implode("\n", $errors))); ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <div class="card">
                <h3>View My Bookings</h3>
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <div>
                        <label for="mobile">Mobile Number</label>
                        <input type="tel" id="mobile" name="mobile" placeholder="09XX-XXX-XXXX" value="<?php echo e($mobileInput); ?>" required>
                    </div>
                    <button type="submit" name="request_otp" class="btn-submit">Send OTP</button>
                </form>
            </div>
        <?php elseif ($step === 2 && $lookupData): ?>
            <div class="card">
                <h3>Verify Your Mobile</h3>
                <p style="margin-bottom: 1rem; color: #597469;">Code sent to <?php echo e(formatMobileForDisplay($lookupData['mobile'])); ?>.</p>
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <div>
                        <label for="otp">OTP Code</label>
                        <input type="text" id="otp" name="otp" inputmode="numeric" maxlength="6" placeholder="123456" required>
                    </div>
                    <button type="submit" name="verify_otp" class="btn-submit">View My Bookings</button>
                </form>
                <p style="margin-top: 1rem; text-align: center;">
                    <a href="my-bookings.php?reset=1" style="color: #1f816a; font-weight: 600;">Use a different mobile number</a>
                </p>
                <?php if (SMS_MOCK_MODE): ?>
                    <div class="success-message" style="margin-top: 1rem;">Development mode: OTP is <?php echo e($lookupData['otp']); ?>. It is also written to <code>logs/sms_mock.log</code>.</div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <h3><?php echo e($patient['first_name'] . ' ' . $patient['last_name']); ?></h3>
                <p style="margin-bottom: 1rem; color: #597469;">Mobile: <?php echo e($mobileInput); ?></p>

                <?php if ($stats): ?>
                    <div class="history-stats">
                        <div class="history-stat">
                            <strong><?php echo e($stats['total']); ?></strong>
                            <span>Total bookings</span>
                        </div>
                        <div class="history-stat">
                            <strong><?php echo e($stats['upcoming']); ?></strong>
                            <span>Upcoming</span>
                        </div>
                        <div class="history-stat">
                            <strong><?php echo e($stats['completed']); ?></strong>
                            <span>Completed</span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (empty($appointments)): ?>
                    <p>No bookings were found yet for this mobile number.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="appointments-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Service</th>
                                    <th>Status</th>
                                    <th>Admin Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td><?php echo e(formatDateForDisplay($appointment['appointment_date'])); ?></td>
                                        <td><?php echo e(formatAppointmentTime($appointment['appointment_time'])); ?></td>
                                        <td><?php echo e($appointment['service_name']); ?></td>
                                        <td><span class="status-badge status-<?php echo e($appointment['status']); ?>"><?php echo e(ucfirst($appointment['status'])); ?></span></td>
                                        <td><?php echo e($appointment['admin_message'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem; flex-wrap: wrap;">
                    <a href="client/book.php" class="btn-book">Book Another Appointment</a>
                    <a href="my-bookings.php?reset=1" class="btn-submit" style="text-decoration: none;">Lookup Another Number</a>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<script src="<?php echo e(BASE_URL . 'assets/js/phone-format.js'); ?>"></script>
<?php include dirname(__DIR__) . '/includes/public/partials/footer.php'; ?>
</body>
</html>
