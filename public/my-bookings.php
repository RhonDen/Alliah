<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (isset($_GET['reset'])) {
    unset($_SESSION['history_otp']);
    unset($_SESSION['history_patient_id']);
    redirect('my-bookings.php');
}

$errors = [];
$success = '';
$step = empty($_SESSION['history_otp']) ? 1 : 2;
$mobileInput = '';
$patient = null;
$appointments = [];
$stats = null;

if (!empty($_SESSION['history_otp']['mobile'])) {
    $mobileInput = formatMobileForDisplay($_SESSION['history_otp']['mobile']);
}

// Handle patient cancellation
if (isPostRequest() && isset($_POST['cancel_appointment'])) {
    if (!isValidCsrfToken($_POST['_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $appointmentId = toPositiveInt($_POST['appointment_id'] ?? null);
        $reason = trim($_POST['cancel_reason'] ?? '');
        $patientId = $_SESSION['history_patient_id'] ?? null;

        if ($appointmentId === null) {
            $errors[] = 'Invalid appointment.';
        } elseif (!$patientId) {
            $errors[] = 'Session expired. Please verify your mobile again.';
            $step = 1;
        } else {
            $result = cancelAppointment($pdo, $appointmentId, (int) $patientId, $reason);
            if ($result['success']) {
                $success = 'Appointment cancelled successfully.';
                $appointments = getUserAppointments($pdo, (int) $patientId);
                $stats = getUserAppointmentStats($pdo, (int) $patientId);
                $step = 3;
            } else {
                $errors = array_merge($errors, $result['errors']);
            }
        }
    }
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
            $verification = requestOtpViaSms($patient['mobile']);
            if ($verification === null) {
                $errors[] = 'Failed to send OTP. Please try again.';
            } else {
                $_SESSION['history_otp'] = [
                    'verification' => $verification,
                    'expires' => time() + OTP_EXPIRY_SECONDS,
                    'sent_at' => time(),
                    'resend_count' => 0,
                    'user_id' => (int) $patient['id'],
                    'mobile' => $patient['mobile'],
                ];
                $step = 2;
                $mobileInput = formatMobileForDisplay($patient['mobile']);
            }
        }
    }
}

