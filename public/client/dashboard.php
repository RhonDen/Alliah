<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
requireRole('client');

$flash = getFlashMessage();
$user = getUser($pdo);
$stats = getUserAppointmentStats($pdo, currentUserId() ?? 0);

// Upcoming
$upcomingAppts = getUserAppointments($pdo, currentUserId() ?? 0, ['scope' => 'upcoming', 'sort' => 'asc']);

// Past (approved/completed)
$pastAppts = getUserAppointments($pdo, currentUserId() ?? 0, ['scope' => 'past']);

// Rejected
$rejectedAppts = getUserAppointments($pdo, currentUserId() ?? 0, ['status' => 'rejected']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - <?php echo e(APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/client.css">
    
</head>
<body>
<?php include '../../includes/public/partials/nav-client.php'; ?>

    <div class="client-container">
        <main>
            <?php if ($flash): ?>
                <div class="<?php echo e($flash['type']); ?>-message"><?php echo e($flash['message']); ?></div>
            <?php endif; ?>

            <div class="stats">
                <div class="stat-card">
                    <h3>Total Appointments</h3>
                    <p><?php echo e($stats['total']); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Upcoming</h3>
                    <p><?php echo e($stats['upcoming']); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Completed</h3>
                    <p><?php echo e($stats['completed']); ?></p>
                </div>
            </div>

            <div id="client-appointments">
                <!-- Upcoming Appointments -->
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

                <!-- Past Appointments -->
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

                <!-- Rejected Appointments -->
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
        </main>
    </div>


<script>
    function refreshClientAppointments() {
        fetch('ajax-appointments.php')
            .then(response => response.text())
            .then(html => {
                document.getElementById('client-appointments').innerHTML = html;
            })
            .catch(error => console.log('Client refresh error:', error));
    }

    refreshClientAppointments();
    setInterval(refreshClientAppointments, 10000);
</script>
</body>
</html>

