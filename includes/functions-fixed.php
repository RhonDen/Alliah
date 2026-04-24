<?php
require_once __DIR__ . '/config.php';

const APPOINTMENT_STATUSES = ['pending', 'approved', 'rejected', 'completed', 'no_show'];
const ACTIVE_APPOINTMENT_STATUSES = ['pending', 'approved'];
const APPOINTMENT_INTERVAL_MINUTES = 30;
const CLINIC_OPEN_TIME = '09:00:00';
const CLINIC_CLOSE_TIME = '17:00:00';
const OTP_EXPIRY_SECONDS = 600;

if (!defined('SMS_MOCK_MODE')) {
    define('SMS_MOCK_MODE', false);
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect($path)
{
    $url = preg_match('#^https?://#i', $path) ? $path : BASE_URL . ltrim($path, '/');
    header('Location: ' . $url);
    exit;
}

function setFlashMessage($type, $message)
{
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getFlashMessage()
{
    if (empty($_SESSION['flash_message'])) {
        return null;
    }

    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);

    return $flash;
}

function csrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField()
{
    return '<input type="hidden" name="_token" value="' . e(csrfToken()) . '">';
}

function isValidCsrfToken($token)
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function isPostRequest()
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function formatAppointmentTime($time)
{
    return date('h:i A', strtotime($time));
}

function formatDateForDisplay($date)
{
    $timestamp = strtotime((string) $date);
    return $timestamp ? date('F j, Y', $timestamp) : (string) $date;
}

function toPositiveInt($value)
{
    $result = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $result === false ? null : (int) $result;
}

function normalizeDate($date)
{
    if (!$date) {
        return null;
    }

    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        return null;
    }

    return $parsed->format('Y-m-d');
}

function normalizeTime($time)
{
    if (!$time) {
        return null;
    }

    $timestamp = strtotime($time);
    if ($timestamp === false) {
        return null;
    }

    return date('H:i:s', $timestamp);
}

function normalizeMobile($mobile)
{
    if ($mobile === null) {
        return null;
    }

    $digits = preg_replace('/\D+/', '', trim((string) $mobile));
    if ($digits === '') {
        return null;
    }

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

function normalizeMobileForSms($mobile)
{
    return normalizeMobile($mobile);
}

function formatMobileForDisplay($mobile)
{
    $normalized = normalizeMobile($mobile);
    if ($normalized === null) {
        return trim((string) $mobile);
    }

    $local = '0' . substr($normalized, 2);
    return substr($local, 0, 4) . '-' . substr($local, 4, 3) . '-' . substr($local, 7);
}

function isValidMobile($mobile)
{
    return normalizeMobile($mobile) !== null;
}

function buildPlaceholderEmail($mobile)
{
    $digits = preg_replace('/\D+/', '', (string) $mobile);
    return 'patient+' . $digits . '@dents-city.local';
}

function findUserByIdentifier($pdo, $identifier)
{
    $identifier = trim((string) $identifier);
    if ($identifier === '') {
        return null;
    }

    if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$identifier]);
        $user = $stmt->fetch();
        if ($user) {
            return $user;
        }
    }

    $normalizedMobile = normalizeMobile($identifier);
    if ($normalizedMobile === null) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE mobile = ? LIMIT 1');
    $stmt->execute([$normalizedMobile]);

    return $stmt->fetch() ?: null;
}

function findClientByMobile($pdo, $mobile)
{
    $normalizedMobile = normalizeMobile($mobile);
    if ($normalizedMobile === null) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'client' AND mobile = ? LIMIT 1");
    $stmt->execute([$normalizedMobile]);

    return $stmt->fetch() ?: null;
}

