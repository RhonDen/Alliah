<?php
require_once '../../includes/bootstrap.php';
requireRole('admin');

$page = $_GET['page'] ?? 'list';
$userId = toPositiveInt($_GET['id'] ?? 0);

if ($page === 'view' && $userId) {
    $user = getUser($pdo, $userId);
    if (!$user || $user['role'] !== 'client') {
        setFlashMessage('error', 'User not found.');
        redirect('users.php');
    }
}

$users = getPatients($pdo); // from functions.php
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - <?php echo e(APP_NAME); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php include '../../includes/public/partials/nav-admin.php'; ?>

<div class="admin-container">
    <?php if ($flash): ?>
        <div class="<?php echo $flash['type'] === 'success' ? 'success-message' : 'error-message'; ?>">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endif; ?>

    <?php if ($page === 'view' && $userId && $user): ?>
        <div class="card">
            <h3>Profile - <?php echo e($user['first_name'] . ' ' . $user['last_name']); ?></h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                <div>
                    <p><strong>Name:</strong> <?php echo e($user['first_name'] . ' ' . $user['last_name']); ?></p>
                    <p><strong>Email:</strong> <?php echo e($user['email']); ?></p>
                    <p><strong>Mobile:</strong> <?php echo e($user['mobile'] ?? ''); ?></p>
                    <p><strong>Age:</strong> <?php echo e($user['age']); ?></p>
                </div>
                <div>
                    <p><strong>Bio:</strong><br><?php echo nl2br(e($user['bio'] ?? 'No bio.')); ?></p>
                    <p><strong>Address:</strong><br><?php echo nl2br(e($user['address'] ?? 'No address.')); ?></p>
                    <p><strong>Emergency Contact:</strong> <?php echo e($user['emergency_contact'] ?? 'N/A'); ?></p>
                </div>
            </div>
            <div style="margin-top: 2rem;">
                <a href="?page=list" class="btn-secondary">← Back to list</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <h3>Clients (<?php echo count($users); ?>)</h3>
            <div style="overflow-x: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Profile</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?php echo e($u['id']); ?></td>
                                <td><?php echo e($u['first_name'] . ' ' . $u['last_name']); ?></td>
                                <td><?php echo e($u['email']); ?></td>
                    <td><?php echo e($u['mobile'] ?? ''); ?></td>
                                <td><a href="?page=view&id=<?php echo $u['id']; ?>">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
