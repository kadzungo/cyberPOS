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

// Load rate from settings (default 60 if missing)
$settings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT rate_per_hour FROM settings WHERE id=1 LIMIT 1"));
$RATE_PER_HOUR = $settings ? (float)$settings['rate_per_hour'] : 60.00;
$RATE_PER_MIN  = $RATE_PER_HOUR / 60.0;

/* ---------------------------
   ADD COMPUTER (unique name)
----------------------------*/
if (isset($_POST['add_pc'])) {

    // LICENSE PC LIMIT CHECK
    $lic = mysqli_fetch_assoc(mysqli_query($conn, "SELECT allowed_pcs FROM system_license WHERE id=1 LIMIT 1"));
    if (!$lic) {
        header("Location: license.php?e=NO_LICENSE");
        exit;
    }

    $allowed = (int)$lic["allowed_pcs"];
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM computers"));
    $pcCount = (int)($row["c"] ?? 0);

    if ($pcCount >= $allowed) {
        header("Location: control.php?err=PC_LIMIT_REACHED&allowed=" . urlencode((string)$allowed));
        exit;
    }

    $pc = trim($_POST['computer_name'] ?? '');
    if ($pc !== '') {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO computers (computer_name, status, last_ping)
            VALUES (?, 'available', NOW())
            ON DUPLICATE KEY UPDATE last_ping = NOW()
        ");
        mysqli_stmt_bind_param($stmt, "s", $pc);
        mysqli_stmt_execute($stmt);

        log_action($conn, "ADD_COMPUTER", "Computer: $pc");
    }

    header("Location: control.php");
    exit;
}

