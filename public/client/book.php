<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if (isset($_GET['reset'])) {
    unset($_SESSION['booking_otp']);
    redirect('client/book.php');
}

$services = getServices($pdo);
$errors = [];
$success = '';
$step = empty($_SESSION['booking_otp']) ? 1 : 2;

$form = [
    'first_name' => '',
    'last_name' => '',
    'mobile' => '',
    'age' => '',
    'service_id' => '',
    'date' => date('Y-m-d'),
    'time' => '',
];

if (!empty($_SESSION['booking_otp'])) {
    $storedBooking = $_SESSION['booking_otp'];
    $form = [
        'first_name' => $storedBooking['first_name'] ?? '',
        'last_name' => $storedBooking['last_name'] ?? '',
        'mobile' => $storedBooking['mobile_display'] ?? '',
        'age' => $storedBooking['age'] ?? '',
        'service_id' => $storedBooking['service_id'] ?? '',
        'date' => $storedBooking['date'] ?? date('Y-m-d'),
        'time' => $storedBooking['time'] ?? '',
    ];
}

if (isPostRequest() && isset($_POST['request_otp'])) {
    $step = 1;
    $form['first_name'] = trim($_POST['first_name'] ?? '');
    $form['last_name'] = trim($_POST['last_name'] ?? '');
    $form['mobile'] = trim($_POST['mobile'] ?? '');
    $form['age'] = trim($_POST['age'] ?? '');
    $form['service_id'] = trim($_POST['service_id'] ?? '');
    $form['date'] = trim($_POST['date'] ?? '');
    $form['time'] = normalizeTime($_POST['time'] ?? '') ?? '';

    if (!isValidCsrfToken($_POST['_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }
    if ($form['first_name'] === '' || $form['last_name'] === '') {
        $errors[] = 'Full name is required.';
    }

    $normalizedMobileDisplay = normalizeMobile($form['mobile']);
    $normalizedMobileSms = normalizeMobileForSms($form['mobile']);
    if ($normalizedMobileSms === null) {
        $errors[] = 'A valid mobile number is required.';
    }

    $age = filter_var($form['age'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 120]]);
    if ($age === false) {
        $errors[] = 'A valid age is required.';
    }

    $validation = validateAppointmentRequest($pdo, 0, $form['service_id'], $form['date'], $form['time']);
    if (!empty($validation['errors'])) {
        $errors = array_merge($errors, $validation['errors']);
    }

    if (empty($errors)) {
        $verification = requestOtpViaSms($normalizedMobileSms);
        if ($verification === null) {
            $errors[] = 'Failed to send OTP. Please try again.';
        } else {
            $_SESSION['booking_otp'] = [
                'verification' => $verification,
                'expires' => time() + OTP_EXPIRY_SECONDS,
                'sent_at' => time(),
                'resend_count' => 0,
                'mobile' => $normalizedMobileSms,
                'mobile_display' => $normalizedMobileDisplay,
                'mobile_sms' => $normalizedMobileSms,
                'first_name' => $form['first_name'],
                'last_name' => $form['last_name'],
                'age' => (int) $age,
                'service_id' => (int) $validation['service_id'],
                'date' => $validation['date'],
                'time' => $validation['time'],
            ];
            $step = 2;
            $form['mobile'] = $normalizedMobileDisplay;
            $form['age'] = (string) $age;
            $form['service_id'] = (string) $validation['service_id'];
            $form['date'] = $validation['date'];
            $form['time'] = $validation['time'];
        }
    }
}

if (isPostRequest() && isset($_POST['resend_otp'])) {
    $step = 2;
    $bookingData = $_SESSION['booking_otp'] ?? null;

    if (!isValidCsrfToken($_POST['_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (!$bookingData) {
        $errors[] = 'Session expired. Please request a new OTP.';
        $step = 1;
    } elseif (time() > (int) ($bookingData['expires'] ?? 0)) {
        unset($_SESSION['booking_otp']);
        $errors[] = 'OTP expired. Please request a new one.';
        $step = 1;
    } else {
        $resendCount = (int) ($bookingData['resend_count'] ?? 0);
        $lastSent = (int) ($bookingData['sent_at'] ?? 0);
        $cooldownRemaining = 60 - (time() - $lastSent);

        if ($resendCount >= 3) {
            $errors[] = 'Maximum resend attempts reached. Please start over.';
        } elseif ($cooldownRemaining > 0) {
            $errors[] = 'Please wait ' . $cooldownRemaining . ' seconds before resending.';
        } else {
            $mobile = $bookingData['mobile_sms'] ?? $bookingData['mobile'] ?? '';
            $verification = requestOtpViaSms($mobile);
            if ($verification === null) {
                $errors[] = 'Failed to resend OTP. Please try again.';
            } else {
                $_SESSION['booking_otp']['verification'] = $verification;
                $_SESSION['booking_otp']['expires'] = time() + OTP_EXPIRY_SECONDS;
                $_SESSION['booking_otp']['sent_at'] = time();
                $_SESSION['booking_otp']['resend_count'] = $resendCount + 1;
                $success = 'A new OTP has been sent to your mobile number.';
            }
        }
    }
}

if (isPostRequest() && isset($_POST['verify_otp'])) {
    $step = 2;
    $bookingData = $_SESSION['booking_otp'] ?? null;
    $submittedOtp = trim($_POST['otp'] ?? '');

    if (!isValidCsrfToken($_POST['_token'] ?? null)) {
        $errors[] = 'Your session expired. Please start again.';
    } elseif (!$bookingData) {
        $errors[] = 'Session expired. Please request a new OTP.';
        $step = 1;
    } elseif (time() > (int) ($bookingData['expires'] ?? 0)) {
        unset($_SESSION['booking_otp']);
        $errors[] = 'OTP expired. Please request a new one.';
        $step = 1;
    } elseif (!verifyOtpSubmission($bookingData['verification'] ?? null, $submittedOtp)) {
        $errors[] = 'Invalid OTP. Please try again.';
    } else {
        $userId = findOrCreateUserByMobile(
            $pdo,
            $bookingData['mobile_sms'],
            $bookingData['first_name'],
            $bookingData['last_name'],
            (int) $bookingData['age']
        );
        $result = bookAppointment(
            $pdo,
            $userId,
            $bookingData['service_id'],
            $bookingData['date'],
            $bookingData['time']
        );

        if ($result['success']) {
            unset($_SESSION['booking_otp']);
            $success = 'Appointment requested. We will text you again once the clinic approves it.';
            $step = 3;
        } else {
            $errors = array_merge($errors, $result['errors']);
        }
    }
}

$bookingData = $_SESSION['booking_otp'] ?? null;
$availableTimeSlots = $step === 1 && $form['date'] ? getAvailableTimeSlots($pdo, $form['date']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment | <?php echo e(APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/client.css">
    <style>
        .otp-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.9rem;
            margin: 1.25rem 0 1.5rem;
        }

        .otp-meta div {
            padding: 0.95rem 1rem;
            border-radius: 18px;
            background: #f4faf7;
            border: 1px solid rgba(31, 129, 106, 0.12);
        }

        .otp-meta strong {
            display: block;
            margin-bottom: 0.25rem;
            color: #1a4c3f;
        }

        .otp-dev {
            margin-top: 1rem;
            padding: 0.85rem 1rem;
            border-radius: 16px;
            background: #eef2f7;
            color: #334155;
            font-size: 0.95rem;
        }

        .otp-step {
            max-width: 420px;
            margin: 0 auto;
        }

        .otp-step h3 {
            text-align: center;
            font-size: 1.4rem;
            margin-bottom: 0.5rem;
        }

        .otp-phone {
            text-align: center;
            color: #567267;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }

        .otp-input-wrap {
            display: flex;
            justify-content: center;
            gap: 0.6rem;
            margin: 1.5rem 0;
        }

        .otp-digit {
            width: 54px;
            height: 64px;
            border: 2px solid #d1e7dd;
            border-radius: 14px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a4c3f;
            background: #f4faf7;
            transition: all 0.2s ease;
            caret-color: #1f816a;
        }

        .otp-digit:focus {
            outline: none;
            border-color: #1f816a;
            background: white;
            box-shadow: 0 0 0 4px rgba(31, 129, 106, 0.12);
        }

        .otp-verify-btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, #1f816a, #48a48f);
            color: white;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 0.5rem;
        }

        .otp-verify-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(31, 129, 106, 0.25);
        }

        .otp-verify-btn:active {
            transform: translateY(0);
        }

        .otp-timer {
            text-align: center;
            font-size: 0.9rem;
            color: #597469;
            margin: 1rem 0 0.5rem;
        }

        .otp-timer .timer-countdown {
            font-weight: 700;
            color: #1f816a;
        }

        .otp-timer.expired .timer-countdown {
            color: #dc2626;
        }

        .resend-form {
            text-align: center;
            margin-top: 0.25rem;
        }

        .resend-btn {
            background: none;
            border: none;
            color: #1f816a;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: underline;
            padding: 0.35rem 0.75rem;
            transition: color 0.2s;
        }

        .resend-btn:disabled {
            color: #94a3b8;
            cursor: not-allowed;
            text-decoration: none;
        }

        .resend-btn:hover:not(:disabled) {
            color: #166b57;
        }

        .otp-startover {
            text-align: center;
            margin-top: 1rem;
        }

        .otp-startover a {
            color: #1f816a;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
        }

        .otp-startover a:hover {
            text-decoration: underline;
        }

        .client-nav {
            margin-bottom: 1.25rem;
        }

        .client-nav-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: #f4faf7;
            color: #1f816a;
            text-decoration: none;
            transition: all 0.2s ease;
            margin-bottom: 0.5rem;
        }

        .client-nav-back:hover {
            background: #1f816a;
            color: white;
        }

        .client-nav-link {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: #1f816a;
            text-decoration: none;
            padding: 0.35rem 0;
        }

        .client-nav-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="client-container">
    <div class="client-nav">
        <a href="<?php echo e(BASE_URL); ?>" class="client-nav-back" aria-label="Back to home">
            <i class="fa-solid fa-chevron-left"></i>
        </a>
        <a href="<?php echo e(BASE_URL . 'my-bookings.php'); ?>" class="client-nav-link">Booking History</a>
    </div>
    <main>
        <?php if (!empty($errors)): ?>
            <div class="error-message"><?php echo nl2br(e(implode("\n", $errors))); ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="success-message"><?php echo e($success); ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <div class="card">
                <h3>Request an Appointment</h3>

                <form method="POST" class="booking-form" autocomplete="on">
                    <?php echo csrfField(); ?>

                    <div class="form-row">
                        <div>
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" autocomplete="given-name" value="<?php echo e($form['first_name']); ?>" required>
                        </div>
                        <div>
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" autocomplete="family-name" value="<?php echo e($form['last_name']); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div>
                            <label for="mobile">Mobile Number *</label>
                            <input type="tel" id="mobile" name="mobile" autocomplete="tel" placeholder="09XX-XXX-XXXX" value="<?php echo e($form['mobile']); ?>" required>
                        </div>
                        <div>
                            <label for="age">Age *</label>
                            <input type="number" id="age" name="age" min="1" max="120" autocomplete="off" value="<?php echo e($form['age']); ?>" required>
                        </div>
                    </div>

                    <div>
                        <label for="service_id">Service *</label>
                        <select id="service_id" name="service_id" required>
                            <option value="">Choose service</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo e($service['id']); ?>" <?php echo (string) $form['service_id'] === (string) $service['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($service['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div>
                            <label for="bookingDate">Preferred Date *</label>
                            <input type="date" id="bookingDate" name="date" min="<?php echo e(date('Y-m-d')); ?>" value="<?php echo e($form['date']); ?>" required>
                        </div>
                        <div>
                            <label for="bookingTime">Preferred Time *</label>
                            <select id="bookingTime" name="time" required>
                                <option value=""><?php echo empty($availableTimeSlots) ? 'Select date first' : 'Select time'; ?></option>
                                <?php foreach ($availableTimeSlots as $slot): ?>
                                    <option value="<?php echo e($slot); ?>" <?php echo $form['time'] === $slot ? 'selected' : ''; ?>>
                                        <?php echo e(formatAppointmentTime($slot)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <p class="form-note">Choose a date, then pick an available time. Tap the date field to open the calendar on mobile.</p>
                    <button type="submit" name="request_otp" class="btn-submit">Send OTP</button>
                </form>
            </div>
        <?php elseif ($step === 2 && $bookingData): ?>
            <div class="card otp-step">
                <h3>Verify Your Mobile Number</h3>
                <p class="otp-phone">Code sent to <?php echo e(formatMobileForDisplay($bookingData['mobile'])); ?></p>

                <div class="otp-meta">
                    <div>
                        <strong>Patient</strong>
                        <span><?php echo e($bookingData['first_name'] . ' ' . $bookingData['last_name']); ?></span>
                    </div>
                    <div>
                        <strong>Date</strong>
                        <span><?php echo e(formatDateForDisplay($bookingData['date'])); ?></span>
                    </div>
                    <div>
                        <strong>Time</strong>
                        <span><?php echo e(formatAppointmentTime($bookingData['time'])); ?></span>
                    </div>
                </div>

                <form method="POST">
                    <?php echo csrfField(); ?>
                    <div>
                        <label for="otp">OTP Code</label>
                        <input type="text" id="otp" name="otp" inputmode="numeric" maxlength="6" placeholder="123456" required style="text-align:center; font-size:1.5rem; letter-spacing:0.5rem; font-weight:700;">
                    </div>
                    <button type="submit" name="verify_otp" class="otp-verify-btn">Verify and Book</button>
                </form>

                <div class="otp-timer" id="otpTimer" data-expires="<?php echo e($bookingData['expires'] ?? 0); ?>" data-sent="<?php echo e($bookingData['sent_at'] ?? time()); ?>">
                    Expires in <span class="timer-countdown" id="timerCountdown">--:--</span>
                </div>

                <form method="POST" class="resend-form">
                    <?php echo csrfField(); ?>
                    <button type="submit" name="resend_otp" class="resend-btn" id="resendBtn" disabled>Resend OTP</button>
                </form>

                <div class="otp-startover">
                    <a href="book.php?reset=1">← Start over</a>
                </div>

                <?php if (SMS_MOCK_MODE): ?>
                    <div class="otp-dev">Development mode: OTP is <?php echo e($bookingData['verification']['otp'] ?? ''); ?>. The same code is also written to <code>logs/sms_mock.log</code>.</div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card" style="text-align: center;">
                <i class="fas fa-check-circle" style="font-size: 3rem; color: #10b981; margin-bottom: 1rem;"></i>
                <h3>Appointment Requested</h3>
                <p>We received your request and will send another SMS after the clinic approves or rejects it.</p>
                <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
                    <a href="book.php" class="btn-submit" style="text-decoration: none;">Book Another</a>
                    <a href="<?php echo e(BASE_URL . 'my-bookings.php'); ?>" class="btn-book">View My Bookings</a>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<script src="<?php echo e(BASE_URL . 'assets/js/phone-format.js'); ?>"></script>
<script>
(function() {
    const timerEl = document.getElementById('otpTimer');
    const countdownEl = document.getElementById('timerCountdown');
    const resendBtn = document.getElementById('resendBtn');

    if (!timerEl || !countdownEl || !resendBtn) return;

    const expiresAt = parseInt(timerEl.dataset.expires || '0', 10) * 1000;
    const sentAt = parseInt(timerEl.dataset.sent || '0', 10) * 1000;
    const cooldownMs = 60000; // 60 seconds

    function formatMs(ms) {
        if (ms <= 0) return '00:00';
        const totalSeconds = Math.ceil(ms / 1000);
        const m = Math.floor(totalSeconds / 60);
        const s = totalSeconds % 60;
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    function updateTimer() {
        const now = Date.now();
        const otpRemaining = expiresAt - now;
        const cooldownRemaining = sentAt + cooldownMs - now;

        if (otpRemaining <= 0) {
            countdownEl.textContent = '00:00 (Expired)';
            timerEl.classList.add('expired');
            resendBtn.disabled = true;
            resendBtn.textContent = 'OTP Expired — Start Over';
            return;
        }

        countdownEl.textContent = formatMs(otpRemaining);

        if (cooldownRemaining > 0) {
            resendBtn.disabled = true;
            resendBtn.textContent = 'Resend in ' + formatMs(cooldownRemaining);
        } else {
            resendBtn.disabled = false;
            resendBtn.textContent = 'Resend OTP';
        }
    }

    updateTimer();
    setInterval(updateTimer, 1000);
})();

document.getElementById('bookingDate')?.addEventListener('change', function () {
    const selectedDate = this.value;
    const timeSelect = document.getElementById('bookingTime');

    if (!selectedDate || !timeSelect) {
        return;
    }

    timeSelect.innerHTML = '<option value="">Loading...</option>';

    fetch(`../api/availability.php?date=${encodeURIComponent(selectedDate)}`)
        .then((response) => response.json())
        .then((data) => {
            timeSelect.innerHTML = '<option value="">Select time</option>';
            if (!data.success || !Array.isArray(data.slots) || data.slots.length === 0) {
                timeSelect.innerHTML = '<option value="">No available slots</option>';
                return;
            }

            data.slots.forEach((slot) => {
                const option = document.createElement('option');
                option.value = slot.value;
                option.textContent = slot.label;
                timeSelect.appendChild(option);
            });
        })
        .catch(() => {
            timeSelect.innerHTML = '<option value="">Unable to load slots</option>';
        });
});
</script>
</body>
</html>
