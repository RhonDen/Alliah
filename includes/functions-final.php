<?php
// Alliah Dental Clinic - Complete Fixed Functions
// All SQL errors fixed, PDO everywhere, syntax clean

require_once __DIR__ . '/config.php';

const APPOINTMENT_STATUSES = ['pending', 'approved', 'rejected', 'completed'];
const ACTIVE_APPOINTMENT_STATUSES = ['pending', 'approved'];
const APPOINTMENT_INTERVAL_MINUTES = 30;
const CLINIC_OPEN_TIME = '09:00:00';
const CLINIC_CLOSE_TIME = '17:00:00';

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    $url = preg_match('#^https?://#i', $path) ? $path : BASE_URL . ltrim($path, '/');
    header('Location: ' . $url);
    exit;
}

function setFlashMessage($type, $message) {
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

function isValidCsrfToken($token) {
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function isPostRequest() {
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function formatAppointmentTime($time) {
    return date('h:i A', strtotime($time));
}

function toPositiveInt($value) {
    $result = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $result === false ? null : (int) $result;
}

function normalizeDate($date) {
    if (!$date) {
        return null;
    }
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        return null;
    }
    return $parsed->format('Y-m-d');
}

function normalizeTime($time) {
    if (!$time) {
        return null;
    }
    $timestamp = strtotime($time);
    if ($timestamp === false) {
        return null;
    }
    return date('H:i:s', $timestamp);
}

function normalizeMobile($mobile) {
    if (empty($mobile)) return null;
    $mobile = trim(preg_replace('/[^0-9]/', '', $mobile));
    if (empty($mobile)) return null;
    if (str_starts_with($mobile, '63')) $mobile = substr($mobile, 2);
    if (str_starts_with($mobile, '0')) $mobile = substr($mobile, 1);
    if (strlen($mobile) !== 10 || $mobile[0] !== '9') return null;
    return '09' . substr($mobile, 1, 3) . '-' . substr($mobile, 4, 3) . '-' . substr($mobile, 7);
}

function findUserByIdentifier($pdo, $identifier) {
    $normMobile = normalizeMobile($identifier);
    $user = null;
    if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$identifier]);
        $user = $stmt->fetch();
    }
    if (!$user && $normMobile !== null) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE mobile = ? LIMIT 1');
        $stmt->execute([$normMobile]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function createWalkInClient($pdo, $first_name, $last_name, $email, $mobile, $age = null) {
    $errors = [];
    $normMobile = normalizeMobile($mobile);
    if ($normMobile === null) {
        $errors[] = 'Invalid mobile number.';
    }
    $first_name = trim($first_name);
    $last_name = trim($last_name);
    if (strlen($first_name) < 2 || strlen($last_name) < 2) {
        $errors[] = 'Name too short.';
    }
    $email = trim($email ?? '');
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }
    if (empty($errors)) {
        $existing = findUserByIdentifier($pdo, $normMobile) ?? findUserByIdentifier($pdo, $email);
        if ($existing) {
            return [
                'success' => false,
                'errors' => ['Patient with this mobile/email already exists.'],
                'user_id' => $existing['id']
            ];
        }
        $stmt = $pdo->prepare('INSERT INTO users (role, first_name, last_name, email, mobile, age, password) VALUES (?, ?, ?, ?, ?, ?, NULL)');
        $success = $stmt->execute(['client', $first_name, $last_name, $email ?: null, $normMobile, $age ?: 0]);
        $user_id = $pdo->lastInsertId();
        return [
            'success' => $success,
            'errors' => $success ? [] : ['Failed to create patient.'],
            'user_id' => $success ? $user_id : null
        ];
    }
    return [
        'success' => false,
        'errors' => $errors,
        'user_id' => null
    ];
}

function isValidAppointmentStatus($status) {
    return in_array($status, APPOINTMENT_STATUSES, true);
}

function getServices($pdo) {
    $stmt = $pdo->query('SELECT * FROM services ORDER BY name');
    return $stmt->fetchAll();
}

function getServiceById($pdo, $serviceId) {
    $stmt = $pdo->prepare('SELECT * FROM services WHERE id = ?');
    $stmt->execute([$serviceId]);
    return $stmt->fetch() ?: null;
}

function getPatients($pdo) {
    $stmt = $pdo->query("SELECT id, first_name, last_name, email, mobile FROM users WHERE role = 'client' ORDER BY first_name, last_name");
    return $stmt->fetchAll();
}

function getTimeSlots($intervalMinutes = APPOINTMENT_INTERVAL_MINUTES) {
    $slots = [];
    $start = strtotime(CLINIC_OPEN_TIME);
    $end = strtotime(CLINIC_CLOSE_TIME);
    while ($start < $end) {
        $slots[] = date('H:i:s', $start);
        $start = strtotime('+' . $intervalMinutes . ' minutes', $start);
    }
    return $slots;
}

