<?php
session_name("admin_session");
session_start();
require_once "../../db/conn.php";

// ----------------------------------------------------
// Security Headers
// ----------------------------------------------------
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ----------------------------------------------------
// Authentication check
// ----------------------------------------------------

// User must be fully logged in
if (empty($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please login to access this page.";
    header("Location: adminlogin.php");
    exit;
}

// If user has pending OTP verification, redirect to OTP page
if (!empty($_SESSION['pending_user_id'])) {
    header("Location: otp_verify.php");
    exit;
}

// ----------------------------------------------------
// At this point, user is fully authenticated
// ----------------------------------------------------
$username = $_SESSION['username'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? 'guest';
// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$user_id = $_SESSION['user_id'];
$response = ["status" => "error", "message" => "Unknown error"];

// Make sure there is a POST action
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

// Check what field is being updated
$action = $_POST['action'] ?? '';

/* ───────────────────────────────────────────────
   ✅ UPDATE USERNAME
   ─────────────────────────────────────────────── */
if ($action === "update_username") {
    $new_username = trim($_POST["new_username"] ?? "");

    if (empty($new_username)) {
        echo json_encode(["status" => "error", "message" => "Username cannot be empty"]);
        exit;
    }

    // Check if username is taken
    $check = $conn->prepare("SELECT id FROM admin WHERE username = ? AND id != ?");
    $check->bind_param("si", $new_username, $user_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Username already taken"]);
        exit;
    }

    // Update username
    $stmt = $conn->prepare("UPDATE admin SET username = ? WHERE id = ?");
    $stmt->bind_param("si", $new_username, $user_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Username updated successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update username"]);
    }
    exit;
}

/* ───────────────────────────────────────────────
   ✅ UPDATE NAME
   ─────────────────────────────────────────────── */
if ($action === "update_name") {
    $new_name = trim($_POST["new_name"] ?? "");

    if (empty($new_name)) {
        echo json_encode(["status" => "error", "message" => "Name cannot be empty"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE admin SET full_name = ? WHERE id = ?");
    $stmt->bind_param("si", $new_name, $user_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Name updated successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update name"]);
    }
    exit;
}

/* ───────────────────────────────────────────────
   ✅ UPDATE PASSWORD
   ─────────────────────────────────────────────── */
if ($action === "update_password") {

    $current_password = $_POST["current_password"] ?? "";
    $new_password     = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        echo json_encode(["status" => "error", "message" => "All fields are required"]);
        exit;
    }

    if ($new_password !== $confirm_password) {
        echo json_encode(["status" => "error", "message" => "New passwords do not match"]);
        exit;
    }

    // Get current password
    $stmt = $conn->prepare("SELECT password FROM admin WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        echo json_encode(["status" => "error", "message" => "Current password is incorrect"]);
        exit;
    }

    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

    // Update password
    $update = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
    $update->bind_param("si", $hashed_password, $user_id);

    if ($update->execute()) {
        echo json_encode(["status" => "success", "message" => "Password updated successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update password"]);
    }

    exit;
}

/* ───────────────────────────────────────────────
   ❌ FALLBACK
   ─────────────────────────────────────────────── */
echo json_encode(["status" => "error", "message" => "Unknown update action"]);
exit;

?>
