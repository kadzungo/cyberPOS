<?php
session_start();

require_once "../config/database.php";
require_once "../includes/audit_log.php";

log_action($conn, "LOGOUT", "User logged out");

session_destroy();

header("Location: login.php");
exit;
?>