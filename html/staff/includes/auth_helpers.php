<?php
// includes/auth_helpers.php
declare(strict_types=1);

function generateRandomToken(int $bytes = 48): string {
    return bin2hex(random_bytes($bytes)); // 96 hex chars for 48 bytes
}

function hashToken(string $token): string {
    // use SHA-256 hex
    return hash('sha256', $token);
}

function setActiveTokenCookie(string $login_history_id, string $token, int $ttl_seconds = 3600) {
    // cookie value encodes login_history_id:token (base64)
    $val = base64_encode($login_history_id . ':' . $token);
    // Not HttpOnly because we use JS to maintain per-tab active token
    // Secure and SameSite=strict recommended for production (requires HTTPS)
    setcookie(
        'staff_active', 
        $val, 
        [
            'expires' => time() + $ttl_seconds,
            'path' => '/',
            'domain' => '',         // set if required
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => false,    // must be false to let JS set per-tab cookie
            'samesite' => 'Strict'
        ]
    );
}

function clearActiveTokenCookie() {
    setcookie('staff_active', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => false,
        'samesite' => 'Strict'
    ]);
}
?>
