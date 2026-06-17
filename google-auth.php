<?php
session_start();
$_SESSION['auth_flash_error'] = 'Google login is no longer available.';
header('Location: login.php');
exit();
