<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to view available appointment times.',
    ]);
    exit;
}

$date = normalizeDate($_GET['date'] ?? null);
if ($date === null) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please choose a valid appointment date.',
    ]);
    exit;
}

$slots = array_map(
    static function (string $slot): array {
        return [
            'value' => $slot,
            'label' => formatAppointmentTime($slot),
        ];
    },
    getAvailableTimeSlots($pdo, $date)
);

echo json_encode([
    'success' => true,
    'date' => $date,
    'slots' => $slots,
    'message' => $slots ? null : 'No appointment slots are available for this date.',
]);
