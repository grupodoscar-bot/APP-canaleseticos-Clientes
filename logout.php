<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/reporter_auth.php';
reporter_logout();
header('Location: /index.php');
exit;
