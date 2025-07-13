<?php
require_once '../config/config.php';
require_once '../includes/secure_auth.php';

$auth = new SecureAuth($db);
$auth->logout();

header('Location: login.php?message=logged_out');
exit;
?>
