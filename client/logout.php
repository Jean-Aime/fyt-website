<?php
session_start();

// Only unset client-specific session keys
unset($_SESSION['user_id']);
unset($_SESSION['user_email']);
unset($_SESSION['user_name']);
unset($_SESSION['user_role']);

// Optionally clear all session variables
// session_unset();

// Optionally destroy the session
// session_destroy();

// Redirect to login or home page
header("Location: ../login.php");
exit;
