<?php
require_once __DIR__ . '/config.php';

const APPOINTMENT_STATUSES = ['pending', 'approved', 'rejected', 'completed'];
const ACTIVE_APPOINTMENT_STATUSES = ['pending', 'approved'];
const APPOINTMENT_INTERVAL_MINUTES = 30;
const CLINIC_OPEN_TIME = '09:00:00';
const CLINIC_CLOSE_TIME = '17:00:00';

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path) {
    $url = preg_match('#^https?://#i', $path) ? $path : BASE_URL . ltrim($path, '/');
    header('Location: ' . $url);
    exit;
}

function setFlashMessage(string $type, string $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getFlashMessage() {
    if (empty($_SESSION['flash_message'])) {
        return null;
    }

    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);

    return $flash;
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="_token" value="' . e(csrfToken()) . '">';
}

function isValidCsrfToken(?string $token) {
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function isPostRequest() {
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function formatAppointmentTime(string $time) {
    return date('h:i A', strtotime($time));
}

function toPositiveInt($value) {
    $result = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $result === false ? null : (int) $result;
}

function normalizeDate(?string $date) {
    if (!$date) {
        return null;
    }

    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        return null;
    }

    return $parsed->format('Y-m-d');
}

function normalizeTime(?string $time) {
    if (!$time) {
        return null;
    }

    $timestamp = strtotime($time);
    if ($timestamp === false) {
        return null;
    }

    return date('H:i:s', $timestamp);
}

function isValidAppointmentStatus(string $status) {
    return in_array($status, APPOINTMENT_STATUSES, true);
}

function isActiveAppointmentStatus(string $status) {
    return in_array($status, ACTIVE_APPOINTMENT_STATUSES, true);
}

function isValidTimeSlot(string $time) {
    return in_array($time, getTimeSlots(), true);
}

function isSlotInPast(string $date, string $time) {
    $slot = strtotime($date . ' ' . $time);
    return $slot !== false && $slot <= time();
}

function getServices(PDO $pdo) {
    $stmt = $pdo->query('SELECT * FROM services ORDER BY name');
    return $stmt->fetchAll();
}

function getServiceById(PDO $pdo, int $serviceId) {
    $stmt = $pdo->prepare('SELECT * FROM services WHERE id = ?');
    $stmt->execute([$serviceId]);
    $service = $stmt->fetch();

    return $service ?: null;
}

function getPatients(PDO $pdo) {
    $stmt = $pdo->query("SELECT id, first_name, last_name, email, mobile FROM users WHERE role = 'client' ORDER BY first_name, last_name");
    return $stmt->fetchAll();
}

function getTimeSlots(int $intervalMinutes = APPOINTMENT_INTERVAL_MINUTES) {
    $slots = [];
    $start = strtotime(CLINIC_OPEN_TIME);
    $end = strtotime(CLINIC_CLOSE_TIME);

    while ($start < $end) {
        $slots[] = date('H:i:s', $start);
        $start = strtotime('+' . $intervalMinutes . ' minutes', $start);
    }

    return $slots;
}

function getBookedTimeSlots(PDO $pdo, string $date, ?int $excludeId = null) {
    $sql = "SELECT appointment_time
            FROM appointments
            WHERE appointment_date = ?
            AND status IN ('pending', 'approved')";
    $params = [$date];

    if ($excludeId !== null) {
        $sql .= ' AND id != ?';
        $params[] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return array_map('normalizeTime', array_column($stmt->fetchAll(), 'appointment_time'));
}

function getAvailableTimeSlots(PDO $pdo, string $date, ?int $excludeId = null) {
    $normalizedDate = normalizeDate($date);
    if ($normalizedDate === null) {
        return [];
    }

    $bookedSlots = array_flip(getBookedTimeSlots($pdo, $normalizedDate, $excludeId));
    $availableSlots = [];

    foreach (getTimeSlots() as $slot) {
        if (isset($bookedSlots[$slot]) || isSlotInPast($normalizedDate, $slot)) {
            continue;
        }

        $availableSlots[] = $slot;
    }

    return $availableSlots;
}

function isSlotAvailable(PDO $pdo, string $date, string $time, ?int $excludeId = null) {
    $normalizedDate = normalizeDate($date);
    $normalizedTime = normalizeTime($time);

    if ($normalizedDate === null || $normalizedTime === null || !isValidTimeSlot($normalizedTime)) {
        return false;
    }

    if (isSlotInPast($normalizedDate, $normalizedTime)) {
        return false;
    }

    $sql = "SELECT COUNT(*)
            FROM appointments
            WHERE appointment_date = ?
            AND appointment_time = ?
            AND status IN ('pending', 'approved')";

    $params = [$normalizedDate, $normalizedTime];

    if ($excludeId !== null) {
        $sql .= ' AND id != ?';
        $params[] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn() === 0;
}

function validateAppointmentRequest(PDO $pdo, int $userId, $serviceId, $date, $time, ?int $excludeId = null) {
    $errors = [];
    $normalizedServiceId = toPositiveInt($serviceId);
    $normalizedDate = normalizeDate($date);
    $normalizedTime = normalizeTime($time);

    if ($userId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'client'");
        $stmt->execute([$userId]);
        if (!$stmt->fetchColumn()) {
            $errors[] = 'Please select a valid patient account.';
        }
    }

    if ($normalizedServiceId === null || getServiceById($pdo, $normalizedServiceId) === null) {
        $errors[] = 'Please select a valid dental service.';
    }

    if ($normalizedDate === null) {
        $errors[] = 'Please choose a valid appointment date.';
    } elseif ($normalizedDate < date('Y-m-d')) {
        $errors[] = 'Appointments can only be booked for today or a future date.';
    }

    if ($normalizedTime === null || !isValidTimeSlot($normalizedTime)) {
        $errors[] = 'Please choose an available clinic time slot.';
    } elseif ($normalizedDate !== null && isSlotInPast($normalizedDate, $normalizedTime)) {
        $errors[] = 'Please choose a time that has not passed yet.';
    }

    if ($normalizedDate !== null && $normalizedTime !== null && empty($errors) && !isSlotAvailable($pdo, $normalizedDate, $normalizedTime, $excludeId)) {
        $errors[] = 'This time slot is already booked. Please choose another schedule.';
    }

    return [
        'errors' => $errors,
        'service_id' => $normalizedServiceId,
        'date' => $normalizedDate,
        'time' => $normalizedTime,
    ];
}

function createAppointment(PDO $pdo, int $userId, $serviceId, $date, $time, string $status = 'pending', ?string $adminMessage = null) {
    $validation = validateAppointmentRequest($pdo, $userId, $serviceId, $date, $time);

    if (!isValidAppointmentStatus($status)) {
        $validation['errors'][] = 'Invalid appointment status.';
    }

    if (!empty($validation['errors'])) {
        return [
            'success' => false,
            'errors' => $validation['errors'],
        ];
    }

    $message = trim((string) $adminMessage);
    $message = $message === '' ? null : $message;

    $stmt = $pdo->prepare(
        'INSERT INTO appointments (user_id, service_id, appointment_date, appointment_time, status, admin_message)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $created = $stmt->execute([
        $userId,
        $validation['service_id'],
        $validation['date'],
        $validation['time'],
        $status,
        $message,
    ]);

    if (!$created) {
        return [
            'success' => false,
            'errors' => ['We could not save the appointment. Please try again.'],
        ];
    }

    return [
        'success' => true,
        'errors' => [],
        'appointment_id' => (int) $pdo->lastInsertId(),
    ];
}

function createWalkInAppointment(PDO $pdo, int $userId, $serviceId, $date, $time) {
    return createAppointment($pdo, $userId, $serviceId, $date, $time, 'approved');
}

?>

