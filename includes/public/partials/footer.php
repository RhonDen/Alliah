<?php
$homeUrl = BASE_URL . 'index.php';
$bookUrl = BASE_URL . 'client/book.php';
$historyUrl = BASE_URL . 'my-bookings.php';
$staffLoginUrl = BASE_URL . 'login.php';
?>
<footer class="footer-enhanced">
    <div class="container">
        <div class="footer-content grid">
            <div class="footer-section">
                <h4>Dents-City Dental</h4>
                <p>Your trusted partner for modern dental care and seamless appointment management.</p>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <a href="<?php echo e($homeUrl); ?>">Home</a>
                <a href="<?php echo e($homeUrl); ?>#services">Services</a>
                <a href="<?php echo e($homeUrl); ?>#about">About</a>
                <a href="<?php echo e($staffLoginUrl); ?>">Staff Login</a>
            </div>
            <div class="footer-section">
                <h4>Patient Access</h4>
                <a href="<?php echo e($bookUrl); ?>">Book Appointment</a>
                <a href="<?php echo e($historyUrl); ?>">My Bookings</a>
                <p>Verify with SMS OTP, no password needed.</p>
            </div>
            <div class="footer-section">
                <h4>Follow Us</h4>
                <div class="social-icons">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Dents-City Dental Clinic. All rights reserved.</p>
        </div>
    </div>
</footer>
