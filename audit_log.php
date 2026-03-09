<?php
function log_action($conn, $action, $details = '') {

    $user_id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $stmt = mysqli_prepare($conn, "
        INSERT INTO audit_logs (user_id, username, action, details, ip_address)
        VALUES (?, ?, ?, ?, ?)
    ");

    mysqli_stmt_bind_param($stmt, "issss",
        $user_id,
        $username,
        $action,
        $details,
        $ip
    );

    mysqli_stmt_execute($stmt);
}