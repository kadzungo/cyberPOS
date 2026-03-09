<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
include("../config/database.php");
require_once "../includes/license_guard.php";
require_once "../includes/role_guard.php";
require_staff_or_admin();

// --------------------
// Quick ranges
// --------------------
$range = trim($_GET['range'] ?? ''); // today|week|month|year

if ($range === 'today') {
    $from = date("Y-m-d");
    $to   = date("Y-m-d");
} elseif ($range === 'week') {
    // Monday -> Sunday
    $monday = date("Y-m-d", strtotime("monday this week"));
    $sunday = date("Y-m-d", strtotime("sunday this week"));
    $from = $monday; $to = $sunday;
} elseif ($range === 'month') {
    $from = date("Y-m-01");
    $to   = date("Y-m-t");
} elseif ($range === 'year') {
    $from = date("Y-01-01");
    $to   = date("Y-12-31");
} else {
    // Manual filters (default today)
    $from = trim($_GET['from'] ?? '');
    $to   = trim($_GET['to'] ?? '');
    if ($from === '') $from = date("Y-m-d");
    if ($to === '')   $to   = date("Y-m-d");
}

$q = trim($_GET['q'] ?? '');

// Safe date boundaries
$fromDT = $from . " 00:00:00";
$toDT   = $to . " 23:59:59";

// Escape for mysqli_query
$fromDTE = mysqli_real_escape_string($conn, $fromDT);
$toDTE   = mysqli_real_escape_string($conn, $toDT);
$qE      = mysqli_real_escape_string($conn, $q);

// --------------------
// Summary cards
// --------------------
$today = date("Y-m-d");
$todayDT1 = mysqli_real_escape_string($conn, $today . " 00:00:00");
$todayDT2 = mysqli_real_escape_string($conn, $today . " 23:59:59");

$todayRow = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(amount_due),0) AS total
    FROM sessions
    WHERE status='ended' AND end_time BETWEEN '$todayDT1' AND '$todayDT2'
")) ?: ['total'=>0];

$monthStart = mysqli_real_escape_string($conn, date("Y-m-01") . " 00:00:00");
$monthEnd   = mysqli_real_escape_string($conn, date("Y-m-t") . " 23:59:59");
$monthRow = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(amount_due),0) AS total
    FROM sessions
    WHERE status='ended' AND end_time BETWEEN '$monthStart' AND '$monthEnd'
")) ?: ['total'=>0];

$rangeRow = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
      COUNT(*) AS sessions_count,
      COALESCE(SUM(amount_due),0) AS revenue,
      COALESCE(SUM(GREATEST(1, CEIL(TIMESTAMPDIFF(SECOND,start_time,end_time)/60))),0) AS minutes_used
    FROM sessions
    WHERE status='ended' AND end_time BETWEEN '$fromDTE' AND '$toDTE'
")) ?: ['sessions_count'=>0,'revenue'=>0,'minutes_used'=>0];