function isSlotAvailable($pdo, $date, $time, $excludeId = null) {
    $normalizedDate = normalizeDate($date);
    $normalizedTime = normalizeTime($time);
    if ($normalizedDate === null || $normalizedTime === null || !in_array($normalizedTime, getTimeSlots(), true)) {
        return false;
    }
    $slotTime = strtotime($normalizedDate . ' ' . $normalizedTime);
    if ($slotTime !== false && $slotTime <= time()) {
        return false;
    }
    $sql = "SELECT COUNT(*) FROM appointments WHERE appointment_date = ? AND appointment_time = ? AND status IN ('pending', 'approved')";
    $params = [$normalizedDate, $normalizedTime];
    if ($excludeId !== null) {
        $sql .= ' AND id != ?';
        $params[] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn() === 0;
}

function validateAppointmentRequest($pdo, $userId, $serviceId, $date, $time, $excludeId = null) {
    $errors = [];
    $normalizedServiceId = toPositiveInt($serviceId);
    $normalizedDate = normalizeDate($date);
    $normalizedTime = normalizeTime($time);
    
    if ($userId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'client'");
        $stmt->execute([$userId]);
        if (!$stmt->fetchColumn()) {
            $errors[] = 'Invalid patient account.';
        }
    }
    if ($normalizedServiceId === null || getServiceById($pdo, $normalizedServiceId) === null) {
        $errors[] = 'Invalid dental service.';
    }
    if ($normalizedDate === null) {
        $errors[] = 'Invalid appointment date.';
    } elseif ($normalizedDate < date('Y-m-d')) {
        $errors[] = 'Future dates only.';
    }
    if ($normalizedTime === null) {
        $errors[] = 'Invalid time slot.';
    }
    if (!empty($errors) == false && !isSlotAvailable($pdo, $normalizedDate, $normalizedTime, $excludeId)) {
        $errors[] = 'Slot already booked.';
    }
    return [
        'errors' => $errors,
        'service_id' => $normalizedServiceId,
        'date' => $normalizedDate,
        'time' => $normalizedTime,
    ];
}

function createAppointment($pdo, $userId, $serviceId, $date, $time, $status = 'pending', $adminMessage = null) {
    $validation = validateAppointmentRequest($pdo, $userId, $serviceId, $date, $time);
    if (!in_array($status, APPOINTMENT_STATUSES, true)) {
        $validation['errors'][] = 'Invalid status.';
    }
    if (!empty($validation['errors'])) {
        return ['success' => false, 'errors' => $validation['errors']];
    }
    $message = trim((string) $adminMessage) ?: null;
    $stmt = $pdo->prepare('INSERT INTO appointments (user_id, service_id, appointment_date, appointment_time, status, admin_message) VALUES (?, ?, ?, ?, ?, ?)');
    $created = $stmt->execute([$userId, $validation['service_id'], $validation['date'], $validation['time'], $status, $message]);
    return $created ? [
        'success' => true,
        'appointment_id' => $pdo->lastInsertId()
    ] : ['success' => false, 'errors' => ['Save failed.']];
}

function getAllAppointments($pdo, $filters = []) {
    $sql = "SELECT a.*, u.first_name, u.last_name, u.email, u.mobile, s.name AS service_name FROM appointments a JOIN users u ON a.user_id = u.id JOIN services s ON a.service_id = s.id WHERE 1=1";
    $params = [];
    if (!empty($filters['date'])) {
        $sql .= ' AND a.appointment_date = ?';
        $params[] = $filters['date'];
    }
    if (!empty($filters['status']) && in_array($filters['status'], APPOINTMENT_STATUSES, true)) {
        $sql .= ' AND a.status = ?';
        $params[] = $filters['status'];
    }
    $sql .= ' ORDER BY a.appointment_date ASC, a.appointment_time ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function updateAppointmentStatus($pdo, $appointmentId, $status, $adminMessage = null) {
    $appointment = getAppointmentById($pdo, $appointmentId);
    if (!$appointment) {
        return ['success' => false, 'errors' => ['Appointment not found.']];
    }
    if (!in_array($status, APPOINTMENT_STATUSES, true)) {
        return ['success' => false, 'errors' => ['Invalid status.']];
    }
    $message = trim((string) $adminMessage) ?: null;
    $stmt = $pdo->prepare('UPDATE appointments SET status = ?, admin_message = ? WHERE id = ?');
    $updated = $stmt->execute([$status, $message, $appointmentId]);
    return ['success' => $updated];
}

function getAdminDashboardStats($pdo) {
    $today = date('Y-m-d');
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(status = 'pending') as pending, SUM(status = 'approved') as approved, SUM(status = 'completed') as completed, SUM(appointment_date = '$today') as today FROM appointments");
    $stats = $stmt->fetch() ?: [];
    return [
        'total' => (int) ($stats['total'] ?? 0),
        'pending' => (int) ($stats['pending'] ?? 0),
        'approved' => (int) ($stats['approved'] ?? 0),
        'completed' => (int) ($stats['completed'] ?? 0),
        'today_total' => (int) ($stats['today'] ?? 0),
    ];
}

function getAppointmentById($pdo, $appointmentId) {
    $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ?');
    $stmt->execute([$appointmentId]);
    return $stmt->fetch() ?: null;
}

function getRecentAppointments($pdo, $limit = 5) {
    $stmt = $pdo->query("SELECT a.*, u.first_name, u.last_name, s.name AS service_name FROM appointments a JOIN users u ON a.user_id = u.id JOIN services s ON a.service_id = s.id ORDER BY a.created_at DESC LIMIT $limit");
    return $stmt->fetchAll();
}
?>

