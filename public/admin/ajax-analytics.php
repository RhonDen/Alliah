<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
requireRole('admin');

$period = $_GET['period'] ?? 'month';
$month = $_GET['month'] ?? date('Y-m');
$labels = [];
$values = [];

if ($period === 'day') {
    for ($i = 0; $i < 24; $i++) {
        $labels[] = $i . ':00';
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE EXTRACT(HOUR FROM appointment_time) = ? AND appointment_date = CURRENT_DATE AND status NOT IN ('rejected','no_show')");
        $stmt->execute([$i]);
        $values[] = (int)$stmt->fetchColumn();
    }
} elseif ($period === 'week') {
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('D', strtotime($date));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = ? AND status NOT IN ('rejected','no_show')");
        $stmt->execute([$date]);
        $values[] = (int)$stmt->fetchColumn();
    }
} else {
    // month – last 30 days
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('M j', strtotime($date));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = ? AND status NOT IN ('rejected','no_show')");
        $stmt->execute([$date]);
        $values[] = (int)$stmt->fetchColumn();
    }
}

header('Content-Type: application/json');
echo json_encode(['labels' => $labels, 'values' => $values]);