// --------------------
// Chart data: daily revenue
// --------------------
$chartLabels = [];
$chartTotals = [];
$chartRes = mysqli_query($conn, "
    SELECT DATE(end_time) AS d, COALESCE(SUM(amount_due),0) AS total
    FROM sessions
    WHERE status='ended' AND end_time BETWEEN '$fromDTE' AND '$toDTE'
    GROUP BY DATE(end_time)
    ORDER BY DATE(end_time) ASC
");
if ($chartRes) {
    while ($cr = mysqli_fetch_assoc($chartRes)) {
        $chartLabels[] = $cr['d'];
        $chartTotals[] = (float)$cr['total'];
    }
}

// --------------------
// Top PCs by revenue (range)
// --------------------
$topPCs = mysqli_query($conn, "
    SELECT computer_name,
           COALESCE(SUM(amount_due),0) AS revenue,
           COALESCE(SUM(GREATEST(1, CEIL(TIMESTAMPDIFF(SECOND,start_time,end_time)/60))),0) AS minutes_used
    FROM sessions
    WHERE status='ended' AND end_time BETWEEN '$fromDTE' AND '$toDTE'
    GROUP BY computer_name
    ORDER BY revenue DESC
    LIMIT 5
");

// --------------------
// Transactions list (range + search)
// --------------------
$listSql = "
    SELECT id, computer_name, start_time, end_time,
           GREATEST(1, CEIL(TIMESTAMPDIFF(SECOND,start_time,end_time)/60)) AS minutes_used,
           amount_due
    FROM sessions
    WHERE status='ended'
      AND end_time BETWEEN '$fromDTE' AND '$toDTE'
";
if ($q !== '') {
    $listSql .= " AND computer_name LIKE '%$qE%' ";
}
$listSql .= " ORDER BY end_time DESC LIMIT 200 ";
$list = mysqli_query($conn, $listSql);

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CyberPOS - Reports</title>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    :root{
      --bg:#f4f7fb; --card:#fff; --text:#0f172a; --muted:#64748b;
      --blue:#0b5ed7; --blue2:#0a4fb7; --line:#e5e7eb;
      --shadow:0 10px 25px rgba(2,6,23,.08); --radius:16px;
    }
    *{box-sizing:border-box}
    body{margin:0;font-family:system-ui,Segoe UI,Arial;background:var(--bg);color:var(--text)}
    a{color:inherit;text-decoration:none}
    .layout{display:flex;min-height:100vh}
    .sidebar{width:260px;background:linear-gradient(180deg,var(--blue),var(--blue2));color:#fff;padding:18px;position:sticky;top:0;height:100vh}
    .brand{font-size:20px;font-weight:800;margin-bottom:18px}
    .dot{width:10px;height:10px;border-radius:50%;background:#22c55e;display:inline-block;margin-left:8px}
    .nav{margin-top:18px}
    .nav a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;color:#eaf2ff;opacity:.95}
    .nav a.active,.nav a:hover{background:rgba(255,255,255,.12)}
    .main{flex:1;padding:22px}
    .topbar{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;margin-bottom:18px;flex-wrap:wrap}
    .title h1{margin:0;font-size:28px}
    .title p{margin:4px 0 0;color:var(--muted)}
    .filters{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
    .input{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:10px 12px;display:flex;gap:8px;align-items:center}
    .input input{border:none;outline:none;background:transparent}
    .btn{border:none;cursor:pointer;border-radius:12px;padding:10px 14px;font-weight:800;display:inline-block}
    .btn-blue{background:var(--blue);color:#fff}
    .btn-blue:hover{filter:brightness(.95)}
    .btn-ghost{background:#fff;border:1px solid var(--line);color:#0f172a}
    .grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:14px 0 18px}
    .card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:16px}
    .label{color:var(--muted);font-weight:700}
    .value{font-size:28px;font-weight:900;margin-top:6px}
    table{width:100%;border-collapse:separate;border-spacing:0 10px}
    th{color:var(--muted);text-align:left;font-size:12px;letter-spacing:.08em;text-transform:uppercase;padding:0 10px}
    td{background:var(--card);border:1px solid var(--line);padding:12px 10px}
    tr td:first-child{border-top-left-radius:14px;border-bottom-left-radius:14px}
    tr td:last-child{border-top-right-radius:14px;border-bottom-right-radius:14px}
    .muted{color:var(--muted);font-size:13px}
    .quick{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
    @media (max-width: 980px){
      .sidebar{display:none}
      .grid{grid-template-columns:1fr}
    }
  </style>
</head>
<body>
<div class="layout">

  <aside class="sidebar">
    <div class="brand">CyberPOS <span class="dot"></span></div>
    <div style="opacity:.9;font-weight:600">Staff Dashboard</div>

    <nav class="nav">
      <a href="control.php">🖥️ PC Control</a>
      <a href="billing.php">💳 Billing</a>
      <a href="users.php">👤 Users</a>
      <a class="active" href="reports.php">📊 Reports</a>
      <a href="#" onclick="alert('Next: Settings');return false;">⚙️ Settings (Next)</a>
      <a href="logout.php">🚪 Logout</a>
    </nav>
  </aside>

  <main class="main">
    <div class="topbar">
      <div class="title">
        <h1>Reports</h1>
        <p>Revenue + sessions summary, chart, export, and transaction list.</p>
        <div class="quick">
          <a class="btn btn-ghost" href="reports.php?range=today">Today</a>
          <a class="btn btn-ghost" href="reports.php?range=week">This Week</a>
          <a class="btn btn-ghost" href="reports.php?range=month">This Month</a>
          <a class="btn btn-ghost" href="reports.php?range=year">This Year</a>
        </div>
      </div>

      <form class="filters" method="get">
        <a class="btn btn-ghost" href="control.php">← Back to PC Control</a>

        <div class="input">From: <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>"></div>
        <div class="input">To: <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>"></div>
        <div class="input">🔎 <input name="q" placeholder="Search PC name..." value="<?php echo htmlspecialchars($q); ?>"></div>

        <button class="btn btn-blue">Apply</button>

        <a class="btn btn-blue"
           href="export_reports.php?from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>&q=<?php echo urlencode($q); ?>">
          Export CSV
        </a>
      </form>
    </div>

    <div class="grid">
      <div class="card">
        <div class="label">Today Revenue</div>
        <div class="value">KSh <?php echo number_format((float)$todayRow['total'], 2); ?></div>
      </div>

      <div class="card">
        <div class="label">This Month Revenue</div>
        <div class="value">KSh <?php echo number_format((float)$monthRow['total'], 2); ?></div>
      </div>

      <div class="card">
        <div class="label">Selected Range</div>
        <div class="value"><?php echo (int)$rangeRow['sessions_count']; ?> sessions</div>
        <div class="muted">
          Revenue: <b>KSh <?php echo number_format((float)$rangeRow['revenue'], 2); ?></b> ·
          Minutes: <b><?php echo (int)$rangeRow['minutes_used']; ?></b>
        </div>
      </div>
    </div>

    <div class="card">
      <div style="font-weight:900;font-size:16px;margin-bottom:10px;">Revenue Trend (Daily)</div>
      <canvas id="revChart" height="90"></canvas>
    </div>

    <script>
      const labels = <?php echo json_encode($chartLabels); ?>;
      const totals = <?php echo json_encode($chartTotals); ?>;

      const ctx = document.getElementById('revChart');
      new Chart(ctx, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [{
            label: 'Revenue (KSh)',
            data: totals,
            tension: 0.25
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { display: true } },
          scales: { y: { beginAtZero: true } }
        }
      });
    </script>

    <div class="card" style="margin-top:14px;">
      <div style="font-weight:900;font-size:16px;margin-bottom:10px;">Top PCs (by revenue)</div>
      <table>
        <thead>
          <tr>
            <th>PC</th>
            <th>Minutes</th>
            <th>Revenue</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($topPCs && mysqli_num_rows($topPCs) > 0) { ?>
            <?php while($r = mysqli_fetch_assoc($topPCs)) { ?>
              <tr>
                <td><b><?php echo htmlspecialchars($r['computer_name']); ?></b></td>
                <td><?php echo (int)$r['minutes_used']; ?></td>
                <td>KSh <?php echo number_format((float)$r['revenue'], 2); ?></td>
              </tr>
            <?php } ?>
          <?php } else { ?>
            <tr><td colspan="3" class="muted">No data for this range.</td></tr>
          <?php } ?>
        </tbody>
      </table>
    </div>

    <div class="card" style="margin-top:14px;">
      <div style="font-weight:900;font-size:16px;margin-bottom:10px;">Transactions (Ended Sessions)</div>
      <div style="overflow:auto">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>PC</th>
              <th>Start</th>
              <th>End</th>
              <th>Minutes</th>
              <th>Amount</th>
              <th>Receipt</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($list && mysqli_num_rows($list) > 0) { ?>
              <?php while($row = mysqli_fetch_assoc($list)) { ?>
                <tr>
                  <td><?php echo (int)$row['id']; ?></td>
                  <td><b><?php echo htmlspecialchars($row['computer_name']); ?></b></td>
                  <td class="muted"><?php echo htmlspecialchars($row['start_time']); ?></td>
                  <td class="muted"><?php echo htmlspecialchars($row['end_time']); ?></td>
                  <td><?php echo (int)$row['minutes_used']; ?></td>
                  <td><b>KSh <?php echo number_format((float)$row['amount_due'], 2); ?></b></td>
                  <td><a class="btn btn-blue" style="padding:8px 10px" href="receipt.php?id=<?php echo (int)$row['id']; ?>">View</a></td>
                </tr>
              <?php } ?>
            <?php } else { ?>
              <tr><td colspan="7" class="muted">No transactions for this range.</td></tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>
</body>
</html>