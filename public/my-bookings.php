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
                    'user_id' => (int) $patient['id'],
                    'mobile' => $patient['mobile'],
                ];
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
</body>
</html>

