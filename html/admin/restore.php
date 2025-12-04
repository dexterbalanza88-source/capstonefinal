<?php
// restore.php - UPDATED WITH BETTER DEBUGGING
session_name("admin_session");
session_start();

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "../../db/conn.php";

// Debug: Log start of restore process
error_log("=== RESTORE PROCESS STARTED ===");
error_log("Session ID: " . session_id());
error_log("User ID: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("Username: " . ($_SESSION['username'] ?? 'NOT SET'));
error_log("POST data: " . print_r($_POST, true));

// Include activity logger
$loggerPath = "../../db/activity_logger.php";
if (file_exists($loggerPath)) {
    error_log("Loading activity logger from: $loggerPath");
    require_once $loggerPath;
    
    // Check if functions exist
    if (function_exists('logActivity')) {
        error_log("logActivity function is available");
    } else {
        error_log("ERROR: logActivity function NOT available");
    }
    
    if (function_exists('logRestore')) {
        error_log("logRestore function is available");
    } else {
        error_log("ERROR: logRestore function NOT available");
    }
} else {
    error_log("ERROR: Activity logger not found at: $loggerPath");
}

header("Content-Type: application/json");

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    error_log("RESTORE FAILED: User not authenticated");
    echo json_encode(["success" => false, "error" => "Not authenticated"]);
    exit;
}

// Check if this is a restore all request
$isRestoreAll = isset($_POST['restore_all']) && $_POST['restore_all'] == true;

