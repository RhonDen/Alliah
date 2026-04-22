<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
requireRole('admin');

$flash = getFlashMessage();
$stats = getAdminDashboardStats($pdo);
$recentLimit = 15;
$recentAppointments = getRecentAppointments($pdo, $recentLimit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo e(APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php include '../../includes/public/partials/nav-admin.php'; ?>

<div class="admin-container admin-layout">
    <main>
        <?php if ($flash): ?>
            <div class="<?php echo e($flash['type']); ?>-message"><?php echo e($flash['message']); ?></div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-card"><h3>Total Appointments</h3><p><?php echo e($stats['total']); ?></p></div>
            <div class="stat-card"><h3>Pending Approvals</h3><p><?php echo e($stats['pending']); ?></p></div>
            <div class="stat-card"><h3>Today's Appointments</h3><p><?php echo e($stats['today_total']); ?></p></div>
            <div class="stat-card"><h3>Completed Visits</h3><p><?php echo e($stats['completed']); ?></p></div>
        </div>

        <div class="card">
            <div id="recent-appointments">
                <?php include '../../includes/public/partials/admin-recent-activity.php'; ?>
            </div>
        </div>
    </main>
</div>



<script>
    function refreshRecent() {
        fetch('ajax-recent.php')
            .then(response => response.text())
            .then(html => {
                document.getElementById('recent-appointments').innerHTML = html;
            })
            .catch(error => console.log('Refresh error:', error));
    }
    setInterval(refreshRecent, 10000);
</script>
</body>
</html>
