<?php
// ==============================
// Secure Staff Session Management
// ==============================

function secure_session_start()
{
    // Start session only if not already started
    if (session_status() === PHP_SESSION_NONE) {

        // Set secure cookie parameters
        session_set_cookie_params([
            'lifetime' => 0,               // Ends with browser close
            'path' => '/',
            'domain' => '',                // Default
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'));

        session_start();
    }

    // --------------------------------------
    // SESSION TIMEOUT (30 minutes)
    // --------------------------------------
    $timeout = 1800; // 30 minutes

    if (isset($_SESSION['LAST_ACTIVITY']) 
        && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout) 
        && isset($_SESSION['staff_id'])) {
        // Only destroy if logged in
        session_unset();
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
        header("Location: stafflogin.php?error=session_expired");
        exit;
    }

    $_SESSION['LAST_ACTIVITY'] = time();

    // --------------------------------------
    // REGENERATE SESSION ID every 30 minutes
    // --------------------------------------
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    } else if (time() - $_SESSION['CREATED'] > $timeout) {
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time();
    }
}

// Check if staff is logged in
function is_staff_logged_in()
{
    return isset($_SESSION['staff_id']) && $_SESSION['staff_role'] === 'staff';
}

// Protect staff pages
function staff_protect()
{
    secure_session_start();

    if (!is_staff_logged_in()) {
        header("Location: stafflogin.php");
        exit;
    }
}
?>
