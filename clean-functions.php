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

function getAvailableTimeSlots(PDO $pdo, string $date) {
    $normalizedDate = normalizeDate($date);
    if ($normalizedDate === null) {
        return [];
    }

    $bookedSlots = array_flip(getBookedTimeSlots($pdo, $normalizedDate));
    $availableSlots = [];

    foreach (getTimeSlots() as $slot) {
        if (isset($bookedSlots[$slot]) || isSlotInPast($normalizedDate, $slot)) {
            continue;
        }

        $availableSlots[] = $slot;
    }

    return $availableSlots;
}

function createWalkInAppointment(PDO $pdo, int $userId, $serviceId, $date, $time) {
    $validation = validateAppointmentRequest($pdo, $userId, $serviceId, $date, $time);
    if (!empty($validation['errors'])) {
        return [
            'success' => false,
            'errors' => $validation['errors'],
        ];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO appointments (user_id, service_id, appointment_date, appointment_time, status)
         VALUES (?, ?, ?, ?, "approved")'
    );

    $created = $stmt->execute([
        $userId,
        $validation['service_id'],
        $validation['date'],
        $validation['time'],
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

?>

