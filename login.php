<?php
session_start();
include("../config/database.php");
require_once "../includes/audit_log.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = mysqli_prepare($conn, "SELECT id, username, password_hash, role FROM users WHERE username=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($res);

    if ($user && password_verify($password, $user["password_hash"])) {
        $_SESSION["user_id"] = (int)$user["id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["role"] = $user["role"];

        log_action($conn, "LOGIN", "User logged in");

        header("Location: control.php");
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CyberPOS Staff Login</title>
  <style>
    body{margin:0;font-family:system-ui,Segoe UI,Arial;background:#f4f7fb;display:flex;min-height:100vh;align-items:center;justify-content:center}
    .card{width:min(420px,92vw);background:#fff;border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 10px 25px rgba(2,6,23,.08);padding:18px}
    h2{margin:0 0 6px}
    p{margin:0 0 14px;color:#64748b}
    input{width:100%;padding:12px 12px;border:1px solid #e5e7eb;border-radius:12px;outline:none;margin-bottom:10px}
    button{width:100%;padding:12px;border:none;border-radius:12px;background:#0b5ed7;color:#fff;font-weight:800;cursor:pointer}
    .err{background:#fee2e2;color:#991b1b;padding:10px;border-radius:12px;margin-bottom:10px;border:1px solid #fecaca}
    .hint{margin-top:10px;color:#64748b;font-size:13px}
  </style>
</head>
<body>
  <form class="card" method="post">
    <h2>Staff Login</h2>
    <p>Sign in to manage computers.</p>

    <?php if ($error): ?>
      <div class="err"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <input name="username" placeholder="Username" required>
    <input name="password" placeholder="Password" type="password" required>
    <button type="submit">Login</button>

    <div class="hint">Tip: If this is first setup, use admin / Admin@123 then change it.</div>
  </form>
</body>
</html>