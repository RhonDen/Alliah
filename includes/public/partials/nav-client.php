<?php
// Client Navbar - relative paths assume from public/client/*
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar">
    <div class="nav-container">
        <div class="logo"><a href="../index.php">Dents-City</a></div>
        <div class="nav-links">
    <a href="dashboard.php" <?php echo $current_page === 'dashboard.php' ? 'class="active"' : ''; ?>>Dashboard</a>
    <a href="profile.php" <?php echo $current_page === 'profile.php' ? 'class="active"' : ''; ?>>Profile</a>
    <a href="book.php" <?php echo $current_page === 'book.php' ? 'class="active"' : ''; ?> >Book</a>
    <a href="history.php" <?php echo $current_page === 'history.php' ? 'class="active"' : ''; ?>>History</a>
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
