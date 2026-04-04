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

function normalizeMobile(?string $mobile) {
    if (empty($mobile)) return null;
    $mobile = trim(preg_replace('/[^0-9]/', '', $mobile));
    if (empty($mobile)) return null;
    if (str_starts_with($mobile, '63')) $mobile = substr($mobile, 2);
    if (str_starts_with($mobile, '0')) $mobile = substr($mobile, 1);
    if (strlen($mobile) !== 10 || $mobile[0] !== '9') return null;
    return '09' . substr($mobile, 1, 3) . '-' . substr($mobile, 4, 3) . '-' . substr($mobile, 7);
}

function isValidMobile(string $mobile) {
    $norm = normalizeMobile($mobile);
    return $norm !== null && strlen($norm) === 11 && $norm[0] === '9' && ctype_digit($norm);
}

function findUserByIdentifier(PDO $pdo, string $identifier) {
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

function createWalkInClient(PDO $pdo, string $first_name, string $last_name, $email, string $mobile, $age = null) {
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
                'errors' => ['Patient with this mobile/email already exists. Use existing patient dropdown.'],
                'user_id' => $existing['id']
            ];
        }
        $stmt = $pdo->prepare('INSERT INTO users (role, first_name, last_name, email, mobile, age, password) VALUES (?, ?, ?, ?, ?, ?, NULL)');
        $success = $stmt->execute(['client', $first_name, $last_name, $email ?: null, $normMobile, $age ?: 0]);
        $user_id = $pdo->lastInsertId();
        return [
            'success' => $success,
            'errors' => $success ? [] : ['Failed to create patient account.'],
            'user_id' => $success ? $user_id : null
        ];
    }
    return [
        'success' => false,
        'errors' => $errors,
        'user_id' => null
    ];
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

