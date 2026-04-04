<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
requireRole('client');  // Require login

$flash = getFlashMessage();
$services = getServices($pdo);
$user = getUser($pdo);  // Load logged-in user data
$error = '';

$form = [
    'first_name' => $user['first_name'] ?? '',
    'last_name' => $user['last_name'] ?? '',
    'mobile' => normalizeMobile($user['mobile'] ?? ''),
    'email' => $user['email'] ?? '',
    'age' => $user['age'] ?? '',
    'service_id' => '',
    'date' => date('Y-m-d'),
    'time' => '',
    'for_child' => false,
];

$pediatricServiceId = null;
foreach ($services as $service) {
    if (stripos($service['name'], 'pediatric') !== false || stripos($service['name'], 'pediatrics') !== false || stripos($service['name'], 'child') !== false) {
        $pediatricServiceId = $service['id'];
        break;
    }
}

$isPediatricSelected = !empty($form['service_id']) && (int)$form['service_id'] === $pediatricServiceId;

if (isPostRequest()) {
    $form['first_name'] = trim($_POST['first_name'] ?? '');
    $form['last_name'] = trim($_POST['last_name'] ?? '');
    $raw_mobile = trim($_POST['mobile'] ?? '');
    $form['mobile'] = normalizeMobile($raw_mobile) ?? $raw_mobile;
    $form['email'] = trim($_POST['email'] ?? '');
    $form['age'] = (int) ($_POST['age'] ?? 0);
    $form['service_id'] = (int) ($_POST['service_id'] ?? 0);
    $form['date'] = $_POST['date'] ?? '';
    $form['time'] = normalizeTime($_POST['time'] ?? '') ?? '';
    $form['for_child'] = isset($_POST['for_child']);

    $errors = [];

    if (!isValidCsrfToken($_POST['_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }

    // Determine userId
    $useChildDetails = $form['for_child'] && $form['service_id'] == $pediatricServiceId;
    if (!$useChildDetails) {
        // Use logged-in user
        $userId = currentUserId();
        // Validate minimal - service/date/time only
    } else {
        // Validate child patient details
        if (empty($form['first_name']) || empty($form['last_name'])) {
            $errors[] = 'Please enter child\'s full name.';
        }
        if (!isValidMobile($form['mobile'])) {
            $errors[] = 'Please enter valid mobile for child.';
        }
        if (!empty($form['email']) && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email.';
        }
        if ($form['age'] < 1 || $form['age'] > 120) {
            $errors[] = 'Valid age required for child.';
        }
        // Find or create child patient
        $stmt = $pdo->prepare('SELECT id FROM users WHERE mobile = ? LIMIT 1');
        $stmt->execute([$form['mobile']]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existingUser) {
            $userId = (int) $existingUser['id'];
            // Update child details
            $update = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, age = ? WHERE id = ?');
            $update->execute([$form['first_name'], $form['last_name'], $form['email'] ?: null, $form['age'], $userId]);
        } else {
            $insert = $pdo->prepare(
                "INSERT INTO users (role, first_name, last_name, mobile, email, age, password, created_at)
                 VALUES ('client', ?, ?, ?, ?, ?, NULL, NOW())"
            );
            $insert->execute([$form['first_name'], $form['last_name'], $form['mobile'], $form['email'] ?: null, $form['age']]);
            $userId = (int) $pdo->lastInsertId();
        }
    }

    $validation = validateAppointmentRequest($pdo, $userId, $form['service_id'], $form['date'], $form['time']);
    $errors = array_merge($errors, $validation['errors']);

    if (empty($errors)) {
        $result = bookAppointment($pdo, $userId, $validation['service_id'], $validation['date'], $validation['time']);
        if ($result['success']) {
            $msg = $useChildDetails ? 'Child appointment booked! Confirmation sent.' : 'Appointment booked successfully!';
            setFlashMessage('success', $msg);
            redirect('book-new.php');
        } else {
            $errors = array_merge($errors, $result['errors']);
        }
    }

    if (!empty($errors)) {
        $error = implode(' ', $errors);
    }

    $availableTimeSlots = $form['date'] ? getAvailableTimeSlots($pdo, $form['date']) : [];
} else {
    $availableTimeSlots = $form['date'] ? getAvailableTimeSlots($pdo, $form['date']) : [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment | Dents-City Dental Clinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/client.css">
    <script src="../assets/js/main.js" defer></script>
</head>
<body>
    <?php include '../../includes/public/partials/nav-client.php'; ?>

    <div class="client-container">
        <main>
            <?php if ($flash): ?>
                <div class="<?php echo e($flash['type']); ?>-message"><?php echo e($flash['message']); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error-message"><?php echo e($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <h3>Book Appointment</h3>
                <p style="color: #666; margin-bottom: 1.5rem; font-size: 0.95rem;">Logged in as <?php echo e($user['first_name'] . ' ' . $user['last_name']); ?>. Fields auto-filled for you.</p>
                <form method="POST" data-availability-url="../api/availability.php">
                    <?php echo csrfField(); ?>
                    <div class="form-row patient-fields">
                        <div>
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo e($form['first_name']); ?>" required>
                        </div>
                        <div>
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo e($form['last_name']); ?>" required>
                        </div>
                    </div>
                    <div class="patient-fields">
                        <label for="mobile">Mobile Number * <small>(used to manage appointments)</small></label>
                        <input type="tel" id="mobile" name="mobile" placeholder="09123456789" value="<?php echo e($form['mobile']); ?>" required>
                    </div>
                    <div class="form-row patient-fields">
                        <div>
                            <label for="email">Email (optional)</label>
                            <input type="email" id="email" name="email" placeholder="your@email.com" value="<?php echo e($form['email']); ?>">
                        </div>
                        <div>
                            <label for="age">Age *</label>
                            <input type="number" id="age" name="age" min="1" max="120" value="<?php echo e($form['age']); ?>" required>
                        </div>
                    </div>
                    <div id="child-section" style="display: none; margin: 1rem 0; padding: 1rem; background: #f0f8f5; border-radius: 12px; border-left: 4px solid #1f816a;">
                        <label>
                            <input type="checkbox" id="for_child" name="for_child" value="1" <?php echo $form['for_child'] ? 'checked' : ''; ?>>
                            Booking for child?
                        </label>
                    </div>
                    <div>
                        <label for="service_id">Service *</label>
                        <select id="service_id" name="service_id" required onchange="handleServiceChange()">
                            <option value="">Choose service</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo e($service['id']); ?>" data-pediatric="<?php echo stripos($service['name'], 'pediatric') !== false || stripos($service['name'], 'child') !== false ? '1' : '0'; ?>" <?php echo (string)$form['service_id']===(string)$service['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($service['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="date">Date *</label>
                        <input type="date" id="date" name="date" value="<?php echo e($form['date']); ?>" min="<?php echo e(date('Y-m-d')); ?>" required onchange="updateTimeSlots()">
                    </div>
                    <div>
                        <label for="time">Time *</label>
                        <select id="time" name="time" required>
                            <option value=""><?php echo empty($availableTimeSlots) ? 'No slots available' : 'Select time'; ?></option>
                            <?php foreach ($availableTimeSlots as $slot): ?>
                                <option value="<?php echo e($slot); ?>"><?php echo e(formatAppointmentTime($slot)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-submit">Book Appointment</button>
                </form>
                <p style="margin-top: 1.5rem; font-size: 0.875rem; text-align: center; color: #666;">
                    <i class="fas fa-phone-alt"></i> Need help? Call (555) 123-4567
                </p>
            </div>
        </main>
    </div>



<script>
function handleServiceChange() {
    const serviceSelect = document.getElementById('service_id');
    const option = serviceSelect.options[serviceSelect.selectedIndex];
    const isPediatric = option.dataset.pediatric === '1';
    const childSection = document.getElementById('child-section');
    const patientFields = document.querySelectorAll('.patient-fields, .form-row.patient-fields input, .patient-fields input');
    
    if (isPediatric) {
        childSection.style.display = 'block';
        const forChild = document.getElementById('for_child');
        toggleChildFields(forChild.checked);
        forChild.onchange = () => toggleChildFields(forChild.checked);
    } else {
        childSection.style.display = 'none';
        document.getElementById('for_child').checked = false;
        // Disable all patient fields for non-pediatric
        patientFields.forEach(field => {
            field.disabled = true;
            field.style.opacity = '0.6';
            field.style.backgroundColor = '#f5f5f5';
        });
    }
}

function toggleChildFields(enabled) {
    const patientInputs = document.querySelectorAll('.patient-fields input');
    patientInputs.forEach(input => {
        input.disabled = !enabled;
        input.style.opacity = enabled ? '1' : '0.6';
        input.style.backgroundColor = enabled ? 'white' : '#f5f5f5';
    });
}

// Init on load
document.addEventListener('DOMContentLoaded', handleServiceChange);

function updateTimeSlots() {
    const form = event.target.form;
    const date = form.date.value;
    if (!date) return;
    
    fetch(`../api/availability.php?date=${date}`)
        .then(r => r.json())
        .then(slots => {
            const timeSelect = form.time;
            timeSelect.innerHTML = '<option value="">Loading...</option>';
            if (slots.length === 0) {
                timeSelect.innerHTML = '<option value="">No available slots</option>';
                return;
            }
            timeSelect.innerHTML = '<option value="">Select time</option>';
            slots.forEach(slot => {
                const opt = document.createElement('option');
                opt.value = slot;
                opt.textContent = new Date(`2000-01-01T${slot}`).toLocaleTimeString([], {hour: 'numeric', minute:'2-digit'});
                timeSelect.appendChild(opt);
            });
        });
}
</script>
</body>
</html>
