<?php
// includes/license_guard.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn)) {
    die("License guard: DB connection not found.");
}

// === SETTINGS ===
$GRACE_DAYS = 3;            // grace after expiry
$VERIFY_EVERY_DAYS = 7;     // call server at most once every 7 days
$OFFLINE_ALLOW_DAYS = 14;   // if no internet, allow up to 14 days since last verified

$LICENSE_API = "http://localhost/cyberpos_license_api/api";
$ALLOW_PAGES = [
    "/cyberpos/staff/license.php", // always allow license page
];

// Current path
$current = $_SERVER["REQUEST_URI"] ?? "";

// Allow pages
foreach ($ALLOW_PAGES as $p) {
    if (stripos($current, $p) !== false) {
        return;
    }
}

function post_json_guard(string $url, array $data): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 8
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        return ["ok" => false, "error" => "CONNECTION_FAILED", "details" => $err, "http" => $code];
    }
    $json = json_decode($resp, true);
    if (!is_array($json)) {
        return ["ok" => false, "error" => "INVALID_SERVER_RESPONSE", "raw" => $resp, "http" => $code];
    }
    $json["http"] = $code;
    return $json;
}

// Load local license
$lic = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM system_license WHERE id=1 LIMIT 1"));

if (!$lic) {
    header("Location: /cyberpos/staff/license.php?e=NO_LICENSE");
    exit;
}

// Tamper check (checksum)
$expected = hash("sha256", $lic["license_key"] . "|" . $lic["machine_hash"] . "|" . $lic["expiry_date"]);
if (!hash_equals($expected, $lic["checksum"])) {
    header("Location: /cyberpos/staff/license.php?e=TAMPERED");
    exit;
}

// Date calculations
$today = new DateTime(date("Y-m-d"));
$expiry = new DateTime($lic["expiry_date"]);
$expiry_plus_grace = (clone $expiry)->modify("+{$GRACE_DAYS} days");

$last_verified = new DateTime($lic["last_verified"]);
$days_since_verify = (int)$last_verified->diff($today)->format("%a");

// 1) If license is far past expiry + grace -> lock immediately (even offline)
if ($today > $expiry_plus_grace) {
    header("Location: /cyberpos/staff/license.php?e=EXPIRED");
    exit;
}

// 2) Decide if we need online verify
$should_verify = ($days_since_verify >= $VERIFY_EVERY_DAYS);

// 3) If should verify, call server
if ($should_verify) {
    $res = post_json_guard($LICENSE_API . "/verify.php", [
        "license_key" => $lic["license_key"],
        "machine_hash" => $lic["machine_hash"]
    ]);

    if (($res["ok"] ?? false) === true) {
        // Update expiry + last_verified from server response
        $server_expiry = $res["data"]["expiry_date"] ?? $lic["expiry_date"];
        $new_checksum = hash("sha256", $lic["license_key"] . "|" . $lic["machine_hash"] . "|" . $server_expiry);

        $stmt = $conn->prepare("UPDATE system_license SET expiry_date=?, last_verified=CURDATE(), checksum=? WHERE id=1");
        $stmt->bind_param("ss", $server_expiry, $new_checksum);
        $stmt->execute();
    } else {
        // If server says expired/suspended -> lock immediately
        $err = $res["error"] ?? "VERIFY_FAILED";
        if (in_array($err, ["LICENSE_EXPIRED", "LICENSE_SUSPENDED", "LICENSE_NOT_ACTIVE", "NOT_ACTIVATED_ON_THIS_MACHINE"], true)) {
            header("Location: /cyberpos/staff/license.php?e=" . urlencode($err));
            exit;
        }

        // If internet issue, allow offline only up to OFFLINE_ALLOW_DAYS
        if ($days_since_verify > $OFFLINE_ALLOW_DAYS) {
            header("Location: /cyberpos/staff/license.php?e=OFFLINE_TOO_LONG");
            exit;
        }
        // else: allow to continue (temporary offline)
    }
}

// If within grace window, you may show warning optionally (not blocking)