<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
requireRole('admin');

$flash = getFlashMessage();
$services = getServices($pdo);
$users = getPatients($pdo);
$error = '';
$selectedUserId = $_POST['user_id'] ?? $_GET['user_id'] ?? '';
$selectedServiceId = $_POST['service_id'] ?? '';
$selectedDate = $_POST['date'] ?? date('Y-m-d');
$selectedTime = normalizeTime($_POST['time'] ?? '') ?? '';
$isNewPatient = ($_POST['patient_mode'] ?? 'existing') === 'new';
$patientName = '';

if ($selectedUserId) {
    $user = $pdo->prepare('SELECT first_name, last_name FROM users WHERE id = ? AND role = "client"');
    $user->execute([$selectedUserId]);
    $userData = $user->fetch();
    $patientName = $userData ? trim($userData['first_name'] . ' ' . $userData['last_name']) : '';
}

if (isPostRequest()) {
    $selectedUserId = $_POST['user_id'] ?? 0;
    $selectedServiceId = $_POST['service_id'] ?? '';
    $selectedDate = $_POST['date'] ?? '';
    $selectedTime = normalizeTime($_POST['time'] ?? '') ?? '';
    $patientMode = $_POST['patient_mode'] ?? 'existing';
    $isNewPatient = $patientMode === 'new';

    if (!isValidCsrfToken($_POST['_token'] ?? null)) {
        $error = 'Your session expired. Please submit the walk-in form again.';
    } else {
        $userId = (int) $selectedUserId;
        if ($isNewPatient) {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $age = toPositiveInt($_POST['age'] ?? 0);
            $clientResult = createWalkInClient($pdo, $firstName, $lastName, $email, $mobile, $age);
            if (!$clientResult['success']) {
                $error = implode(' ', $clientResult['errors']);
            } else {
                $userId = $clientResult['user_id'];
            }
        }

        if (empty($error) && $userId > 0) {
            $result = createWalkInAppointment($pdo, $userId, $selectedServiceId, $selectedDate, $selectedTime);
            if ($result['success']) {
                setFlashMessage('success', 'Walk-in appointment booked and automatically approved for ' . ($isNewPatient ? 'new patient' : 'patient') . '.');
                redirect('walkin.php');
            }
            $error = implode(' ', $result['errors']);
        } elseif (empty($error)) {
            $error = 'Invalid patient selection.';
        }
    }
}

