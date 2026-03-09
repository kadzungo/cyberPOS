<?php
include("../config/database.php");

$pc = trim($_GET['pc'] ?? '');
if ($pc === '') { echo "missing"; exit; }

// 1) Ensure PC exists in computers table
$stmt = mysqli_prepare($conn, "INSERT IGNORE INTO computers (computer_name, status, last_ping) VALUES (?, 'available', NOW())");
mysqli_stmt_bind_param($stmt, "s", $pc);
mysqli_stmt_execute($stmt);

// 2) Update last ping time
$stmt = mysqli_prepare($conn, "UPDATE computers SET last_ping=NOW() WHERE computer_name=?");
mysqli_stmt_bind_param($stmt, "s", $pc);
mysqli_stmt_execute($stmt);

echo "ok";