function userHasAppointments($pdo, $userId)
{
    $stmt = $pdo->prepare('SELECT 1 FROM appointments WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    return (bool) $stmt->fetchColumn();
}

function createWalkInClient($pdo, $firstName, $lastName, $email, $mobile, $age = null)
{
    $errors = [];
    $normalizedMobile = normalizeMobile($mobile);
    $firstName = trim((string) $firstName);
    $lastName = trim((string) $lastName);
    $email = trim((string) $email);
    $age = $age === null ? null : (int) $age;

    if ($normalizedMobile === null) {
        $errors[] = 'Invalid mobile number.';
    }
    if (strlen($firstName) < 2 || strlen($lastName) < 2) {
        $errors[] = 'Name too short.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }
    if ($age !== null && ($age < 1 || $age > 120)) {
        $errors[] = 'Valid age required.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors, 'user_id' => null];
    }

    $existing = findClientByMobile($pdo, $normalizedMobile);
    $emailOwnerId = null;
    if ($email !== '') {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $emailOwnerId = $stmt->fetchColumn();
        if ($emailOwnerId !== false && (!$existing || (int) $emailOwnerId !== (int) $existing['id'])) {
            return ['success' => false, 'errors' => ['That email address is already linked to another account.'], 'user_id' => null];
        }
    }

    if ($existing) {
        $currentEmail = trim((string) ($existing['email'] ?? ''));
        $resolvedEmail = $email !== '' ? $email : ($currentEmail !== '' ? $currentEmail : buildPlaceholderEmail($normalizedMobile));
        $stmt = $pdo->prepare(
            'UPDATE users SET first_name = ?, last_name = ?, email = ?, age = ? WHERE id = ?'
        );
        $success = $stmt->execute([
            $firstName,
            $lastName,
            $resolvedEmail,
            $age ?? 0,
            $existing['id'],
        ]);

        return [
            'success' => $success,
            'errors' => $success ? [] : ['Failed to update patient.'],
            'user_id' => $success ? (int) $existing['id'] : null,
            'created' => false,
        ];
    }

    $resolvedEmail = $email !== '' ? $email : buildPlaceholderEmail($normalizedMobile);
    $stmt = $pdo->prepare(
        'INSERT INTO users (role, first_name, last_name, email, mobile, age, password) VALUES (?, ?, ?, ?, ?, ?, NULL) RETURNING id'
    );
    $success = $stmt->execute([
        'client',
        $firstName,
        $lastName,
        $resolvedEmail,
        $normalizedMobile,
        $age ?? 0,
    ]);
    $userId = $success ? (int) $stmt->fetchColumn() : null;

    return [
        'success' => $success,
        'errors' => $success ? [] : ['Failed to create patient.'],
        'user_id' => $userId,
        'created' => $success,
    ];
}

function generateOtp()
{
    return sprintf('%06d', random_int(0, 999999));
}

function ensureSmsLogDirectory()
{
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    return $logDir;
}

function sendSmsMock($mobile, $message)
{
    $logDir = ensureSmsLogDirectory();
    $entry = date('Y-m-d H:i:s') . ' | TO: ' . normalizeMobile($mobile) . ' | MSG: ' . trim((string) $message) . PHP_EOL;
    return file_put_contents($logDir . '/sms_mock.log', $entry, FILE_APPEND) !== false;
}

function sendUniSms($mobile, $message)
{
    $accessKey = UNISMS_ACCESS_KEY;
    $url = 'https://unismsapi.com/api/sms';

    $normalized = normalizeMobileForSms($mobile);
    if ($normalized === null) {
        return false;
    }

    $data = [
        'recipient' => '+' . $normalized,
        'content' => trim((string) $message),
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
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

function sendUniSmsOtp($mobile, $contentTemplate)
{
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
        'Content-Type: application/json',
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

function verifyUniSmsOtp($referenceId, $pin)
{
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
        'Content-Type: application/json',
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

function sendOtpViaSms($mobile, $otp)
{
    $message = 'Your Dents-City verification code is: ' . $otp . '. Valid for 10 minutes.';
    return SMS_MOCK_MODE ? sendSmsMock($mobile, $message) : sendUniSms($mobile, $message);
}

function requestOtpViaSms($mobile)
{
    if (SMS_MOCK_MODE) {
        $otp = generateOtp();
        $message = 'Your Dents-City verification code is: ' . $otp . '. Valid for 10 minutes.';
        sendSmsMock($mobile, $message);
        return ['type' => 'mock', 'otp' => $otp];
    }

    $referenceId = sendUniSmsOtp($mobile, 'Your Dents-City verification code is #{PIN}. Valid for 10 minutes.');
    if ($referenceId === null) {
        return null;
    }
    return ['type' => 'unisms', 'reference_id' => $referenceId];
}

function verifyOtpSubmission($verification, $submittedOtp)
{
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

function sendAppointmentStatusSms($mobile, $patientName, $date, $time, $service, $status)
{
    $patientName = trim((string) $patientName);
    $service = trim((string) $service);
    $dateLabel = formatDateForDisplay($date);
    $timeLabel = formatAppointmentTime($time);

    if ($status === 'approved') {
        $message = 'Hi ' . $patientName . ', your ' . $service . ' on ' . $dateLabel . ' at ' . $timeLabel . ' is CONFIRMED.';
    } elseif ($status === 'rejected') {
        $message = 'Hi ' . $patientName . ', your ' . $service . ' on ' . $dateLabel . ' at ' . $timeLabel . ' could NOT be approved. Please call us.';
    } else {
        return false;
    }

    return SMS_MOCK_MODE ? sendSmsMock($mobile, $message) : sendUniSms($mobile, $message);
}

function findOrCreateUserByMobile($pdo, $mobile, $firstName, $lastName, $age)
{
    $normalizedMobile = normalizeMobile($mobile);
    if ($normalizedMobile === null) {
        throw new InvalidArgumentException('Invalid mobile number.');
    }

    $firstName = trim((string) $firstName);
    $lastName = trim((string) $lastName);
    $age = (int) $age;

    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE mobile = ? LIMIT 1');
    $stmt->execute([$normalizedMobile]);
    $existing = $stmt->fetch();

    if ($existing) {
        $update = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, age = ? WHERE id = ?');
        $update->execute([$firstName, $lastName, $age, $existing['id']]);
        return (int) $existing['id'];
    }

    $insert = $pdo->prepare(
        'INSERT INTO users (role, first_name, last_name, email, mobile, age, password) VALUES (?, ?, ?, ?, ?, ?, NULL) RETURNING id'
    );
    $insert->execute([
        'client',
        $firstName,
        $lastName,
        buildPlaceholderEmail($normalizedMobile),
        $normalizedMobile,
        $age,
    ]);

    return (int) $insert->fetchColumn();
}

function isValidAppointmentStatus($status)
{
    return in_array($status, APPOINTMENT_STATUSES, true);
}

function isActiveAppointmentStatus($status)
{
    return in_array($status, ACTIVE_APPOINTMENT_STATUSES, true);
}

function isSlotInPast($date, $time)
{
    $slot = strtotime($date . ' ' . $time);
    return $slot !== false && $slot <= time();
}

function getServices($pdo)
{
    $stmt = $pdo->query('SELECT * FROM services ORDER BY name');
    return $stmt->fetchAll();
}

function getServiceById($pdo, $serviceId)
{
    $stmt = $pdo->prepare('SELECT * FROM services WHERE id = ?');
    $stmt->execute([$serviceId]);
    return $stmt->fetch() ?: null;
}

function getAppointmentTeeth($pdo, $appointmentId, $toothType = null)
{
    $sql = 'SELECT tooth_number, tooth_type, procedure_type, notes FROM appointment_teeth WHERE appointment_id = ?';
    $params = [$appointmentId];

    if ($toothType !== null) {
        $sql .= ' AND tooth_type = ?';
        $params[] = $toothType;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function saveAppointmentTeeth($pdo, $appointmentId, $teeth, $toothType = 'permanent', $procedure = 'extraction', $notes = null)
{
    if ((int) $appointmentId < 1) {
        return false;
    }

    $stmt = $pdo->prepare('DELETE FROM appointment_teeth WHERE appointment_id = ? AND tooth_type = ?');
    $stmt->execute([$appointmentId, $toothType]);

    if (empty($teeth) || !is_array($teeth)) {
        return true;
    }

    $insert = $pdo->prepare(
        'INSERT INTO appointment_teeth (appointment_id, tooth_number, tooth_type, procedure_type, notes) VALUES (?, ?, ?, ?, ?)'
    );

    foreach ($teeth as $tooth) {
        $toothNumber = trim((string) $tooth);
        if ($toothNumber === '') {
            continue;
        }

        $insert->execute([$appointmentId, $toothNumber, $toothType, $procedure, $notes]);
    }

    return true;
}

function serviceRequiresTeeth($pdo, $serviceId)
{
    try {
        $stmt = $pdo->prepare('SELECT requires_teeth FROM services WHERE id = ? LIMIT 1');
        $stmt->execute([$serviceId]);
        $row = $stmt->fetch();
        return $row ? (bool) $row['requires_teeth'] : false;
    } catch (PDOException $exception) {
        $stmt = $pdo->prepare('SELECT name FROM services WHERE id = ? LIMIT 1');
        $stmt->execute([$serviceId]);
        $service = $stmt->fetch();
        if (!$service) {
            return false;
        }

        $requiresTeeth = ['Extraction', 'Filling', 'Root Canal', 'Crown', 'Implant', 'Tooth Extraction'];
        return in_array($service['name'], $requiresTeeth, true);
    }
}

function getPatients($pdo, $search = null)
{
    $sql = "SELECT id, first_name, last_name, email, mobile FROM users WHERE role = 'client'";
    $params = [];

    if ($search !== null && $search !== '') {
        $searchTerm = '%' . $search . '%';
        $sql .= " AND (
            first_name ILIKE ?
            OR last_name ILIKE ?
            OR email ILIKE ?
            OR mobile ILIKE ?
            OR CONCAT(first_name, ' ', last_name) ILIKE ?
            OR CONCAT(last_name, ' ', first_name) ILIKE ?
        )";
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
    }

    $sql .= ' ORDER BY first_name, last_name';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getTimeSlots($intervalMinutes = APPOINTMENT_INTERVAL_MINUTES)
{
    $slots = [];
    $start = strtotime(CLINIC_OPEN_TIME);
    $end = strtotime(CLINIC_CLOSE_TIME);

    while ($start < $end) {
        $slots[] = date('H:i:s', $start);
        $start = strtotime('+' . $intervalMinutes . ' minutes', $start);
    }

    return $slots;
}

function isValidTimeSlot($time)
{
    return in_array($time, getTimeSlots(), true);
}

function getBookedTimeSlots($pdo, $date, $excludeId = null)
{
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

function getAvailableTimeSlots($pdo, $date, $excludeId = null)
{
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

function isSlotAvailable($pdo, $date, $time, $excludeId = null)
{
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

function validateAppointmentRequest($pdo, $userId, $serviceId, $date, $time, $excludeId = null)
{
    $errors = [];
    $normalizedServiceId = toPositiveInt($serviceId);
    $normalizedDate = normalizeDate($date);
    $normalizedTime = normalizeTime($time);

    if ((int) $userId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'client'");
        $stmt->execute([(int) $userId]);
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

    if (
        $normalizedDate !== null
        && $normalizedTime !== null
        && empty($errors)
        && !isSlotAvailable($pdo, $normalizedDate, $normalizedTime, $excludeId)
    ) {
        $errors[] = 'This time slot is already booked. Please choose another schedule.';
    }

    return [
        'errors' => $errors,
        'service_id' => $normalizedServiceId,
        'date' => $normalizedDate,
        'time' => $normalizedTime,
    ];
}

function createAppointment($pdo, $userId, $serviceId, $date, $time, $status = 'pending', $adminMessage = null)
{
    $validation = validateAppointmentRequest($pdo, $userId, $serviceId, $date, $time);
    if (!isValidAppointmentStatus($status)) {
        $validation['errors'][] = 'Invalid appointment status.';
    }

    if (!empty($validation['errors'])) {
        return ['success' => false, 'errors' => $validation['errors']];
    }

    $message = trim((string) $adminMessage);
    $message = $message === '' ? null : $message;

    $stmt = $pdo->prepare(
        'INSERT INTO appointments (user_id, service_id, appointment_date, appointment_time, status, admin_message) VALUES (?, ?, ?, ?, ?, ?) RETURNING id'
    );
    $created = $stmt->execute([
        $userId,
        $validation['service_id'],
        $validation['date'],
        $validation['time'],
        $status,
        $message,
    ]);
    $appointmentId = $created ? (int) $stmt->fetchColumn() : 0;

    if ($appointmentId < 1) {
        return ['success' => false, 'errors' => ['We could not save the appointment. Please try again.']];
    }

    return ['success' => true, 'errors' => [], 'appointment_id' => $appointmentId];
}

function bookAppointment($pdo, $userId, $serviceId, $date, $time)
{
    return createAppointment($pdo, $userId, $serviceId, $date, $time, 'pending');
}

function createWalkInAppointment($pdo, $userId, $serviceId, $date, $time)
{
    return createAppointment($pdo, $userId, $serviceId, $date, $time, 'approved');
}

function getUser($pdo, $userId = null)
{
    $resolvedUserId = $userId ?? ($_SESSION['user_id'] ?? null);
    if (!$resolvedUserId) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$resolvedUserId]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function getUserAppointments($pdo, $userId, $filters = [])
{
    $sql = 'SELECT a.*, s.name AS service_name FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.user_id = ?';
    $params = [$userId];

    $status = $filters['status'] ?? null;
    if (is_string($status) && isValidAppointmentStatus($status)) {
        $sql .= ' AND a.status = ?';
        $params[] = $status;
    }

    $scope = $filters['scope'] ?? null;
    $today = date('Y-m-d');
    $now = date('H:i:s');

    if ($scope === 'upcoming') {
        $sql .= ' AND (a.appointment_date > ? OR (a.appointment_date = ? AND a.appointment_time >= ?))';
        $params[] = $today;
        $params[] = $today;
        $params[] = $now;
    } elseif ($scope === 'past') {
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

function getUserAppointmentStats($pdo, $userId)
{
    $today = date('Y-m-d');
    $now = date('H:i:s');
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN (appointment_date > ? OR (appointment_date = ? AND appointment_time >= ?)) AND status != 'rejected' THEN 1 ELSE 0 END) AS upcoming
        FROM appointments
        WHERE user_id = ?"
    );
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

function getAllAppointments($pdo, $filters = [])
{
    $sql = "SELECT a.*, u.first_name, u.last_name, u.email, u.mobile, s.name AS service_name
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        JOIN services s ON a.service_id = s.id
        WHERE 1=1";
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

    $sql .= ' ORDER BY a.appointment_date DESC, a.appointment_time DESC, a.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getAppointmentById($pdo, $appointmentId)
{
    $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ?');
    $stmt->execute([$appointmentId]);
    return $stmt->fetch() ?: null;
}

function canTransitionAppointmentStatus($currentStatus, $newStatus)
{
    $allowedTransitions = [
        'pending' => ['pending', 'approved', 'rejected'],
        'approved' => ['approved', 'completed', 'rejected', 'no_show'],
        'rejected' => ['rejected', 'pending'],
        'completed' => ['completed'],
        'no_show' => ['no_show', 'pending'],
    ];

    return in_array($newStatus, $allowedTransitions[$currentStatus] ?? [], true);
}

function updateAppointmentStatus($pdo, $appointmentId, $status, $adminMessage = null)
{
    $appointment = getAppointmentById($pdo, $appointmentId);
    if ($appointment === null) {
        return ['success' => false, 'errors' => ['Appointment not found.']];
    }

    if (!isValidAppointmentStatus($status)) {
        return ['success' => false, 'errors' => ['Please choose a valid appointment status.']];
    }

    if (!canTransitionAppointmentStatus($appointment['status'], $status)) {
        return ['success' => false, 'errors' => ['That appointment status change is not allowed.']];
    }

    if (
        isActiveAppointmentStatus($status)
        && !isActiveAppointmentStatus($appointment['status'])
        && !isSlotAvailable($pdo, $appointment['appointment_date'], $appointment['appointment_time'], $appointmentId)
    ) {
        return ['success' => false, 'errors' => ['This schedule is no longer available for reactivation.']];
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

function getAdminDashboardStats($pdo)
{
    $today = date('Y-m-d');
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN appointment_date = ? AND status != 'rejected' THEN 1 ELSE 0 END) AS today_total
        FROM appointments"
    );
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

function getRecentAppointments($pdo, $limit = 5)
{
    $safeLimit = max(1, (int) $limit);
    $stmt = $pdo->query(
        "SELECT a.*, u.first_name, u.last_name, s.name AS service_name
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        JOIN services s ON a.service_id = s.id
        ORDER BY a.created_at DESC
        LIMIT {$safeLimit}"
    );
    return $stmt->fetchAll();
}

function getMostBookedServices($pdo, $limit = 5)
{
    $safeLimit = max(1, (int) $limit);
    $stmt = $pdo->query(
        "SELECT s.name, SUM(CASE WHEN a.id IS NOT NULL AND a.status <> 'rejected' THEN 1 ELSE 0 END) AS total
        FROM services s
        LEFT JOIN appointments a ON s.id = a.service_id
        GROUP BY s.id, s.name
        ORDER BY total DESC, s.name ASC
        LIMIT {$safeLimit}"
    );
    return $stmt->fetchAll();
}

function getAppointmentsByPeriod($pdo, $period = 'daily')
{
    if ($period === 'daily') {
        $sql = "SELECT TO_CHAR(appointment_date, 'YYYY-MM-DD') AS label, COUNT(*) AS total FROM appointments GROUP BY appointment_date ORDER BY appointment_date DESC LIMIT 30";
    } elseif ($period === 'weekly') {
        $sql = "SELECT CONCAT(CAST(EXTRACT(YEAR FROM appointment_date) AS INT), '-W', LPAD(CAST(CAST(EXTRACT(WEEK FROM appointment_date) AS INT) AS TEXT), 2, '0')) AS label, COUNT(*) AS total FROM appointments GROUP BY EXTRACT(YEAR FROM appointment_date), EXTRACT(WEEK FROM appointment_date) ORDER BY EXTRACT(YEAR FROM appointment_date) DESC, EXTRACT(WEEK FROM appointment_date) DESC LIMIT 12";
    } else {
        $sql = "SELECT TO_CHAR(appointment_date, 'YYYY-MM') AS label, COUNT(*) AS total FROM appointments GROUP BY TO_CHAR(appointment_date, 'YYYY-MM') ORDER BY TO_CHAR(appointment_date, 'YYYY-MM') DESC LIMIT 12";
    }

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

function getPeakDays($pdo)
{
    $stmt = $pdo->query(
        "SELECT TRIM(TO_CHAR(appointment_date, 'Day')) AS day, COUNT(*) AS total
        FROM appointments
        GROUP BY EXTRACT(DOW FROM appointment_date), TRIM(TO_CHAR(appointment_date, 'Day'))
        ORDER BY total DESC, EXTRACT(DOW FROM appointment_date) ASC"
    );
    return $stmt->fetchAll();
}

function getPeakTimes($pdo)
{
    $stmt = $pdo->query(
        'SELECT appointment_time, COUNT(*) AS total FROM appointments GROUP BY appointment_time ORDER BY total DESC, appointment_time ASC LIMIT 5'
    );
    return $stmt->fetchAll();
}

function getMonthlyComparison($pdo)
{
    $current = date('Y-m');
    $previous = date('Y-m', strtotime('-1 month'));
    $stmt = $pdo->prepare(
        "SELECT TO_CHAR(appointment_date, 'YYYY-MM') AS month, COUNT(*) AS total
        FROM appointments
        WHERE TO_CHAR(appointment_date, 'YYYY-MM') IN (?, ?)
        GROUP BY TO_CHAR(appointment_date, 'YYYY-MM')"
    );
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
