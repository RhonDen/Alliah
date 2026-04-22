<?php
require_once '../../includes/bootstrap.php';
requireRole('admin');

$page = $_GET['page'] ?? 'list';
$userId = toPositiveInt($_GET['id'] ?? 0);

if ($page === 'view' && $userId) {
    $user = getUser($pdo, $userId);
    if (!$user || $user['role'] !== 'client') {
        setFlashMessage('error', 'User not found.');
        redirect('admin/users.php');
    }
    $history = getUserAppointments($pdo, $userId);
}

$users = getPatients($pdo); // Get all patients without search
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients - <?php echo e(APP_NAME); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-page">
<?php include '../../includes/public/partials/nav-admin.php'; ?>

<div class="admin-container admin-layout">
    <main>
        <?php if ($flash): ?>
            <div class="<?php echo e($flash['type']); ?>-message"><?php echo e($flash['message']); ?></div>
        <?php endif; ?>

    <?php if ($page === 'view' && $userId && $user): ?>
        <div class="card">
            <h3><i class="fas fa-user"></i> Client Profile - <?php echo e($user['first_name'] . ' ' . $user['last_name']); ?></h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                <div>
                    <p><strong>Name:</strong> <?php echo e($user['first_name'] . ' ' . $user['last_name']); ?></p>
                    <p><strong>Email:</strong> <?php echo e($user['email']); ?></p>
                    <p><strong>Mobile:</strong> <?php echo e(!empty($user['mobile']) ? formatMobileForDisplay($user['mobile']) : ''); ?></p>
                    <p><strong>Age:</strong> <?php echo e($user['age']); ?></p>
                </div>
                <div>
                    <p><strong>Bio:</strong><br><?php echo nl2br(e($user['bio'] ?? 'No bio.')); ?></p>
                    <p><strong>Address:</strong><br><?php echo nl2br(e($user['address'] ?? 'No address.')); ?></p>
                    <p><strong>Emergency Contact:</strong> <?php echo e($user['emergency_contact'] ?? 'N/A'); ?></p>
                </div>
            </div>

            <div class="card" style="margin-top: 1.5rem;">
                <h4>Appointment History</h4>
                <?php if (!$history): ?>
                    <p>No appointment history found for this client.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="appointments-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Service</th>
                                    <th>Status</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $app): ?>
                                    <tr>
                                        <td><?php echo e($app['appointment_date']); ?></td>
                                        <td><?php echo e(formatAppointmentTime($app['appointment_time'])); ?></td>
                                        <td><?php echo e($app['service_name']); ?></td>
                                        <td class="status-<?php echo e($app['status']); ?>"><?php echo e(ucfirst($app['status'])); ?></td>
                                        <td><?php echo e($app['admin_message'] ?? '—'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div style="margin-top: 2rem;">
                <a href="?page=list" class="btn-secondary">← Back to list</a>
            </div>
        </div>
        <?php else: ?>
            <div class="page-header">
                <h2><i class="fas fa-users"></i> Client Management</h2>
                <p>Manage and view client profiles and appointment history</p>
            </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> All Clients (<?php echo count($users); ?>)</h3>
                        <div class="search-container">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="clientSearch" placeholder="Search clients by name, email, or mobile..." class="search-input">
                        </div>
                    </div>

                    <div style="overflow-x: auto; margin-top: 1.5rem;">
                        <table class="admin-table" id="clientsTable">
                            <thead>
                                <tr>
                                    <th style="display: none;">ID</th>
                                    <th><i class="fas fa-user"></i> Name</th>
                                    <th><i class="fas fa-envelope"></i> Email</th>
                                    <th><i class="fas fa-phone"></i> Mobile</th>
                                    <th><i class="fas fa-eye"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr class="client-row">
                                        <td style="display: none;"><?php echo e($u['id']); ?></td>
                                        <td><?php echo e($u['first_name'] . ' ' . $u['last_name']); ?></td>
                                        <td><?php echo e($u['email']); ?></td>
                                        <td><?php echo e(!empty($u['mobile']) ? formatMobileForDisplay($u['mobile']) : '—'); ?></td>
                                        <td><a href="?page=view&id=<?php echo $u['id']; ?>" class="btn-link"><i class="fas fa-eye"></i> View Profile</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php if (empty($users)): ?>
                    <div style="text-align: center; padding: 3rem; color: var(--gray-500);">
                        <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <p>No clients found.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
document.getElementById('clientSearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('.client-row');
    
    rows.forEach(row => {
        const name = row.cells[1].textContent.toLowerCase(); // Name is now index 1 (was 1, but ID hidden)
        const email = row.cells[2].textContent.toLowerCase(); // Email is now index 2 (was 2)
        const mobile = row.cells[3].textContent.toLowerCase(); // Mobile is now index 3 (was 3)
        
        const matches = name.includes(searchTerm) || email.includes(searchTerm) || mobile.includes(searchTerm);
        row.style.display = matches ? '' : 'none';
    });
});
</script>

</body>
</html>