if (isPostRequest() && isset($_POST['resend_otp'])) {
    $step = 2;
    $lookupData = $_SESSION['history_otp'] ?? null;

    if (!isValidCsrfToken($_POST['_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (!$lookupData) {
        $errors[] = 'Session expired. Please request a new OTP.';
        $step = 1;
    } elseif (time() > (int) ($lookupData['expires'] ?? 0)) {
        unset($_SESSION['history_otp']);
        $errors[] = 'OTP expired. Please request a new one.';
        $step = 1;
    } else {
        $resendCount = (int) ($lookupData['resend_count'] ?? 0);
        $lastSent = (int) ($lookupData['sent_at'] ?? 0);
        $cooldownRemaining = 60 - (time() - $lastSent);

        if ($resendCount >= 3) {
            $errors[] = 'Maximum resend attempts reached. Please start over.';
        } elseif ($cooldownRemaining > 0) {
            $errors[] = 'Please wait ' . $cooldownRemaining . ' seconds before resending.';
        } else {
            $mobile = $lookupData['mobile'] ?? '';
            $verification = requestOtpViaSms($mobile);
            if ($verification === null) {
                $errors[] = 'Failed to resend OTP. Please try again.';
            } else {
                $_SESSION['history_otp']['verification'] = $verification;
                $_SESSION['history_otp']['expires'] = time() + OTP_EXPIRY_SECONDS;
                $_SESSION['history_otp']['sent_at'] = time();
                $_SESSION['history_otp']['resend_count'] = $resendCount + 1;
                $success = 'A new OTP has been sent to your mobile number.';
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
    } elseif (!verifyOtpSubmission($lookupData['verification'] ?? null, $submittedOtp)) {
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
            $_SESSION['history_patient_id'] = (int) $patient['id'];
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
        .section-label {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #1f816a;
            margin-bottom: 1rem;
        }

        .history-stats {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .history-stat {
            flex: 1;
            min-width: 120px;
            background: #f8fbfa;
            border: 1px solid rgba(31, 129, 106, 0.12);
            border-radius: 16px;
            padding: 1.25rem 1rem;
            text-align: center;
        }

        .history-stat strong {
            display: block;
            font-size: 1.8rem;
            font-weight: 800;
            color: #1a4c3f;
            margin-bottom: 0.25rem;
        }

        .history-stat span {
            font-size: 0.85rem;
            color: #597469;
            font-weight: 500;
        }

        .history-table-wrap {
            margin-bottom: 2rem;
        }

        .history-table-wrap h4 {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #597469;
            margin-bottom: 0.75rem;
        }

        .history-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.95rem;
        }

        .history-table thead th {
            text-align: left;
            padding: 0.75rem 1rem;
            font-weight: 600;
            color: #1a4c3f;
            background: #f4faf7;
            border-bottom: 2px solid rgba(31, 129, 106, 0.15);
        }

        .history-table thead th:first-child {
            border-top-left-radius: 12px;
        }

        .history-table thead th:last-child {
            border-top-right-radius: 12px;
        }

        .history-table tbody td {
            padding: 0.9rem 1rem;
            border-bottom: 1px solid rgba(31, 129, 106, 0.08);
            color: #334155;
        }

        .history-table tbody tr:last-child td {
            border-bottom: none;
        }

        .history-table tbody tr:last-child td:first-child {
            border-bottom-left-radius: 12px;
        }

        .history-table tbody tr:last-child td:last-child {
            border-bottom-right-radius: 12px;
        }

        .history-table tbody tr:hover td {
            background: #f8fbfa;
        }

        .history-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            padding-top: 0.5rem;
            border-top: 1px solid rgba(31, 129, 106, 0.1);
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
        <a href="<?php echo e(BASE_URL . 'client/book.php'); ?>" class="client-nav-link">Book Appointment</a>
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
            <div class="card otp-step">
                <h3>Verify Your Mobile</h3>
                <p class="otp-phone">Code sent to <?php echo e(formatMobileForDisplay($lookupData['mobile'])); ?></p>

                <form method="POST" id="otpForm">
                    <?php echo csrfField(); ?>
                    <div class="otp-input-wrap" id="otpInputs">
                        <input type="text" inputmode="numeric" maxlength="1" class="otp-digit" data-index="0" autocomplete="one-time-code">
                        <input type="text" inputmode="numeric" maxlength="1" class="otp-digit" data-index="1">
                        <input type="text" inputmode="numeric" maxlength="1" class="otp-digit" data-index="2">
                        <input type="text" inputmode="numeric" maxlength="1" class="otp-digit" data-index="3">
                        <input type="text" inputmode="numeric" maxlength="1" class="otp-digit" data-index="4">
                        <input type="text" inputmode="numeric" maxlength="1" class="otp-digit" data-index="5">
                    </div>
                    <input type="hidden" name="otp" id="otpHidden" value="">
                    <button type="submit" name="verify_otp" class="otp-verify-btn">View My Bookings</button>
                </form>

                <div class="otp-timer" id="otpTimer" data-expires="<?php echo e($lookupData['expires'] ?? 0); ?>" data-sent="<?php echo e($lookupData['sent_at'] ?? time()); ?>">
                    Expires in <span class="timer-countdown" id="timerCountdown">--:--</span>
                </div>

                <form method="POST" class="resend-form">
                    <?php echo csrfField(); ?>
                    <button type="submit" name="resend_otp" class="resend-btn" id="resendBtn" disabled>Resend OTP</button>
                </form>

                <div class="otp-startover">
                    <a href="my-bookings.php?reset=1">← Use a different mobile number</a>
                </div>

                <?php if (SMS_MOCK_MODE): ?>
                    <div class="success-message" style="margin-top: 1rem;">Development mode: OTP is <?php echo e($lookupData['verification']['otp'] ?? ''); ?>. It is also written to <code>logs/sms_mock.log</code>.</div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="section-label">History</div>

                <?php if ($stats): ?>
                    <div class="history-stats">
                        <div class="history-stat">
                            <strong><?php echo e($stats['total']); ?></strong>
                            <span>Total Bookings</span>
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
                    <p style="color: #597469;">No bookings were found yet for this mobile number.</p>
                <?php else: ?>
                    <div class="history-table-wrap">
                        <h4>Appointment History</h4>
                        <div style="overflow-x: auto;">
                            <table class="history-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Service</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr>
                                            <td><?php echo e(formatDateForDisplay($appointment['appointment_date'])); ?></td>
                                            <td><?php echo e(formatAppointmentTime($appointment['appointment_time'])); ?></td>
                                            <td><?php echo e($appointment['service_name']); ?></td>
                                            <td><span class="status-badge status-<?php echo e($appointment['status']); ?>"><?php echo e(ucfirst($appointment['status'])); ?></span></td>
                                            <td><?php echo e($appointment['admin_message'] ?? '—'); ?></td>
                                            <td>
                                                <?php if (in_array($appointment['status'], ['pending', 'approved'], true) && $appointment['appointment_date'] >= date('Y-m-d')): ?>
                                                    <form method="POST" style="display: flex; flex-direction: column; gap: 0.35rem; min-width: 160px;">
                                                        <?php echo csrfField(); ?>
                                                        <input type="hidden" name="appointment_id" value="<?php echo e($appointment['id']); ?>">
                                                        <input type="text" name="cancel_reason" class="cancel-reason" placeholder="Reason (optional)" maxlength="120">
                                                        <button type="submit" name="cancel_appointment" class="cancel-btn">Cancel Appointment</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="color: #94a3b8; font-size: 0.85rem;">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="history-actions">
                    <a href="client/book.php" class="btn-book">Book Another Appointment</a>
                    <a href="my-bookings.php?reset=1" class="btn-submit" style="text-decoration: none;">Lookup Another Number</a>
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

    // OTP digit input handling
    const otpInputs = document.querySelectorAll('.otp-digit');
    const otpHidden = document.getElementById('otpHidden');
    const otpForm = document.getElementById('otpForm');

    if (otpInputs.length && otpHidden && otpForm) {
        function updateOtpValue() {
            otpHidden.value = Array.from(otpInputs).map(i => i.value).join('');
        }

        otpInputs.forEach((input, idx) => {
            input.addEventListener('input', (e) => {
                const val = e.target.value.replace(/\D/g, '');
                e.target.value = val.slice(-1);
                updateOtpValue();
                if (val && idx + 1) {
                    otpInputs[Math.min(idx + 1, otpInputs.length - 1)]?.focus();
                }
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !input.value && idx - 1 >= 0) {
                    otpInputs[idx - 1].focus();
                }
                if (e.key === 'ArrowLeft' && idx - 1 >= 0) {
                    otpInputs[idx - 1].focus();
                }
                if (e.key === 'ArrowRight' && idx + 1 === 6) {
                    otpInputs[Math.min(idx + 1, otpInputs.length - 1)]?.focus();
                }
            });

            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, otpInputs.length);
                pasted.split('').forEach((char, i) => {
                    if (otpInputs[i]) otpInputs[i].value = char;
                });
                updateOtpValue();
                const nextIdx = Math.min(pasted.length, otpInputs.length - 1);
                otpInputs[nextIdx]?.focus();
            });
        });

        otpForm.addEventListener('submit', () => {
            updateOtpValue();
        });
    }
})();
</script>
</body>
</html>

