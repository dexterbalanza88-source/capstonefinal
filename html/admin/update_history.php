<?php
// update_status.php
ob_clean();
header('Content-Type: application/json');
error_reporting(0);

include "../db/conn.php";
session_start();

// --- Ensure user is logged in ---
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "error" => "Unauthorized user"]);
    exit;
}

$user_id = $_SESSION['user_id'];

// --- Read input JSON safely ---
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!isset($data['ids']) || !isset($data['status'])) {
    echo json_encode(["success" => false, "error" => "Missing parameters"]);
    exit;
}

$ids = $data['ids'];
$status = mysqli_real_escape_string($conn, $data['status']);
$idList = implode(",", array_map('intval', $ids));

// --- Run update query ---
$sql = "UPDATE registration_form 
        SET status = '$status', last_updated_by = '$user_id', last_updated_at = NOW() 
        WHERE id IN ($idList)";

if (mysqli_query($conn, $sql)) {
    // --- Get username of the updater ---
    $uRes = mysqli_query($conn, "SELECT username FROM users WHERE id = '$user_id' LIMIT 1");
    $uRow = mysqli_fetch_assoc($uRes);
    $username = $uRow ? $uRow['username'] : 'Unknown';

    // --- Log activity (optional) ---
    $action = "Updated status to $status for IDs: $idList";
    mysqli_query($conn, "INSERT INTO activity_log (user_id, action, timestamp) VALUES ('$user_id', '$action', NOW())");

    // --- Respond as JSON ---
    echo json_encode([
        "success" => true,
        "status" => $status,
        "user" => $username,
        "action" => "Status updated",
        "time" => date("Y-m-d H:i:s")
    ]);
    exit;
}

// --- If query failed ---
echo json_encode([
    "success" => false,
    "error" => "Database update failed"
]);
exit;
?>