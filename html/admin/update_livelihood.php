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
header('Content-Type: application/json');

// Check session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

// Decode JSON
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['id'])) {
    echo json_encode(["success" => false, "message" => "Invalid data"]);
    exit;
}

$id = intval($data['id']);
$category_id = $data['category_id'] ?? '';
$sub_activity_id = $data['sub_activity_id'] ?? '';
$total_area = $data['total_area'] ?? '';
$farm_location = $data['farm_location'] ?? '';
$remarks = $data['remarks'] ?? '';

// Update livelihood table
$stmt = $conn->prepare("UPDATE livelihood SET category_id=?, sub_activity_id=?, total_area=?, farm_location=?, remarks=? WHERE id=?");
$stmt->bind_param("sssssi", $category_id, $sub_activity_id, $total_area, $farm_location, $remarks, $id);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "Database update failed"]);
}
$stmt->close();
$conn->close();
?>