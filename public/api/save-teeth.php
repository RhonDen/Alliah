<?php
header('Content-Type: application/json; charset=UTF-8');
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
requireRole('admin');

$input = json_decode(file_get_contents('php://input'), true);
$appointmentId = (int) ($input['appointment_id'] ?? 0);
$teeth = $input['teeth'] ?? [];
$toothType = ($input['tooth_type'] ?? 'permanent') === 'primary' ? 'primary' : 'permanent';
$procedure = $input['procedure'] ?? 'extraction';
$allowedProcedures = ['extraction', 'filling', 'root_canal', 'crown', 'implant', 'other'];
if (!in_array($procedure, $allowedProcedures, true)) {
    $procedure = 'extraction';
}

if ($appointmentId < 1) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid appointment ID']);
    exit;
}

$appointment = getAppointmentById($pdo, $appointmentId);
if (!$appointment) {
    echo json_encode(['success' => false, 'error' => 'Appointment not found']);
    exit;
}

$success = saveAppointmentTeeth($pdo, $appointmentId, $teeth, $toothType, $procedure, null);
if (!$success) {
    echo json_encode(['success' => false, 'error' => 'Unable to save tooth selections']);
    exit;
}

echo json_encode(['success' => true]);
