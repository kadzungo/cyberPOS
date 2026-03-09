<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

include("../config/database.php");
require_once "../includes/license_guard.php";
require_once "../includes/role_guard.php";
require_once "../includes/audit_log.php";

require_admin();

$msg = "";

// Add user
if (isset($_POST["add_user"])) {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $role = trim($_POST["role"] ?? "staff");

    if ($role !== "admin" && $role !== "staff") {
        $role = "staff";
    }

    if ($username !== "" && $password !== "") {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $username, $hash, $role);

        if (@mysqli_stmt_execute($stmt)) {
            $msg = "User added.";
            log_action($conn, "ADD_USER", "Added user '{$username}' with role '{$role}'");
        } else {
            $msg = "Username already exists.";
        }
    } else {
        $msg = "Username and password required.";
    }
}

// Reset password
if (isset($_POST["reset_pass"])) {
    $id = (int)($_POST["id"] ?? 0);
    $newpass = $_POST["newpass"] ?? "";

    if ($id > 0 && $newpass !== "") {
        $stmtUser = mysqli_prepare($conn, "SELECT username FROM users WHERE id=? LIMIT 1");
        mysqli_stmt_bind_param($stmtUser, "i", $id);
        mysqli_stmt_execute($stmtUser);
        $resUser = mysqli_stmt_get_result($stmtUser);
        $userRow = mysqli_fetch_assoc($resUser);
        $targetUsername = $userRow["username"] ?? "unknown";

        $hash = password_hash($newpass, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "UPDATE users SET password_hash=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "si", $hash, $id);
        mysqli_stmt_execute($stmt);

        $msg = "Password reset.";
        log_action($conn, "RESET_PASSWORD", "Reset password for user '{$targetUsername}' (ID {$id})");
    }
}

// Delete user
if (isset($_POST["delete_user"])) {
    $id = (int)($_POST["id"] ?? 0);

    if ($id > 0 && $id != (int)$_SESSION["user_id"]) {
        $stmtUser = mysqli_prepare($conn, "SELECT username FROM users WHERE id=? LIMIT 1");
        mysqli_stmt_bind_param($stmtUser, "i", $id);
        mysqli_stmt_execute($stmtUser);
        $resUser = mysqli_stmt_get_result($stmtUser);
        $userRow = mysqli_fetch_assoc($resUser);
        $targetUsername = $userRow["username"] ?? "unknown";

        $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        $msg = "User deleted.";
        log_action($conn, "DELETE_USER", "Deleted user '{$targetUsername}' (ID {$id})");
    } else {
        $msg = "You cannot delete your own account.";
    }
}

$users = mysqli_query($conn, "SELECT id, username, role, created_at FROM users ORDER BY id DESC");
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CyberPOS - Users</title>
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
    .card{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);padding:16px;margin-bottom:14px}
    .row{display:flex;gap:10px;flex-wrap:wrap}
    input,select{padding:10px 12px;border:1px solid #e5e7eb;border-radius:12px;outline:none}
    button{padding:10px 14px;border:none;border-radius:12px;background:#0b5ed7;color:#fff;font-weight:800;cursor:pointer}
    table{width:100%;border-collapse:separate;border-spacing:0 10px}
    th{color:#64748b;text-align:left;font-size:12px;letter-spacing:.08em;text-transform:uppercase;padding:0 10px}
    td{background:#fff;border:1px solid #e5e7eb;padding:12px 10px}
    tr td:first-child{border-top-left-radius:14px;border-bottom-left-radius:14px}
    tr td:last-child{border-top-right-radius:14px;border-bottom-right-radius:14px}
    .msg{background:#dcfce7;border:1px solid #bbf7d0;color:#166534;padding:10px;border-radius:12px;margin-bottom:10px}
    .danger{background:#dc2626}
    .danger:hover{filter:brightness(.95)}
    @media(max-width:900px){
      .sidebar{display:none}
    }
  </style>
</head>
<body>
<div class="layout">

  <?php include("../includes/sidebar.php"); ?>

  <main class="main">

    <div class="top">
      <div>
        <h1>Users</h1>
      </div>
    </div>

    <?php if($msg): ?>
      <div class="msg"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <div class="card">
      <h3 style="margin:0 0 10px">Add User</h3>
      <form method="post" class="row">
        <input name="username" placeholder="Username" required>
        <input name="password" placeholder="Password" type="password" required>
        <select name="role">
          <option value="staff">staff</option>
          <option value="admin">admin</option>
        </select>
        <button name="add_user">Add</button>
      </form>
    </div>

    <div class="card">
      <h3 style="margin:0 0 10px">All Users</h3>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Role</th>
            <th>Created</th>
            <th>Reset Password</th>
            <th>Delete</th>
          </tr>
        </thead>
        <tbody>
        <?php while($u = mysqli_fetch_assoc($users)): ?>
          <tr>
            <td><?php echo (int)$u["id"]; ?></td>
            <td><b><?php echo htmlspecialchars($u["username"]); ?></b></td>
            <td><?php echo htmlspecialchars($u["role"]); ?></td>
            <td><?php echo htmlspecialchars($u["created_at"]); ?></td>
            <td>
              <form method="post" class="row" style="gap:8px;flex-wrap:nowrap">
                <input type="hidden" name="id" value="<?php echo (int)$u["id"]; ?>">
                <input name="newpass" placeholder="New password" type="text" required>
                <button name="reset_pass">Reset</button>
              </form>
            </td>
            <td>
              <form method="post" onsubmit="return confirm('Delete this user?');">
                <input type="hidden" name="id" value="<?php echo (int)$u["id"]; ?>">
                <button class="danger" name="delete_user">Delete</button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>

  </main>
</div>
</body>
</html>