<?php
file_put_contents(__DIR__ . "/hitlog.txt", date("Y-m-d H:i:s") . " hit: " . ($_GET['pc'] ?? '') . PHP_EOL, FILE_APPEND);

include("../config/database.php");

$pc = isset($_GET['pc']) ? trim($_GET['pc']) : '';

if ($pc === '') {
    echo "locked";
    exit;
}

// Check if there is an ACTIVE session for this computer
$sql = "SELECT id FROM sessions 
        WHERE computer_name = ? 
        AND status = 'active' 
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $pc);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) > 0) {
    echo "active";
} else {
    echo "locked";
}