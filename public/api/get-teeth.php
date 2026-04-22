<?php
header('Content-Type: application/json; charset=UTF-8');
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
requireRole('admin');

$appointmentId = (int) ($_GET['appointment_id'] ?? 0);
$toothType = trim((string) ($_GET['tooth_type'] ?? '')) ?: null;
if ($appointmentId < 1) {
    echo json_encode(['success' => false, 'error' => 'Invalid appointment ID']);
    exit;
}

$teeth = getAppointmentTeeth($pdo, $appointmentId, $toothType);
if ($teeth === false) {
    echo json_encode(['success' => false, 'error' => 'Unable to load tooth selections']);
    exit;
}

echo json_encode(['success' => true, 'teeth' => $teeth]);
