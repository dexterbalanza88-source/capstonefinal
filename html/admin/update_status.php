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

// ✅ Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

// ✅ Decode JSON input safely
$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['ids'], $data['status']) || !is_array($data['ids'])) {
    echo json_encode(["success" => false, "error" => "Invalid or missing input"]);
    exit;
}

$status = trim($data['status']);
$ids = array_filter(array_map('intval', $data['ids'])); // prevent empty/invalid IDs

if (empty($ids)) {
    echo json_encode(["success" => false, "error" => "No valid IDs provided"]);
    exit;
}

// ✅ Use prepared statement for safety (avoids injection)
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));
$stmt = $conn->prepare("UPDATE registration_form SET status = ? WHERE id IN ($placeholders)");

if (!$stmt) {
    echo json_encode(["success" => false, "error" => $conn->error]);
    exit;
}

// Bind parameters dynamically
$stmt->bind_param('s' . $types, $status, ...$ids);
$success = $stmt->execute();
$stmt->close();

if (!$success) {
    echo json_encode(["success" => false, "error" => $conn->error]);
    exit;
}

// ✅ Fetch updated names for logging
$idList = implode(',', $ids);
$nameQuery = $conn->query("
    SELECT CONCAT(f_name, ' ', m_name, ' ', s_name) AS full_name
    FROM registration_form
    WHERE id IN ($idList)
");

$names = [];
$action = "Updated status to $status";
$time = date("Y-m-d H:i:s");

// ✅ Insert activity logs in one prepared statement
$logStmt = $conn->prepare("INSERT INTO activity_log (name, action, created_at) VALUES (?, ?, ?)");
if ($logStmt) {
    while ($row = $nameQuery->fetch_assoc()) {
        $fullName = trim($row['full_name']);
        if ($fullName) {
            $names[] = $fullName;
            $logStmt->bind_param("sss", $fullName, $action, $time);
            $logStmt->execute();
        }
    }
    $logStmt->close();
}

// ✅ Refresh table HTML (same behavior)
ob_start();
include "datalist.php"; // make sure this outputs the updated table body only
$updatedTable = ob_get_clean();

// ✅ Return JSON response (frontend ready)
echo json_encode([
    "success" => true,
    "status" => $status,
    "names" => $names,
    "count" => count($names),
    "table_html" => $updatedTable,
    "time" => date("M d, Y h:i A")
]);
?>