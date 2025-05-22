<?php
// This file should be placed in: C:\xampp\htdocs\ankur\logout.php

// Start the session
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destroy the session
session_destroy();

// Redirect to the login page
header("Location: index.php");
exit();
?>