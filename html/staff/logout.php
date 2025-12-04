<?php
require_once "../../db/conn.php";

$staff_id = $_GET['staff_id'] ?? '';
if (!$staff_id) {
    header("Location: stafflogin.php");
    exit;
}

$cookie_name = "staff_token_" . $staff_id;
$token = $_COOKIE[$cookie_name] ?? '';

if ($token) {
    // Delete only this login token
    $stmt = $conn->prepare("DELETE FROM staff_sessions WHERE session_token=?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();

    setcookie($cookie_name, "", time() - 3600, "/");
}

header("Location: stafflogin.php?message=logout_success");
exit;
?>
