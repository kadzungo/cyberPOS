<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../index.php");
    exit;
}

require_once "../config/database.php";
require_once "../includes/machine_id.php";
require_once "../includes/role_guard.php";
require_admin();

// === CONFIG ===
$LICENSE_API = "http://localhost/cyberpos_license_api/api";
$SALT = "CYBERPOS_SALT_CHANGE_ME";

function post_json(string $url, array $data): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 10
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return ["ok" => false, "error" => "CONNECTION_FAILED", "details" => $err];
    }

    $json = json_decode($resp, true);
    if (!is_array($json)) {
        return ["ok" => false, "error" => "INVALID_SERVER_RESPONSE"];
    }
    return $json;
}

$machine_hash = getMachineHash($SALT);

// Load local license
$local = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT * FROM system_license WHERE id=1 LIMIT 1"
));

$msg = "";

/* ✅ STEP 5B: Friendly messages from license_guard redirects */
if (isset($_GET["e"])) {
    $e = $_GET["e"];

    if ($e === "NO_LICENSE") $msg = "❌ No license found. Please activate a license.";
    if ($e === "EXPIRED") $msg = "❌ License expired. Please renew or activate a new license.";
    if ($e === "TAMPERED") $msg = "❌ License data was modified. Please reactivate.";
    if ($e === "LICENSE_SUSPENDED") $msg = "❌ License suspended. Contact vendor.";
    if ($e === "LICENSE_EXPIRED") $msg = "❌ License expired on server. Please renew.";
    if ($e === "OFFLINE_TOO_LONG") $msg = "❌ No internet verification for too long. Connect to internet to continue.";
    if ($e === "NOT_ACTIVATED_ON_THIS_MACHINE") $msg = "❌ License not activated for this PC. Please activate.";
    if ($e === "LICENSE_NOT_ACTIVE") $msg = "❌ License not active. Please contact vendor.";
}

// Activate license
if (isset($_POST["activate"])) {
    $key = strtoupper(trim($_POST["license_key"] ?? ""));

    if ($key === "") {
        $msg = "❌ License key is required.";
    } else {
        $res = post_json($LICENSE_API . "/activate.php", [
            "license_key" => $key,
            "machine_hash" => $machine_hash,
            "machine_name" => getenv("COMPUTERNAME") ?: "UNKNOWN"
        ]);

        if (!($res["ok"] ?? false)) {
            $msg = "❌ Activation failed: " . ($res["error"] ?? "UNKNOWN_ERROR");
        } else {
            $data = $res["data"];
            $expiry = $data["expiry_date"];

            $checksum = hash("sha256", $key . "|" . $machine_hash . "|" . $expiry);

            $stmt = $conn->prepare("
                INSERT INTO system_license
                (id, license_key, machine_hash, expiry_date, last_verified, checksum)
                VALUES (1, ?, ?, ?, CURDATE(), ?)
                ON DUPLICATE KEY UPDATE
                  license_key=VALUES(license_key),
                  machine_hash=VALUES(machine_hash),
                  expiry_date=VALUES(expiry_date),
                  last_verified=VALUES(last_verified),
                  checksum=VALUES(checksum)
            ");
            $stmt->bind_param("ssss", $key, $machine_hash, $expiry, $checksum);
            $stmt->execute();

            $msg = "✅ License activated successfully. Expiry: " . $expiry;

            $local = mysqli_fetch_assoc(mysqli_query(
                $conn,
                "SELECT * FROM system_license WHERE id=1 LIMIT 1"
            ));
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CyberPOS | Licensing</title>
    <style>
        body { font-family: Arial; background:#f6f6f6; }
        .box {
            max-width: 600px;
            margin: 40px auto;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
        }
        input, button {
            padding: 10px;
            width: 100%;
            margin-top: 8px;
        }
        button {
            background: #007bff;
            color: #fff;
            border: none;
            cursor: pointer;
        }
        .msg { margin: 10px 0; font-weight: bold; }
    </style>
</head>
<body>

<div class="box">
    <h2>License Management</h2>

    <?php if ($msg): ?>
        <div class="msg"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <p><b>Machine Hash:</b><br><?= htmlspecialchars($machine_hash) ?></p>

    <hr>

    <h3>Current License</h3>
    <?php if ($local): ?>
        <p><b>Key:</b> <?= htmlspecialchars($local["license_key"]) ?></p>
        <p><b>Expiry:</b> <?= htmlspecialchars($local["expiry_date"]) ?></p>
        <p><b>Last Verified:</b> <?= htmlspecialchars($local["last_verified"]) ?></p>
    <?php else: ?>
        <p>No license activated yet.</p>
    <?php endif; ?>

    <hr>

    <h3>Activate License</h3>
    <form method="post">
        <input type="text" name="license_key"
               placeholder="CYBPOS-B-XXXX-XXXX-XXXX" required>
        <button type="submit" name="activate">Activate License</button>
    </form>
</div>

</body>
</html>