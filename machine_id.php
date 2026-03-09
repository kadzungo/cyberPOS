<?php
function getWindowsMachineGuid(): ?string {
    // Reads MachineGuid from Windows registry (requires Windows + reg command)
    $cmd = 'reg query "HKLM\SOFTWARE\Microsoft\Cryptography" /v MachineGuid';
    $output = shell_exec($cmd);

    if (!$output) return null;

    // Example line: MachineGuid    REG_SZ    xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
    if (preg_match('/MachineGuid\s+REG_SZ\s+([A-Fa-f0-9\-]+)/', $output, $m)) {
        return trim($m[1]);
    }
    return null;
}

function getMachineHash(string $salt = "CYBERPOS_SALT_CHANGE_ME"): string {
    $guid = getWindowsMachineGuid();
    if (!$guid) {
        // fallback: computername + diskfree (not perfect, but better than nothing)
        $fallback = (getenv("COMPUTERNAME") ?: "UNKNOWN") . "|" . (string)disk_total_space("C:");
        $guid = $fallback;
    }
    return hash("sha256", strtoupper(trim($guid)) . "|" . $salt);
}