<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
requireRole('admin');

$recentAppointments = getTodayAppointments($pdo);

include '../../includes/public/partials/admin-recent-activity.php';
