<?php
require_once __DIR__ . '/config.php';

const APPOINTMENT_STATUSES = ['pending', 'approved', 'rejected', 'completed', 'no_show'];
const ACTIVE_APPOINTMENT_STATUSES = ['pending', 'approved'];
const APPOINTMENT_INTERVAL_MINUTES = 30;
const CLINIC_OPEN_TIME = '09:00:00';
const CLINIC_CLOSE_TIME = '17:00:00';
const OTP_EXPIRY_SECONDS = 600;
const SMS_MOCK_MODE = false;

function e($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }

function redirect($path) {
    $url = preg_match('#^https?://#i', $path) ? $path : BASE_URL . ltrim($path, '/');
    header('Location: ' . $url);
    exit;
}

function setFlashMessage($type, $message) { $_SESSION['flash_message'] = ['type' => $type, 'message' => $message]; }

function getFlashMessage() {
    if (empty($_SESSION['flash_message'])) return null;
    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
    return $flash;
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function csrfField() { return '<input type="hidden" name="_token" value="' . e(csrfToken()) . '">'; }

function isValidCsrfToken($token) { return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token); }

function isPostRequest() { return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }

function formatAppointmentTime($time) { return date('h:i A', strtotime($time)); }

function toPositiveInt($value) {
    $result = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $result === false ? null : (int) $result;
}

function normalizeDate($date) {
    if (!$date) return null;
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date ? $parsed->format('Y-m-d') : null;
}

function normalizeTime($time) {
    $timestamp = strtotime($time ?? '');
    return $timestamp === false ? null : date('H:i:s', $timestamp);
}

function normalizeMobile($mobile) {
    $digits = preg_replace('/\D+/', '', trim($mobile ?? ''));
    if ($digits === '') return null;
    if (str_starts_with($digits, '63')) {
        if (strlen($digits) === 12 && substr($digits, 2, 1) === '9') {
            return $digits;
        }
        if (strlen($digits) === 13 && substr($digits, 2, 2) === '09') {
            return '63' . substr($digits, 3);
        }
    }
    if (str_starts_with($digits, '0')) {
        $digits = substr($digits, 1);
    }
    if (strlen($digits) === 10 && substr($digits, 0, 1) === '9') {
        return '63' . $digits;
    }
    return null;
}

function formatMobileForDisplay($mobile) {
    $normalized = normalizeMobile($mobile);
    if ($normalized === null) {
        return trim((string) $mobile);
    }
    $local = '0' . substr($normalized, 2);
    return substr($local, 0, 4) . '-' . substr($local, 4, 3) . '-' . substr($local, 7);
}

function findClientByMobile($pdo, $mobile) {
    $normalizedMobile = normalizeMobile($mobile);
    if ($normalizedMobile === null) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'client' AND mobile = ? LIMIT 1");
    $stmt->execute([$normalizedMobile]);
    return $stmt->fetch() ?: null;
}

function findUserByIdentifier($pdo, $identifier) {
    if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$identifier]);
        $user = $stmt->fetch();
        if ($user) return $user;
    }
    $normMobile = normalizeMobile($identifier);
    if ($normMobile !== null) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE mobile = ? LIMIT 1');
        $stmt->execute([$normMobile]);
        return $stmt->fetch() ?: null;
    }
    return null;
}

function getServices($pdo) { $stmt = $pdo->query('SELECT * FROM services ORDER BY name'); return $stmt->fetchAll(); }

function getServiceById($pdo, $serviceId) {
    $stmt = $pdo->prepare('SELECT * FROM services WHERE id = ?');
    $stmt->execute([$serviceId]);
    return $stmt->fetch() ?: null;
}

function getPatients($pdo) { $stmt = $pdo->query("SELECT id, first_name, last_name, email, mobile FROM users WHERE role = 'client' ORDER BY first_name, last_name"); return $stmt->fetchAll(); }

function getTimeSlots() {
    $slots = [];
    $start = strtotime(CLINIC_OPEN_TIME);
    $end = strtotime(CLINIC_CLOSE_TIME);
    while ($start < $end) {
        $slots[] = date('H:i:s', $start);
        $start = strtotime('+30 minutes', $start);
    }
    return $slots;
}