/* ---------------------------
   START SESSION (unlock)
----------------------------*/
if (isset($_POST['start_session'])) {

    // BLOCK START SESSION IF LICENSE EXPIRED + GRACE OVER
    $lic = mysqli_fetch_assoc(mysqli_query($conn, "SELECT expiry_date, grace_days FROM system_license WHERE id=1 LIMIT 1"));

    if (!$lic) {
        header("Location: license.php?e=NO_LICENSE");
        exit;
    }

    $expiry = new DateTime($lic["expiry_date"]);
    $graceDays = (int)($lic["grace_days"] ?? 3);
    $expiryPlusGrace = (clone $expiry)->modify("+{$graceDays} days");
    $today = new DateTime(date("Y-m-d"));

    if ($today > $expiryPlusGrace) {
        header("Location: license.php?e=EXPIRED");
        exit;
    }

    $pc = trim($_POST['pc'] ?? '');
    if ($pc !== '') {

        // End any active session for this PC first
        $stmt = mysqli_prepare($conn, "
            UPDATE sessions
            SET status='ended', end_time=NOW()
            WHERE computer_name=? AND status='active'
        ");
        mysqli_stmt_bind_param($stmt, "s", $pc);
        mysqli_stmt_execute($stmt);

        // Start new session
        $stmt = mysqli_prepare($conn, "
            INSERT INTO sessions (computer_name, start_time, status, rate_per_hour)
            VALUES (?, NOW(), 'active', ?)
        ");
        mysqli_stmt_bind_param($stmt, "sd", $pc, $RATE_PER_HOUR);
        mysqli_stmt_execute($stmt);

        log_action($conn, "START_SESSION", "PC: $pc");

        // Mark computer occupied
        $stmt = mysqli_prepare($conn, "
            UPDATE computers
            SET status='occupied', last_ping=NOW()
            WHERE computer_name=?
        ");
        mysqli_stmt_bind_param($stmt, "s", $pc);
        mysqli_stmt_execute($stmt);
    }

    header("Location: control.php");
    exit;
}

/* ---------------------------
   END SESSION (lock) + BILLING + RECEIPT REDIRECT
   amount_due = minutes * (rate_per_hour/60)
----------------------------*/
if (isset($_POST['end_session'])) {
    $pc = trim($_POST['pc'] ?? '');
    if ($pc !== '') {

        // End latest active session and compute amount_due using settings rate
        $stmt = mysqli_prepare($conn, "
            UPDATE sessions
            SET
                end_time = NOW(),
                status = 'ended',
                rate_per_hour = ?,
                amount_due = ROUND(
                    GREATEST(1, CEIL(TIMESTAMPDIFF(SECOND, start_time, NOW()) / 60)) * (?),
                2)
            WHERE computer_name = ? AND status = 'active'
            ORDER BY id DESC
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, "dds", $RATE_PER_HOUR, $RATE_PER_MIN, $pc);
        mysqli_stmt_execute($stmt);

        log_action($conn, "END_SESSION", "PC: $pc");

        // Get latest session id
        $stmt2 = mysqli_prepare($conn, "
            SELECT id
            FROM sessions
            WHERE computer_name = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt2, "s", $pc);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_stmt_get_result($stmt2);
        $row2 = mysqli_fetch_assoc($res2);
        $receiptId = (int)($row2['id'] ?? 0);

        // Mark computer available
        $stmt3 = mysqli_prepare($conn, "
            UPDATE computers
            SET status='available', last_ping=NOW()
            WHERE computer_name=?
        ");
        mysqli_stmt_bind_param($stmt3, "s", $pc);
        mysqli_stmt_execute($stmt3);

        // Redirect to receipt
        if ($receiptId > 0) {
            header("Location: receipt.php?id=" . $receiptId);
            exit;
        }
    }

    header("Location: control.php");
    exit;
}

/* ---------------------------
   Helper: check active session
----------------------------*/
function is_active($conn, $pc) {
    $stmt = mysqli_prepare($conn, "
        SELECT id FROM sessions
        WHERE computer_name=? AND status='active'
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "s", $pc);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    return mysqli_stmt_num_rows($stmt) > 0;
}

/* ---------------------------
   SEARCH + LIST (unique computers)
----------------------------*/
$q = trim($_GET['q'] ?? '');

if ($q !== '') {
    $stmt = mysqli_prepare($conn, "
        SELECT computer_name,
               MAX(status) AS status,
               MAX(last_ping) AS last_ping
        FROM computers
        WHERE computer_name LIKE CONCAT('%', ?, '%')
        GROUP BY computer_name
        ORDER BY computer_name
    ");
    mysqli_stmt_bind_param($stmt, "s", $q);
    mysqli_stmt_execute($stmt);
    $pcs = mysqli_stmt_get_result($stmt);
} else {
    $pcs = mysqli_query($conn, "
        SELECT computer_name,
               MAX(status) AS status,
               MAX(last_ping) AS last_ping
        FROM computers
        GROUP BY computer_name
        ORDER BY computer_name
    ");
}

/* ---------------------------
   STATS (unique computers only)
----------------------------*/
$total = 0;
$activeCount = 0;
$lockedCount = 0;

$all = mysqli_query($conn, "SELECT computer_name FROM computers GROUP BY computer_name");
if ($all) {
    while ($r = mysqli_fetch_assoc($all)) {
        $total++;
        if (is_active($conn, $r['computer_name'])) $activeCount++;
        else $lockedCount++;
    }
}

/* ---------------------------
   Staff link (auto detect host)
----------------------------*/
$serverHost = $_SERVER['HTTP_HOST'];
$staffLink = "http://" . $serverHost . "/cyberpos/staff/control.php";
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CyberPOS - Staff Control</title>
  <style>
    :root{
      --bg:#f4f7fb; --card:#fff; --text:#0f172a; --muted:#64748b;
      --blue:#0b5ed7; --blue2:#0a4fb7; --green:#16a34a; --red:#dc2626;
      --line:#e5e7eb; --shadow:0 10px 25px rgba(2,6,23,.08); --radius:16px;
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
    .topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:18px;flex-wrap:wrap}
    .title h1{margin:0;font-size:28px}
    .title p{margin:4px 0 0;color:var(--muted)}
    .actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .search{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:10px 12px;display:flex;gap:8px;align-items:center;min-width:260px}
    .search input{border:none;outline:none;width:100%;font-size:14px;background:transparent}
    .btn{border:none;cursor:pointer;border-radius:12px;padding:10px 14px;font-weight:700}
    .btn-blue{background:var(--blue);color:#fff}
    .btn-blue:hover{filter:brightness(.95)}
    .grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:14px 0 18px}
    .card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:16px}
    .stat{display:flex;align-items:center;justify-content:space-between}
    .stat .label{color:var(--muted);font-weight:700}
    .stat .value{font-size:28px;font-weight:900}
    .pill{font-weight:800;font-size:12px;padding:6px 10px;border-radius:999px;display:inline-block}
    .pill-green{background:#dcfce7;color:#166534}
    .pill-red{background:#fee2e2;color:#991b1b}
    .pill-gray{background:#e2e8f0;color:#334155}
    .tableWrap{overflow:auto}
    table{width:100%;border-collapse:separate;border-spacing:0 10px}
    th{color:var(--muted);text-align:left;font-size:12px;letter-spacing:.08em;text-transform:uppercase;padding:0 10px}
    td{background:var(--card);border:1px solid var(--line);padding:12px 10px}
    tr td:first-child{border-top-left-radius:14px;border-bottom-left-radius:14px}
    tr td:last-child{border-top-right-radius:14px;border-bottom-right-radius:14px}
    .rowTitle{font-weight:900}
    .muted{color:var(--muted);font-size:13px}
    .rowActions{display:flex;gap:8px;flex-wrap:wrap}
    .btn-start{background:var(--green);color:#fff}
    .btn-end{background:var(--red);color:#fff}
    .btn-start:hover,.btn-end:hover{filter:brightness(.95)}
    .addRow{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
    .addRow input{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:10px 12px;min-width:320px;outline:none}
    .alert{
      padding:10px 12px;
      margin-bottom:14px;
      border-radius:10px;
      font-weight:700;
      border:1px solid;
    }
    .alert-error{
      background:#fee2e2;
      color:#991b1b;
      border-color:#fecaca;
    }
    @media (max-width: 980px){
      .sidebar{display:none}
      .grid{grid-template-columns:1fr}
      .search{min-width:unset;flex:1}
    }
  </style>

  <script>
    function confirmEnd(pc){ return confirm("End session for " + pc + "? This will LOCK that computer."); }
    function confirmStart(pc){ return confirm("Start session for " + pc + "? This will UNLOCK that computer."); }
    function copyStaffLink(){
      const el = document.getElementById('staffLinkBox');
      el.select();
      document.execCommand('copy');
      alert("Staff link copied!");
    }
  </script>
</head>
<body>
<div class="layout">

  <?php include("../includes/sidebar.php"); ?>

  <main class="main">
    <div class="topbar">
      <div class="title">
        <h1>PC Control</h1>
        <p>Start or end sessions to unlock/lock client PCs.</p>
      </div>

      <div class="actions">
        <form class="search" method="get">
          🔎 <input name="q" placeholder="Search computer name…" value="<?php echo htmlspecialchars($q); ?>">
        </form>

        <div class="search" style="min-width:360px">
          🔗 <input id="staffLinkBox" value="<?php echo htmlspecialchars($staffLink); ?>" readonly onclick="this.select()">
        </div>
        <button class="btn btn-blue" type="button" onclick="copyStaffLink()">Copy</button>

        <a class="btn btn-blue" href="control.php">Refresh</a>
      </div>
    </div>

    <?php if (isset($_GET['err']) && $_GET['err'] === 'PC_LIMIT_REACHED'): ?>
      <div class="alert alert-error">
        ❌ PC limit reached. Your license allows only <b><?php echo (int)($_GET['allowed'] ?? 0); ?></b> PCs.
        Please upgrade or renew.
      </div>
    <?php endif; ?>

    <div class="grid">
      <div class="card">
        <div class="stat">
          <div><div class="label">Total Computers</div><div class="value"><?php echo (int)$total; ?></div></div>
          <span class="pill pill-gray">All</span>
        </div>
      </div>

      <div class="card">
        <div class="stat">
          <div><div class="label">Active (Unlocked)</div><div class="value"><?php echo (int)$activeCount; ?></div></div>
          <span class="pill pill-green">Active</span>
        </div>
      </div>

      <div class="card">
        <div class="stat">
          <div><div class="label">Locked</div><div class="value"><?php echo (int)$lockedCount; ?></div></div>
          <span class="pill pill-red">Locked</span>
        </div>
      </div>
    </div>

    <div class="card">
      <div style="font-weight:900;font-size:16px;margin-bottom:10px;">Add Computer</div>
      <form method="post" class="addRow">
        <input name="computer_name" placeholder="Example: PC-01 or DESKTOP-XXXX">
        <button class="btn btn-blue" name="add_pc">Add</button>
      </form>
      <div style="color:var(--muted);font-size:13px;margin-top:10px;">
        Tip: On each client PC run <code>hostname</code> and add that name.
      </div>
    </div>

    <div class="card" style="margin-top:14px;">
      <div style="font-weight:900;font-size:16px;margin-bottom:10px;">Computers</div>
      <div class="tableWrap">
        <table>
          <thead>
            <tr>
              <th>Computer</th>
              <th>Status (computers table)</th>
              <th>Session</th>
              <th>Last ping</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php
          if ($pcs && mysqli_num_rows($pcs) > 0) {
              while ($row = mysqli_fetch_assoc($pcs)) {
                  $pc = $row['computer_name'];
                  $active = is_active($conn, $pc);
                  $ping = $row['last_ping'] ? date("Y-m-d H:i", strtotime($row['last_ping'])) : "-";
          ?>
            <tr>
              <td><div class="rowTitle"><?php echo htmlspecialchars($pc); ?></div></td>
              <td><span class="pill pill-gray"><?php echo htmlspecialchars($row['status']); ?></span></td>
              <td>
                <?php if($active): ?>
                  <span class="pill pill-green">ACTIVE</span>
                <?php else: ?>
                  <span class="pill pill-red">LOCKED</span>
                <?php endif; ?>
              </td>
              <td class="muted"><?php echo htmlspecialchars($ping); ?></td>
              <td>
                <div class="rowActions">
                  <form method="post" onsubmit="return confirmStart('<?php echo htmlspecialchars($pc); ?>');">
                    <input type="hidden" name="pc" value="<?php echo htmlspecialchars($pc); ?>">
                    <button class="btn btn-start" name="start_session">Start</button>
                  </form>

                  <form method="post" onsubmit="return confirmEnd('<?php echo htmlspecialchars($pc); ?>');">
                    <input type="hidden" name="pc" value="<?php echo htmlspecialchars($pc); ?>">
                    <button class="btn btn-end" name="end_session">End</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php
              }
          } else {
          ?>
            <tr>
              <td colspan="5" class="muted">No computers found.</td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>
</body>
</html>