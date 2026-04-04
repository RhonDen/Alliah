<?php
// Admin Navbar - relative from public/admin/*
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar">
    <div class="nav-container">
        <div class="logo"><a href="../index.php">Dents-City Admin</a></div>
        <div class="nav-links">
            <a href="dashboard.php" <?php echo $current_page === 'dashboard.php' ? 'class="active"' : ''; ?>>Dashboard</a>
            <a href="appointments.php" <?php echo $current_page === 'appointments.php' ? 'class="active"' : ''; ?>>Appointments</a>
            <a href="walkin.php" <?php echo $current_page === 'walkin.php' ? 'class="active"' : ''; ?>>Walk-in</a>
            <a href="analytics.php" <?php echo $current_page === 'analytics.php' ? 'class="active"' : ''; ?>>Analytics</a>
            <a href="../logout.php" class="btn-outline">Logout</a>
        </div>
        <div class="navbar-hamburger">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </div>
    </div>
</nav>
<script src="../assets/js/navbar.js" defer></script>