function getAvailableTimeSlots($pdo, $date, $excludeId = null) {
    $normalizedDate = normalizeDate($date);
    if ($normalizedDate === null) return [];
    $booked = array_flip(getBookedTimeSlots($pdo, $normalizedDate, $excludeId));
    $available = [];
    foreach (getTimeSlots() as $slot) {
        if (!isset($booked[$slot]) && !isSlotInPast($normalizedDate, $slot)) $available[] = $slot;
    }
    return $available;
}

function isSlotInPast($date, $time) {
    $slot = strtotime($date . ' ' . $time);
    return $slot !== false && $slot <= time();
}

function getBookedTimeSlots($pdo, $date, $excludeId = null) {
    $sql = "SELECT appointment_time FROM appointments WHERE appointment_date = ? AND status IN ('pending', 'approved')";
    $params = [$date];
    if ($excludeId !== null) { $sql .= ' AND id != ?'; $params[] = $excludeId; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map('normalizeTime', array_column($stmt->fetchAll(), 'appointment_time'));
}

function isSlotAvailable($pdo, $date, $time, $excludeId = null) {
    $nDate = normalizeDate($date);
    $nTime = normalizeTime($time);
    if ($nDate === null || $nTime === null || !in_array($nTime, getTimeSlots(), true) || isSlotInPast($nDate, $nTime)) return false;
    $sql = "SELECT COUNT(*) FROM appointments WHERE appointment_date = ? AND appointment_time = ? AND status IN ('pending', 'approved')";
    $params = [$nDate, $nTime];
    if ($excludeId !== null) { $sql .= ' AND id != ?'; $params[] = $excludeId; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn() === 0;
}

function validateAppointmentRequest($pdo, $userId, $serviceId, $date, $time, $excludeId = null) {
    $errors = [];
    $nServiceId = toPositiveInt($serviceId);
    $nDate = normalizeDate($date);
    $nTime = normalizeTime($time);
    if ($userId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'client'");
        $stmt->execute([$userId]);
        if (!$stmt->fetchColumn()) $errors[] = 'Invalid patient.';
    }
    if ($nServiceId === null || getServiceById($pdo, $nServiceId) === null) $errors[] = 'Invalid service.';
    if ($nDate === null) $errors[] = 'Invalid date.';
    elseif ($nDate < date('Y-m-d')) $errors[] = 'Future dates only.';
    if ($nTime === null) $errors[] = 'Invalid time.';
    if (!empty($errors) === false && !isSlotAvailable($pdo, $nDate, $nTime, $excludeId)) $errors[] = 'Slot booked.';
    return ['errors' => $errors, 'service_id' => $nServiceId, 'date' => $nDate, 'time' => $nTime];
}

function createAppointment($pdo, $userId, $serviceId, $date, $time, $status = 'pending', $adminMessage = null) {
    $validation = validateAppointmentRequest($pdo, $userId, $serviceId, $date, $time);
    if (!in_array($status, APPOINTMENT_STATUSES, true)) $validation['errors'][] = 'Invalid status.';
    if (!empty($validation['errors'])) return ['success' => false, 'errors' => $validation['errors']];
    $message = trim((string) $adminMessage) ?: null;
    $stmt = $pdo->prepare('INSERT INTO appointments (user_id, service_id, appointment_date, appointment_time, status, admin_message) VALUES (?, ?, ?, ?, ?, ?)');
    $created = $stmt->execute([$userId, $validation['service_id'], $validation['date'], $validation['time'], $status, $message]);
    return $created ? ['success' => true, 'appointment_id' => $pdo->lastInsertId()] : ['success' => false, 'errors' => ['Save failed.']];
}

function bookAppointment($pdo, $userId, $serviceId, $date, $time) {
    return createAppointment($pdo, $userId, $serviceId, $date, $time, 'pending');
}

function createWalkInAppointment($pdo, $userId, $serviceId, $date, $time) {
    return createAppointment($pdo, $userId, $serviceId, $date, $time, 'approved');
}

function getUser($pdo, $userId = null) {
    $resolvedUserId = $userId ?? ($_SESSION['user_id'] ?? null);
    if (!$resolvedUserId) return null;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$resolvedUserId]);
    return $stmt->fetch() ?: null;
}

