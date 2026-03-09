<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

include("../config/database.php");
require_once "../includes/license_guard.php";
require_once "../includes/role_guard.php";

require_admin();

// Filters
$action = trim($_GET["action"] ?? "");
$user = trim($_GET["user"] ?? "");
$date = trim($_GET["date"] ?? "");

// Build query safely
$sql = "SELECT id, user_id, username, action, details, ip_address, created_at
        FROM audit_logs
        WHERE 1=1";

$params = [];
$types = "";

if ($action !== "") {
    $sql .= " AND action = ?";
    $params[] = $action;
    $types .= "s";
}

if ($user !== "") {
    $sql .= " AND username LIKE CONCAT('%', ?, '%')";
    $params[] = $user;
    $types .= "s";
}

if ($date !== "") {
    $sql .= " AND DATE(created_at) = ?";
    $params[] = $date;
    $types .= "s";
}

$sql .= " ORDER BY id DESC LIMIT 300";

$stmt = mysqli_prepare($conn, $sql);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$logs = mysqli_stmt_get_result($stmt);

// Quick stats
$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM audit_logs");
$totalLogs = (int)mysqli_fetch_assoc($r)["c"];

$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM audit_logs WHERE DATE(created_at)=CURDATE()");
$todayLogs = (int)mysqli_fetch_assoc($r)["c"];
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CyberPOS - Audit Logs</title>
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
    .nav{margin-top:18px}
    .nav a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;color:#eaf2ff;opacity:.95}
    .nav a.active,.nav a:hover{background:rgba(255,255,255,.12)}
    .main{flex:1;padding:22px}
    .topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:18px;flex-wrap:wrap}
    .title h1{margin:0;font-size:28px}
    .title p{margin:4px 0 0;color:var(--muted)}
    .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:14px 0 18px}
    .card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:16px}
    .stat{display:flex;align-items:center;justify-content:space-between}
    .stat .label{color:var(--muted);font-weight:700}
    .stat .value{font-size:28px;font-weight:900}
    .filters{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
    .filters input,.filters select,.filters button,.filters a{
      padding:10px 12px;border:1px solid var(--line);border-radius:12px;background:#fff;font-size:14px
    }
    .filters button{background:var(--blue);color:#fff;border:none;font-weight:800;cursor:pointer}
    .filters a{display:flex;align-items:center;justify-content:center}
    .tableWrap{overflow:auto}
    table{width:100%;border-collapse:separate;border-spacing:0 10px}
    th{color:var(--muted);text-align:left;font-size:12px;letter-spacing:.08em;text-transform:uppercase;padding:0 10px}
    td{background:var(--card);border:1px solid var(--line);padding:12px 10px;vertical-align:top}
    tr td:first-child{border-top-left-radius:14px;border-bottom-left-radius:14px}
    tr td:last-child{border-top-right-radius:14px;border-bottom-right-radius:14px}
    .muted{color:var(--muted);font-size:13px}
    .pill{display:inline-block;padding:6px 10px;border-radius:999px;background:#e2e8f0;color:#334155;font-weight:800;font-size:12px}
    @media (max-width: 980px){
      .sidebar{display:none}
      .grid{grid-template-columns:1fr}
      .filters{grid-template-columns:1fr}
    }
  </style>
</head>
<body>
<div class="layout">

  <?php include("../includes/sidebar.php"); ?>

  <main class="main">
    <div class="topbar">
      <div class="title">
        <h1>Audit Logs</h1>
        <p>Track staff and admin actions across the system.</p>
      </div>
    </div>

    <div class="grid">
      <div class="card">
        <div class="stat">
          <div>
            <div class="label">Total Logs</div>
            <div class="value"><?php echo (int)$totalLogs; ?></div>
          </div>
          <span class="pill">All Time</span>
        </div>
      </div>

      <div class="card">
        <div class="stat">
          <div>
            <div class="label">Today Logs</div>
            <div class="value"><?php echo (int)$todayLogs; ?></div>
          </div>
          <span class="pill">Today</span>
        </div>
      </div>
    </div>

    <div class="card">
      <form method="get" class="filters">
        <select name="action">
          <option value="">All Actions</option>
          <?php
          $actions = ["LOGIN","LOGOUT","ADD_COMPUTER","START_SESSION","END_SESSION","UPDATE_SETTINGS","ADD_USER","RESET_PASSWORD","DELETE_USER"];
          foreach ($actions as $a):
          ?>
            <option value="<?php echo $a; ?>" <?php if ($action === $a) echo "selected"; ?>>
              <?php echo $a; ?>
            </option>
          <?php endforeach; ?>
        </select>

        <input type="text" name="user" placeholder="Search username" value="<?php echo htmlspecialchars($user); ?>">
        <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>">

        <div style="display:flex;gap:10px;">
          <button type="submit">Filter</button>
          <a href="audit_logs.php">Reset</a>
        </div>
      </form>
    </div>

    <div class="card">
      <div style="font-weight:900;font-size:16px;margin-bottom:10px;">Recent Activity</div>
      <div class="tableWrap">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>User</th>
              <th>Action</th>
              <th>Details</th>
              <th>IP Address</th>
              <th>Date / Time</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($logs && mysqli_num_rows($logs) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($logs)): ?>
              <tr>
                <td><?php echo (int)$row["id"]; ?></td>
                <td>
                  <b><?php echo htmlspecialchars($row["username"] ?: "Unknown"); ?></b><br>
                  <span class="muted">User ID: <?php echo (int)($row["user_id"] ?? 0); ?></span>
                </td>
                <td><span class="pill"><?php echo htmlspecialchars($row["action"]); ?></span></td>
                <td><?php echo htmlspecialchars($row["details"] ?: "-"); ?></td>
                <td><?php echo htmlspecialchars($row["ip_address"] ?: "-"); ?></td>
                <td><?php echo htmlspecialchars($row["created_at"]); ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="muted">No audit logs found.</td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>
</body>
</html>