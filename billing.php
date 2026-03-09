<?php
session_start();
if (!isset($_SESSION["user_id"])) { 
    header("Location: login.php"); 
    exit; 
}

include("../config/database.php");
require_once "../includes/license_guard.php";
require_once "../includes/audit_log.php";
require_once "../includes/role_guard.php";
require_staff_or_admin();

// Filter
$view = $_GET['view'] ?? 'today'; // today | all
$where = "";
if ($view === "today") {
    $where = "WHERE DATE(start_time) = CURDATE()";
}

// Fetch sessions
$sql = "
  SELECT id, computer_name, start_time, end_time, rate_per_hour, amount_due, status
  FROM sessions
  $where
  ORDER BY id DESC
";
$sessions = mysqli_query($conn, $sql);

// Totals
$r = mysqli_query($conn, "SELECT IFNULL(SUM(amount_due),0) AS t FROM sessions WHERE DATE(start_time)=CURDATE() AND status='ended'");
$totalToday = (float)mysqli_fetch_assoc($r)['t'];

$r = mysqli_query($conn, "SELECT IFNULL(SUM(amount_due),0) AS t FROM sessions WHERE status='ended'");
$totalAll = (float)mysqli_fetch_assoc($r)['t'];

function minutes_used($start, $end) {
    if (!$start) return 0;
    $s = strtotime($start);
    $e = $end ? strtotime($end) : time();
    return max(1, (int)ceil(($e - $s) / 60));
}

function money($x) { 
    return number_format((float)$x, 2); 
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CyberPOS - Billing</title>
  <style>
    :root{
      --bg:#f4f7fb;
      --card:#fff;
      --text:#0f172a;
      --muted:#64748b;
      --line:#e5e7eb;
      --shadow:0 10px 25px rgba(2,6,23,.08);
      --radius:16px;
      --blue:#0b5ed7;
      --blue2:#0a4fb7;
    }
    *{box-sizing:border-box}
    body{margin:0;font-family:system-ui,Segoe UI,Arial;background:var(--bg);color:var(--text)}
    a{text-decoration:none}
    .layout{display:flex;min-height:100vh}
    .sidebar{width:260px;background:linear-gradient(180deg,var(--blue),var(--blue2));color:#fff;padding:18px;position:sticky;top:0;height:100vh}
    .brand{font-size:20px;font-weight:800;margin-bottom:18px}
    .dot{width:10px;height:10px;border-radius:50%;background:#22c55e;display:inline-block;margin-left:8px}
    .nav{margin-top:18px}
    .nav a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;color:#eaf2ff;opacity:.95}
    .nav a.active,.nav a:hover{background:rgba(255,255,255,.12)}

    .main{flex:1;padding:22px}
    .top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}
    h1{margin:0}
    .card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:16px;margin-bottom:14px}
    .grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
    .stat{display:flex;justify-content:space-between;align-items:center}
    .label{color:var(--muted);font-weight:800}
    .value{font-size:26px;font-weight:900}
    .btn{padding:10px 14px;border:none;border-radius:12px;background:var(--blue);color:#fff;font-weight:900;cursor:pointer;text-decoration:none}
    .btn2{padding:8px 12px;border:1px solid var(--line);border-radius:12px;background:#fff;color:#111827;font-weight:900;cursor:pointer;text-decoration:none}
    table{width:100%;border-collapse:separate;border-spacing:0 10px}
    th{color:var(--muted);text-align:left;font-size:12px;letter-spacing:.08em;text-transform:uppercase;padding:0 10px}
    td{background:#fff;border:1px solid var(--line);padding:12px 10px}
    tr td:first-child{border-top-left-radius:14px;border-bottom-left-radius:14px}
    tr td:last-child{border-top-right-radius:14px;border-bottom-right-radius:14px}
    .muted{color:var(--muted);font-size:13px}
    @media(max-width:900px){
      .sidebar{display:none}
      .grid{grid-template-columns:1fr}
    }
  </style>
</head>
<body>
<div class="layout">

  <?php include("../includes/sidebar.php"); ?>

  <main class="main">

    <div class="top">
      <div>
        <h1>Billing</h1>
        <div class="muted">Per minute billing (rounded up). Receipt available after session ends.</div>
      </div>
    </div>

    <div class="card">
      <div class="grid">
        <div class="stat">
          <div>
            <div class="label">Total Today</div>
            <div class="value"><?php echo money($totalToday); ?></div>
          </div>
          <div class="muted">KES</div>
        </div>

        <div class="stat">
          <div>
            <div class="label">Total All Time</div>
            <div class="value"><?php echo money($totalAll); ?></div>
          </div>
          <div class="muted">KES</div>
        </div>

        <div class="stat">
          <div>
            <div class="label">View</div>
            <div class="value"><?php echo htmlspecialchars($view === 'today' ? 'Today' : 'All'); ?></div>
          </div>
          <div>
            <a class="<?php echo $view==='today'?'btn':'btn2'; ?>" href="billing.php?view=today">Today</a>
            <a class="<?php echo $view==='all'?'btn':'btn2'; ?>" href="billing.php?view=all">All</a>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div style="font-weight:900;margin-bottom:10px;">Ended Sessions</div>
      <div style="overflow:auto;">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Computer</th>
              <th>Start</th>
              <th>End</th>
              <th>Minutes</th>
              <th>Rate / Hr</th>
              <th>Amount Due</th>
              <th>Status</th>
              <th>Receipt</th>
            </tr>
          </thead>
          <tbody>
          <?php while($s = mysqli_fetch_assoc($sessions)): ?>
            <?php
              if ($s['status'] !== 'ended') continue;
              $mins = minutes_used($s['start_time'], $s['end_time']);
            ?>
            <tr>
              <td><?php echo (int)$s['id']; ?></td>
              <td><b><?php echo htmlspecialchars($s['computer_name']); ?></b></td>
              <td class="muted"><?php echo htmlspecialchars($s['start_time']); ?></td>
              <td class="muted"><?php echo htmlspecialchars($s['end_time']); ?></td>
              <td><?php echo (int)$mins; ?></td>
              <td><?php echo money($s['rate_per_hour']); ?></td>
              <td><b><?php echo money($s['amount_due']); ?></b></td>
              <td><?php echo htmlspecialchars($s['status']); ?></td>
              <td>
                <a class="btn2" href="receipt.php?id=<?php echo (int)$s['id']; ?>">Receipt</a>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>
</body>
</html>