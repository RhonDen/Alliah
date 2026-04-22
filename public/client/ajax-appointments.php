<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo 'Client dashboard refresh has been retired. Use /my-bookings.php with SMS OTP.';
