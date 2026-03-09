<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
include("../config/database.php");
require_once "../includes/license_guard.php";
require_once "../includes/audit_log.php";

$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');
$q    = trim($_GET['q'] ?? '');

if ($from === '') $from = date("Y-m-d");
if ($to === '')   $to   = date("Y-m-d");

$fromDT = $from . " 00:00:00";
$toDT   = $to . " 23:59:59";

$fromDTE = mysqli_real_escape_string($conn, $fromDT);
$toDTE   = mysqli_real_escape_string($conn, $toDT);
$qE      = mysqli_real_escape_string($conn, $q);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="cyberpos_reports_'.$from.'_to_'.$to.'.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['SessionID','Computer','StartTime','EndTime','Minutes','RatePerHour','AmountDue']);

$sql = "
  SELECT id, computer_name, start_time, end_time,
         GREATEST(1, CEIL(TIMESTAMPDIFF(SECOND,start_time,end_time)/60)) AS minutes_used,
         rate_per_hour, amount_due
  FROM sessions
  WHERE status='ended'
    AND end_time BETWEEN '$fromDTE' AND '$toDTE'
";
if ($q !== '') {
    $sql .= " AND computer_name LIKE '%$qE%' ";
}
$sql .= " ORDER BY end_time DESC LIMIT 5000 ";

$res = mysqli_query($conn, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        fputcsv($out, [
            (int)$row['id'],
            $row['computer_name'],
            $row['start_time'],
            $row['end_time'],
            (int)$row['minutes_used'],
            number_format((float)$row['rate_per_hour'], 2, '.', ''),
            number_format((float)$row['amount_due'], 2, '.', '')
        ]);
    }
}

fclose($out);
exit;