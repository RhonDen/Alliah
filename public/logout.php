<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

logoutUser();
setFlashMessage('success', 'You have been logged out.');
redirect('index.php');
