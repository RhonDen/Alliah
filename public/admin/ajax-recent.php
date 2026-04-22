<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
requireRole('admin');

$recentLimit = 15;
$recentAppointments = getRecentAppointments($pdo, $recentLimit);

include '../../includes/public/partials/admin-recent-activity.php';
