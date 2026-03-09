<?php
require_once "config/database.php";

$license = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT * FROM system_license WHERE id=1 LIMIT 1")
);

$status = "NOT ACTIVATED";
$status_color = "#dc3545"; // red

if ($license) {
    $today = date("Y-m-d");
    if ($license["expiry_date"] >= $today) {
        $status = "ACTIVE";
        $status_color = "#28a745"; // green
    } else {
        $status = "EXPIRED";
        $status_color = "#ffc107"; // yellow
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CyberPOS</title>
    <style>
        body{
            font-family: Arial, sans-serif;
            background: #0f2027;
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            color: #fff;
            text-align: center;
            padding-top: 90px;
        }
        .card{
            width: 420px;
            margin: auto;
            padding: 28px;
            border-radius: 12px;
            background: rgba(255,255,255,0.08);
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
        }
        h1{ margin: 0 0 8px; }
        .sub{ opacity: 0.85; margin-bottom: 18px; }
        .status{
            margin: 16px 0 22px;
            font-size: 16px;
            font-weight: bold;
        }
        .status span{
            color: <?= $status_color ?>;
        }
        a.btn{
            display: block;
            padding: 12px;
            margin: 10px 0;
            border-radius: 8px;
            text-decoration: none;
            color: #fff;
            background: #007bff;
        }
        a.btn:hover{ background: #0056b3; }
        .footer{
            margin-top: 35px;
            font-size: 12px;
            opacity: 0.7;
        }
    </style>
</head>
<body>

<div class="card">
    <h1>CyberPOS</h1>
    <div class="sub">Cyber Café Management System</div>

    <div class="status">
        License Status: <span><?= htmlspecialchars($status) ?></span>
    </div>

    <!-- Adjust login path if yours is different -->
    <a class="btn" href="staff/login.php">Login</a>
    <a class="btn" href="staff/license.php">Manage License</a>
</div>

<div class="footer">
    Version 1.0 • © <?= date("Y") ?> CyberPOS
</div>

</body>
</html>