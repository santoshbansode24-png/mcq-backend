<?php
/**
 * Admin Logout
 * MCQ Project 2.0
 */
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: index.php');
exit();
?>
