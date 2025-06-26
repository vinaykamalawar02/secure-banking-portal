<?php
require_once 'includes/config.php';

// Log the logout activity if user was logged in
if (is_logged_in()) {
    log_activity($_SESSION['user_id'], $_SESSION['user_role'], 'Logout successful');
}

// Perform logout
logout();

// Redirect to login page
header('Location: index.php');
exit();
?>