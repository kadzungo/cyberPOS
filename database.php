<?php
$conn = mysqli_connect("localhost", "root", "", "cyberpos_db");

if (!$conn) {
    http_response_code(500);
    die("Database connection failed");
}
?>