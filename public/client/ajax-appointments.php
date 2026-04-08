<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
requireRole('client');

$upcomingAppts = getUserAppointments($pdo, currentUserId() ?? 0, ['scope' => 'upcoming', 'sort' => 'asc']);
$pastAppts = getUserAppointments($pdo, currentUserId() ?? 0, ['scope' => 'past']);
$rejectedAppts = getUserAppointments($pdo, currentUserId() ?? 0, ['status' => 'rejected']);
?>

<div id="client-appointments-refresh">
    <div class="card">
        <h3>Your Upcoming Appointments</h3>
        <?php if (count($upcomingAppts) === 0): ?>
            <p>No upcoming appointments. <a href="book.php" class="btn-book">Book now →</a></p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Admin Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingAppts as $app): ?>
                            <tr>
                                <td><?php echo e($app['appointment_date']); ?></td>
                                <td><?php echo e(formatAppointmentTime($app['appointment_time'])); ?></td>
                                <td><?php echo e($app['service_name']); ?></td>
                                <td class="status-<?php echo e($app['status']); ?>"><?php echo e(ucfirst($app['status'])); ?></td>
                                <td><?php echo e($app['admin_message'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Past Appointments</h3>
        <?php if (count($pastAppts) === 0): ?>
            <p>No past appointments.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Admin Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pastAppts as $app): ?>
                            <tr>
                                <td><?php echo e($app['appointment_date']); ?></td>
                                <td><?php echo e(formatAppointmentTime($app['appointment_time'])); ?></td>
                                <td><?php echo e($app['service_name']); ?></td>
                                <td class="status-<?php echo e($app['status']); ?>"><?php echo e(ucfirst($app['status'])); ?></td>
                                <td><?php echo e($app['admin_message'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Rejected/Canceled Appointments</h3>
        <?php if (count($rejectedAppts) === 0): ?>
            <p>No rejected appointments.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Service</th>
                            <th>Admin Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rejectedAppts as $app): ?>
                            <tr>
                                <td><?php echo e($app['appointment_date']); ?></td>
                                <td><?php echo e(formatAppointmentTime($app['appointment_time'])); ?></td>
                                <td><?php echo e($app['service_name']); ?></td>
                                <td><strong><?php echo e($app['admin_message'] ?? 'No reason provided'); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
