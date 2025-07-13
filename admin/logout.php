<?php
require_once '../config/config.php';
require_once __DIR__ . '/../includes/secure_auth.php';

$auth = new SecureAuth($db);

// Perform logout
$auth->logout();

// Redirect to login page
header('Location: login.php?message=You have been logged out successfully');
exit;
?>