function getUserAppointments($pdo, $userId, $filters = []) {
    $sql = "SELECT a.*, s.name AS service_name FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.user_id = ?";
    $params = [$userId];
    $status = $filters['status'] ?? null;
    if (is_string($status) && in_array($status, APPOINTMENT_STATUSES, true)) {
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

function getUserAppointmentStats($pdo, $userId) {
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

function getRecentAppointments($pdo, $limit = 5) {
    $stmt = $pdo->query("SELECT a.*, u.first_name, u.last_name, s.name AS service_name FROM appointments a JOIN users u ON a.user_id = u.id JOIN services s ON a.service_id = s.id ORDER BY a.created_at DESC LIMIT $limit");
    return $stmt->fetchAll();
}

function getPeakDays($pdo) {
    $stmt = $pdo->query("SELECT DAYNAME(appointment_date) as day, COUNT(*) as total FROM appointments GROUP BY day ORDER BY total DESC");
    return $stmt->fetchAll();
}

function getPeakTimes($pdo) {
    $stmt = $pdo->query("SELECT appointment_time, COUNT(*) as total FROM appointments GROUP BY appointment_time ORDER BY total DESC LIMIT 5");
    return $stmt->fetchAll();
}

// Helper: generate 6-digit OTP
function generateOtp(): string {
    return sprintf("%06d", mt_rand(1, 999999));
}

// Normalize mobile to international format for SMS (e.g., 639123456789)
function normalizeMobileForSms(?string $mobile): ?string {
    $digits = preg_replace('/\D+/', '', trim($mobile ?? ''));
    if ($digits === '') return null;
    if (str_starts_with($digits, '63')) {
        // already international
    } elseif (str_starts_with($digits, '0')) {
        $digits = '63' . substr($digits, 1);
    } else {
        $digits = '63' . $digits;
    }
    if (strlen($digits) !== 12 || !str_starts_with($digits, '63')) return null;
    return $digits;
}

function ensureSmsLogDirectory(): string {
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    return $logDir;
}

// Send SMS via UniSMS API
function sendUniSms(string $mobile, string $message): bool {
    $accessKey = UNISMS_ACCESS_KEY;
    $url = 'https://unismsapi.com/api/sms';

    $normalized = normalizeMobileForSms($mobile);
    if ($normalized === null) {
        return false;
    }

    $data = [
        'recipient' => '+' . $normalized,
        'content' => $message,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_USERPWD, $accessKey . ':');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($ch);
    $curlErrorMsg = curl_error($ch);

    $success = $response !== false && $curlError === 0 && $httpCode === 201;

    if (!$success) {
        $logDir = ensureSmsLogDirectory();
        $logEntry = date('Y-m-d H:i:s') . ' | SMS FAILED' . PHP_EOL;
        $logEntry .= '  HTTP Code: ' . $httpCode . PHP_EOL;
        $logEntry .= '  cURL Error: ' . $curlError . ' (' . $curlErrorMsg . ')' . PHP_EOL;
        $logEntry .= '  Response: ' . ($response === false ? 'false' : $response) . PHP_EOL;
        $logEntry .= '  Payload: ' . json_encode($data) . PHP_EOL;
        $logEntry .= str_repeat('-', 40) . PHP_EOL;
        file_put_contents($logDir . '/sms_errors.log', $logEntry, FILE_APPEND);
    }

    return $success;
}

// Send OTP verification SMS
function sendOtpViaSms(string $mobile, string $otp): bool {
    $message = "Your Dents-City verification code is: $otp. Valid for 10 minutes.";
    return SMS_MOCK_MODE ? sendSmsMock($mobile, $message) : sendUniSms($mobile, $message);
}

function sendUniSmsOtp(string $mobile, string $contentTemplate): ?string {
    $accessKey = UNISMS_ACCESS_KEY;
    $url = 'https://unismsapi.com/api/otp';

    $normalized = normalizeMobileForSms($mobile);
    if ($normalized === null) {
        return null;
    }

    $data = [
        'recipient' => '+' . $normalized,
        'content' => $contentTemplate,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_USERPWD, $accessKey . ':');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($ch);
    $curlErrorMsg = curl_error($ch);

    $success = $response !== false && $curlError === 0 && $httpCode === 201;

    if (!$success) {
        $logDir = ensureSmsLogDirectory();
        $logEntry = date('Y-m-d H:i:s') . ' | OTP FAILED' . PHP_EOL;
        $logEntry .= '  HTTP Code: ' . $httpCode . PHP_EOL;
        $logEntry .= '  cURL Error: ' . $curlError . ' (' . $curlErrorMsg . ')' . PHP_EOL;
        $logEntry .= '  Response: ' . ($response === false ? 'false' : $response) . PHP_EOL;
        $logEntry .= '  Payload: ' . json_encode($data) . PHP_EOL;
        $logEntry .= str_repeat('-', 40) . PHP_EOL;
        file_put_contents($logDir . '/sms_errors.log', $logEntry, FILE_APPEND);
        return null;
    }

    $decoded = json_decode((string) $response, true);
    return $decoded['message']['reference_id'] ?? null;
}

function verifyUniSmsOtp(string $referenceId, string $pin): bool {
    $accessKey = UNISMS_ACCESS_KEY;
    $url = 'https://unismsapi.com/api/otp/verify';

    $data = [
        'reference_id' => $referenceId,
        'pin' => $pin,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_USERPWD, $accessKey . ':');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false || $httpCode !== 200) {
        return false;
    }

    $decoded = json_decode((string) $response, true);
    return ($decoded['code'] ?? 0) === 200;
}

function requestOtpViaSms(string $mobile): ?array {
    if (SMS_MOCK_MODE) {
        $otp = generateOtp();
        $message = "Your Dents-City verification code is: $otp. Valid for 10 minutes.";
        sendSmsMock($mobile, $message);
        return ['type' => 'mock', 'otp' => $otp];
    }

    $referenceId = sendUniSmsOtp($mobile, 'Your Dents-City verification code is #{PIN}. Valid for 10 minutes.');
    if ($referenceId === null) {
        return null;
    }
    return ['type' => 'unisms', 'reference_id' => $referenceId];
}

function verifyOtpSubmission(?array $verification, string $submittedOtp): bool {
    if (!is_array($verification)) {
        return false;
    }

    if (($verification['type'] ?? '') === 'mock') {
        return $submittedOtp === (string) ($verification['otp'] ?? '');
    }

    if (($verification['type'] ?? '') === 'unisms') {
        return verifyUniSmsOtp($verification['reference_id'] ?? '', $submittedOtp);
    }

    return false;
}

function sendSmsMock(string $mobile, string $message): bool {
    $logDir = ensureSmsLogDirectory();
    $entry = date('Y-m-d H:i:s') . ' | TO: ' . normalizeMobileForSms($mobile) . ' | MSG: ' . trim($message) . PHP_EOL;
    return file_put_contents($logDir . '/sms_mock.log', $entry, FILE_APPEND) !== false;
}

// Send appointment status SMS (short, includes patient name)
function sendAppointmentStatusSms(string $mobile, string $patientName, string $service, string $date, string $time, string $status): bool {
    if ($status === 'approved') {
        $message = "$patientName, your $service on $date at $time is APPROVED.";
    } elseif ($status === 'rejected') {
        $message = "$patientName, your $service on $date at $time could not be approved. Please call us.";
    } else {
        return false;
    }
    // Keep message short (under 160 chars)
    if (strlen($message) > 160) {
        $message = substr($message, 0, 157) . '...';
    }
    return sendUniSms($mobile, $message);
}

// Find or create user by mobile – preserves history
function findOrCreateUserByMobile(PDO $pdo, string $mobile, string $firstName, string $lastName, int $age): int {
    $normMobile = normalizeMobileForSms($mobile);
    $stmt = $pdo->prepare('SELECT id FROM users WHERE mobile = ? LIMIT 1');
    $stmt->execute([$normMobile]);
    $existing = $stmt->fetch();
    if ($existing) {
        // Update name/age in case they changed
        $update = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, age = ? WHERE id = ?');
        $update->execute([$firstName, $lastName, $age, $existing['id']]);
        return (int)$existing['id'];
    } else {
        $insert = $pdo->prepare('INSERT INTO users (role, first_name, last_name, mobile, age, password) VALUES (?, ?, ?, ?, ?, NULL)');
        $insert->execute(['client', $firstName, $lastName, $normMobile, $age]);
        return (int)$pdo->lastInsertId();
    }
}

?>
