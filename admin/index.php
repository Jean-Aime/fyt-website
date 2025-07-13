<?php
require_once '../config/config.php';
require_once('../includes/secure_auth.php');

$auth = new SecureAuth($db);

if (isLoggedIn()) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit;
