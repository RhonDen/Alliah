<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
requireRole('client');

$user = getUser($pdo);
$flash = getFlashMessage();

if (isPostRequest()) {
    if (!isValidCsrfToken($_POST['_token'] ?? null)) {
        $error = 'Session expired.';
    } else {
        $bio = trim($_POST['bio'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $emergency_contact = trim($_POST['emergency_contact'] ?? '');

        $stmt = $pdo->prepare('UPDATE users SET bio = ?, address = ?, emergency_contact = ? WHERE id = ?');
        if ($stmt->execute([$bio, $address, $emergency_contact, currentUserId()])) {
            setFlashMessage('success', 'Profile updated!');
            redirect('profile.php');
        } else {
            $error = 'Update failed.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo e(APP_NAME); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/client.css">
</head>
<body>
<?php include '../../includes/public/partials/nav-client.php'; ?>

<div class="client-container">
    <main>
        <?php if ($flash): ?>
            <div class="<?php echo $flash['type'] === 'success' ? 'success-message' : 'error-message'; ?>">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3>Your Profile</h3>
            <form method="POST">
                <?php echo csrfField(); ?>
                <div class="input-group">
                    <label>Name</label>
                    <p><?php echo e($user['first_name'] . ' ' . $user['last_name']); ?></p>
                </div>
                <div class="input-group">
                    <label for="email">Email</label>
                    <p><?php echo e($user['email']); ?></p>
                </div>
                <div class="input-group">

                    <label for="mobile">Mobile</label>
                    <p><?php echo e(normalizeMobile($user['mobile'] ?? '')); ?></p>

                </div>
                <div class="input-group">
                    <label for="bio">Bio/About Me</label>
                    <textarea id="bio" name="bio" rows="4" style="width: 100%; padding: 0.9rem; border: 1px solid #d0e2dc; border-radius: 12px; resize: vertical;"><?php echo e($user['bio'] ?? ''); ?></textarea>
                </div>
                <div class="input-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" value="<?php echo e($user['address'] ?? ''); ?>" placeholder="Your home/clinic address">
                </div>
                <div class="input-group">
                    <label for="emergency_contact">Emergency Contact</label>

    <input type="tel" id="emergency_contact" name="emergency_contact" value="<?php echo e(normalizeMobile($user['emergency_contact'] ?? '')); ?>" placeholder="09XX-XXX-XXXX">

                </div>
                <button type="submit" class="btn-submit">Update Profile</button>
            </form>
        </div>
    </main>
</div>


</body>
</html>

