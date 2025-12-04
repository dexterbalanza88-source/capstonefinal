<?php
require_once "includes/session.php";
secure_session_start(); // make sure session is started safely

// Only proceed if staff is logged in
if (isset($_SESSION['staff_id'])) {

    // Optional: Record logout time in database if needed
    // require_once "../../db/conn.php";
    // $stmt = $conn->prepare("UPDATE user_accounts SET last_logout = NOW() WHERE id = ?");
    // $stmt->bind_param("i", $_SESSION['staff_id']);
    // $stmt->execute();

    // Clear staff session
    $_SESSION = [];

    // Destroy session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Destroy session
    session_destroy();
}

// Redirect to staff login with success message
header("Location: stafflogin.php?message=logout_success");
exit;
