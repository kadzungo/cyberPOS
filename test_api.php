<?php
$url = "http://localhost/cyberpos_license_api/api/activate.php";

$payload = json_encode([
  "license_key" => "CYBPOS-B-TEST-0001-0001",
  "machine_hash" => "TESTHASH",
  "machine_name" => "TESTPC"
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
  CURLOPT_POSTFIELDS => $payload
]);

echo curl_exec($ch);
echo "\n\nCURL ERROR: " . curl_error($ch);
curl_close($ch);