<?php
// activity_logger.php - ENHANCED WITH DEBUGGING

function logActivity($conn, $user_id, $username, $user_role, $action_type, $description, $table_name = null, $record_id = null)
{
    // Start debugging
    error_log("[ACTIVITY_LOGGER] Starting logActivity");
    error_log("[ACTIVITY_LOGGER] Params: user_id=$user_id, username=$username, action_type=$action_type, desc=" . substr($description, 0, 50));

    try {
        // Validate connection
        if (!$conn) {
            error_log("[ACTIVITY_LOGGER] ERROR: No database connection");
            return false;
        }

        if (!is_object($conn)) {
            error_log("[ACTIVITY_LOGGER] ERROR: Connection is not an object");
            return false;
        }

        // Check if activity_logs table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'activity_logs'");
        if ($tableCheck->num_rows === 0) {
            error_log("[ACTIVITY_LOGGER] ERROR: activity_logs table does not exist");
            return false;
        }

        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        // Prepare SQL
        $sql = "INSERT INTO activity_logs 
                (user_id, username, user_role, action_type, action_description, 
                 table_name, record_id, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        error_log("[ACTIVITY_LOGGER] SQL: " . substr($sql, 0, 100) . "...");

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $error = $conn->error;
            error_log("[ACTIVITY_LOGGER] ERROR: Prepare failed: $error");
            return false;
        }

        // Handle null values
        $table_name = $table_name ?: null;
        $record_id = $record_id ?: null;

        error_log("[ACTIVITY_LOGGER] Binding parameters...");

        // Bind parameters
        $bound = $stmt->bind_param(
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

        if (!$bound) {
            $error = $stmt->error;
            error_log("[ACTIVITY_LOGGER] ERROR: Bind failed: $error");
            $stmt->close();
            return false;
        }

        error_log("[ACTIVITY_LOGGER] Executing statement...");

        // Execute
        $result = $stmt->execute();

        if (!$result) {
            $error = $stmt->error;
            error_log("[ACTIVITY_LOGGER] ERROR: Execute failed: $error");
            $stmt->close();
            return false;
        }

        $inserted_id = $stmt->insert_id;
        $affected_rows = $stmt->affected_rows;

        error_log("[ACTIVITY_LOGGER] SUCCESS: Inserted ID: $inserted_id, Affected rows: $affected_rows");

        $stmt->close();

        return true;

    } catch (Exception $e) {
        error_log("[ACTIVITY_LOGGER] EXCEPTION: " . $e->getMessage());
        error_log("[ACTIVITY_LOGGER] Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

// Common logging functions - keep as is
function logCreate($conn, $user_id, $username, $user_role, $table_name, $record_id, $description = null)
{
    $desc = $description ?? "Created new record in {$table_name}";
    return logActivity($conn, $user_id, $username, $user_role, 'CREATE', $desc, $table_name, $record_id);
}

function logUpdate($conn, $user_id, $username, $user_role, $table_name, $record_id, $description = null)
{
    $desc = $description ?? "Updated record in {$table_name}";
    return logActivity($conn, $user_id, $username, $user_role, 'UPDATE', $desc, $table_name, $record_id);
}

function logDelete($conn, $user_id, $username, $user_role, $table_name, $record_id, $description = null)
{
    $desc = $description ?? "Deleted record from {$table_name}";
    return logActivity($conn, $user_id, $username, $user_role, 'DELETE', $desc, $table_name, $record_id);
}

function logArchive($conn, $user_id, $username, $user_role, $table_name, $record_id, $description = null)
{
    $desc = $description ?? "Archived record from {$table_name}";
    return logActivity($conn, $user_id, $username, $user_role, 'ARCHIVE', $desc, $table_name, $record_id);
}

function logRestore($conn, $user_id, $username, $user_role, $table_name, $record_id, $description = null)
{
    $desc = $description ?? "Restored record in {$table_name}";
    error_log("[ACTIVITY_LOGGER] logRestore called: $desc");
    return logActivity($conn, $user_id, $username, $user_role, 'RESTORE', $desc, $table_name, $record_id);
}

function logLogin($conn, $user_id, $username, $user_role, $description = null)
{
    $desc = $description ?? "User logged into the system";
    return logActivity($conn, $user_id, $username, $user_role, 'LOGIN', $desc, null, null);
}

function logLogout($conn, $user_id, $username, $user_role, $description = null)
{
    $desc = $description ?? "User logged out of the system";
    return logActivity($conn, $user_id, $username, $user_role, 'LOGOUT', $desc, null, null);
}
?>