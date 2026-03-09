<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
include("../config/database.php");
require_once "../includes/license_guard.php";
require_once "../includes/audit_log.php";

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid receipt.");
}

$stmt = mysqli_prepare($conn, "
    SELECT id, computer_name, start_time, end_time, rate_per_hour, amount_due
    FROM sessions
    WHERE id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);

if (!$row) {
    die("Receipt not found.");
}

$pc    = $row['computer_name'];
$start = $row['start_time'];
$end   = $row['end_time'];
$rate  = (float)$row['rate_per_hour'];
$due   = (float)$row['amount_due'];

$minutes = 0;
if ($start && $end) {
    $minutes = (int)ceil((strtotime($end) - strtotime($start)) / 60);
    if ($minutes < 1) $minutes = 1;
}

// Customize these:
$businessName = "CyberPOS";
$locationLine = "Nairobi, Kenya"; // change if you want
$currency = "KES";
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Receipt #<?php echo $row['id']; ?></title>
  <style>
    body{font-family:system-ui,Segoe UI,Arial;margin:0;background:#f3f4f6}
    .paper{
      width:320px; margin:18px auto; background:#fff; padding:16px;
      border:1px dashed #cbd5e1; border-radius:10px
    }
    h1{font-size:18px;margin:0 0 6px;text-align:center}
    .muted{color:#64748b;font-size:12px;text-align:center;margin:0 0 10px}
    .line{border-top:1px dashed #cbd5e1;margin:10px 0}
    .row{display:flex;justify-content:space-between;font-size:13px;margin:6px 0}
    .total{font-weight:900;font-size:16px}
    .btns{display:flex;gap:8px;justify-content:center;margin-top:12px}
    button,a{
      border:none; padding:10px 12px; border-radius:10px; cursor:pointer;
      font-weight:800; text-decoration:none; display:inline-block;
    }
    .print{background:#0b5ed7;color:#fff}
    .back{background:#e2e8f0;color:#0f172a}
    @media print{
      body{background:#fff}
      .btns{display:none}
      .paper{border:none; margin:0; width:auto}
    }
  </style>
</head>
<body>
  <div class="paper" id="receipt">
    <h1><?php echo htmlspecialchars($businessName); ?></h1>
    <p class="muted"><?php echo htmlspecialchars($locationLine); ?></p>
    <div class="line"></div>

    <div class="row"><span>Receipt</span><span>#<?php echo (int)$row['id']; ?></span></div>
    <div class="row"><span>Computer</span><span><?php echo htmlspecialchars($pc); ?></span></div>
    <div class="row"><span>Start</span><span><?php echo htmlspecialchars(date("Y-m-d H:i", strtotime($start))); ?></span></div>
    <div class="row"><span>End</span><span><?php echo htmlspecialchars(date("Y-m-d H:i", strtotime($end))); ?></span></div>
    <div class="row"><span>Minutes</span><span><?php echo (int)$minutes; ?></span></div>
    <div class="row"><span>Rate / Hour</span><span><?php echo $currency." ".number_format($rate, 2); ?></span></div>

    <div class="line"></div>
    <div class="row total"><span>Total</span><span><?php echo $currency." ".number_format($due, 2); ?></span></div>

    <div class="line"></div>
    <p class="muted">Thank you. Please come again.</p>

    <div class="btns">
      <button class="print" onclick="window.print()">Print</button>
      <a class="back" href="control.php">Back</a>
    </div>
  </div>

  <script>
    // Auto-open print dialog
    window.onload = function(){
      window.print();
    };
  </script>
</body>
</html>