<?php
$homeUrl = BASE_URL . 'index.php';
$bookUrl = BASE_URL . 'client/book.php';
$historyUrl = BASE_URL . 'my-bookings.php';
?>
<nav class="navbar" aria-label="Primary navigation">
    <div class="nav-container">
        <div class="logo"><a href="<?php echo e($homeUrl); ?>">Dents-City</a></div>
        <button class="navbar-hamburger" type="button" aria-controls="primary-navigation" aria-expanded="false" aria-label="Open menu">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>
        <div class="nav-links" id="primary-navigation" role="menu">
            <a href="<?php echo e($bookUrl); ?>">Book Appointment</a>
            <a href="<?php echo e($historyUrl); ?>">History</a>
        </div>
    </div>
</nav>
<script src="<?php echo e(BASE_URL . 'assets/js/navbar.js'); ?>" defer></script>
