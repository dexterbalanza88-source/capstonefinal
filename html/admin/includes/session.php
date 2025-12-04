<?php
// admin/includes/session.php

function secure_session_start()
{
    // -------------------------------
    // Session configuration
    // -------------------------------
    session_name('admin_session');

    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/html/admin/', // include trailing slash for admin folder
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', $secure ? 1 : 0);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // -------------------------------
    // Session timeout (30 minutes)
    // -------------------------------
    $timeout = 1800; // seconds

    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout) {
        session_unset();
        session_destroy();
        header("Location: /html/admin/adminlogin.php?error=session_expired");
        exit;
    }

    $_SESSION['LAST_ACTIVITY'] = time();

    // -------------------------------
    // IP & User-Agent security check
    // -------------------------------
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $current_ip = trim($ips[0]);
    }

    $current_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Check user_id (fully logged in) or pending_user_id (OTP pending)
    $session_id_key = null;
    if (!empty($_SESSION['user_id'])) {
        $session_id_key = 'user_id';
    } elseif (!empty($_SESSION['pending_user_id'])) {
        $session_id_key = 'pending_user_id';
    }

    if ($session_id_key) {
        if (
            ($_SESSION['ip_address'] ?? '') !== $current_ip ||
            ($_SESSION['user_agent'] ?? '') !== $current_ua
        ) {
            session_unset();
            session_destroy();
            header("Location: /html/admin/adminlogin.php?error=session_invalid");
            exit;
        }
    }

    // -------------------------------
    // Set/Update session security info
    // -------------------------------
    $_SESSION['ip_address'] = $current_ip;
    $_SESSION['user_agent'] = $current_ua;

    // Debug log
    error_log("Secure session active: " . session_id() . 
              ", User: " . ($session_id_key ? $_SESSION[$session_id_key] : 'none') . 
              ", IP: $current_ip");
}
?>