$availableTimeSlots = $selectedDate ? getAvailableTimeSlots($pdo, $selectedDate) : [];
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
                
                <div class="patient-mode-toggle">
                    <input type="radio" id="existing-patient" name="patient_mode" value="existing" <?php echo !$isNewPatient ? 'checked' : ''; ?> required>
                    <label for="existing-patient"><i class="fas fa-users"></i> Existing Patient</label>
                    <input type="radio" id="new-patient" name="patient_mode" value="new" <?php echo $isNewPatient ? 'checked' : ''; ?> required>
                    <label for="new-patient"><i class="fas fa-user-plus"></i> New Guest</label>
                </div>

                <div id="existing-patient-section" class="patient-section <?php echo $isNewPatient ? 'hidden' : ''; ?>">
                    <div class="walkin-form-group">
                        <label>Search Patient</label>
                        <div class="patient-search-container">
                            <input type="text" id="patient_search" class="patient-search-input" placeholder="Type name..." value="<?php echo e($patientName); ?>" autocomplete="off">
                            <input type="hidden" id="user_id" name="user_id" value="<?php echo e($selectedUserId); ?>">
                            <div class="patient-search-list" id="patient-search-list">
                                <?php if (empty($users)): ?>
                                    <div class="patient-no-results">No patients found</div>
                                <?php else: ?>
                                    <?php foreach ($users as $u): ?>
                                        <div class="patient-search-item" data-id="<?php echo e($u['id']); ?>" data-name="<?php echo e(strtolower($u['first_name'] . ' ' . $u['last_name'])); ?>">
                                            <div class="patient-search-avatar"><?php echo e(strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1))); ?></div>
                                            <div>
                                                <div class="font-semibold"><?php echo e($u['first_name'] . ' ' . $u['last_name']); ?></div>
                                                <div class="text-sm"><?php echo e($u['mobile'] ?: ''); echo !empty($u['email']) ? ' • ' . e($u['email']) : ''; ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p><button type="button" class="walkin-new-patient" onclick="document.getElementById('new-patient').click()">+ Register new patient</button></p>
                    </div>
                </div>

                <div id="new-patient-section" class="patient-section <?php echo !$isNewPatient ? 'hidden' : ''; ?>">
                    <div class="guest-fields-grid">
                        <div class="walkin-form-group"><label>First Name *</label><input type="text" name="first_name" required></div>
                        <div class="walkin-form-group"><label>Last Name *</label><input type="text" name="last_name" required></div>
                        <div class="walkin-form-group"><label>Mobile *</label><input type="tel" name="mobile" required></div>
                        <div class="walkin-form-group"><label>Age</label><input type="number" name="age" min="1" max="120"></div>
                        <div class="walkin-form-group"><label>Email</label><input type="email" name="email"></div>
                    </div>
                    <small class="text-sm">Guest account created (no password).</small>
                </div>

                <div class="walkin-form-group">
                    <label>Service *</label>
                    <select name="service_id" required>
                        <option value="">Select service</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?php echo e($s['id']); ?>" <?php echo (string)$selectedServiceId === (string)$s['id'] ? 'selected' : ''; ?>><?php echo e($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="walkin-form-group">
                    <label>Date *</label>
                    <input type="date" name="date" id="walkin-date" value="<?php echo e($selectedDate); ?>" min="<?php echo e(date('Y-m-d')); ?>" required>
                </div>

                <div class="walkin-form-group">
                    <label>Time *</label>
                    <select name="time" id="walkin-time" required>
                        <option value=""><?php echo $availableTimeSlots ? 'Select time' : 'No available slots'; ?></option>
                        <?php foreach ($availableTimeSlots as $slot): ?>
                            <option value="<?php echo e($slot); ?>" <?php echo $selectedTime === $slot ? 'selected' : ''; ?>><?php echo e(formatAppointmentTime($slot)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-walkin">Book Walk-in (Auto-approved)</button>
            </form>
        </div>
    </main>
</div>



<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle sections
    const existingRadio = document.getElementById('existing-patient');
    const newRadio = document.getElementById('new-patient');
    const existingSection = document.getElementById('existing-patient-section');
    const newSection = document.getElementById('new-patient-section');
    function toggleSections() {
        const isNew = newRadio.checked;
        existingSection.classList.toggle('hidden', isNew);
        newSection.classList.toggle('hidden', !isNew);
        if (isNew) document.getElementById('user_id').value = '';
    }
    existingRadio.addEventListener('change', toggleSections);
    newRadio.addEventListener('change', toggleSections);
    toggleSections();

    // Patient search
    const searchInput = document.getElementById('patient_search');
    const searchList = document.getElementById('patient-search-list');
    const userIdField = document.getElementById('user_id');
    if (searchInput && searchList) {
        const items = searchList.querySelectorAll('.patient-search-item');
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            let hasVisible = false;
            items.forEach(item => {
                const name = item.getAttribute('data-name') || '';
                if (name.includes(query)) {
                    item.style.display = 'flex';
                    hasVisible = true;
                } else {
                    item.style.display = 'none';
                }
            });
            searchList.classList.toggle('show', query !== '' && hasVisible);
        });
        items.forEach(item => {
            item.addEventListener('click', function() {
                searchInput.value = this.querySelector('.font-semibold').innerText;
                userIdField.value = this.getAttribute('data-id');
                searchList.classList.remove('show');
            });
        });
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchList.contains(e.target)) {
                searchList.classList.remove('show');
            }
        });
    }

    // Time slots auto-load
    const dateInput = document.getElementById('walkin-date');
    const timeSelect = document.getElementById('walkin-time');
    if (dateInput && timeSelect) {
        dateInput.addEventListener('change', function() {
            const date = this.value;
            if (!date) return;
            timeSelect.innerHTML = '<option value="">Loading...</option>';
            fetch(`../api/availability.php?date=${date}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message);
                    timeSelect.innerHTML = '<option value="">Select time</option>';
                    data.slots.forEach(slot => {
                        const opt = document.createElement('option');
                        opt.value = slot.value;
                        opt.textContent = slot.label;
                        timeSelect.appendChild(opt);
                    });
                })
                .catch(err => {
                    timeSelect.innerHTML = '<option value="">Error loading slots</option>';
                    console.error(err);
                });
        });
        if (dateInput.value) dateInput.dispatchEvent(new Event('change'));
    }
});
</script>
<script src="../assets/js/phone-format.js"></script>
</body>
</html>