// For single restore
if (!$isRestoreAll && (!isset($_POST['id']) || empty($_POST['id']))) {
    error_log("RESTORE FAILED: No ID provided");
    echo json_encode(["success" => false, "error" => "No ID provided"]);
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Unknown';
$user_role = $_SESSION['user_role'] ?? 'guest';

error_log("Processing restore - User: $username (ID: $user_id, Role: $user_role)");

try {
    $conn->begin_transaction();

    if ($isRestoreAll) {
        // RESTORE ALL ARCHIVED RECORDS
        error_log("Starting restore all process");

        // Count how many records will be restored
        $countQuery = $conn->query("SELECT COUNT(*) as total FROM archived_register");
        $totalRecords = $countQuery->fetch_assoc()['total'];

        if ($totalRecords == 0) {
            error_log("No archived records found");
            echo json_encode(["success" => false, "error" => "No archived records found"]);
            $conn->rollback();
            exit;
        }

        error_log("Found $totalRecords records to restore");

        // Get all archived records
        $archivedQuery = $conn->query("SELECT * FROM archived_register");
        $restoredCount = 0;
        $failedCount = 0;

        while ($archivedRow = $archivedQuery->fetch_assoc()) {
            $id = $archivedRow['id'];
            $farmerName = $archivedRow['s_name'] . ", " . $archivedRow['f_name'];

            try {
                // Restore registration form
                $columns = [];
                $values = [];

                foreach ($archivedRow as $col => $value) {
                    if ($col !== 'archived_at') {
                        $columns[] = $col;
                        $values[] = $value;
                    }
                }

                $columnsStr = implode(", ", $columns);
                $placeholders = implode(", ", array_fill(0, count($values), "?"));
                $sql = "INSERT INTO registration_form ($columnsStr) VALUES ($placeholders)";

                $stmt = $conn->prepare($sql);
                $types = str_repeat("s", count($values));
                $stmt->bind_param($types, ...$values);

                if ($stmt->execute()) {
                    $new_id = $stmt->insert_id;
                    error_log("Restored farmer ID $id to new ID $new_id: $farmerName");

                    // Restore livelihoods
                    $livQuery = $conn->prepare("SELECT * FROM archived_livelihoods WHERE registration_form_id = ?");
                    $livQuery->bind_param("i", $id);
                    $livQuery->execute();
                    $livResult = $livQuery->get_result();

                    while ($livRow = $livResult->fetch_assoc()) {
                        $livCols = [];
                        $livVals = [];

                        foreach ($livRow as $col => $value) {
                            if ($col !== 'archived_at') {
                                if ($col === 'registration_form_id') {
                                    $livCols[] = $col;
                                    $livVals[] = $new_id;
                                } else {
                                    $livCols[] = $col;
                                    $livVals[] = $value;
                                }
                            }
                        }

                        $livColsStr = implode(", ", $livCols);
                        $livPlaceholders = implode(", ", array_fill(0, count($livVals), "?"));
                        $livSql = "INSERT INTO livelihoods ($livColsStr) VALUES ($livPlaceholders)";

                        $livStmt = $conn->prepare($livSql);
                        $livTypes = str_repeat("s", count($livVals));
                        $livStmt->bind_param($livTypes, ...$livVals);
                        $livStmt->execute();
                        $livStmt->close();
                    }
                    $livQuery->close();

                    // Delete from archived tables
                    $conn->query("DELETE FROM archived_livelihoods WHERE registration_form_id = $id");
                    $conn->query("DELETE FROM archived_register WHERE id = $id");

                    // Log activity - IMPORTANT: Check if function exists
                    if (function_exists('logRestore')) {
                        error_log("Attempting to log restore for ID $new_id");
                        $logResult = logRestore(
                            $conn,
                            $user_id,
                            $username,
                            $user_role,
                            'registration_form',
                            $new_id,
                            "Restored farmer: {$farmerName} (Batch restore)"
                        );
                        error_log("logRestore result for ID $new_id: " . ($logResult ? "SUCCESS" : "FAILED"));
                    } else {
                        error_log("WARNING: logRestore function not available for ID $new_id");
                        // Try direct insert as backup
                        $directLog = $conn->query("INSERT INTO activity_logs 
                            (user_id, username, user_role, action_type, action_description, 
                             table_name, record_id, ip_address, user_agent, created_at)
                            VALUES ($user_id, '$username', '$user_role', 'RESTORE', 
                                   'Restored farmer: {$farmerName} (Batch restore)', 
                                   'registration_form', $new_id, 
                                   '" . ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') . "', 
                                   '" . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "', 
                                   NOW())");
                        error_log("Direct log insert result: " . ($directLog ? "SUCCESS" : "FAILED - " . $conn->error));
                    }

                    $restoredCount++;
                }
                $stmt->close();

            } catch (Exception $e) {
                error_log("Failed to restore record ID $id: " . $e->getMessage());
                $failedCount++;
                continue;
            }
        }

        $conn->commit();
        error_log("Restore all completed: $restoredCount restored, $failedCount failed");

        echo json_encode([
            "success" => true,
            "message" => "Restored $restoredCount out of $totalRecords records",
            "restored_count" => $restoredCount,
            "failed_count" => $failedCount,
            "total_records" => $totalRecords
        ]);

    } else {
        // SINGLE RESTORE
        $id = intval($_POST['id']);
        error_log("Starting single restore for ID: $id");

        // Check if archived record exists
        $check = $conn->prepare("SELECT * FROM archived_register WHERE id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows === 0) {
            error_log("Archived record not found for ID: $id");
            echo json_encode(["success" => false, "error" => "Archived record not found"]);
            $conn->rollback();
            exit;
        }

        $archivedRow = $result->fetch_assoc();
        $farmerName = $archivedRow['s_name'] . ", " . $archivedRow['f_name'];
        $check->close();

        error_log("Found archived record for: $farmerName");

        // Restore registration form
        $columns = [];
        $values = [];

        foreach ($archivedRow as $col => $value) {
            if ($col !== 'archived_at') {
                $columns[] = $col;
                $values[] = $value;
            }
        }

        $columnsStr = implode(", ", $columns);
        $placeholders = implode(", ", array_fill(0, count($values), "?"));
        $sql = "INSERT INTO registration_form ($columnsStr) VALUES ($placeholders)";

        $stmt = $conn->prepare($sql);
        $types = str_repeat("s", count($values));
        $stmt->bind_param($types, ...$values);

        if (!$stmt->execute()) {
            error_log("Failed to restore registration form: " . $stmt->error);
            throw new Exception("Failed to restore registration form: " . $stmt->error);
        }

        $new_id = $stmt->insert_id;
        $stmt->close();
        error_log("Restored registration form with new ID: $new_id");

        // Restore livelihoods
        $livQuery = $conn->prepare("SELECT * FROM archived_livelihoods WHERE registration_form_id = ?");
        $livQuery->bind_param("i", $id);
        $livQuery->execute();
        $livResult = $livQuery->get_result();

        $livelihoodsRestored = 0;

        while ($livRow = $livResult->fetch_assoc()) {
            $livCols = [];
            $livVals = [];

            foreach ($livRow as $col => $value) {
                if ($col !== 'archived_at') {
                    if ($col === 'registration_form_id') {
                        $livCols[] = $col;
                        $livVals[] = $new_id;
                    } else {
                        $livCols[] = $col;
                        $livVals[] = $value;
                    }
                }
            }

            $livColsStr = implode(", ", $livCols);
            $livPlaceholders = implode(", ", array_fill(0, count($livVals), "?"));
            $livSql = "INSERT INTO livelihoods ($livColsStr) VALUES ($livPlaceholders)";

            $livStmt = $conn->prepare($livSql);
            $livTypes = str_repeat("s", count($livVals));
            $livStmt->bind_param($livTypes, ...$livVals);

            if ($livStmt->execute()) {
                $livelihoodsRestored++;
            }
            $livStmt->close();
        }
        $livQuery->close();
        error_log("Restored $livelihoodsRestored livelihood records");

        // Delete from archived tables
        $conn->query("DELETE FROM archived_livelihoods WHERE registration_form_id = $id");
        $conn->query("DELETE FROM archived_register WHERE id = $id");
        error_log("Deleted from archived tables");

        $conn->commit();

        // Log activity - with detailed debugging
        error_log("Attempting to log activity for restored farmer: $farmerName");
        
        if (function_exists('logRestore')) {
            error_log("Using logRestore function");
            $logResult = logRestore(
                $conn,
                $user_id,
                $username,
                $user_role,
                'registration_form',
                $new_id,
                "Restored farmer: {$farmerName} with {$livelihoodsRestored} livelihood(s)"
            );
            error_log("logRestore function result: " . ($logResult ? "SUCCESS" : "FAILED"));
            
            if (!$logResult) {
                // Try alternative method
                error_log("logRestore failed, trying direct insert...");
                $directSql = "INSERT INTO activity_logs 
                    (user_id, username, user_role, action_type, action_description, 
                     table_name, record_id, ip_address, user_agent, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $directStmt = $conn->prepare($directSql);
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $action_desc = "Restored farmer: {$farmerName} with {$livelihoodsRestored} livelihood(s)";
                
                $directStmt->bind_param("isssssiss", 
                    $user_id, $username, $user_role, 'RESTORE', $action_desc,
                    'registration_form', $new_id, $ip, $ua);
                
                $directResult = $directStmt->execute();
                error_log("Direct insert result: " . ($directResult ? "SUCCESS" : "FAILED - " . $directStmt->error));
                $directStmt->close();
            }
        } else {
            error_log("logRestore function not available, using direct insert");
            $directSql = "INSERT INTO activity_logs 
                (user_id, username, user_role, action_type, action_description, 
                 table_name, record_id, ip_address, user_agent, created_at)
                VALUES ($user_id, '$username', '$user_role', 'RESTORE', 
                       'Restored farmer: {$farmerName} with {$livelihoodsRestored} livelihood(s)', 
                       'registration_form', $new_id, 
                       '" . ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') . "', 
                       '" . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "', 
                       NOW())";
            
            $directResult = $conn->query($directSql);
            error_log("Direct SQL result: " . ($directResult ? "SUCCESS" : "FAILED - " . $conn->error));
        }

        error_log("=== SINGLE RESTORE COMPLETED SUCCESSFULLY ===");

        echo json_encode([
            "success" => true,
            "message" => "Restored successfully",
            "farmer_name" => $farmerName,
            "new_id" => $new_id,
            "livelihoods_restored" => $livelihoodsRestored
        ]);
    }

} catch (Exception $e) {
    $conn->rollback();
    error_log("RESTORE ERROR: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(["success" => false, "error" => "Restore failed: " . $e->getMessage()]);
}

if (isset($conn) && $conn) {
    $conn->close();
}

error_log("=== RESTORE SCRIPT ENDED ===");
?>