<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirectForRole();
}

$flash = getFlashMessage();
$identifier = $_GET['identifier'] ?? $_POST['identifier'] ?? '';
$error = '';
$success = '';

if (isPostRequest()) {
    $submittedIdentifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!isValidCsrfToken($_POST['_token'] ?? null)) {
        $error = 'Session expired. Please try again.';
    } elseif ($submittedIdentifier !== $identifier) {
        $error = 'Security check failed.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $user = findUserByIdentifier($pdo, $identifier);
        if (!$user || !empty($user['password'])) {
            $error = 'Invalid or already activated account.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            if ($stmt->execute([$hashed, $user['id']])) {
                loginUser($user);  // Auto-login after set
                setFlashMessage('success', 'Password set successfully. Welcome!');
                redirectForRole('client');
            } else {
                $error = 'Failed to set password. Try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Password | Dents-City</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page">
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo"><a href="index.php">Dents-City</a></div>
            <div class="nav-links">
                <a href="index.php">Home</a>
            </div>
        </div>
    </nav>

    <main class="auth-section">
        <div class="auth-card">
            <div class="auth-header">
                <i class="fas fa-lock-open" style="font-size: 3rem; color: #48a48f; margin-bottom: 1rem;"></i>
                <h1>Complete Your Account</h1>
                <p>Since you already registered through a walk-in appointment, just set your password to activate your account for <?php echo htmlspecialchars($identifier); ?>.</p>
            </div>

            <?php if ($flash): ?>
                <div class="<?php echo $flash['type'] === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo e($flash['message']); ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error-message"><?php echo e($error); ?></div>
            <?php endif; ?>

            <?php if (empty($success)): ?>
            <form method="POST" class="password-form">
                <?php echo csrfField(); ?>
                <input type="hidden" name="identifier" value="<?php echo e($identifier); ?>">
                
                <div class="input-group">
                    <label for="password">New Password <span class="required">(required)</span></label>
                    <input type="password" id="password" name="password" required minlength="8" aria-describedby="password-help">
                    <small id="password-help">At least 8 characters</small>
                </div>
                
                <div class="input-group">
                    <label for="confirm_password">Confirm Password <span class="required">(required)</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8" aria-describedby="confirm-help">
                    <small id="confirm-help">Must match password above</small>
                </div>
                
                <button type="submit" class="btn-auth btn-primary">Set Password & Continue</button>
            </form>

            <div style="text-align: center; margin-top: 1.5rem;">
                <a href="login.php" class="auth-link">← Back to Login</a>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="footer-enhanced">
        <!-- Footer content same as login -->
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Dents-City Dental Clinic. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <style>
        :root {
            --primary: #1f816a;
            --primary-light: #48a48f;
            --success: #10b981;
            --error: #ef4444;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(135deg, var(--primary), #166b57);
            min-height: 100vh; 
            color: #1a202c;
        }
        .auth-page { display: flex; flex-direction: column; min-height: 100vh; }
        
        .navbar { 
            background: rgba(255,255,255,0.95); 
            backdrop-filter: blur(20px); 
            padding: 1rem 2rem; 
        }
        .nav-container { 
            max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; 
        }
        .logo a { 
            font-size: 1.8rem; font-weight: 800; color: var(--primary); text-decoration: none; 
        }
        
        .auth-section { 
            flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; 
        }
        .auth-card { 
            max-width: 480px; width: 100%; background: white; border-radius: 24px; padding: 3rem 2.5rem; 
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); 
            border: 1px solid rgba(72,164,143,0.2);
        }
        .auth-header { text-align: center; margin-bottom: 2rem; }
        .auth-header h1 { color: #1a3a34; font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; }
        .auth-header p { color: #6b7280; font-size: 1.1rem; }
        
        .input-group { margin-bottom: 1.5rem; }
        .input-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; }
        .required { color: var(--error); }
        .input-group input { 
            width: 100%; padding: 1rem 1.25rem; border: 2px solid #e5e7eb; border-radius: 12px; 
            font-size: 1rem; transition: all 0.2s; 
        }
        .input-group input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(31,129,106,0.1); }
        .input-group small { color: #6b7280; font-size: 0.875rem; margin-top: 0.25rem; display: block; }
        
        .btn-auth { 
            width: 100%; background: linear-gradient(135deg, var(--primary), var(--primary-light)); 
            color: white; border: none; padding: 1.25rem; border-radius: 12px; 
            font-weight: 600; font-size: 1.1rem; cursor: pointer; transition: all 0.3s; 
            margin-top: 0.5rem;
        }
        .btn-auth:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(31,129,106,0.3); }
        
        .error-message, .success-message { 
            padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; font-weight: 500; 
        }
        .error-message { background: #fef2f2; color: var(--error); border: 1px solid #fecaca; }
        .success-message { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        
        .auth-link { color: #6b7280; text-decoration: none; font-weight: 500; }
        .auth-link:hover { color: var(--primary); }
        
        .footer-enhanced { 
            background: #0f5132; color: #d1e7dd; padding: 2rem 0 1rem; text-align: center; 
            margin-top: auto;
        }
        .footer-bottom { font-size: 0.875rem; opacity: 0.9; }
        
        @media (max-width: 480px) {
            .auth-card { margin: 1rem; padding: 2rem 1.5rem; }
            .auth-header h1 { font-size: 1.5rem; }
        }
    </style>
    
    <script>
        const password = document.getElementById('password');
        const confirm = document.getElementById('confirm_password');
        if (password && confirm) {
            confirm.addEventListener('input', () => {
                if (confirm.value && confirm.value !== password.value) {
                    confirm.setCustomValidity('Passwords do not match');
                } else {
                    confirm.setCustomValidity('');
                }
            });
        }
    </script>
</body>
</html>
