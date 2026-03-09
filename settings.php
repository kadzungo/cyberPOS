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

require_admin();

// load current settings first
$res = mysqli_query($conn, "SELECT cyber_name, currency, rate_per_hour FROM settings WHERE id=1 LIMIT 1");
$s = mysqli_fetch_assoc($res);
if (!$s) {
    $s = ['cyber_name' => 'CyberPOS', 'currency' => 'KES', 'rate_per_hour' => 60.00];
}

// save
if (isset($_POST['save_settings'])) {
    $oldCyber = $s['cyber_name'];
    $oldCurrency = $s['currency'];
    $oldRate = (float)$s['rate_per_hour'];

    $cyber = trim($_POST['cyber_name'] ?? 'CyberPOS');
    $currency = trim($_POST['currency'] ?? 'KES');
    $rate = (float)($_POST['rate_per_hour'] ?? 60);

    if ($cyber === '') $cyber = 'CyberPOS';
    if ($currency === '') $currency = 'KES';
    if ($rate <= 0) $rate = 60;

    $stmt = mysqli_prepare($conn, "
        UPDATE settings
        SET cyber_name=?, currency=?, rate_per_hour=?
        WHERE id=1
    ");
    mysqli_stmt_bind_param($stmt, "ssd", $cyber, $currency, $rate);
    mysqli_stmt_execute($stmt);

    // ✅ AUDIT LOG
    $details = "Settings changed | Cyber Name: '{$oldCyber}' -> '{$cyber}', Currency: '{$oldCurrency}' -> '{$currency}', Rate/hr: {$oldRate} -> {$rate}";
    log_action($conn, "UPDATE_SETTINGS", $details);

    header("Location: settings.php?saved=1");
    exit;
}

$saved = isset($_GET['saved']);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CyberPOS - Settings</title>
  <style>
    :root{
      --bg:#f4f7fb;--card:#fff;--text:#0f172a;--muted:#64748b;
      --blue:#0b5ed7;--blue2:#0a4fb7;--line:#e5e7eb;
      --shadow:0 10px 25px rgba(2,6,23,.08);--radius:16px;
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
    .card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:16px;max-width:720px}
    .row{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0}
    label{font-weight:800}
    input{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:12px;outline:none}
    .btn{border:none;cursor:pointer;border-radius:12px;padding:10px 14px;font-weight:800}
    .btn-blue{background:var(--blue);color:#fff}
    .note{color:var(--muted);font-size:13px}
    .ok{background:#dcfce7;color:#166534;padding:10px 12px;border-radius:12px;margin-bottom:10px;font-weight:800}
    @media (max-width: 980px){.sidebar{display:none}}
  </style>
</head>
<body>
<div class="layout">
  <?php include("../includes/sidebar.php"); ?>

  <main class="main">
    <h1 style="margin:0 0 12px;">Settings</h1>

    <div class="card">
      <?php if($saved): ?>
        <div class="ok">Saved successfully ✅</div>
      <?php endif; ?>

      <form method="post">
        <div class="row">
          <div style="flex:1;min-width:240px">
            <label>Cyber Name</label>
            <input name="cyber_name" value="<?php echo htmlspecialchars($s['cyber_name']); ?>">
          </div>

          <div style="flex:1;min-width:140px">
            <label>Currency</label>
            <input name="currency" value="<?php echo htmlspecialchars($s['currency']); ?>">
          </div>

          <div style="flex:1;min-width:160px">
            <label>Rate per hour</label>
            <input type="number" step="0.01" name="rate_per_hour" value="<?php echo htmlspecialchars($s['rate_per_hour']); ?>">
          </div>
        </div>

        <button class="btn btn-blue" name="save_settings">Save</button>
        <a class="btn" style="background:#e2e8f0;color:#0f172a;display:inline-block" href="control.php">Back</a>

        <p class="note" style="margin-top:10px;">
          Billing uses: <b>rate_per_hour</b> per minute.
        </p>
      </form>
    </div>
  </main>
</div>
</body>
</html>