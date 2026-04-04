<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
requireRole('admin');

$recent = getRecentAppointments($pdo, 6);
?>
<?php if (!$recent): ?>
    <p>No recent appointments.</p>
<?php else: ?>
    <div style="overflow-x: auto;">
        <table class="appointments-table">
            <thead>
                <tr><th>Patient</th><th>Service</th><th>Date</th><th>Time</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $app): ?>
                <tr>
                    <td><?php echo e($app['first_name'] . ' ' . $app['last_name']); ?></td>
                    <td><?php echo e($app['service_name']); ?></td>
                    <td><?php echo e($app['appointment_date']); ?></td>
                    <td><?php echo e(formatAppointmentTime($app['appointment_time'])); ?></td>
                    <td class="status-<?php echo e($app['status']); ?>"><?php echo e(ucfirst($app['status'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>