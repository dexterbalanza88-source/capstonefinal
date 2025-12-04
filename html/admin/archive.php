<?php
// archive.php - WITH DIRECT LOGGING BACKUP
session_name("admin_session");
session_start();

// Turn on all error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "../../db/conn.php";

// Define a backup logging function if the main one fails
function backupLogActivity($conn, $user_id, $username, $user_role, $action_type, $description, $table_name = null, $record_id = null)
{
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        $sql = "INSERT INTO activity_logs 
                (user_id, username, user_role, action_type, action_description, 
                 table_name, record_id, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            error_log("BACKUP LOG ERROR - Prepare failed: " . $conn->error);
            return false;
        }

        $table_name = $table_name ?: null;
        $record_id = $record_id ?: null;

        $stmt->bind_param(
            "isssssiss",
            $user_id,
            $username,
            $user_role,
            $action_type,
            $description,
            $table_name,
            $record_id,
            $ip_address,
            $user_agent
        );

        $result = $stmt->execute();
        $stmt->close();

        return $result;
    } catch (Exception $e) {
        error_log("BACKUP LOG EXCEPTION: " . $e->getMessage());
        return false;
    }
}

header("Content-Type: application/json");

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "error" => "Not authenticated"]);
    exit;
}

if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(["success" => false, "error" => "No ID provided"]);
    exit;
}

$id = intval($_POST['id']);
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Unknown';
$user_role = $_SESSION['user_role'] ?? 'guest';

// Try to include activity logger
$loggerPath = "../../db/activity_logger.php";
$loggerLoaded = false;

if (file_exists($loggerPath)) {
    require_once $loggerPath;
    $loggerLoaded = true;
    error_log("Activity logger loaded from: $loggerPath");
} else {
    error_log("WARNING: Activity logger not found at: $loggerPath");
}

// Check if farmer exists
$check = $conn->prepare("SELECT id, f_name, s_name, m_name FROM registration_form WHERE id = ?");
if (!$check) {
    echo json_encode(["success" => false, "error" => "Database prepare failed: " . $conn->error]);
    exit;
}

$check->bind_param("i", $id);
if (!$check->execute()) {
    echo json_encode(["success" => false, "error" => "Database query failed"]);
    exit;
}

$result = $check->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "error" => "Record not found or already archived"]);
    exit;
}

$farmer = $result->fetch_assoc();
$middleInitial = !empty($farmer['m_name']) ? strtoupper(substr($farmer['m_name'], 0, 1)) . "." : "";
$farmerName = $farmer['s_name'] . ", " . $farmer['f_name'] . " " . $middleInitial;
$check->close();

try {
    $conn->begin_transaction();

    // Log archive attempt
    error_log("Attempting to log archive for: $farmerName");

    $logResult = false;
    if ($loggerLoaded && function_exists('logArchive')) {
        $logResult = logArchive(
            $conn,
            $user_id,
            $username,
            $user_role,
            'registration_form',
            $id,
            "Archived farmer: {$farmerName}"
        );
        error_log("Main logArchive result: " . ($logResult ? "SUCCESS" : "FAILED"));
    }

    // If main logging failed, use backup
    if (!$logResult) {
        error_log("Using backup logging...");
        $logResult = backupLogActivity(
            $conn,
            $user_id,
            $username,
            $user_role,
            'ARCHIVE',
            "Archived farmer: {$farmerName}",
            'registration_form',
            $id
        );
        error_log("Backup log result: " . ($logResult ? "SUCCESS" : "FAILED"));
    }

    // Your existing archive logic here...
    // 1. Archive registration_form
    $mainData = $conn->prepare("SELECT * FROM registration_form WHERE id = ?");
    // ... rest of your archive code ...

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Archived successfully",
        "farmer_name" => $farmerName,
        "activity_logged" => $logResult ? "Yes" : "No"
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
}

$conn->close();
?>