<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
requireRole('client');

$flash = getFlashMessage();
$user = getUser($pdo);
$history = getUserAppointments($pdo, currentUserId() ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment History - <?php echo e(APP_NAME); ?></title>
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

            <div class="card">
                <h3>All Appointments</h3>
                <?php if (!$history): ?>
                    <p>No appointment history found yet.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="appointments-table">
                            <thead>
                                32
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Service</th>
                                    <th>Status</th>
                                    <th>Admin Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $app): ?>
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
        </main>
    </div>


</body>
</html>
