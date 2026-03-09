<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION["role"] ?? "staff";
$username = $_SESSION["username"] ?? "User";
?>

<aside class="sidebar">

  <div class="brand">
      CyberPOS <span class="dot"></span>
  </div>

  <div style="opacity:.9;font-weight:600;margin-bottom:6px;">
      <?php echo ucfirst(htmlspecialchars($role)); ?> Panel
  </div>

  <div style="font-size:13px;opacity:.8;margin-bottom:16px;">
      Logged in as:<br>
      <b><?php echo htmlspecialchars($username); ?></b>
  </div>

  <nav class="nav">

    <a class="<?php echo $currentPage === 'control.php' ? 'active' : ''; ?>" href="control.php">
      🖥️ PC Control
    </a>

    <a class="<?php echo $currentPage === 'billing.php' ? 'active' : ''; ?>" href="billing.php">
      💳 Billing
    </a>

    <a class="<?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>" href="reports.php">
      📊 Reports
    </a>

    <?php if ($role === "admin"): ?>

      <a class="<?php echo $currentPage === 'users.php' ? 'active' : ''; ?>" href="users.php">
        👤 Users
      </a>

      <a class="<?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>" href="settings.php">
        ⚙️ Settings
      </a>

      <a class="<?php echo $currentPage === 'license.php' ? 'active' : ''; ?>" href="license.php">
        🔐 License
      </a>

      <a class="<?php echo $currentPage === 'audit_logs.php' ? 'active' : ''; ?>" href="audit_logs.php">
        📜 Audit Logs
      </a>

    <?php endif; ?>

    <a href="logout.php">
      🚪 Logout
    </a>

  </nav>

</aside>