function getBookedTimeSlots(PDO $pdo, string $date, $excludeId = null) {
    $sql = "SELECT appointment_time FROM appointments WHERE appointment_date = ? AND status IN ('pending', 'approved')";
    $params = [$date];
    if ($excludeId !== null) {
        $sql .= ' AND id != ?';
        $params[] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map('normalizeTime', array_column($stmt->fetchAll(), 'appointment_time'));
}

function getAvailableTimeSlots(PDO $pdo, string $date, $excludeId = null) {
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

function isSlotAvailable(PDO $pdo, string $date, string $time, $excludeId = null) {
    $normalizedDate = normalizeDate($date);
    $normalizedTime = normalizeTime($time);
    if ($normalizedDate === null || $normalizedTime === null || !isValidTimeSlot($normalizedTime)) {
        return false;
    }
    if (isSlotInPast($normalizedDate, $normalizedTime)) {
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

function validateAppointmentRequest(PDO $pdo, int $userId, $serviceId, $date, $time, $excludeId = null) {
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

function createAppointment(PDO $pdo, int $userId, $serviceId, $date, $time, string $status = 'pending', $adminMessage = null) {
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
    $stmt = $pdo->prepare('INSERT INTO appointments (user_id, service_id, appointment_date, appointment_time, status, admin_message) VALUES (?, ?, ?, ?, ?, ?)');
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

function bookAppointment(PDO $pdo, int $userId, $serviceId, $date, $time) {
    return createAppointment($pdo, $userId, $serviceId, $date, $time, 'pending');
}

function createWalkInAppointment(PDO $pdo, int $userId, $serviceId, $date, $time) {
    return createAppointment($pdo, $userId, $serviceId, $date, $time, 'approved');
}

function getUser(PDO $pdo, $userId = null) {
    $resolvedUserId = $userId ?? ($_SESSION['user_id'] ?? null);
    if (!$resolvedUserId) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$resolvedUserId]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function getUserAppointments(PDO $pdo, int $userId, $filters = []) {
    $sql = "SELECT a.*, s.name AS service_name FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.user_id = ?";
    $params = [$userId];
    $status = $filters['status'] ?? null;
    if (is_string($status) && isValidAppointmentStatus($status)) {
        $sql .= ' AND a.status = ?';
        $params[] = $status;
    }
    $scope = $filters['scope'] ?? null;
    if ($scope === 'upcoming') {
        $today = date('Y-m-d');
        $now = date('H:i:s');
        $sql .= ' AND (a.appointment_date > ? OR (a.appointment_date = ? AND a.appointment_time >= ?))';
        $params[] = $today;
        $params[] = $today;
        $params[] = $now;
    } elseif ($scope === 'past') {
        $today = date('Y-m-d');
        $now = date('H:i:s');
        $sql .= ' AND (a.appointment_date < ? OR (a.appointment_date = ? AND a.appointment_time < ?))';
        $params[] = $today;
        $params[] = $today;
        $params[] = $now;
    }
    $sort = strtolower((string) ($filters['sort'] ?? 'desc'));
    $direction = $sort === 'asc' ? 'ASC' : 'DESC';
    $sql .= " ORDER BY a.appointment_date {$direction}, a.appointment_time {$direction}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getUserAppointmentStats(PDO $pdo, int $userId) {
    $today = date('Y-m-d');
    $now = date('H:i:s');
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending, SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed, SUM(CASE WHEN appointment_date > ? OR (appointment_date = ? AND appointment_time >= ?) THEN 1 ELSE 0 END) AS upcoming FROM appointments WHERE user_id = ?");
    $stmt->execute([$today, $today, $now, $userId]);
    $stats = $stmt->fetch() ?: [];
    return [
        'total' => (int) ($stats['total'] ?? 0),
        'pending' => (int) ($stats['pending'] ?? 0),
        'approved' => (int) ($stats['approved'] ?? 0),
        'completed' => (int) ($stats['completed'] ?? 0),
        'upcoming' => (int) ($stats['upcoming'] ?? 0),
    ];
}

function getAllAppointments(PDO $pdo, $filters = []) {
    $sql = "SELECT a.*, u.first_name, u.last_name, u.email, u.mobile, s.name AS service_name FROM appointments a JOIN users u ON a.user_id = u.id JOIN services s ON a.service_id = s.id WHERE 1 = 1";
    $params = [];
    if (!empty($filters['date']) && normalizeDate($filters['date']) !== null) {
        $sql .= ' AND a.appointment_date = ?';
        $params[] = $filters['date'];
    }
    if (!empty($filters['service'])) {
        $serviceId = toPositiveInt($filters['service']);
        if ($serviceId !== null) {
            $sql .= ' AND a.service_id = ?';
            $params[] = $serviceId;
        }
    }
    if (!empty($filters['status']) && isValidAppointmentStatus($filters['status'])) {
        $sql .= ' AND a.status = ?';
        $params[] = $filters['status'];
    }
    $sql .= ' ORDER BY a.appointment_date ASC, a.appointment_time ASC, a.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getAppointmentById(PDO $pdo, int $appointmentId) {
    $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ?');
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch();
    return $appointment ?: null;
}

function canTransitionAppointmentStatus(string $currentStatus, string $newStatus) {
    $allowedTransitions = [
        'pending' => ['pending', 'approved', 'rejected'],
        'approved' => ['approved', 'completed', 'rejected'],
        'rejected' => ['rejected', 'pending'],
        'completed' => ['completed'],
    ];
    return in_array($newStatus, $allowedTransitions[$currentStatus] ?? [], true);
}

function updateAppointmentStatus(PDO $pdo, int $appointmentId, string $status, $adminMessage = null) {
    $appointment = getAppointmentById($pdo, $appointmentId);
    if ($appointment === null) {
        return [
            'success' => false,
            'errors' => ['Appointment not found.'],
        ];
    }
    if (!isValidAppointmentStatus($status)) {
        return [
            'success' => false,
            'errors' => ['Please choose a valid appointment status.'],
        ];
    }
    if (!canTransitionAppointmentStatus($appointment['status'], $status)) {
        return [
            'success' => false,
            'errors' => ['That appointment status change is not allowed.'],
        ];
    }
    if (
        isActiveAppointmentStatus($status)
        && !isActiveAppointmentStatus($appointment['status'])
        && !isSlotAvailable($pdo, $appointment['appointment_date'], $appointment['appointment_time'], $appointmentId)
    ) {
        return [
            'success' => false,
            'errors' => ['This schedule is no longer available for reactivation.'],
        ];
    }
    $message = trim((string) $adminMessage);
    $message = $message === '' ? null : $message;
    $stmt = $pdo->prepare('UPDATE appointments SET status = ?, admin_message = ? WHERE id = ?');
    $updated = $stmt->execute([$status, $message, $appointmentId]);
    return [
        'success' => $updated,
        'errors' => $updated ? [] : ['The appointment could not be updated.'],
    ];
}

function getAdminDashboardStats(PDO $pdo) {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending, SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed, SUM(CASE WHEN appointment_date = ? THEN 1 ELSE 0 END) AS today_total FROM appointments");
    $stmt->execute([$today]);
    $stats = $stmt->fetch() ?: [];

    return [
        'total' => (int) ($stats['total'] ?? 0),
        'pending' => (int) ($stats['pending'] ?? 0),
        'approved' => (int) ($stats['approved'] ?? 0),
        'completed' => (int) ($stats['completed'] ?? 0),
        'today_total' => (int) ($stats['today_total'] ?? 0),
    ];
}

function getRecentAppointments(PDO $pdo, $limit = 5) {
    $safeLimit = max(1, (int) $limit);
    $stmt = $pdo->query("SELECT a.*, u.first_name, u.last_name, s.name AS service_name FROM appointments a JOIN users u ON a.user_id = u.id JOIN services s ON a.service_id = s.id ORDER BY a.created_at DESC LIMIT {$safeLimit}");
    return $stmt->fetchAll();
}

function getMostBookedServices(PDO $pdo, $limit = 5) {
    $safeLimit = max(1, (int) $limit);
    $stmt = $pdo->query("SELECT s.name, SUM(CASE WHEN a.id IS NOT NULL AND a.status <> 'rejected' THEN 1 ELSE 0 END) AS total FROM services s LEFT JOIN appointments a ON s.id = a.service_id GROUP BY s.id, s.name ORDER BY total DESC, s.name ASC LIMIT {$safeLimit}");
    return $stmt->fetchAll();
}

function getAppointmentsByPeriod(PDO $pdo, string $period = 'daily') {
    if ($period === 'daily') {
        $sql = "SELECT DATE_FORMAT(appointment_date, '%Y-%m-%d') AS label, COUNT(*) AS total FROM appointments GROUP BY appointment_date ORDER BY appointment_date DESC LIMIT 30";
    } elseif ($period === 'weekly') {
        $sql = "SELECT CONCAT(YEAR(appointment_date), '-W', LPAD(WEEK(appointment_date, 1), 2, '0')) AS label, COUNT(*) AS total FROM appointments GROUP BY YEAR(appointment_date), WEEK(appointment_date, 1) ORDER BY YEAR(appointment_date) DESC, WEEK(appointment_date, 1) DESC LIMIT 12";
    } else {
        $sql = "SELECT DATE_FORMAT(appointment_date, '%Y-%m') AS label, COUNT(*) AS total FROM appointments GROUP BY DATE_FORMAT(appointment_date, '%Y-m') ORDER BY DATE_FORMAT(appointment_date, '%Y-m') DESC LIMIT 12";
    }
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

function getPeakDays(PDO $pdo) {
    $stmt = $pdo->query("SELECT DAYNAME(appointment_date) AS day, COUNT(*) AS total FROM appointments GROUP BY DAYOFWEEK(appointment_date), DAYNAME(appointment_date) ORDER BY total DESC, DAYOFWEEK(appointment_date) ASC");
    return $stmt->fetchAll();
}

function getPeakTimes(PDO $pdo) {
    $stmt = $pdo->query("SELECT appointment_time, COUNT(*) AS total FROM appointments GROUP BY appointment_time ORDER BY total DESC, appointment_time ASC LIMIT 5");
    return $stmt->fetchAll();
}

function getMonthlyComparison(PDO $pdo) {
    $current = date('Y-m');
    $previous = date('Y-m', strtotime('-1 month'));
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(appointment_date, '%Y-%m') AS month, COUNT(*) AS total FROM appointments WHERE DATE_FORMAT(appointment_date, '%Y-m') IN (?, ?) GROUP BY DATE_FORMAT(appointment_date, '%Y-m')");
    $stmt->execute([$current, $previous]);

    $result = $stmt->fetchAll();
    $data = ['current' => 0, 'previous' => 0];
    foreach ($result as $row) {
        if ($row['month'] === $current) {
            $data['current'] = (int) $row['total'];
        }

        if ($row['month'] === $previous) {
            $data['previous'] = (int) $row['total'];
        }
    }

    return $data;
}

?>
