<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
requireRole('admin');

$flash = getFlashMessage();
$services = getServices($pdo);
$error = '';

$form = [
    'first_name' => trim($_POST['first_name'] ?? ''),
    'last_name' => trim($_POST['last_name'] ?? ''),
    'mobile' => trim($_POST['mobile'] ?? ''),
    'age' => trim($_POST['age'] ?? ''),
    'email' => trim($_POST['email'] ?? ''),
    'service_id' => trim($_POST['service_id'] ?? ''),
    'date' => trim($_POST['date'] ?? date('Y-m-d')),
    'time' => normalizeTime($_POST['time'] ?? '') ?? '',
];

if (isPostRequest()) {
    if (!isValidCsrfToken($_POST['_token'] ?? null)) {
        $error = 'Your session expired. Please submit the walk-in form again.';
    } else {
        $age = $form['age'] === '' ? null : toPositiveInt($form['age']);
        $clientResult = createWalkInClient(
            $pdo,
            $form['first_name'],
            $form['last_name'],
            $form['email'],
            $form['mobile'],
            $age
        );

        if (!$clientResult['success']) {
            $error = implode(' ', $clientResult['errors']);
        } else {
            $result = createWalkInAppointment($pdo, $clientResult['user_id'], $form['service_id'], $form['date'], $form['time']);
            if ($result['success']) {
                setFlashMessage('success', 'Walk-in appointment booked and approved.');
                redirect('admin/walkin.php');
            }
            $error = implode(' ', $result['errors']);
        }
    }
}

$availableTimeSlots = $form['date'] ? getAvailableTimeSlots($pdo, $form['date']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Booking - <?php echo e(APP_NAME ?? 'Dents-City'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php include '../../includes/public/partials/nav-admin.php'; ?>

<div class="admin-container admin-layout">
    <main>
        <?php if ($flash): ?>
            <div class="<?php echo e($flash['type']); ?>-message"><?php echo e($flash['message']); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error-message"><?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="walkin-card">
            <h3><i class="fas fa-user-clock"></i> Walk-in Appointment</h3>

            <form method="POST" class="walkin-form" novalidate>
                <?php echo csrfField(); ?>

                <div class="guest-fields-grid">
                    <div class="walkin-form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo e($form['first_name']); ?>" required>
                    </div>
                    <div class="walkin-form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo e($form['last_name']); ?>" required>
                    </div>
                    <div class="walkin-form-group">
                        <label for="mobile">Mobile *</label>
                        <input type="tel" id="mobile" name="mobile" value="<?php echo e($form['mobile']); ?>" required maxlength="14" inputmode="numeric" placeholder="0999-999-9999" pattern="\d{4}-\d{3}-\d{4}" title="Format: 0999-999-9999">
                    </div>
                    <div class="walkin-form-group">
                        <label for="age">Age *</label>
                        <input type="number" id="age" name="age" value="<?php echo e($form['age']); ?>" min="1" max="120" required>
                    </div>
                    <div class="walkin-form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo e($form['email']); ?>" placeholder="Optional">
                    </div>
                </div>

                <div class="walkin-form-group">
                    <label for="service_id">Service *</label>
                    <select id="service_id" name="service_id" required>
                        <option value="">Select service</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo e($service['id']); ?>" <?php echo (string) $form['service_id'] === (string) $service['id'] ? 'selected' : ''; ?>>
                                <?php echo e($service['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="walkin-form-group">
                    <label for="walkin-date">Date *</label>
                    <input type="date" name="date" id="walkin-date" value="<?php echo e($form['date']); ?>" min="<?php echo e(date('Y-m-d')); ?>" required>
                </div>

                <div class="walkin-form-group">
                    <label for="walkin-time">Time *</label>
                    <select name="time" id="walkin-time" required>
                        <option value=""><?php echo $availableTimeSlots ? 'Select time' : 'No available slots'; ?></option>
                        <?php foreach ($availableTimeSlots as $slot): ?>
                            <option value="<?php echo e($slot); ?>" <?php echo $form['time'] === $slot ? 'selected' : ''; ?>>
                                <?php echo e(formatAppointmentTime($slot)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-walkin">Book Walk-in (Auto-approved)</button>
            </form>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const dateInput = document.getElementById('walkin-date');
    const timeSelect = document.getElementById('walkin-time');

    if (!dateInput || !timeSelect) {
        return;
    }

    dateInput.addEventListener('change', function () {
        const date = this.value;
        if (!date) {
            return;
        }

        timeSelect.innerHTML = '<option value="">Loading...</option>';

        fetch(`../api/availability.php?date=${encodeURIComponent(date)}`)
            .then((response) => response.json())
            .then((data) => {
                if (!data.success) {
                    throw new Error(data.message || 'Unable to load slots.');
                }

                timeSelect.innerHTML = '<option value="">Select time</option>';
                data.slots.forEach((slot) => {
                    const option = document.createElement('option');
                    option.value = slot.value;
                    option.textContent = slot.label;
                    timeSelect.appendChild(option);
                });
            })
            .catch(() => {
                timeSelect.innerHTML = '<option value="">Error loading slots</option>';
            });
    });

    if (dateInput.value && timeSelect.options.length <= 1) {
        dateInput.dispatchEvent(new Event('change'));
    }
});
</script>
<script src="../assets/js/phone-format.js"></script>
</body>
</html>
