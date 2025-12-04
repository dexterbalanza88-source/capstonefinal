<?php
// ---------------------------
// ADMIN LOGOUT SCRIPT
// ---------------------------

// Use a consistent session name
session_name("admin_session");
session_start();

require_once "../../db/conn.php"; // ensure $conn is valid

// ---------------------------
// 1️⃣ Update login history
// ---------------------------
function updateLogoutTime($conn, $user_id = null, $login_history_id = null)
{
    if (empty($conn))
        return false;

    try {
        // Priority 1: Use login_history_id from session
        if (!empty($login_history_id)) {
            $stmt = $conn->prepare("
                UPDATE login_history 
                SET logout_time = NOW(), status = 'LOGOUT' 
                WHERE id = ? AND logout_time IS NULL
            ");
            $stmt->bind_param("i", $login_history_id);
            if ($stmt->execute())
                return true;
        }

        // Priority 2: Use user_id to find the most recent login
        if (!empty($user_id)) {
            $stmt = $conn->prepare("
                UPDATE login_history 
                SET logout_time = NOW(), status = 'LOGOUT' 
                WHERE user_id = ? AND logout_time IS NULL 
                ORDER BY login_time DESC 
                LIMIT 1
            ");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute())
                return true;
        }

    } catch (Exception $e) {
        error_log("Logout history update failed: " . $e->getMessage());
        return false;
    }

    return false;
}

// ---------------------------
// 2️⃣ Update users table last_logout
// ---------------------------
function updateUsersLogout($conn, $user_id)
{
    if (empty($conn) || empty($user_id))
        return;

    try {
        $stmt = $conn->prepare("
            UPDATE users 
            SET last_logout = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Users table logout update failed: " . $e->getMessage());
    }
}

// ---------------------------
// 3️⃣ Record security event
// ---------------------------
function recordLogoutEvent($conn, $user_id, $user_email)
{
    if (empty($conn) || empty($user_id) || empty($user_email))
        return;

    try {
        $stmt = $conn->prepare("
            INSERT INTO security_events (user_id, email, event_type, event_description, ip_address, user_agent, severity) 
            VALUES (?, ?, 'LOGOUT', 'User logged out successfully', ?, ?, 'LOW')
        ");
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $stmt->bind_param("isss", $user_id, $user_email, $ip, $user_agent);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Logout security event failed: " . $e->getMessage());
    }
}

// ---------------------------
// Execute logout updates
// ---------------------------
$login_history_id = $_SESSION['login_history_id'] ?? $_SESSION['current_login_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? $_SESSION['pending_user_id'] ?? null;
$user_email = $_SESSION['user_email'] ?? null;

updateLogoutTime($conn, $user_id, $login_history_id);
updateUsersLogout($conn, $user_id);
recordLogoutEvent($conn, $user_id, $user_email);

// ---------------------------
// 4️⃣ Clear session completely
// ---------------------------
$_SESSION = [];

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

session_destroy();

// ---------------------------
// 5️⃣ Redirect to login page
// ---------------------------
header("Location: adminlogin.php");
exit;
?>